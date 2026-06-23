<?php
// secure_config/db_credentials.php
// This file contains all sensitive credentials.
// It is included by config/config.php.
// On cPanel/Live Hosting: move this folder OUTSIDE public_html (e.g. /home/user/secure_config/).
// config.php loads db_credentials.php from there automatically.
// Menu PIN hash is stored alongside: menu_pin.hash (created by the app, not plain text).
//
// Copy this file to db_credentials.php (same folder) and fill in real values.
// db_credentials.php is gitignored and must never be committed.

$host = $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = explode(':', $host)[0];
$isLocal = (
    php_sapi_name() === 'cli' ||
    strpos($hostOnly, 'localhost') !== false ||
    strpos($hostOnly, '127.0.0.1') !== false ||
    strpos($hostOnly, '10.') === 0 ||
    strpos($hostOnly, '192.168.') === 0 ||
    strpos($hostOnly, '172.') === 0
);

if ($isLocal) {
    // LOCALHOST (XAMPP / LAN)
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'chiya');
} else {
    // LIVE HOSTING (cPanel)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'your_db_user');
    define('DB_PASS', 'your_db_password');
    define('DB_NAME', 'your_db_name');
}
