<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/order_settlement.php';
require_once '../includes/realtime.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Please login']);
    exit;
}
if ($_SESSION['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Admin access only']);
    exit;
}

verify_csrf();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'mark_paid') {
        $sessionId = $_POST['session_id'];
        $method = $_POST['payment_method'] ?? 'CASH'; // CASH, FONEPAY, SPLIT

        // 1. Calculate Total Bill (Sum of ALL items in session, regardless of paid status)
        // We need the GROSS total to know if we are fully paid or not
        $stmt = $pdo->prepare("SELECT SUM(oi.unit_price_snapshot * oi.qty) as total
                               FROM order_items oi
                               JOIN orders o ON oi.order_id = o.id
                               WHERE o.session_id = ?
                               AND oi.kitchen_status != 'CANCELLED'");
        $stmt->execute([$sessionId]);
        $grossTotal = floatval($stmt->fetchColumn() ?: 0);

        // 2. Get Amount Already Paid
        $stmt = $pdo->prepare("SELECT paid_amount_cash, paid_amount_online FROM table_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $alreadyPaid = floatval($sessionData['paid_amount_cash']) + floatval($sessionData['paid_amount_online']);

        // 3. Current Due
        $currentDue = $grossTotal - $alreadyPaid;

        if ($currentDue <= 0) {
            echo json_encode(['error' => "Bill is already fully settled."]);
            exit;
        }

        $cash = 0;
        $online = 0;

        if ($method === 'SPLIT') {
            $cash = floatval($_POST['cash_amount']);
            $online = floatval($_POST['online_amount']);
        } elseif ($method === 'CASH') {
            $cash = floatval($currentDue); // Default to paying remaining
            // But careful, user might want to pay LESS (Part Pay). Front-end usually sends specific amount if it's not auto-fill.
            // If frontend sends specific 'cash_amount' even for CASH method, we should use it? 
            // Currently the front-end 'mark_paid' usually implies "Pay All" OR "Split".
            // However, for EC-01 (Partial), the user might use SPLIT but provide amount < Total.
            // Let's rely on logic: if POST cash/online is set, use it. If not, assume Full Pay.
        } elseif ($method === 'FONEPAY') {
            $online = floatval($currentDue);
        }

        // Override if amounts provided in POST for single methods (Generic handler)
        if (isset($_POST['cash_amount']) && $method === 'CASH')
            $cash = floatval($_POST['cash_amount']);
        if (isset($_POST['online_amount']) && $method === 'FONEPAY')
            $online = floatval($_POST['online_amount']);

        $payingNow = $cash + $online;

        // Validation: Cannot pay more than due
        if (($payingNow - $currentDue) > 1.0) {
            echo json_encode(['error' => "Payment ($payingNow) exceeds Due ($currentDue)."]);
            exit;
        }
        if ($payingNow <= 0) {
            echo json_encode(['error' => "Invalid payment amount."]);
            exit;
        }

        $pdo->beginTransaction();

        // 4. Update Session Payment Totals
        // Determine New Status
        $newTotalPaid = $alreadyPaid + $payingNow;
        $remaining = $grossTotal - $newTotalPaid;

        $isFullyPaid = ($remaining <= 1.0); // Tolerance
        $paymentStatus = $isFullyPaid ? 'PAID' : 'PARTIAL';
        $isWaitPaid = $isFullyPaid ? 1 : 0; // Only mark is_paid=1 if fully settled

        // Update Session
        $stmt = $pdo->prepare("UPDATE table_sessions 
                               SET is_paid = ?, 
                                   paid_at = NOW(), 
                                   payment_method = ?, 
                                   payment_status = ?,
                                   paid_amount_cash = paid_amount_cash + ?, 
                                   paid_amount_online = paid_amount_online + ? 
                               WHERE id = ?");
        $stmt->execute([$isWaitPaid, $method, $paymentStatus, $cash, $online, $sessionId]);

        // 5. Mark Items as Paid ONLY if Fully Paid
        // (EC-08 Rule: "Items paid must be marked PAID". But we don't know WHICH items. 
        //  The safest logic for "Partial" is to keep items unpaid until the bill is cleared, 
        //  OR mark items sequentially. Simple approach: Keep items unpaid until full settlement.
        //  Refinement: If we want to support "2 customers left", we effectively just reduced the bill due.
        //  Marking specific items requires UI selection. Without it, we keep global due.)
        if ($isFullyPaid) {
            settle_session_items_on_payment($pdo, $sessionId, (int) $_SESSION['user_id']);
        }

        // 6. Log Transaction
        // Get table name for audit
        $stmt = $pdo->prepare("SELECT c.code FROM tables c JOIN table_sessions cs ON c.id = cs.table_id WHERE cs.id = ?");
        $stmt->execute([$sessionId]);
        $tableCode = $stmt->fetchColumn() ?: 'Unknown';

        // Add 'Partial' tag to note if relevant
        $txnMethod = $method;
        if (!$isFullyPaid)
            $txnMethod .= ' (PARTIAL)';

        $stmt = $pdo->prepare("INSERT INTO payment_transactions (session_id, table_name, amount, payment_method, cash_amount, online_amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$sessionId, $tableCode, $payingNow, $txnMethod, $cash, $online, $_SESSION['user_id']]);

        $pdo->commit();

        // Performance Guard: Sync daily summary
        require_once '../includes/perf_guard.php';
        PerfGuard::syncDailySummary($pdo, date('Y-m-d'), true);

        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'mark_credit') {
        $sessionId = $_POST['session_id'];
        $customerId = $_POST['customer_id'] ?? null;
        $note = $_POST['note'] ?? '';
        $creditType = $_POST['credit_type'] ?? 'FULL';

        if (!$customerId) {
            echo json_encode(['error' => 'Customer is required for credit']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Calculate Total Amount of UNPAID items
        $stmt = $pdo->prepare("SELECT SUM(oi.unit_price_snapshot * oi.qty) as total 
                               FROM order_items oi
                               JOIN orders o ON oi.order_id = o.id
                               WHERE o.session_id = ? 
                               AND oi.is_paid = 0
                               AND oi.kitchen_status != 'CANCELLED'");
        $stmt->execute([$sessionId]);
        $total = floatval($stmt->fetchColumn() ?: 0);

        if ($total <= 0) {
            $pdo->rollBack();
            echo json_encode(['error' => 'No unpaid items remaining. This session may have already been paid.']);
            exit;
        }

        $paidCash = 0;
        $paidOnline = 0;

        if ($creditType === 'PARTIAL') {
            $paidCash = floatval($_POST['partial_cash'] ?? 0);
            $paidOnline = floatval($_POST['partial_online'] ?? 0);
        }

        $totalPaid = $paidCash + $paidOnline;
        $creditAmount = $total - $totalPaid;

        if ($creditAmount > 0) {
            // 2. Insert Credit Transaction
            $stmt = $pdo->prepare("SELECT name FROM credit_customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer_name = $stmt->fetchColumn() ?: 'Unknown';

            // Get table name for audit
            $stmt = $pdo->prepare("SELECT c.code FROM tables c JOIN table_sessions cs ON c.id = cs.table_id WHERE cs.id = ?");
            $stmt->execute([$sessionId]);
            $tableCode = $stmt->fetchColumn() ?: 'Unknown';

            $stmt = $pdo->prepare("INSERT INTO credit_transactions (customer_id, customer_name, session_id, table_name, amount, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $finalNote = $note;
            if ($creditType === 'PARTIAL') {
                $parts = [];
                if ($paidCash > 0)
                    $parts[] = "Rs. $paidCash Cash";
                if ($paidOnline > 0)
                    $parts[] = "Rs. $paidOnline Online";
                $finalNote = "Partial Credit (Paid: " . implode(' + ', $parts) . ")";
            } else if (empty($finalNote) || $finalNote === '-') {
                $finalNote = "Full Credit Given";
            }
            $stmt->execute([$customerId, $customer_name, $sessionId, $tableCode, $creditAmount, $finalNote, $_SESSION['user_id']]);
        }

        // 3. Mark items paid + SERVED so kitchen queue clears (even if worker forgot status)
        settle_session_items_on_payment($pdo, $sessionId, (int) $_SESSION['user_id']);

        // 4. Update Session with accumulative totals
        $paymentStatus = 'CREDIT';
        if ($creditAmount <= 0.5) {
            $paymentStatus = 'PAID';
        }

        // Fix for Sales Report: If partial payment, label as SPLIT so it displays Cash/Online pills
        $methodLabel = 'CREDIT';
        if ($paidCash > 0 || $paidOnline > 0) {
            $methodLabel = 'SPLIT';
        }

        $stmt = $pdo->prepare("UPDATE table_sessions 
                               SET is_paid = 1, 
                                   paid_at = NOW(), 
                                   payment_mode = 'CREDIT', 
                                   payment_status = ?, 
                                   payment_method = ?, 
                                   paid_amount_cash = paid_amount_cash + ?, 
                                   paid_amount_online = paid_amount_online + ? 
                               WHERE id = ?");
        $stmt->execute([$paymentStatus, $methodLabel, $paidCash, $paidOnline, $sessionId]);

        // 4.1 Log specific cash/online portion to payment_transactions for consolidated sales reporting
        if ($totalPaid > 0) {
            $stmt = $pdo->prepare("INSERT INTO payment_transactions (session_id, table_name, amount, payment_method, cash_amount, online_amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $methodTag = "CREDIT-SPLIT";
            $stmt->execute([$sessionId, $tableCode, $totalPaid, $methodTag, $paidCash, $paidOnline, $_SESSION['user_id']]);
        }

        // 5. Update Customer Balance Cache
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_transactions WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $newBalance = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("UPDATE credit_customers SET current_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $customerId]);

        $pdo->commit();

        // Performance Guard: Sync daily summary
        require_once '../includes/perf_guard.php';
        PerfGuard::syncDailySummary($pdo, date('Y-m-d'), true);

        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'clear_table') {
        // Admin force clear (sets Free)
        $sessionId = $_POST['session_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT table_id, is_paid FROM table_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch();
        if (!$sessionData) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Session not found']);
            exit;
        }
        $tableId = $sessionData['table_id'];
        $isPaid = (int) $sessionData['is_paid'];

        // 1. Free the table
        $stmt = $pdo->prepare("UPDATE tables SET status = 'FREE' WHERE id = ?");
        $stmt->execute([$tableId]);

        if ($isPaid === 1) {
            // IF PAID/CREDIT: Just close the session properly without deleting data (Preserves History)
            $stmt = $pdo->prepare("UPDATE table_sessions SET closed_at = NOW(), closed_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $sessionId]);
        } else {
            // IF NOT PAID: This was likely a mistake or cancelled table. Delete everything.
            // 2. Delete Order Items first
            require_once '../includes/stock_deduct.php';
            restore_stock_for_session($pdo, $sessionId, $_SESSION['user_id'] ?? null);
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE session_id = ?)");
            $stmt->execute([$sessionId]);

            // 3. Delete Orders
            $stmt = $pdo->prepare("DELETE FROM orders WHERE session_id = ?");
            $stmt->execute([$sessionId]);

            // 4. Delete Session (PURGE)
            $stmt = $pdo->prepare("DELETE FROM table_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        }

        $pdo->commit();
        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'cancel_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT kitchen_status FROM order_items WHERE id = ? FOR UPDATE");
        $stmt->execute([$itemId]);
        $orderLine = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orderLine) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Item not found']);
            exit;
        }

        if ($orderLine['kitchen_status'] === 'SERVED') {
            $pdo->rollBack();
            echo json_encode(['error' => 'Cannot cancel an item that has already been served']);
            exit;
        }

        require_once '../includes/stock_deduct.php';
        $reason = $orderLine['kitchen_status'] === 'SERVED' ? 'kitchen_cleared' : 'removed';
        restore_stock_for_item($pdo, $itemId, $_SESSION['user_id'] ?? null, $reason);

        $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
        $stmt->execute([$itemId]);

        $pdo->commit();
        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'edit_qty') {
        $itemId = (int) $_POST['item_id'];
        $delta = (int) $_POST['delta'];
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT qty, is_paid FROM order_items WHERE id = ? FOR UPDATE");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Item not found']);
            exit;
        }
        if ($item['is_paid'] == 1) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Cannot edit paid items']);
            exit;
        }
        
        $oldQty = (int) $item['qty'];
        $newQty = $oldQty + $delta;
        
        require_once '../includes/stock_deduct.php';
        
        if ($newQty <= 0) {
            restore_stock_for_item($pdo, $itemId, $_SESSION['user_id'] ?? null);
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
            $stmt->execute([$itemId]);
        } else {
            if ($oldQty !== $newQty) {
                adjust_stock_for_qty_change($pdo, $itemId, $oldQty, $newQty, $_SESSION['user_id'] ?? null);
            }
            $stmt = $pdo->prepare("UPDATE order_items SET qty = ? WHERE id = ?");
            $stmt->execute([$newQty, $itemId]);
        }
        
        $pdo->commit();
        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'bulk_edit_qty') {
        $updates = json_decode($_POST['updates'], true);
        if (!is_array($updates)) {
            echo json_encode(['error' => 'Invalid data format']);
            exit;
        }

        $pdo->beginTransaction();
        
        foreach ($updates as $itemId => $newQty) {
            $itemId = (int) $itemId;
            $newQty = (int) $newQty;

            $stmt = $pdo->prepare("SELECT qty, is_paid FROM order_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            
            if ($item && $item['is_paid'] == 0) {
                require_once '../includes/stock_deduct.php';
                $oldQty = (int) $item['qty'];

                if ($newQty <= 0) {
                    restore_stock_for_item($pdo, $itemId, $_SESSION['user_id'] ?? null);
                    $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
                    $stmt->execute([$itemId]);
                } else {
                    if ($oldQty !== $newQty) {
                        adjust_stock_for_qty_change($pdo, $itemId, $oldQty, $newQty, $_SESSION['user_id'] ?? null);
                    }
                    $stmt = $pdo->prepare("UPDATE order_items SET qty = ? WHERE id = ?");
                    $stmt->execute([$newQty, $itemId]);
                }
            }
        }
        
        $pdo->commit();
        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } elseif ($action === 'cancel_table_orders') {
        $tableCode = $_POST['table_code'];

        $pdo->beginTransaction();

        // 1. Get Active Session ID for this table
        $stmt = $pdo->prepare("
            SELECT cs.id, cs.table_id 
            FROM table_sessions cs
            JOIN tables c ON cs.table_id = c.id
            WHERE c.code = ? AND cs.closed_at IS NULL
        ");
        $stmt->execute([$tableCode]);
        $session = $stmt->fetch();

        if ($session) {
            $sessionId = $session['id'];
            $tableId = $session['table_id'];

            // 2. Delete All Items
            // Get all order IDs first
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($orderIds)) {
                require_once '../includes/stock_deduct.php';
                restore_stock_for_session($pdo, $sessionId, $_SESSION['user_id'] ?? null);

                $inQuery = implode(',', array_fill(0, count($orderIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($inQuery)");
                $stmt->execute($orderIds);

                // Also delete orders themselves for cleanup? Usually fine to keep empty orders or delete them.
                // Let's delete them to be thorough.
                $stmt = $pdo->prepare("DELETE FROM orders WHERE session_id = ?");
                $stmt->execute([$sessionId]);
            }

            // 3. Mark Table Free & Close Session (as requested "make clear and free")
            $timestamp = date('Y-m-d H:i:s');

            // Mark session as cancelled/closed
            $stmt = $pdo->prepare("
                UPDATE table_sessions 
                SET closed_at = ?, closed_by = ?, payment_status = 'CANCELLED'
                WHERE id = ?
            ");
            $stmt->execute([$timestamp, $_SESSION['user_id'], $sessionId]);

            // Free the table
            $stmt = $pdo->prepare("UPDATE tables SET status = 'FREE' WHERE id = ?");
            $stmt->execute([$tableId]);

            $pdo->commit();
            realtime_notify_orders();
            echo json_encode(['success' => true]);
        } else {
            $pdo->rollBack();
            echo json_encode(['error' => 'No active session found for this table']);
        }
    } elseif ($action === 'recalc_balances') {
        // Maintenance Tool: Fix all customer balances in one pass (no per-customer round trips)
        $count = $pdo->exec("
            UPDATE credit_customers cc
            LEFT JOIN (
                SELECT customer_id, SUM(amount) as real_balance
                FROM credit_transactions
                GROUP BY customer_id
            ) t ON t.customer_id = cc.id
            SET cc.current_balance = COALESCE(t.real_balance, 0)
        ");
        echo json_encode(['success' => true, 'count' => $count]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    $db = $GLOBALS['pdo'] ?? null;
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('admin_action: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
