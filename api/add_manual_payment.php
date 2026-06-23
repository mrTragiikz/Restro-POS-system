<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

verify_csrf_token($data['csrf_token'] ?? null);

if (!isset($data['customer_id']) || !isset($data['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$customerId = $data['customer_id'];
$amount = floatval($data['amount']);
$note = $data['note'] ?? 'Manual Payment';

// Ensure we are recording a payment (which reduces debt), so we expect positive input for "Amount Paid"
if ($amount <= 0) {
    echo json_encode(['error' => 'Payment amount must be valid']);
    exit;
}

try {
    // 1. Get Customer Name for snapshot
    $cust = db_query_one("SELECT name FROM credit_customers WHERE id = ?", [$customerId]);
    $customer_name = $cust['name'] ?? 'Unknown';

    $payment_mode = $data['payment_mode'] ?? 'Cash';

    // Store as negative value in credit_transactions to represent payment/reduction of debt
    $db_amount = -1 * abs($amount);

    // Insert Payment
    db_execute("INSERT INTO credit_transactions (customer_id, customer_name, amount, note, payment_mode, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())", [$customerId, $customer_name, $db_amount, $note, $payment_mode, $_SESSION['user_id']]);

    // Get ID
    $transaction_id = db_last_insert_id();

    // 2. Check if balance is now 0 (or less) to auto-delete customer
    $bal = db_query_one("SELECT SUM(amount) as balance FROM credit_transactions WHERE customer_id = ?", [$customerId]);
    $current_balance = floatval($bal['balance'] ?? 0);

    if ($current_balance <= 0.01) {
        db_execute("DELETE FROM credit_customers WHERE id = ?", [$customerId]);
        $deleted = true;
    } else {
        $deleted = false;
    }

    // Performance Guard: Sync daily summary
    require_once '../includes/summary_helper.php';
    rebuild_daily_summary(date('Y-m-d'), null, true);

    echo json_encode(['success' => true, 'message' => 'Payment recorded', 'deleted' => $deleted, 'transaction_id' => $transaction_id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>