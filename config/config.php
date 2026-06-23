<?php
// config/config.php
// Main Configuration File for Chiya Sansar Admin Portal

// ============================================================================
// 1. DATABASE CREDENTIALS (SECURE LOADING)
// ============================================================================

// Priority 1: Single file OUTSIDE webroot (Best for cPanel)
// Example: /home/user/chiya_db_config.php
$secure_path_file = dirname(__DIR__, 2) . '/chiya_db_config.php';

// Priority 2: Folder OUTSIDE webroot via secure_config
// Example: /home/user/secure_config/db_credentials.php
$secure_path_folder = dirname(__DIR__, 2) . '/secure_config/db_credentials.php';

// Priority 3: Internal SECURE folder (secure_config inside project)
$internal_secure = dirname(__DIR__) . '/secure_config/db_credentials.php';

// Priority 4: Fallback to local file in this folder (Legacy/Dev)
$local_fallback = __DIR__ . '/db_credentials.php';


if (file_exists($secure_path_file)) {
    require_once $secure_path_file;
} elseif (file_exists($secure_path_folder)) {
    require_once $secure_path_folder;
} elseif (file_exists($internal_secure)) {
    require_once $internal_secure;
} elseif (file_exists($local_fallback)) {
    require_once $local_fallback;
} else {
    die("❌ Error: Database configuration file not found. Please notify the administrator.");
}


// ============================================================================
// 2. BASE URL CONFIGURATION (AUTO-DETECTION)
// ============================================================================

if (defined('BASE_URL_OVERRIDE') && BASE_URL_OVERRIDE !== 'AUTO') {
    // Use manual override if set in db_credentials.php
    define('BASE_URL', rtrim(BASE_URL_OVERRIDE, '/') . '/');
} else {
    // Auto-detect the base path from the physical location of this config file
    $config_dir = str_replace('\\', '/', realpath(__DIR__));
    $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $app_root = dirname($config_dir);

    // Calculate path relative to document root
    $base_path = str_ireplace($doc_root, '', $app_root);
    $base_path = '/' . trim($base_path, '/\\') . '/';
    $base_path = str_replace('//', '/', $base_path);

    if ($base_path === '//') {
        $base_path = '/';
    }

    define('BASE_URL', $base_path);
}


// ============================================================================
// 3. SYSTEM SETTINGS
// ============================================================================

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Error Reporting
// Error Reporting
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($host === 'localhost') {
    // Development Mode
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production Mode (Errors logged, not shown to visitors)
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}


// ============================================================================
// 3b. APPLICATION TIME HELPERS (Nepal — receipts, reports)
// ============================================================================
require_once dirname(__DIR__) . '/includes/datetime_helper.php';

// ============================================================================
// 4. SESSION CONFIGURATION
// ============================================================================

// Lifetime: 30 Days (2592000 seconds)
$session_lifetime = 2592000;
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $session_lifetime);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
}

// Session Cookie Parameters (Security + Persistence)
if (session_status() === PHP_SESSION_NONE) {
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'secure' => $is_https, // Auto-detect SSL
        'httponly' => true,      // Prevent JS access
        'samesite' => 'Lax'
    ]);
}


// ============================================================================
// 5. SESSION PATH STABILIZATION (HOSTING FIX)
// ============================================================================
// Ensures sessions work even if the hosting tmp directory is restrictive.

$session_save_dir = dirname(__DIR__) . '/sessions';

if (!is_dir($session_save_dir)) {
    @mkdir($session_save_dir, 0755, true);
}

if (is_dir($session_save_dir) && is_writable($session_save_dir)) {
    // Protect the session directory from web access
    if (!file_exists($session_save_dir . '/.htaccess')) {
        @file_put_contents($session_save_dir . '/.htaccess', "Deny from all");
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_save_path($session_save_dir);
    }
}


// ============================================================================
// 6. MULTI-PORTAL SESSION LOGIC
// ============================================================================
// Allows separate logins for Admin and Waiter in the same browser.

$req_uri = $_SERVER['REQUEST_URI'] ?? '';
$sess_name = 'CHIYA_SANSAR'; // Default Global Session

// Check Custom Header from App.js/API calls
$portal_header = isset($_SERVER['HTTP_X_PORTAL']) ? strtoupper($_SERVER['HTTP_X_PORTAL']) : '';
$portal_param = isset($_GET['portal']) ? strtoupper($_GET['portal']) : '';

// Determine Session Name based on Context
// Priority: Header > GET Param > URL Path > Fallback
if (
    $portal_header === 'WAITER' ||
    $portal_param === 'WAITER' ||
    (isset($_GET['type']) && strtoupper($_GET['type']) === 'WAITER') ||
    strpos($req_uri, '/waiter/') !== false ||
    strpos($req_uri, 'login_waiter') !== false
) {
    $sess_name = 'CHIYA_WAITER';
} elseif (
    $portal_header === 'ADMIN' ||
    $portal_param === 'ADMIN' ||
    (isset($_GET['type']) && strtoupper($_GET['type']) === 'ADMIN') ||
    strpos($req_uri, '/admin/') !== false ||
    strpos($req_uri, '/tools/') !== false ||
    strpos($req_uri, 'login_admin') !== false
) {
    $sess_name = 'CHIYA_ADMIN';
}

// Start the Session
if (session_status() === PHP_SESSION_NONE) {
    session_name($sess_name);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Backstop GC for sessions that end without calling logout() (browser closed,
// crash, etc.). Files past gc_maxlifetime (30 days) should still get swept
// eventually — run the sweep here on a ~1-in-50 chance per request instead of
// relying solely on PHP's own probability roll, which historically never
// seemed to clear sessions/ in practice on this host. Must run after
// session_start() — session_gc() requires an active session.
if (session_status() === PHP_SESSION_ACTIVE && mt_rand(1, 50) === 1) {
    session_gc();
}

// Empty-session sweep: session_start() above creates a file on disk for every
// visitor, even anonymous ones who never log in (login pages, index.php, etc.).
// Those files stay 0 bytes forever and don't deserve the full 30-day
// gc_maxlifetime. Sweep them out on a low-probability roll so sessions/ doesn't
// refill with dead files between real session_gc() runs.
if (isset($session_save_dir) && is_dir($session_save_dir) && mt_rand(1, 50) === 1) {
    $cutoff = time() - 600; // 10 minutes — long enough not to race an in-progress login
    foreach (glob($session_save_dir . '/sess_*') as $sessFile) {
        if (is_file($sessFile) && filesize($sessFile) === 0 && filemtime($sessFile) < $cutoff) {
            @unlink($sessFile);
        }
    }
}

// Keep-Alive: Refresh cookie on every request
if (session_status() === PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $session_lifetime, '/', '', $is_https, true);
}

