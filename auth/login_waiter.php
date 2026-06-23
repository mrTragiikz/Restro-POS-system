<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/settings.php';

$error = '';
$portal_enabled = waiter_portal_is_enabled();
$portal_schedule = waiter_portal_get_schedule();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Kill-switch: refuse all waiter logins while the portal is deactivated,
    // even with correct credentials.
    if (!$portal_enabled) {
        $error = "The waiter portal is currently unavailable.";
    } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $user = db_query_one("SELECT * FROM users WHERE username = ? AND role = 'WAITER' LIMIT 1", [$username]);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        session_write_close();

        header("Location: " . BASE_URL . "waiter/index.php");
        exit;
    } else {
        $error = "Invalid waiter credentials";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiter Login - Chiya Sansar</title>
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
            <div class="developer-credit">
                Info: <strong>sharmaprabin160@gmail.com</strong>
            </div>
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

            <?php if (!$portal_enabled): ?>
                <div
                    style="background:#FBF3E7; border:1px solid #EBD9BC; border-radius:14px; padding:24px 20px; margin-bottom:18px; text-align:center;">
                    <div style="width:54px; height:54px; margin:0 auto 14px; border-radius:50%; background:#F3E4C9; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#B5852E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                    <?php if ($portal_schedule): ?>
                        <div style="font-weight:700; color:#8a6515; font-size:1.05rem; margin-bottom:6px;">Currently Closed</div>
                        <div style="color:#9c7c45; font-size:0.9rem; line-height:1.5; margin-bottom:10px;">
                            Opens at <strong id="portal-open-label"></strong>
                        </div>
                        <div style="color:#9c7c45; font-size:0.85rem; margin-bottom:4px;">Will be open in</div>
                        <div id="portal-countdown" style="font-size:1.3rem; font-weight:800; color:#8a6515; letter-spacing:0.02em;">--:--:--</div>
                    <?php else: ?>
                        <div style="font-weight:700; color:#8a6515; font-size:1.05rem; margin-bottom:6px;">Portal Currently Unavailable</div>
                        <div style="color:#9c7c45; font-size:0.9rem; line-height:1.5;">
                            The waiter portal has been temporarily turned off by the administrator.
                            Please contact the counter to enable access.
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($error): ?>
                <div
                    style="background: #FFF5F5; color: #C05850; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #FFE0E0; text-align: left;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php $disabledAttr = $portal_enabled ? '' : 'disabled'; ?>
            <form method="POST" style="<?php echo $portal_enabled ? '' : 'opacity:0.55; pointer-events:none;'; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="auth-field">
                    <label>Waiter ID</label>
                    <input type="text" name="username" required <?php echo $portal_enabled ? 'autofocus' : ''; ?> <?php echo $disabledAttr; ?>>
                </div>
                <div class="auth-field">
                    <label>Password</label>
                    <input type="password" name="password" id="password-field" required style="padding-right: 40px;" <?php echo $disabledAttr; ?>>
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
                    <button type="submit" class="auth-next-btn" <?php echo $disabledAttr; ?>>
                        <?php echo $portal_enabled ? 'Next' : 'Unavailable'; ?>
                    </button>
                </div>
            </form>
            <a href="<?php echo BASE_URL; ?>" class="auth-back-link">Back to Home</a>
        </div>
    </div>

    <?php if (!$portal_enabled): ?>
    <script>
        // Portal is currently OFF — poll the public status endpoint and reload
        // automatically the moment an admin enables it, so the waiter can log in
        // without a manual refresh.
        (function () {
            var statusUrl = '<?php echo BASE_URL; ?>api/waiter_portal_status.php';
            var timer = setInterval(function () {
                fetch(statusUrl, { cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.enabled) {
                            clearInterval(timer);
                            window.location.reload();
                        }
                    })
                    .catch(function () { /* ignore transient errors, keep polling */ });
            }, 3000);
        })();
    </script>
    <?php endif; ?>

    <?php if (!$portal_enabled && $portal_schedule): ?>
    <script>
        // Live "opens in HH:MM:SS" countdown to the scheduled opening time (Nepal time).
        (function () {
            var openParts = '<?php echo $portal_schedule['open']; ?>'.split(':');
            var openHour = parseInt(openParts[0], 10);
            var openMinute = parseInt(openParts[1], 10);

            function nepalNow() {
                return new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kathmandu' }));
            }

            function formatLabel(h, m) {
                var ampm = h >= 12 ? 'PM' : 'AM';
                var h12 = h % 12; if (h12 === 0) h12 = 12;
                return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
            }

            var label = document.getElementById('portal-open-label');
            if (label) label.textContent = formatLabel(openHour, openMinute);

            function tick() {
                var now = nepalNow();
                var target = new Date(now);
                target.setHours(openHour, openMinute, 0, 0);
                if (target <= now) {
                    target.setDate(target.getDate() + 1);
                }
                var diffMs = target - now;
                var totalSec = Math.max(0, Math.floor(diffMs / 1000));
                var hh = Math.floor(totalSec / 3600);
                var mm = Math.floor((totalSec % 3600) / 60);
                var ss = totalSec % 60;
                var el = document.getElementById('portal-countdown');
                if (el) {
                    el.textContent = String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
                }
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
    <?php endif; ?>

</body>

</html>