<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// Kill-switch: block waiters from loading order data while portal is deactivated.
if (($_SESSION['role'] ?? '') === 'WAITER' && !waiter_portal_is_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'The waiter portal is currently unavailable.', 'portal_disabled' => true]);
    exit;
}

$table_id = $_GET['table_id'] ?? null;

if (!$table_id) {
    echo json_encode(['orders' => []]);
    exit;
}

try {
    // Get active session with optimized separation
    $stmt = $pdo->prepare("
        SELECT 
            cs.id, 
            cs.is_paid, 
            cs.opened_at, 
            cs.table_id,
            cs.paid_amount_cash,
            cs.paid_amount_online,
            cs.payment_mode
        FROM table_sessions cs
        WHERE cs.table_id = ? AND cs.closed_at IS NULL 
        LIMIT 1
    ");
    $stmt->execute([$table_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        // Fetch Credit Info in a separate single-pass query
        // Using MAX(customer_name) ensures we get a name without a separate subquery scan
        $cStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(amount), 0) as credit_amount, 
                MAX(customer_name) as credit_customer_name 
            FROM credit_transactions 
            WHERE session_id = ?
        ");
        $cStmt->execute([$session['id']]);
        $creditInfo = $cStmt->fetch(PDO::FETCH_ASSOC);

        $session['credit_amount'] = $creditInfo['credit_amount'] ?? 0;
        $session['credit_customer_name'] = $creditInfo['credit_customer_name'] ?? null;
    }

    if (!$session) {
        // No active session logic
        echo json_encode(['orders' => [], 'session' => null]);
        exit;
    }

    // Explicitly cast numbers
    $session['paid_amount_cash'] = (float) ($session['paid_amount_cash'] ?? 0);
    $session['paid_amount_online'] = (float) ($session['paid_amount_online'] ?? 0);

    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, COALESCE(m.is_counter_item, 0) AS is_counter_item,
               si.track_stock, si.stock_qty, si.unit_type
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN menu_items m ON oi.menu_item_id = m.id
        LEFT JOIN stock_items si ON si.menu_item_id = m.id AND si.stock_area = 'counter'
        WHERE o.session_id = ?
        ORDER BY oi.created_at DESC
    ");
    $stmt->execute([$session['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inject is_paid from session if not present in item
    $sessionIsPaid = $session['is_paid'];
    foreach ($items as &$item) {
        if (!isset($item['is_paid'])) {
            $item['is_paid'] = $sessionIsPaid;
        }
    }
    unset($item);

    echo json_encode(['orders' => $items, 'session' => $session]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
