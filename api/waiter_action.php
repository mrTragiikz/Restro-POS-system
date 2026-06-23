<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/realtime.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');

// Allow Waiter and Admin
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
if (!in_array($_SESSION['role'], ['WAITER', 'ADMIN'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Kill-switch: block waiters from acting while the portal is deactivated (admins exempt).
if ($_SESSION['role'] === 'WAITER' && !waiter_portal_is_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'The waiter portal is currently unavailable.', 'portal_disabled' => true]);
    exit;
}

verify_csrf();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'serve_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            echo json_encode(['error' => 'Invalid item']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT oi.id, oi.kitchen_status, o.session_id
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE oi.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Item not found']);
            exit;
        }

        if ($row['kitchen_status'] !== 'SERVED') {
            $upd = $pdo->prepare("UPDATE order_items SET kitchen_status = 'SERVED' WHERE id = ?");
            $upd->execute([$itemId]);
        }

        require_once '../includes/stock_deduct.php';
        $sessionId = (int) $row['session_id'];
        if ($sessionId > 0) {
            deduct_counter_stock_for_session($pdo, $sessionId, $_SESSION['user_id'] ?? null);
        }

        $pdo->commit();
        realtime_notify_orders();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
