<?php
// includes/csrf.php

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token()
{
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token = null): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'CSRF Token Mismatch']);
            exit;
        }
        die('CSRF Token Mismatch');
    }
}

function verify_csrf(): void
{
    verify_csrf_token(null);
}
