<?php
// api/menu_action.php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/menu_pin.php';

header('Content-Type: application/json');

// Only Admins can manage menu
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $items = db_query_all("SELECT * FROM menu_items ORDER BY name ASC");
        echo json_encode(['success' => true, 'items' => $items]);

    } elseif ($action === 'save') {
        menu_pin_require_unlocked();
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $is_available = intval($_POST['is_available'] ?? 1);
        $is_counter_item = intval($_POST['is_counter_item'] ?? 0);

        if (empty($name) || $price < 0) {
            throw new Exception("Name and valid price are required");
        }

        if ($id > 0) {
            db_execute("UPDATE menu_items SET name = ?, price = ?, is_available = ?, is_counter_item = ? WHERE id = ?", [$name, $price, $is_available, $is_counter_item, $id]);
        } else {
            db_execute("INSERT INTO menu_items (name, price, is_available, is_counter_item) VALUES (?, ?, ?, ?)", [$name, $price, $is_available, $is_counter_item]);
        }
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        menu_pin_require_unlocked();
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0)
            throw new Exception("Invalid ID");

        db_execute("DELETE FROM menu_items WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'toggle_status') {
        menu_pin_require_unlocked();
        verify_csrf();
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0)
            throw new Exception("Invalid ID");

        db_execute("UPDATE menu_items SET is_available = 1 - is_available WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
