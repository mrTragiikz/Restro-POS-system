<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/realtime.php';
require_once '../includes/settings.php';

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
if ($_SESSION['role'] !== 'WAITER' && $_SESSION['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Kill-switch: block waiters while the portal is deactivated (admins exempt).
if ($_SESSION['role'] === 'WAITER' && !waiter_portal_is_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'The waiter portal is currently unavailable.', 'portal_disabled' => true]);
    exit;
}

verify_csrf();

$itemId = $_POST['item_id'] ?? null;

if (!$itemId) {
    echo json_encode(['error' => 'Item ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check item status
    $stmt = $pdo->prepare("SELECT kitchen_status FROM order_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception("Item not found");
    }

    if ($item['kitchen_status'] !== 'PENDING') {
        throw new Exception("Can't delete: Order is being ready or already prepared.");
    }

    // Restore stock if it was deducted
    require_once '../includes/stock_deduct.php';
    restore_stock_for_item($pdo, $itemId, $_SESSION['user_id'] ?? null);

    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
    $stmt->execute([$itemId]);

    $pdo->commit();
    realtime_notify_orders();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
