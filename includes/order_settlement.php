<?php
// includes/order_settlement.php
// When counter/admin fully settles a table, close out kitchen workflow so items
// do not stay PENDING in the kitchen queue (avoids double prep).

require_once __DIR__ . '/stock_schema.php';
require_once __DIR__ . '/stock_deduct.php';

/**
 * Mark every active line on a session as paid and SERVED (except CANCELLED).
 * Deducts tracked counter stock once per order line (stock_deducted flag).
 * Call only when the session bill is fully settled (cash, split, or credit).
 */
function settle_session_items_on_payment(PDO $pdo, $sessionId, ?int $userId = null)
{
    $sessionId = (int) $sessionId;

    ensure_stock_schema($pdo);

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            SET oi.is_paid = 1,
                oi.kitchen_status = 'SERVED'
            WHERE o.session_id = ?
              AND oi.kitchen_status != 'CANCELLED'
        ");
        $stmt->execute([$sessionId]);

        // Skip order lines with no counter stock tracking (do not block future backfill for tracked items)
        $skip = $pdo->prepare("
            UPDATE order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            LEFT JOIN stock_items si ON si.menu_item_id = oi.menu_item_id
                AND si.stock_area = 'counter'
                AND (si.track_stock = 1 OR si.track_sold = 1)
            SET oi.stock_deducted = 1
            WHERE o.session_id = ?
              AND oi.kitchen_status != 'CANCELLED'
              AND oi.stock_deducted = 0
              AND si.id IS NULL
        ");
        $skip->execute([$sessionId]);

        // Deduct any served tracked lines not yet processed (e.g. paid at counter without waiter serve).
        deduct_counter_stock_for_session($pdo, $sessionId, $userId);

        if ($ownTx) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
