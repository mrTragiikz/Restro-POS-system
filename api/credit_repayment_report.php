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
$type = $_GET['type'] ?? 'summary';
$range = PerfGuard::makeRange($date);

try {
    if ($type === 'summary') {
        // Try to get from daily_summary
        $stmt = $pdo->prepare("SELECT total_credit_given, total_credit_paid FROM daily_summary WHERE daily_date = ?");
        $stmt->execute([$date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$summary) {
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_transactions WHERE created_at >= ? AND created_at < ? AND amount > 0");
            $stmt->execute([$range['start'], $range['end']]);
            $todayCredit = floatval($stmt->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("SELECT SUM(ABS(amount)) FROM credit_transactions WHERE created_at >= ? AND created_at < ? AND amount < 0");
            $stmt->execute([$range['start'], $range['end']]);
            $todayPaid = floatval($stmt->fetchColumn() ?: 0);

            $summary = [
                'total_credit_given' => $todayCredit,
                'total_credit_paid' => $todayPaid
            ];
            PerfGuard::syncDailySummary($pdo, $date);
        }

        // OPTIMIZED: Calculate historical balances using daily summaries when possible
        // Formula: Balance(Start) = Sum(All Summaries before Date)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_credit_given - total_credit_paid), 0) FROM daily_summary WHERE daily_date < ?");
        $stmt->execute([$date]);
        $oldOutstanding = floatval($stmt->fetchColumn() ?: 0);

        // BREAKDOWN FOR PAID (Cash vs Online) - range indexed, fast.
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN payment_mode = 'Online' THEN amount ELSE 0 END) as online,
                SUM(CASE WHEN payment_mode != 'Online' THEN amount ELSE 0 END) as cash
            FROM credit_transactions 
            WHERE created_at >= ? AND created_at < ? AND amount < 0
        ");
        $stmt->execute([$range['start'], $range['end']]);
        $cleared = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'date' => $date,
            'stats' => [
                'old_outstanding' => $oldOutstanding,
                'today_new_credit' => $summary['total_credit_given'],
                'today_cleared_payment' => $summary['total_credit_paid'],
                'today_cleared_cash' => abs($cleared['cash'] ?? 0),
                'today_cleared_online' => abs($cleared['online'] ?? 0),
                'current_total_outstanding' => $oldOutstanding + $summary['total_credit_given'] - $summary['total_credit_paid']
            ]
        ]);
    } else {
        $limit = PerfGuard::clampLimit($_GET['limit'] ?? 50);
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $listType = $_GET['list_type'] ?? 'repayment';

        $op = ($listType === 'new_credit') ? '>' : '<';

        // OPTIMIZED: Removed expensive GROUP_CONCAT subquery (items) not used in the dashboard view.
        // This makes the list load much faster.
        $stmt = $pdo->prepare("
            SELECT ct.id, ct.amount, ct.note, ct.created_at, COALESCE(ct.customer_name, c.name) as customer_name, ct.customer_id, ct.session_id
            FROM credit_transactions ct
            LEFT JOIN credit_customers c ON ct.customer_id = c.id
            WHERE ct.created_at >= ? AND ct.created_at < ? AND ct.amount $op 0
            ORDER BY ct.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $range['start']);
        $stmt->bindValue(2, $range['end']);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'date' => $date,
            'items' => $items,
            'has_more' => count($items) === $limit
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
