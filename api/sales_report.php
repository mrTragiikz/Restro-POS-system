<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/perf_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(401);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'summary'; // 'summary' or 'list'
$range = PerfGuard::makeRange($date);

try {
    if ($type === 'summary') {
        require_once '../includes/summary_helper.php';

        // Fix: Auto-Refresh the summary for the requested date.
        // This ensures that if you are viewing "Yesterday", it includes EVERYTHING (including late night sales).
        // It uses the smart throttling (5 seconds) so it's very fast.
        $stats = rebuild_daily_summary($date, $pdo);

        $row = [
            'total_cash' => $stats['cash'],
            'total_online' => $stats['online'],
            'total_credit_given' => $stats['credit_given'],
            'total_sales' => $stats['sales'],
            'lifetime_upto_yesterday' => $stats['lifetime_upto_yesterday'] ?? 0
        ];

        $out = [
            'date' => $date,
            'summary' => [
                'cash' => $row['total_cash'],
                'online' => $row['total_online'],
                'credit' => $row['total_credit_given'],
                'lifetime' => $row['lifetime_upto_yesterday'] ?? 0,
                'total_day' => $row['total_sales']
            ]
        ];

        echo json_encode($out);
    } else {
        $limit = PerfGuard::clampLimit($_GET['limit'] ?? 100);
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $fetchLimit = $limit + 1;

        // LIST: UNION of Collections (Cash/Online) AND Credit
        // IMPORTANT: The "Total Collected" summary displayed above strictly excludes Credit.
        // This list simply shows ALL activity.

        // LIST: UNION of Collections (Cash/Online) AND Credit Given
        // This ensures the "Credit Given" summary matches the list items.

        // OPTIMIZED QUERY: Push ORDER BY and LIMIT inside the UNION to avoid full table scans/sorts.
        // We must fetch ($offset + $limit) rows from each side to ensure correct pagination ordering.
        $innerLimit = $offset + $fetchLimit;

        /*
        IMPORTANT:
        This query is intentionally optimized.
        LIMIT must remain inside each UNION subquery.
        Do NOT rewrite to outer LIMIT structure.
        Reverting will cause full table scans and dashboard slowdown.
        */

        $sql = "
            (
                SELECT 
                    pt.created_at as paid_at,
                    pt.table_name as code,
                    pt.payment_method,
                    pt.cash_amount as paid_amount_cash,
                    pt.online_amount as paid_amount_online,

                    0 as discount_amount,
                    NULL as discount_type,
                    0 as credit_amount,
                    NULL as customer_name
                FROM payment_transactions pt
                WHERE pt.created_at >= ? AND pt.created_at < ?
                ORDER BY pt.created_at DESC
                LIMIT ?
            )
            UNION ALL
            (
                SELECT 
                    ct.created_at as paid_at,
                    'CREDIT' as code,
                    'CREDIT' as payment_method,
                    0 as paid_amount_cash,
                    0 as paid_amount_online,
                    NULL as discount_amount,
                    NULL as discount_type,
                    ct.amount as credit_amount,
                    COALESCE(ct.customer_name, c.name) as customer_name
                FROM credit_transactions ct
                LEFT JOIN credit_customers c ON ct.customer_id = c.id
                WHERE ct.created_at >= ? AND ct.created_at < ? AND ct.amount > 0
                ORDER BY ct.created_at DESC
                LIMIT ?
            )
            ORDER BY paid_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $pdo->prepare($sql);

        // Bind params: Dates, InnerLimits, Dates, InnerLimits, OuterLimit, Offset
        // 1. PT Start
        $stmt->bindValue(1, $range['start']);
        // 2. PT End
        $stmt->bindValue(2, $range['end']);
        // 3. PT Inner Limit
        $stmt->bindValue(3, (int) $innerLimit, PDO::PARAM_INT);

        // 4. CT Start
        $stmt->bindValue(4, $range['start']);
        // 5. CT End
        $stmt->bindValue(5, $range['end']);
        // 6. CT Inner Limit
        $stmt->bindValue(6, (int) $innerLimit, PDO::PARAM_INT);

        // 7. Outer Limit
        $stmt->bindValue(7, (int) $fetchLimit, PDO::PARAM_INT);
        // 8. Outer Offset
        $stmt->bindValue(8, (int) $offset, PDO::PARAM_INT);

        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = false;
        if (count($sessions) > $limit) {
            $hasMore = true;
            array_pop($sessions);
        }

        echo json_encode([
            'date' => $date,
            'sessions' => $sessions,
            'has_more' => $hasMore
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
