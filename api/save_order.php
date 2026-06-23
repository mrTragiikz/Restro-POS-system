<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/stock_availability.php';
require_once '../includes/realtime.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');

// Handle Pre-flight
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

// Kill-switch: block waiters from acting while the portal is deactivated.
if (($_SESSION['role'] ?? '') === 'WAITER' && !waiter_portal_is_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'The waiter portal is currently unavailable.', 'portal_disabled' => true]);
    exit;
}

verify_csrf();

// INPUT VALIDATION
$table_id = $_POST['table_id'] ?? null;
$items_json = $_POST['items'] ?? '[]';
$items = json_decode($items_json, true);

if (!$table_id || empty($items)) {
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ========================================================================
    // 1. MANAGE TABLE SESSION
    // ========================================================================

    // Check if table has an active session
    $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id = ? AND closed_at IS NULL LIMIT 1");
    $stmt->execute([$table_id]);
    $session = $stmt->fetch();

    $session_id = null;

    if ($session) {
        // Reuse existing session
        $session_id = $session['id'];

        // Reactivate session if it was mistakenly marked paid but still open
        $pdo->prepare("UPDATE table_sessions SET is_paid = 0 WHERE id = ?")->execute([$session_id]);
        $pdo->prepare("UPDATE tables SET status = 'OCCUPIED' WHERE id = ?")->execute([$table_id]);
    } else {
        // Create NEW session
        $stmt = $pdo->prepare("INSERT INTO table_sessions (table_id, opened_by) VALUES (?, ?)");
        $stmt->execute([$table_id, $_SESSION['user_id']]);
        $session_id = $pdo->lastInsertId();

        // Mark Table as Occupied
        $stmt = $pdo->prepare("UPDATE tables SET status = 'OCCUPIED' WHERE id = ?");
        $stmt->execute([$table_id]);
    }


    // ========================================================================
    // 2. STOCK CHECK (counter items with track stock on)
    // ========================================================================
    $stockError = validate_counter_stock_order_items($pdo, $items);
    if ($stockError !== null) {
        $pdo->rollBack();
        echo json_encode([
            'error' => $stockError['message'],
            'stock_error' => $stockError,
        ]);
        exit;
    }

    // ========================================================================
    // 3. CREATE ORDER HEADER
    // ========================================================================
    $stmt = $pdo->prepare("INSERT INTO orders (session_id, created_by) VALUES (?, ?)");
    $stmt->execute([$session_id, $_SESSION['user_id']]);
    $order_id = $pdo->lastInsertId();


    // ========================================================================
    // 4. INSERT ORDER ITEMS
    // ========================================================================
    $stmt = $pdo->prepare("
        INSERT INTO order_items 
        (order_id, menu_item_id, item_name_snapshot, unit_price_snapshot, qty, note, kitchen_status) 
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
    ");

    foreach ($items as $item) {
        $stmt->execute([
            $order_id,
            $item['id'],
            $item['name'],
            $item['price'],
            $item['qty'],
            $item['note'] ?? null
        ]);
    }

    $pdo->commit();

    realtime_notify_orders();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
