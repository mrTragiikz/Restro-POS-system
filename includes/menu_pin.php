<?php
// includes/menu_pin.php — Menu management PIN (hashed file in secure_config)

const MENU_PIN_SESSION_KEY = 'menu_pin_unlocked_at';
const MENU_PIN_UNLOCK_TTL = 28800; // 8 hours

/**
 * Paths to try (same order as config.php secure loading).
 * Outside webroot: /home/user/secure_config/menu_pin.hash
 */
function menu_pin_candidate_paths(): array
{
    return [
        dirname(__DIR__, 2) . '/secure_config/menu_pin.hash',
        dirname(__DIR__) . '/secure_config/menu_pin.hash',
    ];
}

function menu_pin_find_file(): ?string
{
    foreach (menu_pin_candidate_paths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/** Writable secure_config directory (prefer outside public_html). */
function menu_pin_storage_path(): string
{
    foreach (menu_pin_candidate_paths() as $path) {
        $dir = dirname($path);
        if (is_dir($dir) && is_writable($dir)) {
            return $path;
        }
    }
    $fallback = dirname(__DIR__) . '/secure_config/menu_pin.hash';
    $dir = dirname($fallback);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $fallback;
}

function menu_pin_migrate_legacy_plaintext(): void
{
    $legacy = dirname(__DIR__) . '/config/menu_pin.txt';
    if (!is_file($legacy)) {
        return;
    }
    $plain = trim((string) file_get_contents($legacy));
    if ($plain === '' || strlen($plain) !== 4 || !ctype_digit($plain)) {
        $plain = '1234';
    }
    menu_pin_set($plain);
    @unlink($legacy);
}

function menu_pin_read_hash(): string
{
    $path = menu_pin_find_file();
    if ($path !== null) {
        $hash = trim((string) file_get_contents($path));
        if ($hash !== '') {
            return $hash;
        }
    }

    menu_pin_migrate_legacy_plaintext();

    $path = menu_pin_find_file();
    if ($path !== null) {
        $hash = trim((string) file_get_contents($path));
        if ($hash !== '') {
            return $hash;
        }
    }

    menu_pin_set('1234');
    return trim((string) file_get_contents(menu_pin_storage_path()));
}

function menu_pin_set(string $plainPin): bool
{
    if (strlen($plainPin) !== 4 || !ctype_digit($plainPin)) {
        throw new InvalidArgumentException('PIN must be exactly 4 digits.');
    }

    $hash = password_hash($plainPin, PASSWORD_DEFAULT);
    $path = menu_pin_storage_path();
    if (file_put_contents($path, $hash . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Failed to save PIN. Check secure_config folder permissions.');
    }

    return true;
}

function menu_pin_verify(string $plainPin): bool
{
    if (strlen($plainPin) !== 4 || !ctype_digit($plainPin)) {
        return false;
    }
    return password_verify($plainPin, menu_pin_read_hash());
}

function menu_pin_unlock_session(): void
{
    $_SESSION[MENU_PIN_SESSION_KEY] = time();
}

function menu_pin_session_valid(): bool
{
    if (empty($_SESSION[MENU_PIN_SESSION_KEY])) {
        return false;
    }
    $at = (int) $_SESSION[MENU_PIN_SESSION_KEY];
    return (time() - $at) < MENU_PIN_UNLOCK_TTL;
}

function menu_pin_clear_session(): void
{
    unset($_SESSION[MENU_PIN_SESSION_KEY]);
}

/** JSON API guard for menu / users / stock mutations. */
function menu_pin_require_unlocked(): void
{
    if (menu_pin_session_valid()) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Menu PIN required',
        'code' => 'MENU_PIN_REQUIRED',
    ]);
    exit;
}

/** Full-page admin guard (redirect instead of JSON). */
function menu_pin_require_unlocked_or_redirect(string $redirectUrl): void
{
    if (menu_pin_session_valid()) {
        return;
    }
    header('Location: ' . $redirectUrl);
    exit;
}
