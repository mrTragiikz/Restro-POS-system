<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Query users table for ADMIN role
    $user = db_query_one("SELECT * FROM users WHERE username = ? AND role = 'ADMIN' LIMIT 1", [$username]);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Force save session to handle WAMP slow writes
        session_write_close();

        header("Location: " . BASE_URL . "admin/index.php");
        exit;
    } else {
        $error = "Invalid admin credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Chiya Sansar</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <script src="<?php echo BASE_URL; ?>assets/js/click_sound.js"></script>
</head>

<body>

    <header>
        <div class="logo">
            <img src="<?php echo BASE_URL; ?>assets/logo.jpeg" alt="Chiya Sansar Logo">
            <h1>Chiya Sansar</h1>
        </div>
        <div class="header-right">
            <div id="nepal-clock" class="time-badge">
                Loading Time...
            </div>
        </div>
    </header>

    <script>
        function updateNepalTime() {
            const now = new Date();
            const options = {
                timeZone: 'Asia/Kathmandu',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const timeString = now.toLocaleTimeString('en-US', options);
            const clockEl = document.getElementById('nepal-clock');
            if (clockEl) {
                clockEl.textContent = timeString;
            }
        }
        setInterval(updateNepalTime, 1000);
        document.addEventListener('DOMContentLoaded', updateNepalTime);
    </script>

    <script>
        // Browsers block auto-fullscreen on page load (requires a user gesture).
        // If the page was fullscreen before navigating/refreshing here, re-enter it on the next click/tap.
        document.addEventListener('fullscreenchange', function () {
            sessionStorage.setItem('wasFullscreen', document.fullscreenElement ? '1' : '0');
        });

        if (sessionStorage.getItem('wasFullscreen') === '1') {
            const resumeFullscreen = function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(() => { });
                }
                document.removeEventListener('click', resumeFullscreen);
                document.removeEventListener('touchend', resumeFullscreen);
                document.removeEventListener('keydown', resumeFullscreen);
            };
            document.addEventListener('click', resumeFullscreen, { once: true });
            document.addEventListener('touchend', resumeFullscreen, { once: true });
            document.addEventListener('keydown', resumeFullscreen, { once: true });
        }
    </script>

    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-brand">Chiya Sansar</div>
            <h2 class="auth-title">Sign in</h2>
            <p class="auth-subtitle">to continue to <strong>Chiya Sansar POS</strong></p>

            <?php if ($error): ?>
                <div
                    style="background: #FFF5F5; color: #C05850; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #FFE0E0; text-align: left;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="auth-field">
                    <label>Username</label>
                    <input type="text" name="username" id="login-username" required autofocus>
                </div>
                <div class="auth-field">
                    <label>Password</label>
                    <input type="password" name="password" id="password-field" required style="padding-right: 40px;">
                    <span id="toggle-password" class="toggle-password">
                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </span>
                </div>
                <script>
                    document.getElementById('toggle-password').addEventListener('click', function () {
                        var pwd = document.getElementById('password-field');
                        var isHidden = pwd.getAttribute('type') === 'password';
                        pwd.setAttribute('type', isHidden ? 'text' : 'password');
                        this.querySelector('.eye-open').style.display = isHidden ? 'none' : '';
                        this.querySelector('.eye-closed').style.display = isHidden ? '' : 'none';
                    });
                </script>
                <div class="auth-actions">
                    <button type="submit" class="auth-next-btn">Next</button>
                </div>
            </form>
            <a href="<?php echo BASE_URL; ?>" class="auth-back-link">Back to Home</a>
        </div>
    </div>


</body>

</html>