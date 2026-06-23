<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/stock_availability.php';
require_once '../includes/stock_schema.php';

header('Content-Type: application/json');

if (isset($pdo) && $pdo instanceof PDO) {
    ensure_stock_schema($pdo);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['WAITER', 'ADMIN'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$data = get_counter_stock_availability($pdo);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode([
    'success' => true,
    'out_of_stock' => $data['out_of_stock'],
    'stock' => $data['stock'],
    'available' => $data['available'],
]);
