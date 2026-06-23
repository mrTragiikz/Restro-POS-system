<?php
// includes/stock_deduct.php
// Deduct counter stock and/or log sales when a table session is fully paid.

require_once __DIR__ . '/stock_schema.php';
require_once __DIR__ . '/stock_helpers.php';

/**
 * Deduct tracked counter stock and log sales for order lines not yet processed.
 * Uses order_items.stock_deducted to prevent double processing.
 *
 * @return array<int, array{menu_item_id:int, qty:int, stock_after:int}>
 */
function deduct_counter_stock_for_session(PDO $pdo, int $sessionId, ?int $userId = null, bool $reconcileOnly = false): array
{
    ensure_stock_schema($pdo);

    $deducted = [];

    $stmt = $pdo->prepare("
        SELECT oi.id AS order_item_id,
               oi.menu_item_id,
               oi.qty,
               oi.item_name_snapshot,
               oi.created_at AS sold_at,
               cs.paid_at AS session_paid_at,
               t.code AS table_code,
               si.id AS stock_item_id,
               si.stock_qty,
               si.unit_type,
               si.track_stock,
               si.track_sold,
               si.sold_tracking_started_at
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN table_sessions cs ON o.session_id = cs.id
        INNER JOIN tables t ON cs.table_id = t.id
        INNER JOIN stock_items si ON si.menu_item_id = oi.menu_item_id
            AND si.stock_area = 'counter'
            AND (si.track_stock = 1 OR si.track_sold = 1)
        WHERE o.session_id = ?
          AND oi.kitchen_status != 'CANCELLED'
          AND oi.kitchen_status = 'SERVED'
          AND oi.stock_deducted = 0
          AND oi.created_at >= " . stock_sql_lifecycle_since('si') . "
    ");
    $stmt->execute([$sessionId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $line) {
        $qtySold = (int) $line['qty'];
        if ($qtySold <= 0) {
            continue;
        }

        $stockItemId = (int) $line['stock_item_id'];
        $trackStock = (int) $line['track_stock'] === 1;
        $trackSold = (int) $line['track_sold'] === 1;
        $unitType = $line['unit_type'];
        $isPiece = ($unitType === null || $unitType === 'piece');

        $mark = $pdo->prepare("UPDATE order_items SET stock_deducted = 1 WHERE id = ? AND stock_deducted = 0");
        $mark->execute([(int) $line['order_item_id']]);
        if ($mark->rowCount() === 0) {
            continue;
        }

        $lock = $pdo->prepare("SELECT stock_qty FROM stock_items WHERE id = ? FOR UPDATE");
        $lock->execute([$stockItemId]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            continue;
        }

        $before = (int) $current['stock_qty'];
        $after = $before;

        if ($trackStock && $isPiece && !$reconcileOnly) {
            $after = $before - $qtySold;
            $upd = $pdo->prepare("UPDATE stock_items SET stock_qty = ? WHERE id = ?");
            $upd->execute([$after, $stockItemId]);
        }

        $shouldLogSale = false;
        if ($trackSold) {
            $soldSince = $line['sold_tracking_started_at'] ?? null;
            if ($soldSince === null || $soldSince === '') {
                $soldSince = date('Y-m-d H:i:s');
                $updSince = $pdo->prepare(
                    "UPDATE stock_items SET sold_tracking_started_at = ? WHERE id = ? AND sold_tracking_started_at IS NULL"
                );
                $updSince->execute([$soldSince, $stockItemId]);
            }
            $shouldLogSale = true;
        } elseif ($trackStock && $isPiece) {
            $shouldLogSale = true;
        }

        if ($shouldLogSale) {
            $tableCode = $line['table_code'] ?? '';
            $note = sprintf(
                '%d %s has been reduced from %s',
                $qtySold,
                $line['item_name_snapshot'],
                $tableCode
            );
            if ($reconcileOnly) {
                $note .= ' (recorded from billing)';
            }
            $loggedAt = $line['session_paid_at'] ?: $line['sold_at'] ?: date('Y-m-d H:i:s');
            $qtyAfterLog = $reconcileOnly ? $before : $after;
            $mov = $pdo->prepare("
                INSERT INTO stock_movements (stock_item_id, movement_type, qty_change, qty_after, note, created_by, created_at)
                VALUES (?, 'sale', ?, ?, ?, ?, ?)
            ");
            $mov->execute([$stockItemId, -$qtySold, $qtyAfterLog, $note, $userId, $loggedAt]);
        }

        if ($trackStock && $isPiece && !$reconcileOnly) {
            $deducted[] = [
                'menu_item_id' => (int) $line['menu_item_id'],
                'qty' => $qtySold,
                'stock_after' => $after,
            ];
        }
    }

    return $deducted;
}

/**
 * Adjust counter stock when admin changes qty on an already-deducted (SERVED) order line.
 * Logs a delta sale movement (e.g. -1 sold on return) instead of full restore + re-deduct.
 */
function adjust_stock_for_qty_change(PDO $pdo, int $orderItemId, int $oldQty, int $newQty, ?int $userId = null): void
{
    if ($oldQty === $newQty) {
        return;
    }

    ensure_stock_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT oi.id AS order_item_id,
               oi.menu_item_id,
               oi.item_name_snapshot,
               oi.created_at AS order_created_at,
               oi.stock_deducted,
               t.code AS table_code,
               si.id AS stock_item_id,
               si.created_at AS stock_created_at,
               si.stock_tracking_started_at,
               si.sold_tracking_started_at,
               si.stock_qty,
               si.unit_type,
               si.track_stock,
               si.track_sold
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN table_sessions cs ON o.session_id = cs.id
        INNER JOIN tables t ON cs.table_id = t.id
        INNER JOIN stock_items si ON si.menu_item_id = oi.menu_item_id
            AND si.stock_area = 'counter'
        WHERE oi.id = ?
          AND oi.stock_deducted = 1
          AND oi.kitchen_status = 'SERVED'
    ");
    $stmt->execute([$orderItemId]);
    $line = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$line) {
        return;
    }

    $lifecycleStart = stock_order_lifecycle_since([
        'created_at' => $line['stock_created_at'],
        'stock_tracking_started_at' => $line['stock_tracking_started_at'],
        'sold_tracking_started_at' => $line['sold_tracking_started_at'],
    ]);
    if (($line['order_created_at'] ?? '') < $lifecycleStart) {
        return;
    }

    $delta = $newQty - $oldQty;
    if ($delta === 0) {
        return;
    }

    $stockItemId = (int) $line['stock_item_id'];
    $trackStock = (int) $line['track_stock'] === 1;
    $trackSold = (int) $line['track_sold'] === 1;
    $unitType = $line['unit_type'];
    $isPiece = ($unitType === null || $unitType === 'piece');
    $itemName = $line['item_name_snapshot'];
    $tableCode = $line['table_code'] ?? '';

    $lock = $pdo->prepare("SELECT stock_qty FROM stock_items WHERE id = ? FOR UPDATE");
    $lock->execute([$stockItemId]);
    $current = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        return;
    }

    $before = (int) $current['stock_qty'];
    $after = $before;

    if ($trackStock && $isPiece) {
        $after = $delta < 0 ? $before + abs($delta) : $before - $delta;
        $upd = $pdo->prepare("UPDATE stock_items SET stock_qty = ? WHERE id = ?");
        $upd->execute([$after, $stockItemId]);
    }

    if ($trackSold || ($trackStock && $isPiece)) {
        if ($delta < 0) {
            $returnQty = abs($delta);
            $pieceWord = $returnQty === 1 ? 'piece' : 'pieces';
            $note = sprintf(
                '%d %s was served at %s, %d %s has been returned back to stock',
                $oldQty,
                $itemName,
                $tableCode,
                $returnQty,
                $pieceWord
            );
            $qtyChange = $returnQty;
        } else {
            $extraQty = $delta;
            $note = sprintf(
                '%d %s has been reduced from %s (qty updated from %d to %d)',
                $extraQty,
                $itemName,
                $tableCode,
                $oldQty,
                $newQty
            );
            $qtyChange = -$extraQty;
        }

        // A return (qty came back to stock) is an ADD, not a sale — keep it out of
        // the Sold list/total and show it under the Added section instead.
        $movementType = $delta < 0 ? 'add' : 'sale';

        $mov = $pdo->prepare("
            INSERT INTO stock_movements (stock_item_id, movement_type, qty_change, qty_after, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $mov->execute([$stockItemId, $movementType, $qtyChange, $after, $note, $userId]);
    }
}

/**
 * Safely restores stock when an order line is removed or a session is cancelled.
 * Logs a sale return (positive qty_change) in the Sold history when stock was deducted.
 * Resets stock_deducted = 0.
 *
 * @param 'removed'|'session_cancelled'|'kitchen_cleared' $reason
 */
function restore_stock_for_item(PDO $pdo, int $orderItemId, ?int $userId = null, string $reason = 'removed'): void
{
    ensure_stock_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT oi.id AS order_item_id,
               oi.menu_item_id,
               oi.qty,
               oi.created_at AS order_created_at,
               oi.item_name_snapshot,
               oi.stock_deducted,
               t.code AS table_code,
               si.id AS stock_item_id,
               si.created_at AS stock_created_at,
               si.stock_tracking_started_at,
               si.sold_tracking_started_at,
               si.stock_qty,
               si.unit_type,
               si.track_stock,
               si.track_sold
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN table_sessions cs ON o.session_id = cs.id
        INNER JOIN tables t ON cs.table_id = t.id
        INNER JOIN stock_items si ON si.menu_item_id = oi.menu_item_id
            AND si.stock_area = 'counter'
        WHERE oi.id = ?
          AND oi.stock_deducted = 1
          AND oi.kitchen_status = 'SERVED'
    ");
    $stmt->execute([$orderItemId]);
    $line = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$line) {
        return;
    }

    $lifecycleStart = stock_order_lifecycle_since([
        'created_at' => $line['stock_created_at'],
        'stock_tracking_started_at' => $line['stock_tracking_started_at'],
        'sold_tracking_started_at' => $line['sold_tracking_started_at'],
    ]);
    if (($line['order_created_at'] ?? '') < $lifecycleStart) {
        // Served under a previous stock record — never put pieces back on the current count.
        $mark = $pdo->prepare("UPDATE order_items SET stock_deducted = 0 WHERE id = ?");
        $mark->execute([$orderItemId]);
        return;
    }

    $qtyRestored = (int) $line['qty'];
    if ($qtyRestored <= 0) {
        return;
    }

    $stockItemId = (int) $line['stock_item_id'];
    $trackStock = (int) $line['track_stock'] === 1;
    $trackSold = (int) $line['track_sold'] === 1;
    $unitType = $line['unit_type'];
    $isPiece = ($unitType === null || $unitType === 'piece');

    $lock = $pdo->prepare("SELECT stock_qty FROM stock_items WHERE id = ? FOR UPDATE");
    $lock->execute([$stockItemId]);
    $current = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        return;
    }

    $before = (int) $current['stock_qty'];
    $after = $before;

    if ($trackStock && $isPiece) {
        $after = $before + $qtyRestored;
        $upd = $pdo->prepare("UPDATE stock_items SET stock_qty = ? WHERE id = ?");
        $upd->execute([$after, $stockItemId]);
    }

    if ($trackSold || ($trackStock && $isPiece)) {
        $tableCode = $line['table_code'] ?? '';
        $itemName = $line['item_name_snapshot'];

        if ($reason === 'session_cancelled' || $reason === 'kitchen_cleared') {
            $note = sprintf(
                'Admin cleared order from Kitchen Monitor, %d %s returned to stock (%s)',
                $qtyRestored,
                $itemName,
                $tableCode
            );
        } else {
            $note = sprintf(
                'Admin removed item from bill, %d %s returned to stock (%s)',
                $qtyRestored,
                $itemName,
                $tableCode
            );
        }

        // Restoring removed/cancelled stock is an ADD (returned to shelf), not a sale.
        $mov = $pdo->prepare("
            INSERT INTO stock_movements (stock_item_id, movement_type, qty_change, qty_after, note, created_by)
            VALUES (?, 'add', ?, ?, ?, ?)
        ");
        $mov->execute([$stockItemId, $qtyRestored, $after, $note, $userId]);
    }

    $mark = $pdo->prepare("UPDATE order_items SET stock_deducted = 0 WHERE id = ?");
    $mark->execute([$orderItemId]);
}

/**
 * Iterates through all items in a session and restores stock if it was already deducted.
 */
function restore_stock_for_session(PDO $pdo, int $sessionId, ?int $userId = null): void
{
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($orderIds)) return;
    $inQuery = implode(',', array_map('intval', $orderIds));

    $itemStmt = $pdo->query("
        SELECT oi.id FROM order_items oi
        WHERE oi.order_id IN ($inQuery)
          AND oi.stock_deducted = 1
          AND oi.kitchen_status = 'SERVED'
    ");
    $itemIds = $itemStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($itemIds as $id) {
        restore_stock_for_item($pdo, (int) $id, $userId, 'kitchen_cleared');
    }
}

