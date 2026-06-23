<?php
// api/user_action.php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/menu_pin.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');

// Only Admins can manage users
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

$action = $_GET['action'] ?? '';

try {
    if ($action === 'verify_menu_pin') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        verify_csrf();

        $pin = trim($_POST['pin'] ?? '');
        if (!menu_pin_verify($pin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Incorrect PIN']);
            exit;
        }

        menu_pin_unlock_session();
        echo json_encode(['success' => true, 'message' => 'PIN verified']);

    } elseif ($action === 'list') {
        $stmt = $pdo->query("
            SELECT id, username, role, is_active, created_at
            FROM users
            WHERE role IN ('ADMIN', 'WAITER')
            ORDER BY role ASC, username ASC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);

    } elseif ($action === 'change_password') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request method");
        }

        verify_csrf();
        menu_pin_require_unlocked();

        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if ($user_id <= 0 || empty($new_password)) {
            throw new Exception("User ID and Password are required");
        }

        $target = db_query_one("SELECT id, role FROM users WHERE id = ?", [$user_id]);
        if (!$target || !in_array($target['role'], ['ADMIN', 'WAITER'], true)) {
            throw new Exception('User not found or cannot be managed here');
        }

        if (strlen($new_password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role IN ('ADMIN', 'WAITER')");
        $stmt->execute([$password_hash, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);

    } elseif ($action === 'change_menu_pin') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request method");
        }
        verify_csrf();
        menu_pin_require_unlocked();

        $new_pin = trim($_POST['new_pin'] ?? '');
        menu_pin_set($new_pin);
        menu_pin_clear_session();

        echo json_encode(['success' => true, 'message' => 'PIN updated successfully. Enter the new PIN again.']);

    } elseif ($action === 'get_waiter_portal_state') {
        $schedule = waiter_portal_get_schedule();
        echo json_encode([
            'success' => true,
            'enabled' => waiter_portal_is_enabled(),
            'schedule' => $schedule,
            'manage_waiter_unlocked' => manage_waiter_is_unlocked(),
        ]);

    } elseif ($action === 'set_waiter_portal_state') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        verify_csrf();

        if (!manage_waiter_is_unlocked()) {
            throw new Exception('Your Manage Waiter trial has ended. Contact the developer to unlock this feature.');
        }

        if (waiter_portal_get_schedule() !== null) {
            throw new Exception('A timer is active. Remove the timer to control the portal manually.');
        }

        $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 1 || $_POST['enabled'] === true);
        $ok = waiter_portal_set_enabled($enabled, (int) $_SESSION['user_id']);
        if (!$ok) {
            throw new Exception('Failed to update waiter portal state');
        }

        echo json_encode([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Waiter portal enabled' : 'Waiter portal deactivated',
        ]);

    } elseif ($action === 'set_waiter_portal_schedule') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        verify_csrf();

        if (!manage_waiter_is_unlocked()) {
            throw new Exception('Your Manage Waiter trial has ended. Contact the developer to unlock this feature.');
        }

        $open = trim($_POST['open_time'] ?? '');
        $close = trim($_POST['close_time'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $close)) {
            throw new Exception('Invalid time format');
        }

        $ok = waiter_portal_set_schedule($open, $close, (int) $_SESSION['user_id']);
        if (!$ok) {
            throw new Exception('Failed to save timer');
        }

        echo json_encode([
            'success' => true,
            'enabled' => waiter_portal_is_enabled(),
            'schedule' => ['open' => $open, 'close' => $close],
            'message' => 'Timer set successfully',
        ]);

    } elseif ($action === 'clear_waiter_portal_schedule') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        verify_csrf();

        if (!manage_waiter_is_unlocked()) {
            throw new Exception('Your Manage Waiter trial has ended. Contact the developer to unlock this feature.');
        }

        $ok = waiter_portal_clear_schedule((int) $_SESSION['user_id']);
        if (!$ok) {
            throw new Exception('Failed to remove timer');
        }

        echo json_encode([
            'success' => true,
            'enabled' => waiter_portal_is_enabled(),
            'message' => 'Timer removed',
        ]);

    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
