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

$name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$address = trim($_POST['address'] ?? '');

if (empty($name) || empty($phone) || empty($address)) {
    echo json_encode(['error' => 'Name, Phone and Address are required']);
    exit;
}

$note = trim($_POST['note'] ?? '');

try {
    $stmt = $pdo->prepare("INSERT INTO credit_customers (name, phone, address) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $address]);
    $id = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'customer' => ['id' => $id, 'full_name' => $name, 'phone' => $phone, 'address' => $address]]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate phone
        echo json_encode(['error' => 'Customer with this phone already exists']);
    } else {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
