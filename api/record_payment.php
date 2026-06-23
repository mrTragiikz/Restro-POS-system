<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

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
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

verify_csrf();

$customer_id = $_POST['customer_id'] ?? null;
$amount = floatval($_POST['amount'] ?? 0);
$note = trim($_POST['note'] ?? '');

if (!$customer_id) {
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['error' => 'Valid payment amount is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    $payment_mode = $_POST['payment_mode'] ?? 'Cash';

    // 1. Get Customer Name for snapshot
    $stmt = $pdo->prepare("SELECT name FROM credit_customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $cust['name'] ?? 'Unknown';

    // Payment is recorded as a NEGATIVE amount in the credit_transactions table
    $payment_amount = -$amount;
    $final_note = "PAYMENT: [" . $payment_mode . "] " . $note;

    $stmt = $pdo->prepare("INSERT INTO credit_transactions (customer_id, customer_name, amount, note, payment_mode, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$customer_id, $customer_name, $payment_amount, $final_note, $payment_mode, $_SESSION['user_id']]);
    $transaction_id = $pdo->lastInsertId();

    // 2. Check if balance is now 0 (or less) to auto-delete customer
    $stmt = $pdo->prepare("SELECT SUM(amount) as balance FROM credit_transactions WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $bal = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_balance = floatval($bal['balance'] ?? 0);

    if ($current_balance <= 0.01) {
        $pdo->prepare("DELETE FROM credit_customers WHERE id = ?")->execute([$customer_id]);
        $deleted = true;
    } else {
        $pdo->prepare("UPDATE credit_customers SET current_balance = ? WHERE id = ?")->execute([$current_balance, $customer_id]);
        $deleted = false;
    }

    $pdo->commit();

    // Performance Guard: Sync daily summary
    require_once '../includes/perf_guard.php';
    PerfGuard::syncDailySummary($pdo, date('Y-m-d'), true);

    echo json_encode(['success' => true, 'deleted' => $deleted, 'transaction_id' => $transaction_id]);
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>