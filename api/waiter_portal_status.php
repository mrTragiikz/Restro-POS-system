<?php
// api/waiter_portal_status.php — Public, read-only check of the waiter portal switch.
// Used by the waiter login page to unlock itself live when admin enables the portal.
// Exposes nothing sensitive — only whether the login form should be active.

require_once '../config/config.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'enabled' => waiter_portal_is_enabled(),
    'schedule' => waiter_portal_get_schedule(),
]);
