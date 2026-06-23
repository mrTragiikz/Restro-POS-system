<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Detect Portal for JS
$portal_js = 'DEFAULT';
if (strpos($_SERVER['REQUEST_URI'], '/waiter/') !== false)
    $portal_js = 'WAITER';
elseif (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false)
    $portal_js = 'ADMIN';

$manage_waiter_promo_seconds_left = 0;
if ($portal_js === 'ADMIN' && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/settings.php';
    $manage_waiter_promo_seconds_left = manage_waiter_promo_seconds_left();
}
?>
<script>
    window.CURRENT_PORTAL = '<?php echo $portal_js; ?>';
    window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chiya Sansar</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=1.14">
    <!-- Admin/Portal Specific Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/admin_floorplan.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin_floorplan.css'); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap"
        rel="stylesheet">
    <script>
        // CLEANER TOUCH HANDLING: Prevent double-tap zoom only, without blocking scroll move
        var lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            var now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
    <script src="<?php echo BASE_URL; ?>assets/js/click_sound.js"></script>
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <header>
            <div class="logo" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                <h1>Chiya Sansar <span style="font-size:0.8em; font-weight:300;">|
                        <?php echo ucfirst(strtolower($_SESSION['role'] ?? '')); ?>
                    </span></h1>
                <!-- Fullscreen Toggle -->
                <?php if ($portal_js !== 'WAITER'): ?>
                <button type="button" id="fullscreen-toggle-btn" class="fullscreen-toggle-btn" onclick="toggleFullscreenMode()" title="Enter Fullscreen">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"></path><path d="M21 8V5a2 2 0 0 0-2-2h-3"></path><path d="M3 16v3a2 2 0 0 0 2 2h3"></path><path d="M16 21h3a2 2 0 0 0 2-2v-3"></path></svg>
                    <span id="fullscreen-toggle-label">Full Screen</span>
                </button>
                <?php endif; ?>
            </div>

            <?php if ($manage_waiter_promo_seconds_left > 0): ?>
            <div id="manage-waiter-promo-banner" class="manage-waiter-promo-banner" data-seconds-left="<?php echo (int) $manage_waiter_promo_seconds_left; ?>">
                <span class="manage-waiter-promo-text">
                    ✨ <strong>Manage Waiter</strong>, ends in
                    <strong id="manage-waiter-promo-countdown">--h --m --s</strong>
                </span>
                <button type="button" class="manage-waiter-promo-btn manage-waiter-promo-btn-flash" onclick="window.location.href='<?php echo BASE_URL; ?>admin/index.php?openTab=manage-waiter'">
                    Try Now
                </button>
            </div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 25px;">
                <!-- Info & Clock -->
                <div class="header-right">
                    <div class="developer-credit">Info: sharmaprabin160@gmail.com</div>
                    <div class="time-badge" id="nepal-time">--:--:--</div>
                </div>

                <!-- User Actions -->
                <div class="nav-actions" style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                    <span style="margin-right: 0; font-weight: 600; color: var(--primary); font-size: 0.9rem;">Hi,
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </span>
                    <?php if ($portal_js !== 'ADMIN'): ?>
                        <a href="<?php echo BASE_URL; ?>logout.php?portal=<?php echo urlencode($portal_js); ?>"
                            id="logout-link" onclick="return confirmLogout(event)" class="btn btn-secondary btn-sm"
                            style="padding: 6px 16px; font-size: 0.8rem; border-radius: 8px;">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Logout Confirmation Modal -->
        <div id="logout-confirm-backdrop" class="logout-confirm-backdrop">
            <div class="logout-confirm-card">
                <div class="logout-confirm-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </div>
                <h3>Log Out?</h3>
                <p>Are you sure you want to end this session and log out?</p>
                <div class="logout-confirm-actions">
                    <button type="button" class="logout-btn logout-btn-cancel" onclick="closeLogoutConfirm()">Cancel</button>
                    <button type="button" class="logout-btn logout-btn-confirm" onclick="proceedLogout()">Yes, Log Out</button>
                </div>
            </div>
        </div>

        <style>
            .manage-waiter-promo-banner {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 14px;
                background: linear-gradient(135deg, #fff7ec, #fdecd2);
                border: 1px solid #f0d9b0;
                border-radius: 999px;
                padding: 8px 18px;
                text-align: center;
                position: relative;
                overflow: hidden;
                flex-shrink: 0;
            }

            .manage-waiter-promo-banner::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                width: 50%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
                animation: manageWaiterPromoShine 3.5s linear infinite;
                pointer-events: none;
            }

            @keyframes manageWaiterPromoShine {
                0% { left: -50%; }
                100% { left: 100%; }
            }

            .manage-waiter-promo-text {
                color: #000;
                font-size: 0.85rem;
                font-weight: 500;
                text-align: center;
                white-space: nowrap;
            }

            .manage-waiter-promo-btn {
                border: none;
                border-radius: 999px;
                padding: 6px 16px;
                font-weight: 700;
                font-size: 0.78rem;
                color: #fff;
                background: linear-gradient(135deg, #ef4444, #b91c1c);
                cursor: pointer;
                box-shadow: 0 4px 12px -2px rgba(185, 28, 28, 0.4);
                transition: background 0.15s, transform 0.1s;
                flex-shrink: 0;
                white-space: nowrap;
            }

            .manage-waiter-promo-btn:hover {
                background: linear-gradient(135deg, #dc2626, #991b1b);
            }

            .manage-waiter-promo-btn:active {
                transform: scale(0.96);
            }

            .manage-waiter-promo-btn-flash {
                animation: manageWaiterPromoFlash 2s ease-in-out infinite;
            }

            @keyframes manageWaiterPromoFlash {
                0%, 100% { opacity: 1; box-shadow: 0 4px 12px -2px rgba(185, 28, 28, 0.4); }
                50% { opacity: 0.55; box-shadow: 0 4px 18px -2px rgba(185, 28, 28, 0.7); }
            }

            .fullscreen-toggle-btn {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #ef4444, #b91c1c);
                color: #fff;
                font-weight: 700;
                font-size: 0.8rem;
                cursor: pointer;
                box-shadow: 0 4px 12px -2px rgba(185, 28, 28, 0.4);
                transition: background 0.15s, transform 0.1s;
            }

            .fullscreen-toggle-btn:hover {
                background: linear-gradient(135deg, #dc2626, #991b1b);
            }

            .fullscreen-toggle-btn:active {
                transform: scale(0.96);
            }

            .fullscreen-toggle-btn svg {
                width: 16px;
                height: 16px;
                flex-shrink: 0;
            }

            .logout-confirm-backdrop {
                display: none;
                position: fixed;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(3px);
                z-index: 9700;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.25s ease;
            }

            .logout-confirm-backdrop.active {
                display: flex;
                opacity: 1;
            }

            .logout-confirm-card {
                background: #fff;
                width: 90%;
                max-width: 360px;
                border-radius: 18px;
                padding: 28px 24px;
                text-align: center;
                box-shadow: 0 24px 50px -10px rgba(0, 0, 0, 0.35);
                transform: scale(0.9) translateY(10px);
                opacity: 0;
                transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.25s ease;
            }

            .logout-confirm-backdrop.active .logout-confirm-card {
                transform: scale(1) translateY(0);
                opacity: 1;
            }

            .logout-confirm-icon {
                width: 56px;
                height: 56px;
                margin: 0 auto 14px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                background: linear-gradient(135deg, #ef4444, #b91c1c);
                box-shadow: 0 6px 16px -4px rgba(185, 28, 28, 0.5);
            }

            .logout-confirm-icon svg {
                width: 26px;
                height: 26px;
            }

            .logout-confirm-card h3 {
                margin: 0 0 6px;
                font-size: 1.2rem;
                font-weight: 800;
                color: #3e2723;
            }

            .logout-confirm-card p {
                margin: 0 0 22px;
                color: #777;
                font-size: 0.9rem;
                line-height: 1.5;
            }

            .logout-confirm-actions {
                display: flex;
                gap: 10px;
            }

            .logout-btn {
                flex: 1;
                padding: 11px 16px;
                border-radius: 10px;
                border: none;
                font-weight: 700;
                font-size: 0.9rem;
                cursor: pointer;
                transition: background 0.15s, transform 0.1s;
            }

            .logout-btn:active {
                transform: scale(0.97);
            }

            .logout-btn-cancel {
                background: #f1ede7;
                color: #5d4037;
            }

            .logout-btn-cancel:hover {
                background: #e6ded4;
            }

            .logout-btn-confirm {
                background: linear-gradient(135deg, #ef4444, #b91c1c);
                color: #fff;
            }

            .logout-btn-confirm:hover {
                background: linear-gradient(135deg, #dc2626, #991b1b);
            }
        </style>

        <script>
            let pendingLogoutHref = null;

            function confirmLogout(e) {
                e.preventDefault();
                pendingLogoutHref = e.currentTarget.href;
                document.getElementById('logout-confirm-backdrop').classList.add('active');
                return false;
            }

            function closeLogoutConfirm() {
                document.getElementById('logout-confirm-backdrop').classList.remove('active');
            }

            function proceedLogout() {
                window.location.href = pendingLogoutHref || document.getElementById('logout-link').href;
            }

            document.getElementById('logout-confirm-backdrop').addEventListener('click', function (e) {
                if (e.target === this) closeLogoutConfirm();
            });
        </script>

        <script>
            function updateNepalTime() {
                const options = {
                    timeZone: 'Asia/Kathmandu',
                    hour12: true,
                    hour: 'numeric',
                    minute: 'numeric',
                    second: 'numeric'
                };
                const timeString = new Date().toLocaleTimeString('en-US', options);
                // Remove any flag emojis if present (clean text)
                document.getElementById('nepal-time').textContent = timeString;
            }
            setInterval(updateNepalTime, 1000);
            updateNepalTime();
        </script>

        <?php if ($portal_js !== 'WAITER'): ?>
        <script>
            const ENTER_FS_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"></path><path d="M21 8V5a2 2 0 0 0-2-2h-3"></path><path d="M3 16v3a2 2 0 0 0 2 2h3"></path><path d="M16 21h3a2 2 0 0 0 2-2v-3"></path></svg>';
            const EXIT_FS_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3"></path><path d="M21 8h-3a2 2 0 0 1-2-2V3"></path><path d="M3 16h3a2 2 0 0 1 2 2v3"></path><path d="M16 21v-3a2 2 0 0 1 2-2h3"></path></svg>';

            function toggleFullscreenMode() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(() => { });
                } else {
                    document.exitFullscreen().catch(() => { });
                }
            }

            function updateFullscreenButton() {
                const btn = document.getElementById('fullscreen-toggle-btn');
                const label = document.getElementById('fullscreen-toggle-label');
                if (!btn || !label) return;
                const isFs = !!document.fullscreenElement;
                btn.title = isFs ? 'Exit Fullscreen' : 'Enter Fullscreen';
                label.textContent = isFs ? 'Exit Full Screen' : 'Full Screen';
                btn.querySelector('svg').outerHTML = isFs ? EXIT_FS_ICON : ENTER_FS_ICON;
                sessionStorage.setItem('wasFullscreen', isFs ? '1' : '0');
            }

            // Browsers block auto-fullscreen on page load (requires a user gesture).
            // If the page was fullscreen before a refresh, re-enter it on the next click/tap.
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

            document.addEventListener('fullscreenchange', updateFullscreenButton);
        </script>
        <?php endif; ?>

        <?php if ($manage_waiter_promo_seconds_left > 0): ?>
        <script>
            (function () {
                const initialSecondsLeft = <?php echo (int) $manage_waiter_promo_seconds_left; ?>;

                function startCountdown() {
                    const banner = document.getElementById('manage-waiter-promo-banner');
                    const bannerLabel = document.getElementById('manage-waiter-promo-countdown');
                    const navBadge = document.getElementById('nav-manage-waiter-countdown');
                    if (!bannerLabel && !navBadge) return;

                    let secondsLeft = initialSecondsLeft;
                    let timer = null;

                    function tick() {
                        if (secondsLeft <= 0) {
                            if (banner) banner.style.display = 'none';
                            if (navBadge) navBadge.style.display = 'none';
                            if (timer) clearInterval(timer);
                            return;
                        }
                        const h = Math.floor(secondsLeft / 3600);
                        const m = Math.floor((secondsLeft % 3600) / 60);
                        const s = secondsLeft % 60;
                        const text = h + 'h ' + m + 'm ' + s + 's';
                        if (bannerLabel) bannerLabel.textContent = text;
                        if (navBadge) navBadge.textContent = text;
                        secondsLeft -= 1;
                    }
                    tick();
                    timer = setInterval(tick, 1000);
                }

                // The sidebar badge lives in a page-specific file loaded after
                // header.php, so the DOM may not have it yet at this point.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', startCountdown);
                } else {
                    startCountdown();
                }
            })();
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <div class="container">