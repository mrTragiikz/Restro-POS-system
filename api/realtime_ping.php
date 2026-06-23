<?php
// api/realtime_ping.php — returns current order/status version (fast, no session lock)
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/realtime.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Kill-switch: if a WAITER is polling while the portal is deactivated, tell the
// client to log out immediately (no refresh needed).
$role = $_SESSION['role'] ?? '';
$portalDisabled = ($role === 'WAITER') && !waiter_portal_is_enabled();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$state = realtime_read_state();
echo json_encode([
    'version' => $state ? (int) ($state['version'] ?? 0) : 0,
    'portal_disabled' => $portalDisabled,
]);
