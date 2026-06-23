<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chiya Sansar - Premium Experience</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body {
            background-image: linear-gradient(rgba(253, 251, 247, 0.78), rgba(253, 251, 247, 0.78)),
                url('<?php echo BASE_URL; ?>assets/bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .account-picker {
            max-width: 560px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .account-row {
            display: flex;
            align-items: center;
            gap: 18px;
            text-decoration: none;
            color: inherit;
            padding: 20px 22px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(166, 150, 137, 0.08), 0 14px 30px -8px rgba(166, 150, 137, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.9);
            transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.25s ease;
        }

        .account-row:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 8px rgba(166, 150, 137, 0.1), 0 22px 40px -10px rgba(188, 138, 95, 0.28);
        }

        .account-row:active {
            transform: translateY(-1px) scale(0.99);
        }

        .account-avatar {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 50%;
            background: #FDFBF7;
            border: 1px solid #F3EEE8;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
        }

        .account-avatar img {
            width: 75%;
            height: 75%;
            object-fit: contain;
        }

        .account-info {
            flex: 1;
            min-width: 0;
            text-align: left;
        }

        .account-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .account-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .account-continue-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 10px 18px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 4px 12px -2px rgba(188, 138, 95, 0.4);
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .account-row:hover .account-continue-btn {
            background: linear-gradient(135deg, var(--primary-hover), #8a5a36);
            transform: translateX(2px);
        }

        .account-continue-btn.continue-admin {
            background: linear-gradient(135deg, #22c55e, #15803d);
            box-shadow: 0 4px 12px -2px rgba(21, 128, 61, 0.4);
        }

        .account-row:hover .account-continue-btn.continue-admin {
            background: linear-gradient(135deg, #16a34a, #166534);
        }

        .account-continue-btn.continue-waiter {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            box-shadow: 0 4px 12px -2px rgba(185, 28, 28, 0.4);
        }

        .account-row:hover .account-continue-btn.continue-waiter {
            background: linear-gradient(135deg, #dc2626, #991b1b);
        }

        .account-continue-btn svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .welcome-banner {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 30px;
            padding: 0 20px;
            opacity: 0;
            animation: welcomeFadeUp 0.9s ease-out 0.15s forwards;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #000;
        }

        .welcome-sub {
            color: #000;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @keyframes welcomeFadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .account-picker {
            opacity: 0;
            animation: welcomeFadeUp 0.7s ease-out 0.45s forwards;
        }

        @media (max-width: 600px) {
            .account-picker {
                margin: 0 10px;
                gap: 14px;
            }

            .account-row {
                padding: 16px;
                gap: 12px;
            }

            .account-avatar {
                width: 42px;
                height: 42px;
                min-width: 42px;
            }

            .account-name {
                font-size: 1rem;
            }

            .account-sub {
                font-size: 0.8rem;
            }

            .account-continue-btn {
                padding: 10px;
            }

            .account-continue-btn .continue-label {
                display: none;
            }
        }
    </style>
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
        // Run immediately
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

    <div class="container" style="display: flex; flex-direction: column; justify-content: flex-start; padding-top: 12px;">

        <div class="welcome-banner">
            <h2 class="welcome-title">Chiya Sansar POS</h2>
            <p class="welcome-sub">Pick a portal to continue.</p>
        </div>

        <div class="account-picker">
            <a href="<?php echo BASE_URL; ?>auth/login_admin.php" class="account-row">
                <div class="account-avatar">
                    <img src="<?php echo BASE_URL; ?>assets/portal_logo/admin.png" alt="Admin">
                </div>
                <div class="account-info">
                    <div class="account-name">Counter Portal</div>
                    <div class="account-sub">Control tables, oversee billing, and view daily analytics.</div>
                </div>
                <span class="account-continue-btn continue-admin">
                    <span class="continue-label">Continue</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
                </span>
            </a>

            <a href="<?php echo BASE_URL; ?>auth/login_waiter.php" class="account-row">
                <div class="account-avatar">
                    <img src="<?php echo BASE_URL; ?>assets/portal_logo/waiter.png" alt="Waiter">
                </div>
                <div class="account-info">
                    <div class="account-name">Waiter Portal</div>
                    <div class="account-sub">Seat guests, take orders, and send them to the counter.</div>
                </div>
                <span class="account-continue-btn continue-waiter">
                    <span class="continue-label">Continue</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
                </span>
            </a>
        </div>

        <footer style="text-align: center; margin-top: 60px; color: #000; font-size: 0.85rem;">
            &copy;
            <?php echo date('Y'); ?> Chiya Sansar. All rights reserved.
        </footer>
    </div>

</body>

</html>