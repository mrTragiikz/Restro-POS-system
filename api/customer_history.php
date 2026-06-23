<?php
ob_start();
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(401);
    exit;
}

$customerId = $_GET['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

try {
    // 1. Get Customer Info
    $stmt = $pdo->prepare("SELECT id, name as full_name, phone, address, created_at FROM credit_customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }

    // 2. Get Recent Transactions (Limit 50)
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.amount, t.created_at, t.note,
            c.code as table_code, 
            t.session_id as session_id,
            (SELECT GROUP_CONCAT(CONCAT(item_name_snapshot, ' x ', qty) SEPARATOR ', ') 
             FROM order_items 
             WHERE order_id IN (SELECT id FROM orders WHERE session_id = t.session_id)) as items
        FROM credit_transactions t
        LEFT JOIN table_sessions s ON t.session_id = s.id
        LEFT JOIN tables c ON s.table_id = c.id
        WHERE t.customer_id = ?
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$customerId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate Totals via SQL
    require_once '../includes/perf_guard.php';
    $range = PerfGuard::makeRange(date('Y-m-d'));

    $stmtStats = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_accrued,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_paid,
            SUM(CASE WHEN amount < 0 AND created_at >= ? AND created_at < ? THEN ABS(amount) ELSE 0 END) as paid_today
        FROM credit_transactions
        WHERE customer_id = ?
    ");
    $stmtStats->execute([$range['start'], $range['end'], $customerId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $totalAccrued = (float) ($stats['total_accrued'] ?? 0);
    $totalPaid = (float) ($stats['total_paid'] ?? 0);
    $paidToday = (float) ($stats['paid_today'] ?? 0);
    $netOutstanding = $totalAccrued - $totalPaid;

    ob_clean();
    echo json_encode([
        'customer' => $customer,
        'history' => $history,
        'stats' => [
            'total_accrued' => $totalAccrued,
            'total_paid' => $totalPaid,
            'paid_today' => $paidToday,
            'outstanding' => $netOutstanding
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
