<?php
// api/status.php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    // 1. Fetch Tables Status with Current Total
    // Order/credit totals are aggregated only across currently-open sessions
    // (table_sessions.closed_at IS NULL), not the whole orders/order_items/
    // credit_transactions history — keeps this fast as those tables grow,
    // since it was previously joining+grouping the full history every tick.
    $stmt = $pdo->query("
        SELECT
            c.*,
            cs.id as active_session_id,
            cs.is_paid,
            cs.paid_amount_cash as paid_cash,
            cs.paid_amount_online as paid_online,
            COALESCE(ord_totals.current_total, 0) as current_total,
            credit_totals.paid_credit as paid_credit
        FROM tables c
        LEFT JOIN table_sessions cs ON c.id = cs.table_id AND cs.closed_at IS NULL
        LEFT JOIN (
            SELECT o.session_id, SUM(oi.unit_price_snapshot * oi.qty) as current_total
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN table_sessions ocs ON ocs.id = o.session_id AND ocs.closed_at IS NULL
            GROUP BY o.session_id
        ) ord_totals ON ord_totals.session_id = cs.id
        LEFT JOIN (
            SELECT ct.session_id, SUM(ct.amount) as paid_credit
            FROM credit_transactions ct
            JOIN table_sessions ccs ON ccs.id = ct.session_id AND ccs.closed_at IS NULL
            GROUP BY ct.session_id
        ) credit_totals ON credit_totals.session_id = cs.id
        ORDER BY c.code ASC
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate unpaid_total in PHP to avoid complex SQL with missing columns
    foreach ($tables as &$c) {
        $total = floatval($c['current_total'] ?? 0);
        $paid = floatval($c['paid_cash'] ?? 0) + floatval($c['paid_online'] ?? 0) + floatval($c['paid_credit'] ?? 0);

        // Fix: If session is explicitly marked as PAID (e.g. Full Credit), unpaid amount is 0.
        // However, if new items were added (re-opening the session), is_paid might be 0, 
        // but we still need to subtract the previous credit. Use standard subtraction logic always.
        // We only force 0 if is_paid is 1, just as a safety net.
        if (isset($c['is_paid']) && $c['is_paid'] == 1) {
            $c['unpaid_total'] = 0;
        } else {
            $c['unpaid_total'] = max(0, $total - $paid);
        }
    }
    unset($c); // Break reference

    // 2. Fetch active order queue for admin Kitchen Monitor
    $queueQuery = "
        SELECT oi.*, m.name as menu_name, c.code as table_code, cs.is_paid as session_is_paid 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN table_sessions cs ON o.session_id = cs.id
        JOIN tables c ON cs.table_id = c.id
        LEFT JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE cs.closed_at IS NULL
          AND cs.is_paid = 0
          AND oi.is_paid = 0
          AND oi.kitchen_status IN ('PENDING', 'CONFIRMED', 'PREPARING', 'READY', 'SERVED')
        ORDER BY oi.created_at ASC
    ";
    $stmt = $pdo->query($queueQuery);
    $activeQueue = $stmt->fetchAll();

    $response = [
        'tables' => $tables,
        'queue' => $activeQueue,
        'timestamp' => time()
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
