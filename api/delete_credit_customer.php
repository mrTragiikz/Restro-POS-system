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
    echo json_encode(['error' => 'Forbidden: Admin access required']);
    exit;
}

verify_csrf();

$customer_id = $_POST['customer_id'] ?? null;

if (!$customer_id) {
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    /* 
       Keep transactions for audit history 
       $stmt = $pdo->prepare("DELETE FROM credit_transactions WHERE customer_id = ?");
       $stmt->execute([$customer_id]);
    */

    // Delete customer
    $stmt = $pdo->prepare("DELETE FROM credit_customers WHERE id = ?");
    $stmt->execute([$customer_id]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>