<?php
// includes/auth.php

function require_role($role)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        // Redirect to appropriate login based on required role
        $login_page = '';
        switch ($role) {
            case 'ADMIN':
                $login_page = '/auth/login_admin.php';
                break;
            case 'WAITER':
                $login_page = '/auth/login_waiter.php';
                break;
            default:
                $login_page = '/index.php';
        }
        header("Location: " . BASE_URL . ltrim($login_page, '/'));
        exit;
    }
}

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Kill-switch guard for waiter pages. If the current user is a WAITER and the
 * waiter portal has been deactivated by admin, force a logout immediately.
 * Call this right after require_role('WAITER') on every waiter page.
 */
function require_waiter_portal_enabled()
{
    if (($_SESSION['role'] ?? '') !== 'WAITER') {
        return; // admins and others unaffected
    }

    require_once __DIR__ . '/settings.php';
    if (!waiter_portal_is_enabled()) {
        // Clear session and send to logout so they cannot interact at all.
        header("Location: " . BASE_URL . "logout.php?portal=WAITER");
        exit;
    }
}

function logout()
{
    // 1. Clear Data
    $_SESSION = [];

    // 2. Expire the Cookie explicitly
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // 3. Mark as invalid (optional double safety)
    if (isset($_COOKIE[session_name()])) {
        unset($_COOKIE[session_name()]);
    }

    // 4. Delete the session file on disk immediately so the sessions/ directory
    // doesn't accumulate one file per login forever (gc_maxlifetime is 30 days,
    // so relying on PHP's GC sweep alone left thousands of stale files behind).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    header("Location: " . BASE_URL . "index.php");
    exit;
}
