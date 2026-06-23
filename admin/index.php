<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/db.php';
require_once '../includes/summary_helper.php';

// Enforce Admin Access
require_role('ADMIN');

// Init Date
$today = date('Y-m-d');

// ============================================================================
// 1. DASHBOARD ANALYTICS (Optimized)
// ============================================================================

// A. Rebuild Daily Summary
// Ensures 'daily_summary' table has the latest stats for today (cached 60s)
$todayStats = rebuild_daily_summary($today);


// B. Get Balance Upto Yesterday (OPTIMIZED)
// Instead of summing millions of ledger rows, we sum the daily_summary table.
// This is much faster since it's 1 row per day.
$resRaw = db_query_one("
    SELECT COALESCE(SUM(total_credit_given - total_credit_paid), 0) as val 
    FROM daily_summary 
    WHERE daily_date < ?
", [$today]);
$initOldCredit = floatval($resRaw['val']);

// C. Use Today's Metrics from Summary
$initTodayCredit = floatval($todayStats['credit_given']);
$initTodayPaid = [
    'total' => floatval($todayStats['credit_paid']),
    // For cash/online breakdown in audit view, we might still need a quick query 
    // OR we could have included it in the summary. For simplicity, we keep the specific breakdown query
    // but it's only for Today and thus very fast (indexed).
    'online' => floatval(db_query_one("SELECT ABS(COALESCE(SUM(amount), 0)) as val FROM credit_transactions WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND amount < 0 AND payment_mode = 'Online'", [$today, $today])['val']),
    'cash' => floatval(db_query_one("SELECT ABS(COALESCE(SUM(amount), 0)) as val FROM credit_transactions WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND amount < 0 AND payment_mode != 'Online'", [$today, $today])['val'])
];

// D. Calculate Net Changes
$netChangeToday = $initTodayCredit - $initTodayPaid['total'];
$currBal = $initOldCredit + $netChangeToday; // Total current balance


require_once '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Admin Styles loaded via header.php -> assets/css/admin.css -->


<!-- Mobile Overlay & Toggle -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="">☰</i>
</button>

<!-- Navigation Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">Admin Panel</div>
    </div>

    <div class="sidebar-menu">
        <div class="nav-item active" id="nav-tables" onclick="switchTab('tables')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></span>
            <span>Tables & Billing</span>
        </div>
        <div class="nav-item" id="nav-kitchen" onclick="switchTab('kitchen')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l1.5 1.5L3 5"></path><path d="M7 2l1.5 1.5L7 5"></path><path d="M11 2l1.5 1.5L11 5"></path><path d="M5 8h14a2 2 0 0 1 2 2v1a7 7 0 0 1-7 7h-4a7 7 0 0 1-7-7v-1a2 2 0 0 1 2-2z"></path><line x1="2" y1="21" x2="22" y2="21"></line></svg></span>
            <span>Kitchen Monitor</span>
        </div>
        <div class="nav-item" id="nav-menu" onclick="switchTab('menu')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></span>
            <span>Menu</span>
        </div>
        <a href="<?php echo BASE_URL; ?>admin/stockmanagement.php" class="nav-item" id="nav-stock"
            onclick="return openStockManagement(event)">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></span>
            <span>Stock Management</span>
        </a>
        <div class="nav-item" id="nav-daily-sales" onclick="switchTab('daily-sales')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg></span>
            <span>Total Sales</span>
        </div>
        <div class="nav-item" id="nav-sales" onclick="switchTab('sales')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
            <span>Credit Audit</span>
        </div>
        <div class="nav-item" id="nav-users" onclick="switchTab('users')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
            <span>Users</span>
        </div>
        <div class="nav-item" id="nav-manage-waiter" onclick="switchTab('manage-waiter')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 19h20"></path><path d="M4 19c0-6 3.5-10 8-10s8 4 8 10"></path><path d="M12 9V5"></path><path d="M9.5 5h5"></path></svg></span>
            <span>Manage Waiter</span>
            <?php if ($manage_waiter_promo_seconds_left > 0): ?>
                <span id="nav-manage-waiter-countdown" class="nav-manage-waiter-countdown" data-seconds-left="<?php echo (int) $manage_waiter_promo_seconds_left; ?>">--h --m --s</span>
            <?php endif; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>logout.php?portal=ADMIN" class="nav-item nav-item-logout" id="nav-logout"
            onclick="return confirmLogout(event)">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
            <span>Logout</span>
        </a>
    </div>

    <!-- Simple Footer inside Sidebar -->
    <div style="padding: 24px; font-size: 0.8rem; color: #aaa; text-align: center;">
        &copy; Chiya Sansar
    </div>
</aside>


<!-- Main Content Area -->
<div class="admin-shell">

    <!-- Page Header -->
    <div class="admin-page-header flex flex-between">
        <div>
            <h2 class="admin-page-title">Dashboard</h2>
            <div id="clock" class="admin-clock">--:--</div>
        </div>
    </div>

    <!-- TAB CONTENT: TABLES (Floor Plan View) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/mobile_floorplan.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/mobile_floorplan.css'); ?>">

    <style>
        /* DEEP ALIGNMENT WITH ADMIN */
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        body {
            background-color: #fbf7f0 !important;
            overflow-x: hidden;
            position: relative;
            width: 100%;
        }

        /* Prevent Android "Scroll Leaking" to body */
        @media screen and (max-width: 768px) {
            body {
                overscroll-behavior-y: none;
                /* Optional: height: 100vh; overflow: hidden; if you want 100% pure scroll containment */
            }
        }

        /* Distributed Status Labels (Waiters) */
        .d-label {
            position: absolute;
            font-size: 0.75rem;
            /* Bigger as requested */
            font-weight: 800;
            padding: 3px 6px;
            border-radius: 6px;
            background: #fff;
            z-index: 20;
            line-height: 1;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        /* Served Badge - Top Center (or shifting Right) */
        .d-top {
            top: -6px;
            /* Pop out slightly */
            right: -4px;
            /* Move to Top Right corner */
            left: auto;
            transform: none;
            background: #5e64ff;
            /* Blue */
            color: white;
            border: none;
            z-index: 22;
        }

        /* Paid Badge - Top Left */
        /* Paid Badge - Left Side, Vertical, 270 deg (Top-Left pointing down) */
        .d-left {
            top: 50%;
            /* Center vertically initially */
            left: -22px !important;
            /* Move outside more to avoid text overlap */
            transform: translateY(-50%) rotate(-90deg);
            /* Center Y, then rotate */
            transform-origin: center;
            background: #ffffff;
            color: #2e7d32;
            /* Green */
            border: 1px solid #2e7d32;
            z-index: 21;
            /* Make it look like a vertical tag */
            border-radius: 4px 4px 0 0;
            /* Rounded top when vertical */
            padding: 4px 8px;
            /* Slightly more padding for touch/visibility */
            box-shadow: -2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Card Backgrounds */
        /* --- PROFESSIONAL CARD STYLING (Restored) --- */

        /* Base Card Style */
        .table-spot,
        .zone-cabin-1,
        .zone-cabin-2,
        .zone-cabin-3,
        .zone-cabin-4,
        .zone-cabin-5 {
            border-radius: 16px !important;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: visible !important;
            /* Allow badges to pop */
        }

        .table-spot:active {
            transform: scale(0.98);
        }

        /* Modern Gradients for Statuses */
        .status-occupied {
            background: linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%) !important;
            background: linear-gradient(135deg, #ff8a65 0%, #ffab91 100%) !important;
            /* Warm Orange/Terra */
            color: #3e2723 !important;
        }

        .status-paid {
            background: linear-gradient(135deg, #a8e063 0%, #56ab2f 100%) !important;
            background: linear-gradient(135deg, #81c784 0%, #a5d6a7 100%) !important;
            /* Fresh Green */
            color: #1b5e20 !important;
        }

        .status-free {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%) !important;
            color: #455a64 !important;
            border: 1px solid #ddd !important;
        }

        /* Typography inside cards */
        .mini-code {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
            text-shadow: 0 1px 1px rgba(255, 255, 255, 0.2);
        }

        /* Icon styling */
        .zone-icon {
            font-size: 1.4rem;
            margin-bottom: 2px;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.1));
        }

        /* Anchor wrapper */
        /* Anchor wrapper */
        .spot-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            text-decoration: none;
            color: inherit;
            position: relative;
            padding: 4px 2px;
            /* Reduced breathing room to prevent overflow */
            box-sizing: border-box;
            /* IMPORTANT: Ensure padding doesn't add to width/height */
        }

        .table-spot {
            display: flex !important;
            flex-direction: column;
            justify-content: space-between;
            padding-bottom: 2px;
            /* Ensure box has enough space */
            box-sizing: border-box;
        }

        /* Mini Status Styling - Professional Glassmorphism Pill */
        /* Mini Status Styling - Perfect Fit & No Cutoff */
        .mini-status {
            font-size: 0.6rem;
            /* Smaller to fit "2 COOKING" */
            font-weight: 800;
            margin-top: 4px;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.95);
            padding: 2px 6px;
            border-radius: 12px;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

            /* Centering & Sizing */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            width: auto;
            max-width: 96%;
            /* Allow it to barely touch edges */

            text-align: center;
            letter-spacing: -0.2px;
            /* Tigher text */
            z-index: 15;

            /* NO TRUNCATION */
            overflow: visible;
            text-overflow: clip;
        }

        /* Specific text colors will be handled by logic or span */

        .small-label {
            font-size: 0.5rem;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .mini-total {
            font-size: 0.7rem;
            font-weight: 800;
            margin-top: 2px;
        }

        .admin-shell {
            padding: 20px 10px;
            /* Reduced padding for mobile fits */
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3e2723;
            margin: 0;
        }

        /* Aggressive Layout Reset - Fixed Gapping */
        .floor-layout {
            display: flex !important;
            flex-direction: column !important;
            height: auto !important;
            min-height: unset !important;
            overflow: visible !important;
        }

        .zone-yellow-block {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: visible !important;
            margin-bottom: 20px;
            gap: 10px;
            flex: 0 0 auto !important;
            /* Do not shrink */
        }

        .yellow-sub-col-right,
        .yellow-sub-col-left {
            display: flex !important;
            flex-direction: column !important;
            gap: 15px !important;
            height: auto !important;
            width: 48% !important;
            /* Slightly less than 50% to avoid rounding issues */
            flex: 0 0 auto !important;
            position: relative !important;
        }

        .right-col-stack {
            display: flex !important;
            flex-direction: column !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            gap: 15px !important;
            /* Ensure it spans enough rows */
        }

        /* New Bottom Center Zone Styling */
        .zone-tables-bottom-center {
            grid-column: 2 / 4 !important;
            /* Align with zone-tables-area (Cols 2-3) */
            grid-row: 5 !important;
            /* Place in a new Row 5 */
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            /* 2 Columns */
            gap: 20px !important;
            /* Match gap of tables-area */
            padding: 0 20px 20px 20px !important;
            /* Match horizontal padding */
            height: auto !important;
            align-content: start !important;
        }

        /* Menu Search Bar Styling - Compact & Modern */
        .menu-search-container {
            margin: 15px 0 20px 12px;
            position: relative;
            max-width: 450px;
            /* Constrain width for better aesthetic */
        }

        .menu-search-container input {
            width: 100%;
            height: 46px;
            /* Explicit compact height */
            padding: 0 20px 0 48px;
            /* Balanced padding */
            border-radius: 23px;
            /* Full pill shape */
            border: 1px solid #e0e0e0;
            background: #fff;
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            outline: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            font-family: inherit;
        }

        .menu-search-container input:focus {
            border-color: #3e2723;
            box-shadow: 0 4px 12px rgba(62, 39, 35, 0.1);
            transform: translateY(-1px);
        }

        .menu-search-container input::placeholder {
            color: #9e9e9e;
            font-weight: 400;
        }

        .menu-search-container::before {
            content: "🔍";
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            opacity: 0.4;
            pointer-events: none;
            z-index: 5;
            filter: grayscale(100%);
            /* Cleaner look */
        }

        /* Responsive Search Bar */
        @media screen and (max-width: 768px) {
            .menu-search-container {
                max-width: 100%;
                /* Full width on mobile */
                margin-bottom: 20px;
            }

            .menu-search-container input {
                height: 44px;
                font-size: 0.9rem;
            }
        }

        /* --- MOBILE BILLING MODAL RE-DESIGN V2 (ROBUST FLEXBOX) --- */
        @media screen and (max-width: 768px) {

            /* RESTORE TOUCH SCROLL: Remove position:fixed/overflow:hidden which was blocking gestures */
            html,
            body {
                overflow-x: hidden !important;
                height: auto !important;
                min-height: 100% !important;
                width: 100% !important;
                position: relative !important;
                overscroll-behavior-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            .admin-shell {
                height: auto !important;
                min-height: 100vh !important;
                overflow: visible !important;
                display: block !important;
                padding-top: 10px !important;
                /* Tighten top gap */
            }

            .admin-page-header {
                margin-bottom: 15px !important;
                padding: 0 5px !important;
            }

            .admin-page-title {
                font-size: 1.4rem !important;
                /* Smaller on mobile */
            }

            /* FLOOR PLAN (Ensure overrides exist) */
            .floor-plan-wrapper {
                overflow: visible !important;
                max-height: none !important;
                padding-bottom: 80px !important;
                -webkit-overflow-scrolling: touch !important;
                touch-action: manipulation !important;
                flex-shrink: 0 !important;
            }

            /* Custom Mobile Scrollbar */
            .floor-plan-wrapper::-webkit-scrollbar {
                width: 6px;
                display: block;
            }

            .floor-plan-wrapper::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.05);
            }

            .floor-plan-wrapper::-webkit-scrollbar-thumb {
                background: rgba(139, 69, 19, 0.3);
                /* Themed brownish transparent */
                border-radius: 3px;
            }

            .floor-layout {
                transform: scale(0.58) !important;
                transform-origin: top left !important;
                min-height: 0 !important;
                height: auto !important;
                /* COMPENSATE FOR SCALE GHOST WIDTH */
                width: 540px !important;
                margin-right: -227px !important;
                margin-bottom: -150px !important;
            }

            /* Fix cropping on small screens (iPhone 12/13/14) */
            @media screen and (max-width: 450px) {
                .floor-layout {
                    transform: scale(0.48) !important;
                }
            }

            /* MODAL CONTAINER - RESET */
            #billing-modal .card,
            #password-modal .card,
            #menu-item-modal .card,
            #add-customer-modal .card {
                width: 100% !important;
                max-width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                padding: 0 !important;
                background: #f4f6f8 !important;
                box-shadow: none !important;
                overscroll-behavior: contain !important;
                /* Prevent scroll chaining */
            }

            #modal-container,
            #password-modal .card>div:nth-child(2),
            #menu-item-modal .card>div:nth-child(2),
            #add-customer-modal .card>div:nth-child(2) {
                display: block !important;
                height: 100% !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                position: relative !important;
                touch-action: pan-y !important;
                overscroll-behavior-y: contain !important;
                scroll-behavior: smooth !important;
            }

            /* Modal Scrollbar Styling */
            #modal-container::-webkit-scrollbar {
                width: 6px;
                display: block;
            }

            #modal-container::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.02);
            }

            #modal-container::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 3px;
            }

            /* HEADER - Sticky */
            .billing-header {
                background: #fff !important;
                padding: 15px 20px !important;
                border-bottom: 1px solid #e0e0e0 !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                position: sticky !important;
                /* STICKY */
                top: 0 !important;
                z-index: 100 !important;
                /* High Z to stay on top */
                flex-shrink: 0 !important;
            }

            /* Close Button targeting - RED CIRCLE & TOP RIGHT & PINNED (FIXED) */
            .billing-header > button {
                position: absolute !important;
                top: 15px !important;
                right: 15px !important;
                transform: none !important;
                margin: 0 !important;
                padding: 0 !important;

                font-size: 1.5rem !important;
                line-height: 1 !important;
                color: #fff !important;
                background: #d32f2f !important;
                border: none !important;
                border-radius: 50% !important;
                z-index: 105 !important;
                width: 35px !important;
                height: 35px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: 0 4px 10px rgba(211, 47, 47, 0.4) !important;
                flex-shrink: 0 !important;
            }

            /* Ensure text doesn't hit the button */
            .billing-header>div:first-child {
                flex: 1 !important;
                padding-right: 50px !important;
            }

            .billing-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                align-items: center;
                margin-top: 5px;
            }

            /* SCROLL AREA - No Internal Scroll */
            .billing-content-scroll {
                flex: none !important;
                overflow: visible !important;
                height: auto !important;
                padding: 15px !important;
                background: #f4f6f8 !important;
                display: block !important;
            }

            /* TABLE -> CARD TRANSFORM (FLEX VERSION) */
            #billing-modal .pos-table,
            #modal-container .pos-table {
                display: block !important;
                width: 100% !important;
                border-collapse: separate !important;
            }

            #billing-modal .pos-table thead,
            #modal-container .pos-table thead {
                display: none !important;
            }

            #billing-modal .pos-table tbody,
            #modal-container .pos-table tbody {
                display: block !important;
                width: 100% !important;
            }

            /* ROW = CARD */
            #billing-modal .pos-table tr,
            #modal-container .pos-table tr {
                display: flex !important;
                flex-direction: column !important;
                position: relative !important;
                background: #fff !important;
                border-radius: 12px !important;
                padding: 15px 15px 40px 15px !important;
                /* Extra bottom padding for total price */
                margin-bottom: 12px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
                border: 1px solid #ecf0f1 !important;
                min-height: 80px !important;
            }

            /* Hide separator rows */
            #billing-modal .pos-table tr[style*="background:#f9f9f9"],
            #modal-container .pos-table tr[style*="background:#f9f9f9"] {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                padding: 10px 0 5px 0 !important;
                min-height: 0 !important;
            }

            #billing-modal .pos-table tr[style*="background:#f9f9f9"] td,
            #modal-container .pos-table tr[style*="background:#f9f9f9"] td {
                color: #95a5a6 !important;
                font-size: 0.75rem !important;
                font-weight: 800 !important;
                letter-spacing: 1px;
                text-transform: uppercase;
                padding: 0 !important;
            }

            /* CELL 1: ITEM NAME */
            #billing-modal .pos-table td:nth-child(1),
            #modal-container .pos-table td:nth-child(1) {
                display: block !important;
                width: 100% !important;
                font-size: 1.1rem !important;
                font-weight: 700 !important;
                color: #2c3e50 !important;
                margin-bottom: 5px !important;
                padding-right: 70px !important;
                /* Space for badge */
                white-space: normal !important;
                order: 1 !important;
            }

            .item-note {
                display: block;
                margin-top: 2px;
                font-size: 0.85rem;
                color: #7f8c8d;
                font-style: italic;
            }

            /* CELL 2: STATUS BADGE (Moved next to Price) */
            #billing-modal .pos-table td:nth-child(2),
            #modal-container .pos-table td:nth-child(2) {
                display: block !important;
                position: absolute !important;
                top: auto !important;
                bottom: 18px !important;
                right: 90px !important;
                /* Left of the price */
                width: auto !important;
                padding: 0 !important;
                z-index: 5 !important;
            }

            .badge {
                font-size: 0.7rem !important;
                padding: 4px 8px !important;
                font-weight: 700 !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* CELL 3: QTY (Bottom Left) */
            #billing-modal .pos-table td:nth-child(3),
            #modal-container .pos-table td:nth-child(3) {
                display: block !important;
                position: absolute !important;
                bottom: 15px !important;
                left: 15px !important;
                font-size: 1rem !important;
                color: #7f8c8d !important;
                font-weight: 600 !important;
            }

            #billing-modal .pos-table td:nth-child(3)::before,
            #modal-container .pos-table td:nth-child(3)::before {
                content: "QTY: ";
                font-size: 0.8rem;
                color: #bdc3c7;
            }

            /* CELL 4: PRICE (HIDDEN in Billing) */
            #billing-modal .pos-table td:nth-child(4),
            #modal-container .pos-table td:nth-child(4) {
                display: none !important;
            }

            /* CELL 5: TOTAL PRICE (Bottom Right) */
            #billing-modal .pos-table td:nth-child(5),
            #modal-container .pos-table td:nth-child(5) {
                display: block !important;
                position: absolute !important;
                bottom: 15px !important;
                right: 15px !important;
                font-size: 1.3rem !important;
                font-weight: 800 !important;
                color: #2c3e50 !important;
            }

            /* SUMMARY BOX */
            .billing-summary {
                padding: 20px !important;
                border-radius: 12px !important;
                background: #fff !important;
                margin-top: 20px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
                border: 1px solid #ecf0f1 !important;
            }

            .action-panel {
                padding: 20px !important;
                background: #fff !important;
                border-top: 1px solid #eee !important;
                padding-bottom: max(30px, env(safe-area-inset-bottom)) !important;
                /* iPhone Safety */
            }

            .btn-full-width {
                padding: 16px !important;
                border-radius: 12px !important;
                font-size: 1.1rem !important;
                font-weight: 700 !important;
            }
        }
    </style>


    <div id="tab-tables" class="tab-content">
        <!-- Floor Plan Container -->
        <div id="floor-plan-container" class="floor-plan-wrapper">
            <!-- Static Background Layout -->
            <div class="floor-layout">

                <!-- TOP SECTION -->
                <div class="layout-row-top">
                    <!-- Top Cabins 3-5 -->
                    <div class="zone-top-cabins">
                        <div class="table-spot" id="spot-Cabin 3">
                            <div class="spot-content">
                                <div class="zone-icon">🏡</div>
                                <div class="mini-code">Cabin 3</div>
                            </div>
                        </div>
                        <div class="table-spot" id="spot-Cabin 4">
                            <div class="spot-content">
                                <div class="zone-icon">🏡</div>
                                <div class="mini-code">Cabin 4</div>
                            </div>
                        </div>
                        <div class="table-spot" id="spot-Cabin 5">
                            <div class="spot-content">
                                <div class="zone-icon">🏡</div>
                                <div class="mini-code">Cabin 5</div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Right Utilities -->
                    <div class="zone-top-right">
                        <div class="zone-toilet">
                            <div class="zone-icon">🚽</div>
                            <div>Toilet</div>
                        </div>
                    </div>
                </div>

                <!-- MAIN MID SECTION -->
                <div class="layout-mid-grid">

                    <!-- LEFT COLUMN -->
                    <div class="col-left-stack">
                        <!-- Side Cabins 2, 1 -->
                        <div class="table-spot" id="spot-Cabin 2">
                            <div class="spot-content">
                                <div class="zone-icon">🏡</div>
                                <div class="mini-code">Cabin 2</div>
                            </div>
                        </div>
                        <div class="table-spot" id="spot-Cabin 1">
                            <div class="spot-content">
                                <div class="zone-icon">🏡</div>
                                <div class="mini-code">Cabin 1</div>
                            </div>
                        </div>

                        <!-- Yellow Strip (Tables 15-11 Vertical) -->
                        <div class="zone-yellow-strip-left">
                            <div class="table-spot" id="spot-Table 15">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Table 15</div>
                                </div>
                            </div>
                            <div class="table-spot" id="spot-Table 14">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Table 14</div>
                                </div>
                            </div>
                            <div class="table-spot" id="spot-Table 13">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Table 13</div>
                                </div>
                            </div>
                            <div class="table-spot" id="spot-Table 12">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Table 12</div>
                                </div>
                            </div>
                            <div class="table-spot" id="spot-Table 11">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Table 11</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CENTER AREA -->
                    <div class="col-center-stack">
                        <!-- Uphead Box -->
                        <div class="zone-center-uphead"
                            style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                            <div style="display:flex; justify-content:center; gap:15px; margin-bottom:15px;">
                                <div class="table-spot" id="spot-Table 24">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 24</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 25">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 25</div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:center; gap:15px;">
                                <div class="table-spot" id="spot-Table 26">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 26</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 27">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 27</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="zone-center-grey">
                            <div class="table-spot big-spot" id="spot-Garden 1">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Garden 1</div>
                                </div>
                            </div>
                            <div class="table-spot big-spot" id="spot-Garden 2">
                                <div class="spot-content">
                                    <div class="zone-icon">🪑</div>
                                    <div class="mini-code">Garden 2</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT AREA (Yellow Block) -->
                    <div class="col-right-stack"
                        style="display: flex; flex-direction: column; gap: 15px; align-items: flex-end;">
                        <div class="zone-staff">
                            <div class="zone-icon">🧑‍🍳</div>
                            <div>Staff Room</div>
                        </div>
                        <div class="zone-yellow-block-right">
                            <div class="sub-col">
                                <div class="table-spot" id="spot-Table 6">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 6</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 7">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 7</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 8">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 8</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 9">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 9</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 10">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 10</div>
                                    </div>
                                </div>
                            </div>
                            <div class="sub-col">
                                <div class="table-spot" id="spot-Table 5">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 5</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 4">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 4</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 3">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 3</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 2">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 2</div>
                                    </div>
                                </div>
                                <div class="table-spot" id="spot-Table 1">
                                    <div class="spot-content">
                                        <div class="zone-icon">🪑</div>
                                        <div class="mini-code">Table 1</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM SECTION -->
                <div class="layout-row-bottom">
                    <!-- Left Stack -->
                    <div class="bottom-left-stack">
                        <!-- Top Row: Stage + Tables 16/17 -->
                        <div style="display: flex; align-items: flex-end; gap: 10px;">
                            <!-- Stage Area -->
                            <div class="zone-stage">
                                <div class="zone-icon">🎤</div>
                                <div>STAGE</div>
                            </div>

                            <!-- Combined Box for 16, 17, 18 & 19 -->
                            <div class="zone-white-box-bottom" style="margin-left: 25px;">
                                <div class="row">
                                    <div class="table-spot" id="spot-Table 16">
                                        <div class="spot-content">
                                            <div class="zone-icon">🪑</div>
                                            <div class="mini-code">Table 16</div>
                                        </div>
                                    </div>
                                    <div class="table-spot" id="spot-Table 17">
                                        <div class="spot-content">
                                            <div class="zone-icon">🪑</div>
                                            <div class="mini-code">Table 17</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="table-spot" id="spot-Table 18">
                                        <div class="spot-content">
                                            <div class="zone-icon">🪑</div>
                                            <div class="mini-code">Table 18</div>
                                        </div>
                                    </div>
                                    <div class="table-spot" id="spot-Table 19">
                                        <div class="spot-content">
                                            <div class="zone-icon">🪑</div>
                                            <div class="mini-code">Table 19</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Stack (Counter/Kitchen) -->
                    <div class="bottom-right-stack">
                        <div class="zone-counter">
                            <div class="zone-icon">💻</div>
                            <div>Counter</div>
                        </div>
                        <div class="zone-kitchen">
                            <div class="zone-icon">👨‍🍳</div>
                            <div>Kitchen</div>
                        </div>
                    </div>
                </div>

                <!-- Grey Box (Special) -->
                <div class="zone-grey-box-bottom">
                    <div style="display:flex; justify-content:center; gap:15px; margin-bottom:15px;">
                        <div class="table-spot" id="spot-Table 20">
                            <div class="spot-content">
                                <div class="zone-icon">🪑</div>
                                <div class="mini-code">Table 20</div>
                            </div>
                        </div>
                        <div class="table-spot" id="spot-Table 21">
                            <div class="spot-content">
                                <div class="zone-icon">🪑</div>
                                <div class="mini-code">Table 21</div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:center; gap:15px;">
                        <div class="table-spot" id="spot-Table 22">
                            <div class="spot-content">
                                <div class="zone-icon">🪑</div>
                                <div class="mini-code">Table 22</div>
                            </div>
                        </div>
                        <div class="table-spot" id="spot-Table 23">
                            <div class="spot-content">
                                <div class="zone-icon">🪑</div>
                                <div class="mini-code">Table 23</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Parking -->
            <div class="zone-parking"><span style="margin-right:10px; font-size:2rem;">🅿️</span>Parking</div>
        </div>
    </div>
    <!-- Removed premature closing div of admin-shell here -->
    <?php /* Final closing div moved to line 1365 */ ?>

    <!-- TAB CONTENT: KITCHEN -->
    <div id="tab-kitchen" class="tab-content" style="display:none;">
        <div id="admin-queue-grid" class="kitchen-queue-container">Loading...</div>
    </div>

    <!-- TAB CONTENT: TOTAL SALES (CASH) -->
    <div id="tab-daily-sales" class="tab-content" style="display:none;">
        <div class="card">
            <div class="flex flex-between mb-4" style="align-items:center;">
                <h3>Total Sales (Cash)</h3>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="date" id="sales-date" value="<?php echo date('Y-m-d'); ?>" onchange="loadSales()"
                        style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                    <button onclick="exportSalesPDF()" class="btn"
                        style="background: #3e2723; color: white; padding: 8px 15px; border-radius: 6px; font-size: 0.85rem; border:none; cursor:pointer; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2">
                            </path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Export Statement
                    </button>
                </div>
            </div>
            <div class="mb-4" id="sales-total-container">
                <div id="sales-total" style="display:flex; justify-content:flex-end; align-items:center;">Loading...
                </div>
            </div>

            <div id="sales-list-container" style="display:none;">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Cottage</th>
                            <th>Method</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="sales-list">
                        <tr>
                            <td colspan="4" style="text-align:center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
                <div id="sales-load-more" style="text-align:center; padding:15px; display:none;">
                    <button onclick="loadDetailedSales(true)" class="btn btn-secondary">Load More</button>
                </div>
            </div>
            <div id="sales-details-prompt"
                style="text-align:center; padding:30px; border: 1px dashed #ddd; border-radius:8px; background:#fafafa;">
                <p style="color:#666; margin-bottom:15px;">Transaction details are hidden for performance.</p>
                <button onclick="loadDetailedSales()" class="btn"
                    style="background:var(--primary-accent); color:white;">View Itemized Sales</button>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: CREDIT AUDIT -->
    <div id="tab-sales" class="tab-content" style="display:none; margin-top: 5px;">

        <!-- Persistent Header -->
        <div style="padding: 0 0 15px 0; margin-bottom:10px;">
            <h1 style="font-size:2rem; font-weight:800; color:#3e2723; margin:0;">Credit Audit</h1>
            <p class="text-muted" style="margin:5px 0 0 0; font-size:0.9rem;">
                <?php echo date('D, M d, Y, h:i A'); ?>
            </p>
        </div>

        <div class="credit-layout" style="display:flex; gap: 20px;">
            <!-- LEFT PANEL: Customer List -->
            <div class="credit-sidebar"
                style="overflow: hidden; display: flex; flex-direction: column; width: 300px; flex-shrink: 0;">
                <!-- Pinned Search Header -->
                <div class="credit-search-box" style="flex-shrink: 0; background: white; z-index: 10;">
                    <input type="text" id="cust-search" placeholder="Search customer..." onkeyup="filterCustomers()">
                    <button class="btn btn-add-cust" onclick="openAddCustomerModal()">+</button>
                </div>

                <!-- Scrollable List -->
                <div id="credit-customer-list" class="customer-list-scroll" style="flex: 1; overflow-y: auto;">
                    <!-- Populated by JS -->
                    <div style="padding:20px; text-align:center; color:#999;">Loading...</div>
                </div>
            </div>

            <!-- RIGHT PANEL: Profile Detail -->
            <div class="credit-main" style="flex:1; display:flex; flex-direction:column;">
                <div id="credit-profile-view" style="height:100%; display:flex; flex-direction:column;">
                    <div id="credit-dashboard-overview"
                        style="height:100%; display:flex; flex-direction:column; overflow:hidden;">
                        <!-- Pinned Top Section -->
                        <div
                            style="padding:15px; padding-bottom:5px; flex-shrink:0; background:white; z-index:10; border-bottom:1px solid #f0f0f0;">
                            <!-- Clean Header & Date Picker -->
                            <div class="credit-date-header"
                                style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
                                <input type="date" id="credit-audit-date" value="<?php echo $today; ?>"
                                    onchange="loadCreditDashboard()"
                                    style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px; font-weight:600; font-size:1rem; outline:none; font-family:inherit;">
                                <button onclick="exportAllCreditCustomers()" class="btn" title="Export every customer's full transaction history in one PDF"
                                    style="background: #3e2723; color: white; padding: 10px 20px; border-radius: 8px; font-size: 0.9rem; border:none; cursor:pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; white-space: nowrap;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M17 21v-8H7v8"></path>
                                        <path d="M7 3v5h8"></path>
                                        <path d="M21 3v18H3V3z"></path>
                                    </svg>
                                    Export All Customers
                                </button>
                            </div>

                            <!-- 3 Key Metrics (Compact) -->
                            <div class="audit-stats-grid"
                                style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:10px;">
                                <div class="stat-card"
                                    style="background:#fff; border:1px solid #eee; border-left: 3px solid #555; padding:12px;">
                                    <div class="stat-icon"
                                        style="background:#f5f5f5; color:#555; width:36px; height:36px; font-size:1.1rem; line-height:36px;">
                                        📉</div>
                                    <div>
                                        <div class="stat-label" style="font-size:0.7rem;">OLD CREDIT (UPTO
                                            YESTERDAY)
                                        </div>
                                        <div class="stat-value" id="audit-total-credit"
                                            style="color:#333; font-size:1.1rem;">Rs.
                                            <?php echo number_format($initOldCredit); ?>
                                        </div>
                                        <div style="font-size:0.65rem; color:#888;">Balance before today</div>
                                    </div>
                                </div>

                                <div class="stat-card"
                                    style="background:linear-gradient(135deg, #fff3e0, #ffe0b2); border:none; padding:12px;">
                                    <div class="stat-icon"
                                        style="background:rgba(255,255,255,0.5); color:#e65100; width:36px; height:36px; font-size:1.1rem; line-height:36px;">
                                        📒</div>
                                    <div>
                                        <div class="stat-label" style="color:#e65100; font-size:0.7rem;">TODAY FRESH
                                            CREDIT
                                        </div>
                                        <div class="stat-value" id="audit-today-credit"
                                            style="color:#bf360c; font-size:1.1rem;">Rs.
                                            <?php echo number_format($initTodayCredit); ?>
                                        </div>
                                        <div style="font-size:0.65rem; opacity:0.8;">Fresh credit taken today</div>
                                    </div>
                                </div>

                                <div class="stat-card"
                                    style="background:linear-gradient(135deg, #e8f5e9, #c8e6c9); border:none; padding:12px;">
                                    <div class="stat-icon"
                                        style="background:rgba(255,255,255,0.5); color:#2e7d32; width:36px; height:36px; font-size:1.1rem; line-height:36px;">
                                        💰</div>
                                    <div>
                                        <div class="stat-label" style="color:#1b5e20; font-size:0.7rem;">TODAY
                                            CREDIT
                                            PAID
                                        </div>
                                        <div class="stat-value" id="audit-cleared-payment"
                                            style="color:#2e7d32; font-size:1.1rem;">Rs.
                                            <?php echo number_format($initTodayPaid['total']); ?>
                                        </div>
                                        <div
                                            style="display:flex; justify-content:space-between; margin-top:8px; gap:8px;">
                                            <div
                                                style="background:rgba(255,255,255,0.6); padding:4px 8px; border-radius:4px; flex:1;">
                                                <div style="font-size:0.6rem; color:#1b5e20;">CASH</div>
                                                <div id="audit-cleared-cash"
                                                    style="font-weight:700; color:#2e7d32; font-size:0.85rem;">Rs.
                                                    <?php echo number_format($initTodayPaid['cash']); ?>
                                                </div>
                                            </div>
                                            <div
                                                style="background:rgba(255,255,255,0.6); padding:4px 8px; border-radius:4px; flex:1;">
                                                <div style="font-size:0.6rem; color:#1b5e20;">ONLINE</div>
                                                <div id="audit-cleared-online"
                                                    style="font-weight:700; color:#2e7d32; font-size:0.85rem;">Rs.
                                                    <?php echo number_format($initTodayPaid['online']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Content -->
                        <div style="padding:15px; flex:1; overflow-y:auto; padding-bottom:150px;">
                            <div id="audit-transaction-lists">
                                <div class="card" style="padding:0; border-radius:8px; margin-bottom:15px;">
                                    <div
                                        style="padding:10px 15px; background:#fff3e0; border-bottom:1px solid #ffe0b2; font-weight:700; color:#e65100; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                                        <span>New Credit Given (Who took credit)</span>
                                        <button id="btn-load-new-credit" onclick="loadDetailedCredit('new_credit')"
                                            class="btn-sm"
                                            style="background:#e65100; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:0.7rem; cursor:pointer;">Load
                                            List</button>
                                    </div>
                                    <div id="audit-new-credit-container" style="display:none;">
                                        <table class="pos-table" style="margin:0; width:100%;">
                                            <thead>
                                                <tr style="background:#fff8e1;">
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Time</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Customer</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Note</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem; text-align:right;">
                                                        Credit
                                                        Amount
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="audit-new-credit-list"></tbody>
                                        </table>
                                        <div id="new-credit-more"
                                            style="text-align:center; padding:10px; display:none;">
                                            <button onclick="loadDetailedCredit('new_credit', true)"
                                                style="border:none; background:none; color:#e65100; text-decoration:underline; font-size:0.8rem; cursor:pointer;">Load
                                                More</button>
                                        </div>
                                    </div>
                                    <div id="audit-new-credit-prompt"
                                        style="padding:20px; text-align:center; color:#999; font-size:0.85rem;">
                                        Click
                                        'Load
                                        List' to view transactions</div>
                                </div>

                                <div class="card" style="padding:0; border-radius:8px; margin-bottom: 250px;">
                                    <div
                                        style="padding:10px 15px; background:#e8f5e9; border-bottom:1px solid #c8e6c9; font-weight:700; color:#2e7d32; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                                        <span>Today Credit Paid Transactions (Who paid)</span>
                                        <button id="btn-load-repay" onclick="loadDetailedCredit('repayment')"
                                            class="btn-sm"
                                            style="background:#2e7d32; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:0.7rem; cursor:pointer;">Load
                                            List</button>
                                    </div>
                                    <div id="audit-repay-container" style="display:none;">
                                        <table class="pos-table" style="margin:0; width:100%;">
                                            <thead>
                                                <tr style="background:#f1f8e9;">
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Time</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Customer</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem;">Note</th>
                                                    <th style="padding:8px 10px; font-size:0.8rem; text-align:right;">
                                                        Amount
                                                        Cleared
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="audit-repay-list"></tbody>
                                        </table>
                                        <div id="repay-more" style="text-align:center; padding:10px; display:none;">
                                            <button onclick="loadDetailedCredit('repayment', true)"
                                                style="border:none; background:none; color:#2e7d32; text-decoration:underline; font-size:0.8rem; cursor:pointer;">Load
                                                More</button>
                                        </div>
                                    </div>
                                    <div id="audit-repay-prompt"
                                        style="padding:20px; text-align:center; color:#999; font-size:0.85rem;">
                                        Click
                                        'Load
                                        List' to view transactions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="credit-customer-profile-container"
                        style="display:none; height:100%; flex-direction:column;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: MENU -->
    <div id="tab-menu" class="tab-content" style="display:none;">
        <div class="card menu-card">
            <div class="menu-header-row">
                <div class="menu-titles">
                    <h3>Menu Management</h3>
                    <p>Manage list of items, prices, and availability.</p>
                </div>
                <button onclick="openMenuModal()" class="btn-shine">
                    <span>+</span> Add New Item
                </button>
            </div>

            <div class="menu-search-container">
                <input type="text" id="menu-search-input" placeholder="Search dishes (e.g. MoMo, Tea, Latte)..."
                    onkeyup="filterMenu()">
            </div>

            <div class="menu-list-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Item Name</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="menu-list-body">
                        <tr>
                            <td colspan="5" class="loading-text">Loading items...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: USERS -->
    <div id="tab-users" class="tab-content" style="display:none;">
        <div class="card">
            <div class="flex flex-between mb-4" style="align-items:center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h3 style="margin:0;">User Management</h3>
                    <p class="text-muted" style="margin:0; font-size: 0.9rem;">Change PIN for menu, users, and stock access.</p>
                </div>
                <div style="margin-top: 10px;">
                    <button onclick="openChangePinModal()" class="btn" style="background:#fff; color:var(--primary-accent); border: 2px solid var(--primary-accent); border-radius: 12px; padding: 10px 18px; font-weight: 700; display:flex; align-items:center; gap:8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.2s;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Change PIN
                    </button>
                </div>
            </div>

            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="user-list-body">
                    <tr>
                        <td colspan="4" style="text-align:center; padding:20px;">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB CONTENT: MANAGE WAITER -->
    <div id="tab-manage-waiter" class="tab-content" style="display:none;">
        <div class="card">
            <div class="mb-4">
                <h3 style="margin:0;">Manage Waiter</h3>
                <p class="text-muted" style="margin:0; font-size: 0.9rem;">Control whether waiters can log in and take orders.</p>
            </div>

            <!-- LOCKED / PAYWALL STATE (shown once the trial ends) -->
            <div id="manage-waiter-locked" style="display:none; text-align:center; background:#fdfbf7; border:1px solid #ece2d2; border-radius:16px; padding:40px 24px;">
                <div style="width:56px; height:56px; margin:0 auto 16px; border-radius:50%; background:#fbeaea; display:flex; align-items:center; justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d9534f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <div style="font-weight:700; color:#2b2620; font-size:1.1rem; margin-bottom:8px;">Your Trial Has Ended</div>
                <div style="color:#9a948c; font-size:0.92rem; line-height:1.6; max-width:380px; margin:0 auto;">
                    The Manage Waiter trial period is over. Contact the developer to unlock this feature permanently.
                </div>
            </div>

            <div id="manage-waiter-unlocked-content">
            <!-- WAITER PORTAL KILL-SWITCH -->
            <div id="waiter-portal-control" style="background:#fdfbf7; border:1px solid #ece2d2; border-radius:16px; padding:20px 24px; margin-top:6px; box-shadow:0 2px 10px rgba(0,0,0,0.03);">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;">
                    <div style="display:flex; align-items:flex-start; gap:14px;">
                        <span id="waiter-portal-dot" style="width:13px; height:13px; border-radius:50%; background:#c4c4c4; flex-shrink:0; margin-top:5px; box-shadow:0 0 0 5px rgba(0,0,0,0.04);"></span>
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="font-weight:700; color:#2b2620; font-size:1.05rem;">Waiter Portal Access</span>
                                <span id="waiter-portal-badge" style="font-size:0.7rem; font-weight:700; letter-spacing:0.05em; padding:3px 11px; border-radius:999px; background:#eee; color:#888;">…</span>
                            </div>
                            <div id="waiter-portal-status-text" style="font-size:0.86rem; color:#9a948c; margin-top:5px;">Checking status</div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <button id="waiter-portal-timer-btn" onclick="openTimerPanel()" class="btn"
                            style="border:none; border-radius:12px; padding:12px 22px; font-weight:700; font-size:0.92rem; color:#5d4037; background:#fff; border:2px solid #e0d6c4; transition:all 0.2s;">
                            Set Timer
                        </button>
                        <button id="waiter-portal-toggle-btn" onclick="toggleWaiterPortalState()" class="btn" disabled
                            style="border:none; border-radius:12px; padding:12px 26px; font-weight:700; font-size:0.92rem; color:#fff; background:#c4c4c4; min-width:170px; transition:all 0.2s; cursor:not-allowed; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                            Loading
                        </button>
                    </div>
                </div>

                <!-- TIMER: ACTIVE SUMMARY (shown when a schedule is saved) -->
                <div id="waiter-timer-summary" style="display:none; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-top:18px; padding-top:18px; border-top:1px solid #ece2d2;">
                    <div style="font-size:0.9rem; color:#5d4037;">
                        <strong>Opening:</strong> <span id="waiter-timer-open-label">--</span>
                        &nbsp;&nbsp;
                        <strong>Closing:</strong> <span id="waiter-timer-close-label">--</span>
                    </div>
                    <button onclick="removeTimer()" class="btn"
                        style="border:none; border-radius:12px; padding:10px 20px; font-weight:700; font-size:0.88rem; color:#fff; background:#d9534f; transition:all 0.2s;">
                        Remove Timer
                    </button>
                </div>

                <!-- TIMER: SELECTION PANEL -->
                <div id="waiter-timer-panel" style="display:none; margin-top:18px; padding-top:18px; border-top:1px solid #ece2d2;">

                    <!-- Desktop/tablet: native selects (unchanged) -->
                    <div class="timer-panel-desktop" style="gap:24px; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:700; font-size:0.85rem; color:#5d4037; margin-bottom:8px;">Opening Time</div>
                            <div style="display:flex; gap:8px;">
                                <select id="timer-open-hour" class="timer-select"></select>
                                <select id="timer-open-minute" class="timer-select"></select>
                                <select id="timer-open-ampm" class="timer-select">
                                    <option value="AM">AM</option>
                                    <option value="PM">PM</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:0.85rem; color:#5d4037; margin-bottom:8px;">Closing Time</div>
                            <div style="display:flex; gap:8px;">
                                <select id="timer-close-hour" class="timer-select"></select>
                                <select id="timer-close-minute" class="timer-select"></select>
                                <select id="timer-close-ampm" class="timer-select">
                                    <option value="AM">AM</option>
                                    <option value="PM">PM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile: custom scrollable wheel picker -->
                    <div class="timer-panel-mobile" style="gap:24px; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:700; font-size:0.85rem; color:#5d4037; margin-bottom:8px;">Opening Time</div>
                            <div class="wheel-time-group" data-prefix="open"></div>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:0.85rem; color:#5d4037; margin-bottom:8px;">Closing Time</div>
                            <div class="wheel-time-group" data-prefix="close"></div>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:18px;">
                        <button onclick="confirmTimer()" class="btn"
                            style="border:none; border-radius:12px; padding:11px 24px; font-weight:700; font-size:0.9rem; color:#fff; background:#2e9e5b;">
                            Confirm
                        </button>
                        <button onclick="closeTimerPanel()" class="btn"
                            style="border:none; border-radius:12px; padding:11px 24px; font-weight:700; font-size:0.9rem; color:#5d4037; background:transparent;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <table class="pos-table" style="margin-top:24px;">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="waiter-list-body">
                    <tr>
                        <td colspan="4" style="text-align:center; padding:20px;">Loading waiters...</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <style>
        .timer-select {
            border:1px solid #e0d6c4;
            border-radius:10px;
            padding:9px 10px;
            font-weight:600;
            font-size:0.9rem;
            color:#2b2620;
            background:#fff;
        }

        .timer-panel-desktop {
            display: flex;
        }

        .timer-panel-mobile {
            display: none;
        }

        @media (max-width: 600px) {
            .timer-panel-desktop {
                display: none;
            }

            .timer-panel-mobile {
                display: flex;
            }
        }

        .wheel-time-group {
            display: flex;
            gap: 8px;
            background: #fff;
            border: 1px solid #e0d6c4;
            border-radius: 12px;
            padding: 6px;
            position: relative;
        }

        .wheel-time-group::before {
            content: '';
            position: absolute;
            left: 6px;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            height: 34px;
            background: #f3ece0;
            border-radius: 8px;
            pointer-events: none;
            z-index: 0;
        }

        .wheel-col {
            position: relative;
            width: 56px;
            height: 138px;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            -ms-overflow-style: none;
            scrollbar-width: none;
            z-index: 1;
        }

        .wheel-col::-webkit-scrollbar {
            display: none;
        }

        .wheel-col-ampm {
            width: 50px;
        }

        .wheel-col-pad {
            height: 52px;
            flex-shrink: 0;
        }

        .wheel-item {
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
            color: #b8aea0;
            scroll-snap-align: center;
            user-select: none;
            cursor: pointer;
            transition: color 0.15s ease;
        }

        .wheel-item.active {
            color: #2b2620;
            font-weight: 800;
        }

        @media (max-width: 600px) {
            .timer-panel-mobile {
                gap: 16px !important;
            }

            .timer-panel-mobile > div {
                width: 100%;
            }

            .wheel-time-group {
                justify-content: space-between;
            }
        }
    </style>

</div>

<!-- GLOBAL ADD CUSTOMER MODAL -->
<div id="add-customer-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div class="card"
        style="padding:0; width:90%; max-width:400px; border-radius:16px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
        <div
            style="background:#fbf7f0; padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:var(--primary-accent); font-size:1.2rem;">Add New Customer</h3>
            <button onclick="document.getElementById('add-customer-modal').style.display='none'"
                style="border:none; background:transparent; font-size:1.5rem; color:#888; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:25px; overflow-y:auto; max-height: 80vh;">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Full
                    Name <span style="color:red">*</span></label>
                <input type="text" id="global-cust-name" placeholder="E.g. Ram Bahadur"
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Phone
                    Number <span style="color:red">*</span></label>
                <input type="text" id="global-cust-phone" placeholder="E.g. 9841..."
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Address
                    <span style="color:red">*</span></label>
                <input type="text" id="global-cust-address" placeholder="E.g. Kathmandu"
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s;">
            </div>

            <div style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Note
                    (Optional)</label>
                <textarea id="global-cust-note" placeholder="Any special note..."
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s; height:80px;"></textarea>
            </div>

            <div style="display:flex; gap:12px;">
                <button onclick="submitGlobalCustomer()" class="btn"
                    style="flex:1; background:#2e7d32; color:white; padding:12px; border-radius:8px; border:none; font-weight:600; cursor:pointer;">Save
                    Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- MENU PIN VERIFICATION MODAL -->
<div id="menu-pin-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.2); z-index:9500; align-items:center; justify-content:center; backdrop-filter:blur(30px); -webkit-backdrop-filter:blur(30px);">
    <div class="pin-modal-card">
        <div class="pin-lock-icon">🔒</div>
        <h3 class="pin-title">Enter PIN</h3>
        <p class="pin-subtitle">This section is protected</p>
        <div class="pin-dots" id="pin-dots">
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
        </div>
        <p class="pin-error" id="pin-error">Incorrect PIN</p>
        <div class="pin-pad">
            <button type="button" class="pin-key" onclick="pinKeyPress('1')">1</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('2')">2</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('3')">3</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('4')">4</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('5')">5</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('6')">6</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('7')">7</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('8')">8</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('9')">9</button>
            <button type="button" class="pin-key pin-key-cancel" onclick="pinCancel()">✕</button>
            <button type="button" class="pin-key" onclick="pinKeyPress('0')">0</button>
            <button type="button" class="pin-key pin-key-backspace" onclick="pinBackspace()">⌫</button>
        </div>
    </div>
</div>

<style>
    /* --- COMPACT CONFIRM MODAL (waiter portal toggle) --- */
    .swal-compact {
        border-radius: 18px !important;
    }

    .swal-compact .swal2-icon {
        width: 48px;
        height: 48px;
        margin: 10px auto 6px;
        border-width: 3px;
    }

    .swal-compact .swal2-icon .swal2-icon-content {
        font-size: 1.8rem;
    }

    .swal-compact .swal2-title {
        font-size: 1.15rem;
        padding: 4px 8px 0;
    }

    .swal-compact .swal2-html-container {
        font-size: 0.9rem;
        margin: 8px 12px 4px;
        line-height: 1.4;
    }

    .swal-compact .swal2-actions {
        margin-top: 14px;
        gap: 8px;
    }

    .swal-compact .swal2-styled {
        padding: 8px 18px;
        font-size: 0.9rem;
        border-radius: 10px;
    }

    /* --- MENU PIN MODAL STYLES --- */
    .pin-modal-card {
        background: rgba(255, 255, 255, 0.92);
        width: 90%;
        max-width: 320px;
        border-radius: 32px;
        padding: 35px 28px 28px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(255,255,255,0.5) inset;
        text-align: center;
        animation: pinModalIn 0.35s cubic-bezier(0.19, 1, 0.22, 1) forwards;
        transform: scale(0.85);
        opacity: 0;
    }

    @keyframes pinModalIn {
        from { transform: scale(0.85); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    @keyframes pinShake {
        0%, 100% { transform: translateX(0); }
        15% { transform: translateX(-12px); }
        30% { transform: translateX(10px); }
        45% { transform: translateX(-8px); }
        60% { transform: translateX(6px); }
        75% { transform: translateX(-3px); }
    }

    .pin-lock-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #3e2723 0%, #5d4037 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        font-size: 1.5rem;
        box-shadow: 0 6px 20px rgba(62, 39, 35, 0.3);
    }

    .pin-title {
        margin: 0 0 4px;
        font-size: 1.3rem;
        font-weight: 800;
        color: #1a1a1a;
        letter-spacing: -0.5px;
    }

    .pin-subtitle {
        margin: 0 0 24px;
        font-size: 0.85rem;
        color: #888;
        font-weight: 500;
    }

    .pin-dots {
        display: flex;
        justify-content: center;
        gap: 14px;
        margin-bottom: 8px;
        transition: transform 0.4s;
    }

    .pin-dots.shake {
        animation: pinShake 0.5s ease-in-out;
    }

    .pin-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #ccc;
        background: transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .pin-dot.filled {
        background: #3e2723;
        border-color: #3e2723;
        transform: scale(1.15);
        box-shadow: 0 2px 8px rgba(62, 39, 35, 0.35);
    }

    .pin-dot.error {
        background: #ff3b30;
        border-color: #ff3b30;
        box-shadow: 0 2px 8px rgba(255, 59, 48, 0.35);
    }

    .pin-dot.success {
        background: #34c759;
        border-color: #34c759;
        box-shadow: 0 2px 8px rgba(52, 199, 89, 0.35);
    }

    .pin-error {
        color: #ff3b30;
        font-size: 0.8rem;
        font-weight: 600;
        margin: 0 0 12px;
        min-height: 20px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .pin-error.visible {
        opacity: 1;
    }

    .pin-pad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        max-width: 260px;
        margin: 0 auto;
    }

    .pin-key {
        width: 100%;
        aspect-ratio: 1.3;
        border-radius: 16px;
        border: none;
        background: rgba(0, 0, 0, 0.04);
        font-size: 1.4rem;
        font-weight: 600;
        color: #1a1a1a;
        cursor: pointer;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: inherit;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }

    .pin-key:active {
        background: rgba(0, 0, 0, 0.12);
        transform: scale(0.92);
    }

    .pin-key-cancel {
        color: #ff3b30;
        font-size: 1.2rem;
        font-weight: 700;
    }

    .pin-key-backspace {
        font-size: 1.3rem;
        color: #666;
    }

    /* Mobile adjustments for PIN modal */
    @media screen and (max-width: 768px) {
        .pin-modal-card {
            max-width: 300px;
            padding: 28px 22px 22px;
            border-radius: 28px;
        }
        .pin-key {
            font-size: 1.3rem;
            border-radius: 14px;
        }
        .pin-pad {
            gap: 8px;
            max-width: 240px;
        }
    }
</style>


<!-- MODERN PREMIUM CONFIRMATION MODAL (IOS GLASS) -->
<div id="modern-confirm-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.15); z-index:9000; align-items:center; justify-content:center; backdrop-filter:blur(25px); -webkit-backdrop-filter:blur(25px); animation: fadeIn 0.3s ease-out;">
    <div
        style="background:rgba(255, 255, 255, 0.85); width:90%; max-width:300px; border-radius:35px; padding:35px 24px; box-shadow:0 30px 70px rgba(0,0,0,0.15); text-align:center; border:1px solid rgba(255,255,255,0.4); transform:scale(1.1); animation: iosPopIn 0.35s cubic-bezier(0.19, 1, 0.22, 1) forwards;">
        <div id="m-confirm-icon"
            style="width:50px; height:50px; background:rgba(255, 59, 48, 0.1); color:#ff3b30; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:1.5rem;">
            ⚠️</div>
        <h3 id="m-confirm-title"
            style="margin:0 0 8px; color:#000; font-size:1.25rem; font-weight:800; letter-spacing:-0.4px;">Confirm</h3>
        <p id="m-confirm-msg"
            style="margin:0 0 30px; color:#3a3a3c; font-size:0.9rem; line-height:1.45; font-weight:500;">Please confirm
            this action.</p>

        <div style="display:flex; flex-direction:column; gap:8px;">
            <button id="m-confirm-btn" class="ios-btn-main"
                style="width:100%; padding:16px; border-radius:18px; border:none; background:#ff3b30; color:white; font-weight:700; font-size:1rem; cursor:pointer; transition:0.2s; box-shadow: 0 4px 15px rgba(255, 59, 48, 0.3);">Action</button>
            <button onclick="closeModernConfirm()"
                style="width:100%; padding:14px; border-radius:18px; border:none; background:transparent; color:#0071e3; font-weight:600; font-size:1rem; cursor:pointer;">Cancel</button>
        </div>
    </div>
</div>

<style>
    @keyframes iosPopIn {
        from {
            transform: scale(0.85);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .ios-btn-main:active {
        transform: scale(0.95);
        opacity: 0.8;
    }

    body.no-scroll,
    html.no-scroll {
        overflow: hidden !important;
        height: 100vh !important;
        width: 100vw !important;
    }
</style>
<!-- BILLING MODAL RESTRUCTURED -->
<div id="billing-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:2000; align-items:center; justify-content:center; backdrop-filter: blur(2px);">
    <div class="card"
        style="width:95%; max-width:550px; max-height:90vh; padding: 25px; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position:relative; overflow: hidden;">
        <!-- Close Button -->
        <button onclick="closeModal()"
            style="position:absolute; top:20px; right:20px; width:32px; height:32px; border-radius:50%; background:#f5f5f5; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem; color:#666; transition:0.2s;">&times;</button>

        <div id="modal-container" class="billing-modal-wrapper">
            <!-- Content injected by JS -->
            <div style="display:flex; justify-content:center; align-items:center; height:200px;">
                <div class="spinner"></div> <!-- Assuming spinner class exists or just text -->
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- TRANSFER MODAL (PROFESSIONAL) -->
<div id="transfer-modal" class="transfer-modal-backdrop" style="display:none;">
    <div class="transfer-card">
        <div class="transfer-header">
            <div class="transfer-icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 1l4 4-4 4"></path>
                    <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                    <path d="M7 23l-4-4 4-4"></path>
                    <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                </svg>
            </div>
            <h3 style="margin:0; color:#37474f; font-size:1.25rem;">Transfer Order</h3>
            <p style="margin:5px 0 0; color:#78909c; font-size:0.9rem;">Move order from <strong id="transfer-src-code"
                    style="color:#1976d2;"></strong></p>
        </div>

        <div class="transfer-body">
            <div class="transfer-field">
                <label>Select Target Table</label>
                <div class="custom-dropdown-container">
                    <input type="hidden" id="transfer-target-select" value="">

                    <div id="transfer-dropdown-trigger" class="custom-dropdown-trigger"
                        onclick="toggleTransferDropdown()">
                        <span id="transfer-dropdown-text">-- Choose Free Table --</span>
                        <div class="custom-select-arrow" style="position:static; transform:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>

                    <div id="transfer-dropdown-options" class="custom-dropdown-options">
                        <!-- JS injected options -->
                    </div>
                </div>
            </div>
        </div>

        <div class="transfer-footer">
            <button onclick="closeTransferModal()" class="btn-transfer-cancel">Cancel</button>
            <button onclick="submitTransfer()" class="btn-transfer-confirm">Confirm Transfer</button>
        </div>
    </div>
</div>

<!-- PASSWORD CHANGE MODAL -->
<div id="password-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2200; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div class="card"
        style="padding:0; width:90%; max-width:400px; border-radius:16px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
        <div
            style="background:#fbf7f0; padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:var(--primary-accent); font-size:1.2rem;">Change Password</h3>
            <button onclick="document.getElementById('password-modal').style.display='none'"
                style="border:none; background:transparent; font-size:1.5rem; color:#888; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:25px;">
            <input type="hidden" id="change-pass-user-id">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Target
                    User</label>
                <div id="change-pass-username"
                    style="font-weight:700; color:var(--primary); font-size:1.1rem; padding:10px; background:#fff8f0; border-radius:8px;">
                    --</div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">
                    New Password <span style="color:red">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password" id="new-password-input" placeholder="Min 6 characters"
                        style="width:100%; padding:12px; padding-right:45px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s;">
                    <button type="button" onclick="togglePasswordVisibility('new-password-input', this)"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#888; cursor:pointer; display:flex; align-items:center;">
                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">
                    Confirm New Password <span style="color:red">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password" id="confirm-password-input" placeholder="Re-type new password"
                        style="width:100%; padding:12px; padding-right:45px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none; transition:0.2s;">
                    <button type="button" onclick="togglePasswordVisibility('confirm-password-input', this)"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#888; cursor:pointer; display:flex; align-items:center;">
                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button onclick="submitPasswordChange()" class="btn"
                    style="flex:1; background:var(--primary); color:white; padding:12px; border-radius:8px; border:none; font-weight:600; cursor:pointer;">Update
                    Password</button>
            </div>
        </div>
    </div>
</div>

<!-- MENU ITEM MODAL -->
<div id="menu-item-modal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2300; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
    <div class="card"
        style="padding:0; width:90%; max-width:400px; border-radius:16px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
        <div
            style="background:#fbf7f0; padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="menu-modal-title" style="margin:0; color:var(--primary-accent); font-size:1.2rem;">Add Menu Item
            </h3>
            <button onclick="document.getElementById('menu-item-modal').style.display='none'; toggleNoScroll(false);"
                style="border:none; background:transparent; font-size:1.5rem; color:#888; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:25px;">
            <input type="hidden" id="menu-item-id">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Item
                    Name</label>
                <input type="text" id="menu-item-name" placeholder="e.g. Masala Tea"
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#555; font-size:0.9rem;">Price
                    (Rs.)</label>
                <input type="number" id="menu-item-price" placeholder="0.00"
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem; outline:none;">
            </div>

            <div style="margin-bottom:25px; display:flex; flex-direction:column; gap:10px;">
                <label
                    style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#555; font-size:0.9rem;">
                    <input type="checkbox" id="menu-item-available" checked>
                    Available in Menu
                </label>
                <label
                    style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#555; font-size:0.9rem;">
                    <input type="checkbox" id="menu-item-counter">
                    Counter Item (hide from Kitchen, direct Serve)
                </label>
            </div>

            <div style="display:flex; gap:12px;">
                <button onclick="submitMenuSave()" class="btn"
                    style="flex:1; background:var(--primary-accent); color:white; padding:12px; border-radius:8px; border:none; font-weight:700; cursor:pointer;">Save
                    Item</button>
            </div>
        </div>
    </div>
</div>

<script>        const csrfToken = '<?php echo csrf_token(); ?>';
    const SERVER_TODAY = '<?php echo date('Y-m-d'); ?>';
    let currentTab = 'tables';
    let currentTableId = null;
    let pendingTab = null;

    // AJAX Cancellation Helpers (Hoisted)
    let salesController = null;
    let creditController = null;
    let custSearchController = null;
    let userController = null;
    let waiterListController = null;
    let menuController = null;

    function closeModal() {
        document.querySelectorAll('.modal').forEach(el => { el.style.display = 'none'; });
        document.getElementById('billing-modal').style.display = 'none';
        document.getElementById('password-modal').style.display = 'none';
        toggleNoScroll(false);
    }

    // Scroll Lock Helper
    function toggleNoScroll(on) {
        const shell = document.querySelector('.admin-shell');
        if (on) {
            document.body.classList.add('no-scroll');
            document.documentElement.classList.add('no-scroll');
            if (shell) shell.style.overflow = 'hidden';
        } else {
            document.body.classList.remove('no-scroll');
            document.documentElement.classList.remove('no-scroll');
            if (shell) shell.style.overflow = '';
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        // Clock
        setInterval(() => {
            document.getElementById('clock').textContent = new Date().toLocaleString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }, 1000);

        // Initial Layout Adjustment for Mobile
        if (window.innerWidth <= 768) {
            // Logic if needed
        }

        // Live floor/kitchen sync + slower poll for other tabs only
        refresh();
        App.startLiveSync('<?php echo BASE_URL; ?>api/realtime_ping.php', onOrdersRealtime);
        App.startPolling(() => {
            if (currentTab !== 'tables' && currentTab !== 'kitchen') {
                refresh();
            }
        }, 30000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && (currentTab === 'tables' || currentTab === 'kitchen')) {
                onOrdersRealtime();
            }
        });

        // --- PERSISTENCE RESTORATION ---

        // 1. Restore Tab (an explicit ?openTab= link wins over the saved tab,
        // but only for this one load — strip it from the URL so a later
        // refresh falls back to whatever tab the user actually clicked)
        const openTabParam = new URLSearchParams(window.location.search).get('openTab');
        const savedTab = openTabParam || localStorage.getItem('admin_active_tab');
        if (openTabParam) {
            const cleanUrl = window.location.pathname;
            window.history.replaceState({}, '', cleanUrl);
        }
        if (savedTab && document.getElementById('tab-' + savedTab)) {
            switchTab(savedTab);
        } else if (savedTab === 'stock') {
            window.location.href = '<?php echo BASE_URL; ?>admin/stockmanagement.php';
            return;
        }

        // 2. Restore Global Scroll (for non-fixed tabs)
        const savedScroll = localStorage.getItem('admin_window_scroll');
        if (savedScroll) {
            setTimeout(() => window.scrollTo(0, parseInt(savedScroll)), 100);
        }

        // 3. Save Scroll on Unload
        window.addEventListener('scroll', () => {
            localStorage.setItem('admin_window_scroll', window.scrollY);
        });

    });

    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    function openStockManagement(e) {
        if (e && e.preventDefault) e.preventDefault();
        
        // PIN gate: intercept stock management if not verified
        if (!_menuPinOK) {
            _pinPending = true;
            _pendingTabTarget = 'stock';
            _showPinModal();
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
            return false;
        }
        
        window.location.href = '<?php echo BASE_URL; ?>admin/stockmanagement.php';
        return false;
    }

    // ============================
    // MENU PIN (verified on server — not stored in browser)
    // ============================
    let _pinBuffer = '';
    let _pinPending = false;
    let _menuPinOK = false; // Reset on every page reload
    let _pendingTabTarget = null;
    let _pendingPinAction = null; // optional callback run after a successful one-off PIN check

    function switchTab(tab) {
        // PIN gate: intercept 'menu' and 'users' if not yet verified this session
        if ((tab === 'menu' || tab === 'users') && !_menuPinOK) {
            _pinPending = true;
            _pendingTabTarget = tab;
            _showPinModal();
            // On mobile, close sidebar so PIN modal is visible
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
            return;
        }

        // Hide all contents
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        // Unset active nav
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

        // Show target
        document.getElementById('tab-' + tab).style.display = 'block';
        document.getElementById('nav-' + tab).classList.add('active');

        currentTab = tab;
        refresh();

        // On mobile, close sidebar after click
        if (window.innerWidth <= 768) {
            toggleSidebar();
        }

        // Save State
        localStorage.setItem('admin_active_tab', tab);
    }

    // --- PIN Modal Logic ---
    function _showPinModal() {
        _pinBuffer = '';
        _updatePinDots();
        document.getElementById('pin-error').classList.remove('visible');
        document.getElementById('pin-dots').classList.remove('shake');
        const modal = document.getElementById('menu-pin-modal');
        modal.style.display = 'flex';
        // Re-trigger card animation
        const card = modal.querySelector('.pin-modal-card');
        card.style.animation = 'none';
        card.offsetHeight; // reflow
        card.style.animation = '';
        // Enable keyboard input
        document.addEventListener('keydown', _pinKeyHandler);
    }

    // One-off PIN confirmation for a specific sensitive action (e.g. changing a
    // password). Unlike the session-wide Menu/Users gate, this always re-prompts.
    function requirePinThen(callback) {
        _pendingPinAction = callback;
        _pinPending = true;
        _showPinModal();
    }

    function _hidePinModal() {
        document.getElementById('menu-pin-modal').style.display = 'none';
        _pinBuffer = '';
        _pinPending = false;
        _pendingPinAction = null;
        // Remove keyboard listener
        document.removeEventListener('keydown', _pinKeyHandler);
    }

    function _pinKeyHandler(e) {
        if (document.getElementById('menu-pin-modal').style.display === 'none') return;
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            pinKeyPress(e.key);
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            pinBackspace();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            pinCancel();
        }
    }

    function _updatePinDots() {
        const dots = document.querySelectorAll('#pin-dots .pin-dot');
        dots.forEach((dot, i) => {
            dot.classList.remove('filled', 'error', 'success');
            if (i < _pinBuffer.length) dot.classList.add('filled');
        });
    }

    function pinKeyPress(digit) {
        if (_pinBuffer.length >= 4) return;
        _pinBuffer += digit;
        document.getElementById('pin-error').classList.remove('visible');
        _updatePinDots();

        if (_pinBuffer.length === 4) {
            setTimeout(_verifyPin, 200);
        }
    }

    function pinBackspace() {
        if (_pinBuffer.length === 0) return;
        _pinBuffer = _pinBuffer.slice(0, -1);
        document.getElementById('pin-error').classList.remove('visible');
        _updatePinDots();
    }

    function pinCancel() {
        _hidePinModal();
    }

    function _verifyPin() {
        const dots = document.querySelectorAll('#pin-dots .pin-dot');
        const formData = new FormData();
        formData.append('pin', _pinBuffer);
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/user_action.php?action=verify_menu_pin', 'POST', formData)
            .then(res => {
                if (!res || !res.success) {
                    document.getElementById('pin-error').classList.add('visible');
                    dots.forEach(d => { d.classList.remove('filled'); d.classList.add('error'); });
                    document.getElementById('pin-dots').classList.add('shake');
                    _pinBuffer = '';
                    setTimeout(() => {
                        dots.forEach(d => d.classList.remove('error'));
                        document.getElementById('pin-dots').classList.remove('shake');
                        _updatePinDots();
                    }, 500);
                    return;
                }

                dots.forEach(d => { d.classList.remove('filled'); d.classList.add('success'); });
                setTimeout(() => {
                    const action = _pendingPinAction;
                    _hidePinModal();
                    if (action) {
                        action();
                        return;
                    }
                    _menuPinOK = true;
                    if (_pendingTabTarget) {
                        const target = _pendingTabTarget;
                        _pendingTabTarget = null;
                        if (target === 'stock') {
                            window.location.href = '<?php echo BASE_URL; ?>admin/stockmanagement.php';
                            return;
                        }
                        switchTab(target);
                    }
                }, 300);
            })
            .catch(() => {
                document.getElementById('pin-error').classList.add('visible');
                _pinBuffer = '';
                _updatePinDots();
            });
    }

    let ordersRealtimeTimer = null;
    function onOrdersRealtime() {
        if (ordersRealtimeTimer) clearTimeout(ordersRealtimeTimer);
        ordersRealtimeTimer = setTimeout(() => {
            refresh();

            const billingModal = document.getElementById('billing-modal');
            if (billingModal && billingModal.style.display === 'flex' && currentTableId && window.currentTableCode) {
                openBilling(window.currentTableCode, currentTableId);
            }
        }, 100);
    }

    async function refresh() {
        if (currentTab === 'tables' || currentTab === 'kitchen') {
            return App.request('<?php echo BASE_URL; ?>api/status.php?type=admin').then(renderStatus);
        } else if (currentTab === 'sales') {
            const p1 = loadCreditCustomersList(true); // Silent refresh
            let p2 = Promise.resolve();
            if (!currentSelectedCustId) {
                p2 = loadCreditDashboard(true);
            }
            return Promise.all([p1, p2]);
        } else if (currentTab === 'daily-sales') {
            return loadSales(true);
        } else if (currentTab === 'users') {
            return loadUsers(true);
        } else if (currentTab === 'manage-waiter') {
            return Promise.all([loadWaiterPortalState(), loadWaiters(true)]);
        } else if (currentTab === 'menu') {
            return loadMenuItems(true);
        }
    }



    function loadUsers(isSilent = false) {
        if (userController) userController.abort();
        userController = new AbortController();

        const body = document.getElementById('user-list-body');
        if (!isSilent && body) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px;"><div class="spinner" style="margin:0 auto 10px;"></div>Processing Users...</td></tr>';
        }

        return App.request('<?php echo BASE_URL; ?>api/user_action.php?action=list', 'GET', null, { signal: userController.signal })
            .then(data => {
                if (!body) return;
                if (data && data.users) {
                    const users = data.users.filter(u => u.role === 'ADMIN' || u.role === 'WAITER');
                    if (users.length === 0) {
                        body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No users found.</td></tr>';
                        return;
                    }
                    body.innerHTML = users.map(u => `
                        <tr>
                            <td data-label="Username" style="font-weight:600;">${u.username}</td>
                            <td data-label="Role"><span class="badge" style="background:#eee; color:#666;">${u.role}</span></td>
                            <td data-label="Status"><span class="badge" style="background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9;">Active</span></td>
                            <td data-label="Actions" style="text-align:right;">
                                <button onclick="openPasswordModal(${u.id}, ${JSON.stringify(u.username || '').replace(/"/g, '&quot;')})" class="btn btn-secondary btn-sm" style="border-radius:6px; font-size:0.75rem; padding:6px 12px;">Change Password</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    if (!isSilent) body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Failed to load users</td></tr>';
                }
            }).catch(err => {
                if (err.name === 'AbortError') return;
                if (!isSilent && body) body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Error loading users</td></tr>';
            });
    }

    function loadWaiters(isSilent = false) {
        if (waiterListController) waiterListController.abort();
        waiterListController = new AbortController();

        const body = document.getElementById('waiter-list-body');
        if (!isSilent && body) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px;"><div class="spinner" style="margin:0 auto 10px;"></div>Loading waiters...</td></tr>';
        }

        return App.request('<?php echo BASE_URL; ?>api/user_action.php?action=list', 'GET', null, { signal: waiterListController.signal })
            .then(data => {
                if (!body) return;
                if (data && data.users) {
                    const waiters = data.users.filter(u => u.role === 'WAITER');
                    if (waiters.length === 0) {
                        body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No waiters found.</td></tr>';
                        return;
                    }
                    body.innerHTML = waiters.map(u => `
                        <tr>
                            <td data-label="Username" style="font-weight:600;">${u.username}</td>
                            <td data-label="Role"><span class="badge" style="background:#eee; color:#666;">${u.role}</span></td>
                            <td data-label="Status"><span class="badge" style="background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9;">Active</span></td>
                            <td data-label="Actions" style="text-align:right;">
                                <button onclick="openPasswordModal(${u.id}, ${JSON.stringify(u.username || '').replace(/"/g, '&quot;')})" class="btn btn-secondary btn-sm" style="border-radius:6px; font-size:0.75rem; padding:6px 12px;">Change Password</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    const stillLoading = body.querySelector('.spinner');
                    if (!isSilent || stillLoading) {
                        body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Failed to load waiters</td></tr>';
                    }
                }
            }).catch(err => {
                if (err.name === 'AbortError') return;
                if (!body) return;
                const stillLoading = body.querySelector('.spinner');
                if (!isSilent || stillLoading) {
                    body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Error loading waiters</td></tr>';
                }
            });
    }

    // ===== WAITER PORTAL =====
    let waiterPortalEnabled = null;
    let waiterPortalSchedule = null; // { open: 'HH:MM', close: 'HH:MM' } or null

    function formatTime12h(hhmm) {
        const [h, m] = hhmm.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        let h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    }

    function renderWaiterPortalState(enabled) {
        waiterPortalEnabled = enabled;
        const dot = document.getElementById('waiter-portal-dot');
        const badge = document.getElementById('waiter-portal-badge');
        const statusText = document.getElementById('waiter-portal-status-text');
        const btn = document.getElementById('waiter-portal-toggle-btn');
        const timerBtn = document.getElementById('waiter-portal-timer-btn');
        const timerSummary = document.getElementById('waiter-timer-summary');
        if (!dot || !statusText || !btn) return;

        const hasSchedule = !!waiterPortalSchedule;

        btn.disabled = hasSchedule;
        btn.style.cursor = hasSchedule ? 'not-allowed' : 'pointer';
        btn.style.opacity = hasSchedule ? '0.5' : '1';
        if (timerBtn) timerBtn.style.display = hasSchedule ? 'none' : '';
        if (timerSummary) timerSummary.style.display = hasSchedule ? 'flex' : 'none';

        if (hasSchedule) {
            document.getElementById('waiter-timer-open-label').textContent = formatTime12h(waiterPortalSchedule.open);
            document.getElementById('waiter-timer-close-label').textContent = formatTime12h(waiterPortalSchedule.close);
        }

        if (enabled) {
            dot.style.background = '#2e9e5b';
            dot.style.boxShadow = '0 0 0 5px rgba(46,158,91,0.15)';
            if (badge) {
                badge.textContent = 'ON';
                badge.style.background = '#e7f6ec';
                badge.style.color = '#2e9e5b';
            }
            statusText.textContent = hasSchedule ? 'Open now (within scheduled hours).' : 'Waiters can log in and take orders.';
            statusText.style.color = '#7a8a7e';
            btn.textContent = 'Deactivate Portal';
            btn.style.background = '#d9534f';
        } else {
            dot.style.background = '#d9534f';
            dot.style.boxShadow = '0 0 0 5px rgba(217,83,79,0.15)';
            if (badge) {
                badge.textContent = 'OFF';
                badge.style.background = '#fbeaea';
                badge.style.color = '#d9534f';
            }
            statusText.textContent = hasSchedule ? 'Closed now (outside scheduled hours).' : 'Waiter login is currently blocked.';
            statusText.style.color = '#bf6b67';
            btn.textContent = 'Enable Portal';
            btn.style.background = '#2e9e5b';
        }
    }

    function loadWaiterPortalState() {
        return App.request('<?php echo BASE_URL; ?>api/user_action.php?action=get_waiter_portal_state', 'GET', null)
            .then(data => {
                if (data && data.success) {
                    const lockedEl = document.getElementById('manage-waiter-locked');
                    const contentEl = document.getElementById('manage-waiter-unlocked-content');
                    const isUnlocked = data.manage_waiter_unlocked !== false;
                    if (lockedEl) lockedEl.style.display = isUnlocked ? 'none' : 'block';
                    if (contentEl) contentEl.style.display = isUnlocked ? 'block' : 'none';
                    if (!isUnlocked) return;

                    waiterPortalSchedule = data.schedule || null;
                    renderWaiterPortalState(!!data.enabled);
                }
            }).catch(() => { /* keep disabled-looking button on error */ });
    }

    function toggleWaiterPortalState() {
        if (waiterPortalEnabled === null || waiterPortalSchedule) return;
        const btn = document.getElementById('waiter-portal-toggle-btn');
        const nextState = !waiterPortalEnabled;

        const formData = new FormData();
        formData.append('enabled', nextState ? '1' : '0');
        formData.append('csrf_token', csrfToken);

        if (btn) {
            btn.disabled = true;
            btn.style.cursor = 'not-allowed';
        }

        App.request('<?php echo BASE_URL; ?>api/user_action.php?action=set_waiter_portal_state', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    renderWaiterPortalState(!!data.enabled);
                    App.showToast(data.enabled ? 'Waiter portal enabled.' : 'Waiter portal disabled.', 'success');
                } else {
                    App.showToast('Failed to update waiter portal state.', 'error');
                    renderWaiterPortalState(waiterPortalEnabled);
                }
            })
            .catch(() => {
                App.showToast('Failed to update waiter portal state.', 'error');
                renderWaiterPortalState(waiterPortalEnabled);
            });
    }

    function isMobileTimerUI() {
        return window.matchMedia('(max-width: 600px)').matches;
    }

    function populateTimerSelect(selectId, max, padded) {
        const select = document.getElementById(selectId);
        select.innerHTML = '';
        for (let i = 1; i <= max; i++) {
            const val = padded ? String(i).padStart(2, '0') : String(i);
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            select.appendChild(opt);
        }
    }

    function populateMinuteSelect(selectId) {
        const select = document.getElementById(selectId);
        select.innerHTML = '';
        for (let i = 0; i < 60; i++) {
            const val = String(i).padStart(2, '0');
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            select.appendChild(opt);
        }
    }

    const WHEEL_ITEM_HEIGHT = 34;
    const wheelState = {}; // { 'open-hour': '07', 'open-minute': '30', 'open-ampm': 'AM', ... }

    function buildWheelColumn(container, key, values) {
        const col = document.createElement('div');
        col.className = 'wheel-col' + (key.endsWith('ampm') ? ' wheel-col-ampm' : '');
        col.dataset.key = key;

        const padTop = document.createElement('div');
        padTop.className = 'wheel-col-pad';
        col.appendChild(padTop);

        values.forEach(val => {
            const item = document.createElement('div');
            item.className = 'wheel-item';
            item.textContent = val;
            item.dataset.value = val;
            item.onclick = () => scrollWheelTo(col, key, val, true);
            col.appendChild(item);
        });

        const padBottom = document.createElement('div');
        padBottom.className = 'wheel-col-pad';
        col.appendChild(padBottom);

        let scrollTimer = null;
        col.addEventListener('scroll', () => {
            if (scrollTimer) clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => updateWheelActive(col, key), 80);
        });

        container.appendChild(col);
        return col;
    }

    function updateWheelActive(col, key, silent) {
        const index = Math.round(col.scrollTop / WHEEL_ITEM_HEIGHT);
        const items = col.querySelectorAll('.wheel-item');
        items.forEach((item, i) => item.classList.toggle('active', i === index));
        const value = items[index] ? items[index].dataset.value : null;
        if (value !== null) wheelState[key] = value;
        if (!silent && col.scrollTop !== index * WHEEL_ITEM_HEIGHT) {
            col.scrollTo({ top: index * WHEEL_ITEM_HEIGHT, behavior: 'smooth' });
        }
    }

    function scrollWheelTo(col, key, value, smooth) {
        const items = Array.from(col.querySelectorAll('.wheel-item'));
        const index = items.findIndex(item => item.dataset.value === value);
        if (index === -1) return;
        col.scrollTo({ top: index * WHEEL_ITEM_HEIGHT, behavior: smooth ? 'smooth' : 'auto' });
        wheelState[key] = value;
        items.forEach((item, i) => item.classList.toggle('active', i === index));
    }

    function buildTimeWheelGroup(prefix) {
        const container = document.querySelector(`.wheel-time-group[data-prefix="${prefix}"]`);
        container.innerHTML = '';
        const hours = Array.from({ length: 12 }, (_, i) => String(i + 1));
        const minutes = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));
        buildWheelColumn(container, prefix + '-hour', hours);
        buildWheelColumn(container, prefix + '-minute', minutes);
        buildWheelColumn(container, prefix + '-ampm', ['AM', 'PM']);
    }

    function setWheelGroupValue(prefix, hour12, minute, ampm) {
        const container = document.querySelector(`.wheel-time-group[data-prefix="${prefix}"]`);
        scrollWheelTo(container.querySelector(`[data-key="${prefix}-hour"]`), prefix + '-hour', String(hour12), false);
        scrollWheelTo(container.querySelector(`[data-key="${prefix}-minute"]`), prefix + '-minute', minute, false);
        scrollWheelTo(container.querySelector(`[data-key="${prefix}-ampm"]`), prefix + '-ampm', ampm, false);
    }

    function openTimerPanel() {
        if (isMobileTimerUI()) {
            buildTimeWheelGroup('open');
            buildTimeWheelGroup('close');

            if (waiterPortalSchedule) {
                const openParts = to12Hour(waiterPortalSchedule.open);
                const closeParts = to12Hour(waiterPortalSchedule.close);
                setWheelGroupValue('open', openParts.hour, openParts.minute, openParts.ampm);
                setWheelGroupValue('close', closeParts.hour, closeParts.minute, closeParts.ampm);
            } else {
                setWheelGroupValue('open', '9', '00', 'AM');
                setWheelGroupValue('close', '9', '00', 'PM');
            }
        } else {
            populateTimerSelect('timer-open-hour', 12, false);
            populateTimerSelect('timer-close-hour', 12, false);
            populateMinuteSelect('timer-open-minute');
            populateMinuteSelect('timer-close-minute');
        }

        document.getElementById('waiter-timer-panel').style.display = 'block';
    }

    function closeTimerPanel() {
        document.getElementById('waiter-timer-panel').style.display = 'none';
    }

    function to24Hour(hour12, minute, ampm) {
        let h = parseInt(hour12, 10) % 12;
        if (ampm === 'PM') h += 12;
        return String(h).padStart(2, '0') + ':' + minute;
    }

    function to12Hour(hhmm) {
        const [h, m] = hhmm.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        let h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return { hour: String(h12), minute: String(m).padStart(2, '0'), ampm };
    }

    function confirmTimer() {
        let openTime, closeTime;
        if (isMobileTimerUI()) {
            openTime = to24Hour(
                wheelState['open-hour'] || '9',
                wheelState['open-minute'] || '00',
                wheelState['open-ampm'] || 'AM'
            );
            closeTime = to24Hour(
                wheelState['close-hour'] || '9',
                wheelState['close-minute'] || '00',
                wheelState['close-ampm'] || 'PM'
            );
        } else {
            openTime = to24Hour(
                document.getElementById('timer-open-hour').value,
                document.getElementById('timer-open-minute').value,
                document.getElementById('timer-open-ampm').value
            );
            closeTime = to24Hour(
                document.getElementById('timer-close-hour').value,
                document.getElementById('timer-close-minute').value,
                document.getElementById('timer-close-ampm').value
            );
        }

        const formData = new FormData();
        formData.append('open_time', openTime);
        formData.append('close_time', closeTime);
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/user_action.php?action=set_waiter_portal_schedule', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    waiterPortalSchedule = data.schedule;
                    closeTimerPanel();
                    renderWaiterPortalState(!!data.enabled);
                    App.showToast('Timer set successfully.', 'success');
                } else {
                    App.showToast(data.error || 'Failed to set timer.', 'error');
                }
            })
            .catch(() => App.showToast('Failed to set timer.', 'error'));
    }

    function removeTimer() {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/user_action.php?action=clear_waiter_portal_schedule', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    waiterPortalSchedule = null;
                    renderWaiterPortalState(!!data.enabled);
                    App.showToast('Timer removed.', 'success');
                } else {
                    App.showToast(data.error || 'Failed to remove timer.', 'error');
                }
            })
            .catch(() => App.showToast('Failed to remove timer.', 'error'));
    }

    function openPasswordModal(id, username, skipPin = false) {
        if (skipPin) {
            _openPasswordModalConfirmed(id, username);
        } else {
            requirePinThen(() => _openPasswordModalConfirmed(id, username));
        }
    }

    function _openPasswordModalConfirmed(id, username) {
        document.getElementById('change-pass-user-id').value = id;
        document.getElementById('change-pass-username').innerText = username;
        document.getElementById('new-password-input').value = '';
        document.getElementById('confirm-password-input').value = '';

        // Reset password visibility
        document.getElementById('new-password-input').type = 'password';
        document.getElementById('confirm-password-input').type = 'password';

        document.getElementById('password-modal').style.display = 'flex';
        toggleNoScroll(true);
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const svg = btn.querySelector('.eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
            input.type = 'password';
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    }

    function submitPasswordChange() {
        const userId = document.getElementById('change-pass-user-id').value;
        const newPassword = document.getElementById('new-password-input').value;
        const confirmPassword = document.getElementById('confirm-password-input').value;

        if (!newPassword || newPassword.length < 6) {
            App.showToast('Password must be at least 6 characters', 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            App.showToast('Passwords do not match', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('new_password', newPassword);
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/user_action.php?action=change_password', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Password updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#bc8a5f'
                    });
                    document.getElementById('password-modal').style.display = 'none';
                    toggleNoScroll(false);
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: (data ? data.error : 'Unknown error'),
                        icon: 'error',
                        confirmButtonColor: '#bc8a5f'
                    });
                }
            });
    }

    function renderStatus(data) {
        if (!data) return;

        // Handle API Error
        if (data.error) {
            const grid = document.getElementById('floor-plan-container');
            if (grid) {
                grid.innerHTML = `<div style="grid-column:1/-1; padding:20px; background:#ffebee; color:#c62828; border-radius:8px; text-align:center;">
                    <strong>Connection Error:</strong> ${data.error}<br>
                    <small>Check database connection or server logs.</small>
                </div>`;
            }
            return;
        }

        // 1. Tables Grid (Floor Plan Update) matching Waiter Design
        if (data.tables) {
            window.allTables = data.tables; // Store for Transfer Logic
            const queueItems = data.queue || [];

            data.tables.forEach(c => {
                const spotId = 'spot-' + c.code;
                const spotEl = document.getElementById(spotId);
                if (!spotEl) return;

                let isFree = c.status === 'FREE';
                let statusText = isFree ? 'Free' : 'Occupied';

                // Determine Class
                let cardClass = isFree ? 'status-free' : 'status-occupied';
                if (parseInt(c.is_paid) === 1) {
                    statusText = 'Fully Paid';
                    cardClass = 'status-paid';
                }

                // Update Class List efficiently
                if (!spotEl.classList.contains(cardClass)) {
                    spotEl.classList.remove('status-free', 'status-occupied', 'status-paid');
                    spotEl.classList.add(cardClass);
                }

                const total = parseFloat(c.current_total || 0);
                let totalHtml = ''; // Admin usually doesn't show total on main card unless requested, logic preserved

                // --- WAITER INDICATORS LOGIC ---
                let distLabelsHtml = '';
                const items = queueItems.filter(q => q.table_code === c.code);

                // --- CALC COUNTS ---
                let countUnserved = 0, countServed = 0, countPaid = 0;

                if (items.length > 0) {
                    items.forEach(item => {
                        const qty = parseInt(item.qty || 1);
                        const itemPaid = (parseInt(item.is_paid) === 1) || (parseInt(item.session_is_paid) === 1);
                        
                        if (itemPaid) countPaid += qty;

                        if (item.kitchen_status === 'SERVED') {
                            countServed += qty;
                        } else if (!itemPaid) {
                            countUnserved += qty;
                        }
                    });
                }

                // If Session is Fully Paid formally
                if (parseInt(c.is_paid) === 1) {
                    statusText = 'Fully Paid';
                }

                // --- GENERATE BADGES ---
                if (countServed > 0) {
                    distLabelsHtml += `<div class="d-label d-top">${countServed} Served</div>`;
                }

                if (countPaid > 0 && parseInt(c.is_paid) !== 1) {
                    distLabelsHtml += `<div class="d-label d-left">${countPaid} Paid</div>`;
                }

                if (countUnserved > 0) {
                    statusText = `<span style="color:#d84315;">${countUnserved} to Serve</span>`;
                } else if (countServed > 0 && parseInt(c.is_paid) !== 1) {
                    statusText = "All Served";
                }
                
                // Overwrite if fully paid
                if (parseInt(c.is_paid) === 1) {
                    statusText = "Fully Paid";
                }

                // --- ICON LOGIC ---
                let icon = '🏡';
                if (c.code.toLowerCase().includes('table')) icon = '🪑';

                // --- RENDER ---
                const innerHTML = `
                    <div class="zone-icon">${icon}</div>
                    <div class="mini-code">${c.code}</div>
                    <div class="mini-status">${statusText}</div>
                    ${totalHtml}
                    ${distLabelsHtml}
                `;

                const spotContentEl = spotEl.querySelector('.spot-content');

                // OPTIMIZATION: Only update DOM if changed
                if (spotContentEl) {
                    if (spotContentEl.innerHTML !== innerHTML) {
                        spotContentEl.innerHTML = innerHTML;
                    }
                } else {
                    if (spotEl.innerHTML !== innerHTML) {
                        spotEl.innerHTML = innerHTML;
                    }
                }

                // Attach Click Handler - Admin Billing (Once)
                if (!spotEl.dataset.clickBound) {
                    spotEl.onclick = function () { openBilling(c.code, c.id, c.status); };
                    spotEl.dataset.clickBound = "true";
                }
                spotEl.title = `${c.code} - ${statusText}`;
            });
        }

        // 2. Kitchen Monitor Reuse (Optimized)
        const queueGrid = document.getElementById('admin-queue-grid');
        if (queueGrid && data.queue) {
            let html = '';
            if (data.queue.length === 0) {
                html = '<div style="background:white; padding:40px; border-radius:12px; text-align:center; color:#888; grid-column:1/-1;">No active orders in kitchen</div>';
            } else {
                const groups = {};
                data.queue.forEach(item => {
                    if (!groups[item.table_code]) groups[item.table_code] = [];
                    groups[item.table_code].push(item);
                });

                Object.keys(groups).sort().forEach(code => {
                    html += `<div class="queue-card" style="background:#fff; border-radius:12px; padding:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:flex; flex-direction:column; gap:8px; border:1px solid #f0f0f0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f5f5f5; padding-bottom:8px; margin-bottom:4px;">
                        <h3 style="margin:0; color:var(--primary-accent); font-size:1rem; font-weight:700;">${code}</h3>
                        ${groups[code].every(item => item.kitchen_status === 'SERVED') ? '' : `<button onclick="cancelTableOrders(${JSON.stringify(code).replace(/"/g, '&quot;')})" title="Delete Entire Order" 
                            style="background:#ffebee; color:#d32f2f; border:1px solid #ffcdd2; padding:4px 8px; border-radius:6px; cursor:pointer; font-size:0.75rem; font-weight:600; transition:all 0.2s;">
                            Clear All
                        </button>`}
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                    ${groups[code].map(item => `
                            <div class="item-action-row" style="display:flex; justify-content:space-between; align-items:center; background:#fafafa; padding:6px 8px; border-radius:6px;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span style="font-size:0.9rem; font-weight:600; color:#333;">${item.menu_name}</span>
                                    <span style="background:#e3f2fd; color:#1565c0; font-size:0.75rem; font-weight:700; padding:1px 6px; border-radius:4px;">x${item.qty}</span>
                                </div>
                                ${item.kitchen_status === 'SERVED' ? '<span style="font-size:0.75rem; font-weight:bold; color:#4caf50; padding-right:4px;">Served</span>' : '<button onclick="cancelItem(' + item.id + ')" style="background:#fff; color:#ff5252; border:1px solid #ffcdd2; width:22px; height:22px; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:bold;">&times;</button>'}
                            </div>
                    `).join('')}
                    </div>
                    </div>`;
                });
            }
            // Only update kitchen monitor if changed
            if (queueGrid.innerHTML !== html) {
                queueGrid.innerHTML = html;
            }
        }
    }



    function openBilling(code, id, status) {
        currentTableId = id;
        window.currentTableCode = code; // Store for reload reference

        const container = document.getElementById('modal-container');
        container.innerHTML = '<div style="flex:1; display:flex; justify-content:center; align-items:center; color:#999;">Loading details...</div>';
        document.getElementById('billing-modal').style.display = 'flex';
        toggleNoScroll(true);



        // Fetch Orders
        window.draftQtys = {};
        window.isEditMode = false;
        App.request('<?php echo BASE_URL; ?>api/get_orders.php?table_id=' + id + '&_t=' + new Date().getTime()).then(res => {
            if (!res || !res.session) {
                container.innerHTML = `
                    <div class="billing-header">
                        <div style="flex:1;">
                            <h2 class="billing-title">${code}</h2>
                            <span class="badge badge-success">FREE</span>
                        </div>
                        <button onclick="closeModal()" style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
                    </div>
                    <div style="padding:50px; text-align:center; color:#666;">
                        <div style="font-size:3rem; margin-bottom:15px; opacity:0.5;">🍃</div>
                        <p style="font-size:1.2rem; font-weight:600;">Table is Empty</p>
                        <p>No active orders or session found.</p>
                        <button onclick="closeModal()" class="btn" style="margin-top:20px; background:#eee; color:#333;">Close</button>
                    </div>
                `;
                return;
            }

            const session = res.session;
            const orders = res.orders || [];

            // Calculate Totals strictly from Data
            let subtotalOfItems = 0;
            let totalUnpaidValue = 0;

            orders.forEach(o => {
                // Logic Correction: Only active items (not cancelled) usually count. 
                // But here we rely on get_orders returning active items.
                const linePrice = (parseFloat(o.unit_price_snapshot) * parseInt(o.qty));
                subtotalOfItems += linePrice;
                if (o.is_paid == 0) {
                    totalUnpaidValue += linePrice;
                }
            });

            // Apply Logic to Global Totals
            let grandTotal = subtotalOfItems;

            // Payment Data from Session (Source of Truth)
            const paidCash = parseFloat(session.paid_amount_cash || 0);
            const paidOnline = parseFloat(session.paid_amount_online || 0);
            const creditAmt = parseFloat(session.credit_amount || 0);
            const totalPaid = paidCash + paidOnline + creditAmt;

            // FIX: Robust NetPayable Calculation handling Mixed/Partial Payments
            // Logic: Net Payable = Grand Total - (Paid + Credit)
            let netPayable = grandTotal - totalPaid;

            // Double Safety: If session is explicitly marked paid (e.g. via Credit), force 0.
            // REMOVED: Since users can add items AFTER payment, we must trust the calculation (GrandTotal - Paid).
            // if (session.is_paid == 1) netPayable = 0;

            if (netPayable < 0.1) netPayable = 0; // Floating point tolerance

            // Render Items
            let itemsHtml = '';
            if (orders.length > 0) {
                const paidItems = orders.filter(o => o.is_paid == 1);
                const unpaidItems = orders.filter(o => o.is_paid == 0);

                // Helper to consolidate
                const consolidate = (list) => {
                    const grouped = {};
                    list.forEach(o => {
                        const key = `${o.item_name_snapshot}_${o.unit_price_snapshot}_${o.kitchen_status}_${o.note || ''}`;
                        if (!grouped[key]) {
                            grouped[key] = { ...o, qty: 0 };
                        }
                        grouped[key].qty += parseInt(o.qty || 1);
                    });
                    return Object.values(grouped);
                };

                const renderItems = (items, isPaidGroup) => {
                    if (items.length === 0) return '';
                    let html = `<tr style="background:#f9f9f9;"><td colspan="5" style="padding:10px; font-weight:800; color:${isPaidGroup ? '#2e7d32' : '#d32f2f'}; font-size:0.8rem;">${isPaidGroup ? '✓ ITEMS MARKED PAID' : '⚠️ ITEMS NOT MARKED PAID'}</td></tr>`;
                    html += items.map(o => {
                        const lineTotal = parseFloat(o.unit_price_snapshot) * parseInt(o.qty);
                        let maxQtyAttr = '';
                        if (o.is_paid == 0) {
                            window.draftQtys[o.id] = parseInt(o.qty);
                            
                            if (o.track_stock == 1 && (o.unit_type === null || o.unit_type === 'piece')) {
                                let stockAvailable = parseInt(o.stock_qty || 0);
                                let currentQty = parseInt(o.qty || 0);
                                let isDeducted = parseInt(o.stock_deducted || 0);
                                let maxQty = isDeducted ? (currentQty + stockAvailable) : stockAvailable;
                                maxQtyAttr = `data-max-qty="${maxQty}"`;
                            }
                        }

                        let kBadgeClass = o.is_paid == 1 ? 'badge-success' : 'badge-pending';
                        let kStatusText = o.is_paid == 1 ? 'PAID' : o.kitchen_status;

                        if (o.is_paid != 1) {
                            if (o.kitchen_status === 'PREPARING') kBadgeClass = 'badge-preparing';
                            if (o.kitchen_status === 'READY') kBadgeClass = 'badge-ready';
                            if (o.kitchen_status === 'SERVED') kBadgeClass = 'badge-served';
                        }

                        return `
                            <tr style="${o.is_paid == 1 ? '' : ''}">
                                <td>
                                    <span class="item-name">${o.item_name_snapshot}</span>
                                    ${o.note ? `<span class="item-note">${o.note}</span>` : ''}
                                </td>
                                <td><span class="badge ${kBadgeClass}" style="font-size:0.75rem;">${kStatusText}</span></td>
                                <td style="text-align:center;">
                                    ${o.is_paid == 0 ? `
                                    <span class="qty-read-only" style="font-weight:600; color:#374151;">${o.qty}</span>
                                    <div class="qty-editable" style="display:none; align-items:center; justify-content:center; gap:8px;">
                                        <button onclick="updateDraftQty(${o.id}, -1)" style="width:28px; height:28px; border-radius:6px; border:1px solid #d1d5db; background:#f9fafb; cursor:pointer; font-weight:600; color:#4b5563; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.05); font-size:1rem; transition:0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">-</button>
                                        <span id="draft-qty-val-${o.id}" data-original-qty="${o.qty}" ${maxQtyAttr} style="font-weight:700; min-width:20px; text-align:center; font-size:1rem; color:#111827;">${o.qty}</span>
                                    </div>
                                    ` : o.qty}
                                </td>
                                <td style="text-align:right;">${parseFloat(o.unit_price_snapshot).toFixed(0)}</td>
                                <td style="text-align:right; font-weight:600;">${lineTotal.toFixed(0)}</td>
                                <td style="text-align:center;">
                                    ${o.is_paid == 0 ? `
                                    <button onclick="deleteItem(${o.id})" class="btn-delete-item" style="background:none; border:none; color:#ef4444; cursor:pointer; padding:4px; display:none; transition:0.2s; border-radius:4px;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'" title="Delete Item">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                    ` : ''}
                                </td>
                            </tr>
                            `;
                    }).join('');
                    return html;
                };

                itemsHtml = renderItems(unpaidItems, false) + renderItems(consolidate(paidItems), true);
            } else {
                itemsHtml = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">No items ordered yet.</td></tr>';
            }

            // Summary Row
            let summaryHtml = `
                    <div class="summary-row" style="font-size:0.9rem; font-weight:600; color:#555;">
                        <span>Total Bill Amount</span>
                        <span>Rs. ${grandTotal.toFixed(0)}</span>
                    </div>
                `;

            container.innerHTML = `
                    <div class="billing-header">
                        <div style="flex:1; padding-right:50px;">
                            <h2 class="billing-title">${code}</h2>
                            <div class="billing-badges">
                                <span class="badge badge-info">Session #${session.id}</span>
                                ${netPayable < 1 && grandTotal > 0
                    ? '<span class="badge badge-success">FULLY PAID</span>'
                    : '<span class="badge badge-danger">DUE: Rs. ' + netPayable.toFixed(0) + '</span>'}
                                ${Object.keys(window.draftQtys).length > 0 ? `<button id="btn-edit-cancel" onclick="cancelEditMode()" style="display:none; margin-left:auto; background:#ffffff; border:1px solid #d1d5db; padding:6px 16px; border-radius:6px; cursor:pointer; font-size:0.9rem; font-weight:700; color:#374151; box-shadow:0 1px 2px rgba(0,0,0,0.05); align-items:center; justify-content:center;">Cancel</button>
                                <button id="btn-edit-mode-toggle" onclick="toggleEditMode()" style="margin-left:auto; background:#ffffff; border:1px solid #d1d5db; padding:4px 12px; border-radius:6px; cursor:pointer; font-size:0.75rem; font-weight:700; color:#374151; transition:all 0.2s ease; box-shadow:0 1px 2px rgba(0,0,0,0.05); display:inline-flex; align-items:center; gap:6px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                    Edit Items
                                </button>` : ''}
                            </div>
                        </div>
                        <button onclick="closeModal()">&times;</button>
                    </div>

                    <div class="billing-content-scroll">
                        <div style="max-height: 40vh; overflow-y: auto; margin-bottom: 15px;">
                            <table class="pos-table">
                                <thead>
                                    <tr>
                                        <th style="width:40%;">Item</th>
                                        <th>Status</th>
                                        <th style="text-align:center;">Qty</th>
                                        <th style="text-align:right;">Price</th>
                                        <th style="text-align:right;">Total</th>
                                        <th style="text-align:center; width:45px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                            </table>
                        </div>

                        <div class="billing-summary">
                            ${summaryHtml}

                            ${paidCash > 0 ? `
                            <div class="summary-row" style="color:#2e7d32; font-size:0.85rem; margin-top:4px;">
                                <span>Paid via Cash</span>
                                <span>Rs. ${paidCash.toFixed(0)}</span>
                            </div>
                            ` : ''}

                            ${paidOnline > 0 ? `
                            <div class="summary-row" style="color:#2e7d32; font-size:0.85rem; margin-top:4px;">
                                <span>Paid via Online</span>
                                <span>Rs. ${paidOnline.toFixed(0)}</span>
                            </div>
                            ` : ''}

                            ${(session.credit_amount > 0) ? `
                            <div class="summary-row" style="color:#e65100; font-size:0.85rem; margin-top:4px; font-weight:700;">
                                <span>Credited Amount ${session.credit_customer_name ? `<span style="font-weight:400; font-size:0.75rem; color:#d84315;">(${session.credit_customer_name})</span>` : ''}</span>
                                <span>Rs. ${parseFloat(session.credit_amount).toFixed(0)}</span>
                            </div>
                            ` : ''}
                            
                            <div class="summary-row total" style="margin-top:8px; border-top: 2px dashed #ddd; padding-top:10px;">
                                <span style="font-size:1.1rem; font-weight:800; color:${netPayable > 0 ? '#d32f2f' : '#2e7d32'};">
                                    ${netPayable > 0 ? 'Remaining Due' : 'Net Payable'}
                                </span>
                                <span id="bill-grand-total" data-val="${netPayable}" style="font-size:1.2rem;">Rs. ${netPayable.toFixed(0)}</span>
                            </div>
                        </div>
                    </div>

                    <div class="action-panel" id="billing-actions">
                        <!-- Actions injected by checkSessionStatus -->
                    </div>
                `;

            const activeOrders = orders.filter(o => o.kitchen_status !== 'CANCELLED');
            const hasKitchenKot = activeOrders.some(o =>
                parseInt(o.is_paid) === 0 &&
                parseInt(o.is_counter_item) !== 1 &&
                !o.kot_printed_at
            );
            const hasCounterKot = activeOrders.some(o =>
                parseInt(o.is_paid) === 0 &&
                parseInt(o.is_counter_item) === 1 &&
                !o.kot_printed_at
            );

            checkSessionStatus(session, netPayable, subtotalOfItems, hasKitchenKot, hasCounterKot);
        });
    }

    function buildKotButtonsHtml(sessionId, hasKitchen, hasCounter) {
        if (!hasKitchen && !hasCounter) return '';

        const kitchenBtn = hasKitchen ? `
            <button onclick="printKOT(${sessionId}, 'kitchen')"
                    class="btn btn-full-width" style="background:#fff3e0; color:#e65100; border:2px solid #ff9800; font-weight:700;">
                    Kitchen KOT
            </button>` : '';

        const counterBtn = hasCounter ? `
            <button onclick="printKOT(${sessionId}, 'counter')"
                    class="btn btn-full-width" style="background:#e8eaf6; color:#3949ab; border:2px solid #5c6bc0; font-weight:700;">
                    Counter KOT
            </button>` : '';

        if (hasKitchen && hasCounter) {
            return `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">${kitchenBtn}${counterBtn}</div>`;
        }
        return `<div style="margin-bottom:10px;">${kitchenBtn || counterBtn}</div>`;
    }

    function refreshBillingKotButtons() {
        if (!currentTableId) return;
        const modal = document.getElementById('billing-modal');
        if (!modal || modal.style.display === 'none') return;

        App.request('<?php echo BASE_URL; ?>api/get_orders.php?table_id=' + currentTableId + '&_t=' + new Date().getTime())
            .then(res => {
                if (!res || !res.session) return;

                const orders = res.orders || [];
                const activeOrders = orders.filter(o => o.kitchen_status !== 'CANCELLED');
                const hasKitchenKot = activeOrders.some(o =>
                    parseInt(o.is_paid) === 0 &&
                    parseInt(o.is_counter_item) !== 1 &&
                    !o.kot_printed_at
                );
                const hasCounterKot = activeOrders.some(o =>
                    parseInt(o.is_paid) === 0 &&
                    parseInt(o.is_counter_item) === 1 &&
                    !o.kot_printed_at
                );

                let kotEl = document.getElementById('billing-kot-buttons');
                const actionPanel = document.getElementById('billing-actions');
                if (!kotEl && actionPanel) {
                    kotEl = document.createElement('div');
                    kotEl.id = 'billing-kot-buttons';
                    actionPanel.insertBefore(kotEl, actionPanel.firstChild);
                }
                if (kotEl) {
                    kotEl.innerHTML = buildKotButtonsHtml(res.session.id, hasKitchenKot, hasCounterKot);
                }
            });
    }

    function checkSessionStatus(session, subtotal, rawSubtotal, hasKitchenKot = false, hasCounterKot = false) {
        const actionPanel = document.getElementById('billing-actions');
        if (!actionPanel) return;

        // Logic Fix: Even if session says 'is_paid', if there is a remaining balance (new orders), treat as UNPAID.
        // isPaid = TRUE only if the balance is effectively 0.
        const isPaid = (subtotal < 1);
        const payMode = session.payment_mode || 'CASH';

        const kotButtonsHtml = buildKotButtonsHtml(session.id, hasKitchenKot, hasCounterKot);

        // Button Logic
        actionPanel.innerHTML = `
            <div id="billing-kot-buttons">${kotButtonsHtml}</div>

            <div id="credit-panel" class="hidden" style="background:#fff3e0; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #ffcc80; max-height:70vh; overflow-y:auto;">
                <h4 style="margin:0 0 10px 0; color:#e65100;">Credit Settlement</h4>
                
                <!-- Credit Type Selection -->
                <div style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-weight:600; color:#444;">
                        <input type="radio" name="credit_type" value="FULL" checked onchange="togglePartialInput()"> Full Credit
                    </label>
                    <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-weight:600; color:#444;">
                        <input type="radio" name="credit_type" value="PARTIAL" onchange="togglePartialInput()"> Part Pay + Credit
                    </label>
                </div>

                <!-- Partial Pay Input -->
                <div id="partial-pay-input" class="hidden" style="margin-bottom:15px; background:white; padding:15px; border-radius:6px; border:1px solid #ddd;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#444; margin-bottom:10px;">Partial Payment Details</label>
                        <div>
                            <label style="font-size:0.75rem; color:#666;">Cash Amount</label>
                            <div style="display:flex; align-items:center;">
                                <span style="padding:8px; background:#eee; border:1px solid #ccc; border-right:none; border-radius:4px 0 0 4px;">Rs.</span>
                                <input type="number" id="partial_cash" placeholder="0" min="0" oninput="updateCreditRemaining()" style="flex:1; padding:8px; border:1px solid #ccc; border-radius:0 4px 4px 0; width:100%;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.75rem; color:#666;">Online (Fonepay)</label>
                            <div style="display:flex; align-items:center;">
                                <span style="padding:8px; background:#eee; border:1px solid #ccc; border-right:none; border-radius:4px 0 0 4px;">Rs.</span>
                                <input type="number" id="partial_online" placeholder="0" min="0" oninput="updateCreditRemaining()" style="flex:1; padding:8px; border:1px solid #ccc; border-radius:0 4px 4px 0; width:100%;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; padding:10px; background:#fbe9e7; border-radius:6px; border:1px solid #ffccbc; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:#d84315; font-weight:700;">Remaining to Credit:</span>
                        <span id="credit-remaining-disp" style="font-weight:800; color:#d84315; font-size:1rem;">Rs. ${subtotal.toFixed(0)}</span>
                    </div>
                <div style="margin-top:15px; margin-bottom:10px;">
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:#5d4037; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.3px;">Select Customer</label>
                        <div class="cust-combo-trigger" id="cust-combo-trigger" onclick="openCustomerCombo()">
                            <span class="cust-combo-trigger-text placeholder" id="cust-combo-trigger-text">-- Choose Customer --</span>
                            <span class="cust-combo-arrow">▾</span>
                        </div>
                        <select id="credit-customer-select" class="hidden">
                            <option value="">-- Choose Customer --</option>
                        </select>
                    </div>
                </div>

            <!-- Main Actions -->
            <div id="main-actions" class="action-buttons" style="${isPaid ? 'display:none' : 'display:grid'}">

                <!-- TRANSFER BUTTON (Added) -->
                <button onclick="openTransferModal('${window.currentTableCode}')" 
                        class="btn btn-full-width" style="background:#fff; color:#5c6bc0; border:2px solid #5c6bc0; margin-bottom:10px; font-weight:700;">
                        ⇄ Transfer Table
                </button>

                <!-- 1. Default Initial Button -->
                <button id="btn-initial-process" onclick="showPaymentOptions()" 
                        class="btn btn-paid btn-full-width">
                        Process Order (Pay / Credit)
                </button>

                <!-- 2. Credit Mode Confirmation Button (Hidden by default) -->
                <button id="btn-confirm-credit" onclick="confirmCredit(${session.id})" 
                        class="btn btn-credit btn-full-width hidden" style="background:var(--primary-accent); display:none;">
                        Confirm Payment
                </button>

                <!-- 3. Back/Cancel Selection Button (Hidden by default) -->
                <button id="btn-cancel-credit" onclick="toggleCreditPanel()" 
                        class="btn btn-full-width hidden" style="background:#eee; color:#333; display:none;">
                        Cancel / Go Back
                </button>
                
                <button onclick="adminAction('clear_table', ${session.id})" 
                        class="btn btn-clean btn-full-width" disabled title="Payment required first">
                        Clean Table (Close Session)
                </button>
            </div>

            <!-- New Payment Method Selection -->
            <div id="payment-options" class="payment-options-container" style="display:none; flex-direction:column; gap:15px;">
                <h4 style="margin:0; text-align:center; color:#666; font-size:0.9rem; text-transform:uppercase; letter-spacing:1px;">Select Payment Strategy</h4>
                <div class="payment-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <button onclick="adminAction('mark_paid', ${session.id}, 'CASH')" class="pay-card btn-cash">
                        <div class="pay-icon">💵</div>
                        <div class="pay-label">Cash</div>
                    </button>
                    <button onclick="adminAction('mark_paid', ${session.id}, 'FONEPAY')" class="pay-card btn-fonepay">
                        <div class="pay-icon"><img src="../assets/fonepaylogo.png" alt="Fonepay"></div>
                        <div class="pay-label">Fonepay</div>
                    </button>
                     <button onclick="hidePaymentOptions(); toggleCreditPanel();" class="pay-card btn-credit-new">
                        <div class="pay-icon" style="font-size:1.8rem;">📝</div>
                        <div class="pay-label">Credit</div>
                    </button>
                    <button onclick="showSplitPayment(${session.id}, ${subtotal})" class="pay-card btn-split">
                        <div class="pay-icon">⚖️</div>
                        <div class="pay-label">Split</div>
                    </button>
                </div>
                 <button onclick="hidePaymentOptions()" class="btn-cancel-payment">Cancel</button>
            </div>

            <!-- Split Payment Form (Hidden) -->
            <div id="split-payment-form" class="hidden" style="background:#f8f9fa; padding:15px; border-radius:12px; border:1px solid #ddd; margin-bottom:15px;">
                <h4 style="margin:0 0 10px 0; color:#333;">Split Payment (Total: Rs. <span id="split-total-disp">0</span>)</h4>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div style="display:flex; flex-direction:column;">
                        <label style="font-size:0.85rem; font-weight:600;">Cash Amount</label>
                        <input type="number" id="split-cash" class="form-control" oninput="updateSplit('cash')" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-weight:bold; margin-top:auto;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                         <label style="font-size:0.85rem; font-weight:600;">Online (Fonepay / eSewa)</label>
                        <input type="number" id="split-online" class="form-control" oninput="updateSplit('online')" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-weight:bold; margin-top:auto;">
                    </div>
                </div>
                
                <div id="split-error" style="color:red; font-size:0.85rem; margin-bottom:10px; display:none;">Total must equal payable amount.</div>

                <div style="display:flex; gap:10px;">
                    <button onclick="confirmSplitPayment(${session.id}, this)" class="btn btn-success" style="flex:1;">Confirm Payment</button>
                    <button onclick="hideSplitPayment()" class="btn" style="background:#eee;">Cancel</button>
                </div>
            </div>
            
            <!-- Post-Payment Actions (Only visible if Paid) -->
             <div id="paid-actions" class="${isPaid ? '' : 'hidden'}">
                <button onclick="printReceipt(${session.id})" 
                        class="btn btn-full-width" style="background:#3e2723; color:white; margin-bottom:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Print Receipt
                </button>
                <button onclick="adminAction('clear_table', ${session.id})" 
                        class="btn btn-clean btn-full-width" style="margin-top:0;">
                        Clean Table (Close Session)
                </button>
             </div>
            
            <div class="action-explainer" style="margin-top:15px;">
                ${isPaid
                ? (() => {
                    const method = session.payment_method || payMode;
                    const isCredit = method === 'CREDIT';
                    const color = isCredit ? '#ef6c00' : '#2e7d32';
                    const bg = isCredit ? '#fff3e0' : '#e8f5e9';
                    const border = isCredit ? '#ffe0b2' : '#c8e6c9';
                    const title = isCredit ? 'Updated in Credit' : 'Payment Completed';

                    return `<div style="
                        background: ${bg};
                        border: 1px solid ${border};
                        color: ${color};
                        padding: 15px;
                        border-radius: 8px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        text-align: center;
                        gap: 5px;
                   ">
                        <div style="background:${color}; color:white; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:5px;">✓</div>
                        <div style="font-weight:700; font-size:1rem;">${title}</div>
                        <div style="font-size:0.8rem; margin-top:5px; opacity:0.8; font-weight:600;">Ready to Clean Table</div>
                   </div>`;
                })()
                : 'Select payment method to complete the session.'}
            </div>
        `;
    }

    // SPLIT PAYMENT JS
    let currentTotalPayable = 0;

    function showPaymentOptions() {
        document.getElementById('btn-initial-process').style.display = 'none';
        document.getElementById('split-payment-form').style.display = 'none'; // Reset form
        const opts = document.getElementById('payment-options');
        opts.style.display = 'flex';
    }

    function hidePaymentOptions() {
        document.getElementById('btn-initial-process').style.display = 'block';
        document.getElementById('payment-options').style.display = 'none';
    }

    function showSplitPayment(sid, total) {
        currentTotalPayable = parseFloat(total);
        document.getElementById('payment-options').style.display = 'none';

        // Hide Main Actions to focus on Split Form
        document.getElementById('main-actions').style.display = 'none';

        // Show Form
        const form = document.getElementById('split-payment-form');
        form.classList.remove('hidden');
        form.style.display = 'block';
        form.style.marginTop = '20px';

        document.getElementById('split-total-disp').innerText = currentTotalPayable.toFixed(0);

        // Default: All Cash
        document.getElementById('split-cash').value = currentTotalPayable;
        document.getElementById('split-online').value = 0;

        // Clear errors
        document.getElementById('split-error').style.display = 'none';

        // Focus cash
        setTimeout(() => document.getElementById('split-cash').focus(), 100);
    }

    function hideSplitPayment() {
        document.getElementById('split-payment-form').style.display = 'none';
        document.getElementById('main-actions').style.display = 'grid'; // Restore main actions
        document.getElementById('btn-initial-process').style.display = 'block';
    }

    window.toggleEditMode = function() {
        window.isEditMode = true;
        document.querySelectorAll('.qty-read-only').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.qty-editable').forEach(el => el.style.display = 'flex');
        document.querySelectorAll('.btn-delete-item').forEach(el => el.style.display = 'inline-flex');
        
        const btn = document.getElementById('btn-edit-mode-toggle');
        const cancelBtn = document.getElementById('btn-edit-cancel');
        if(btn) {
            btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save`;
            btn.style.background = '#dc2626'; // Red color
            btn.style.color = '#fff';
            btn.style.borderColor = '#b91c1c';
            btn.style.padding = '6px 16px'; // Little bigger
            btn.style.fontSize = '0.9rem'; // Bigger font
            btn.style.boxShadow = '0 2px 4px rgba(220,38,38,0.2)';
            btn.style.marginLeft = '8px';
            btn.onclick = saveQuantities;
        }
        if(cancelBtn) {
            cancelBtn.style.display = 'inline-flex';
        }
    };

    window.cancelEditMode = function() {
        window.isEditMode = false;
        openBilling(window.currentTableCode, currentTableId);
    };

    window.updateDraftQty = function(id, delta) {
        if (window.draftQtys[id] !== undefined) {
            let newQty = window.draftQtys[id] + delta;
            if (newQty < 0) newQty = 0;
            
            const disp = document.getElementById('draft-qty-val-' + id);
            
            let origQty = disp ? parseInt(disp.getAttribute('data-original-qty') || 0) : 0;
            if (newQty > origQty) {
                if (typeof App !== 'undefined' && App.showToast) {
                    App.showToast('Admin can only deduct quantity. Please use waiter app to increase.', 'error');
                }
                newQty = origQty;
            }

            if (disp && disp.hasAttribute('data-max-qty')) {
                let maxQty = parseInt(disp.getAttribute('data-max-qty'));
                if (newQty > maxQty) {
                    if (typeof App !== 'undefined' && App.showToast) {
                        App.showToast('Exceeds available stock limit of ' + maxQty, 'error');
                    }
                    newQty = maxQty;
                }
            }

            window.draftQtys[id] = newQty;
            if (disp) disp.innerText = newQty;
        }
    };

    window.saveQuantities = function() {
        const changed = {};
        Object.keys(window.draftQtys).forEach(id => {
            const el = document.getElementById('draft-qty-val-' + id);
            const draftVal = window.draftQtys[id];
            const origVal = el ? parseInt(el.dataset.originalQty || draftVal, 10) : draftVal;
            if (parseInt(draftVal, 10) !== origVal) {
                changed[id] = parseInt(draftVal, 10);
            }
        });
        if (Object.keys(changed).length === 0) {
            App.showToast('No quantity changes to save', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'bulk_edit_qty');
        formData.append('updates', JSON.stringify(changed));
        formData.append('csrf_token', csrfToken);
        
        const btn = document.getElementById('btn-edit-mode-toggle');
        if (btn) btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Saving...`;
        
        App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    window.isEditMode = false;
                    openBilling(window.currentTableCode, currentTableId);
                } else {
                    App.showToast(data.error || 'Failed to save quantities', 'error');
                    if (btn) btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save`;
                }
            });
    };

    window.deleteItem = function(itemId) {
        showConfirmModal('Are you sure you want to delete this item? This will immediately return its stock.', function() {
            const formData = new FormData();
            formData.append('action', 'cancel_item');
            formData.append('item_id', itemId);
            formData.append('csrf_token', csrfToken);
            
            App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', formData)
                .then(data => {
                    if (data && data.success) {
                        openBilling(window.currentTableCode, currentTableId);
                    } else {
                        App.showToast(data.error || 'Failed to delete item', 'error');
                    }
                });
        });
    };

    function showConfirmModal(message, onConfirm) {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.backgroundColor = 'rgba(0,0,0,0.6)';
        overlay.style.zIndex = '999999';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.backdropFilter = 'blur(4px)';

        const modal = document.createElement('div');
        modal.style.background = '#fff';
        modal.style.padding = '24px 30px';
        modal.style.borderRadius = '12px';
        modal.style.boxShadow = '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)';
        modal.style.maxWidth = '400px';
        modal.style.width = '90%';
        modal.style.textAlign = 'center';

        const icon = document.createElement('div');
        icon.innerHTML = `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:15px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;

        const title = document.createElement('h3');
        title.innerText = 'Confirm Deletion';
        title.style.margin = '0 0 10px 0';
        title.style.color = '#111827';
        title.style.fontSize = '1.25rem';
        title.style.fontWeight = '700';
        
        const msg = document.createElement('p');
        msg.innerText = message;
        msg.style.margin = '0 0 24px 0';
        msg.style.color = '#4b5563';
        msg.style.fontSize = '0.95rem';
        msg.style.lineHeight = '1.4';

        const btnContainer = document.createElement('div');
        btnContainer.style.display = 'flex';
        btnContainer.style.justifyContent = 'center';
        btnContainer.style.gap = '12px';

        const cancelBtn = document.createElement('button');
        cancelBtn.innerText = 'Cancel';
        cancelBtn.style.padding = '10px 20px';
        cancelBtn.style.border = '1px solid #d1d5db';
        cancelBtn.style.background = '#fff';
        cancelBtn.style.borderRadius = '8px';
        cancelBtn.style.cursor = 'pointer';
        cancelBtn.style.color = '#374151';
        cancelBtn.style.fontWeight = '600';
        cancelBtn.style.fontSize = '0.95rem';
        cancelBtn.onclick = function() {
            document.body.removeChild(overlay);
        };

        const confirmBtn = document.createElement('button');
        confirmBtn.innerText = 'Yes, Delete';
        confirmBtn.style.padding = '10px 20px';
        confirmBtn.style.border = 'none';
        confirmBtn.style.background = '#ef4444';
        confirmBtn.style.color = '#fff';
        confirmBtn.style.borderRadius = '8px';
        confirmBtn.style.cursor = 'pointer';
        confirmBtn.style.fontWeight = '600';
        confirmBtn.style.fontSize = '0.95rem';
        confirmBtn.style.boxShadow = '0 4px 6px -1px rgba(239,68,68,0.2)';
        confirmBtn.onclick = function() {
            document.body.removeChild(overlay);
            onConfirm();
        };

        btnContainer.appendChild(cancelBtn);
        btnContainer.appendChild(confirmBtn);

        modal.appendChild(icon);
        modal.appendChild(title);
        modal.appendChild(msg);
        modal.appendChild(btnContainer);
        overlay.appendChild(modal);

        document.body.appendChild(overlay);
    }

    function updateSplit(source) {
        const cashInput = document.getElementById('split-cash');
        const onlineInput = document.getElementById('split-online');
        const err = document.getElementById('split-error');

        let cash = parseFloat(cashInput.value) || 0;
        let online = parseFloat(onlineInput.value) || 0;

        // Logic: if user types in Cash, update Online to be remaining.
        if (source === 'cash') {
            online = currentTotalPayable - cash;
            if (online < 0) online = 0;
            onlineInput.value = online; // removed toFixed to avoid fighting user input, or use toFixed(2)
        } else {
            cash = currentTotalPayable - online;
            if (cash < 0) cash = 0;
            cashInput.value = cash;
        }

        // Validate
        const totalEntered = cash + online;
        if (totalEntered > currentTotalPayable + 1) {
            // Overpayment
            err.style.display = 'block';
            err.style.color = 'red';
            err.innerText = `Overpayment: Total (${totalEntered.toFixed(2)}) exceeds Payable (${currentTotalPayable.toFixed(2)})`;
        } else if (totalEntered < currentTotalPayable - 1) {
            // Partial Payment (Allowed)
            err.style.display = 'block';
            err.style.color = '#e65100'; // Orange
            err.innerText = `Partial Payment: Remaining Due Rs. ${(currentTotalPayable - totalEntered).toFixed(0)}`;
        } else {
            // Exact Match
            err.style.display = 'none';
        }
    }

    function confirmSplitPayment(sid, btnElement) {
        const cashInput = document.getElementById('split-cash');
        const onlineInput = document.getElementById('split-online');

        if (cashInput.value.trim() === '' || onlineInput.value.trim() === '') {
            showCustomAlert('Missing Input', 'Both Cash and Online fields must be filled (can be 0).', 'warning');
            return;
        }

        const cash = parseFloat(cashInput.value);
        const online = parseFloat(onlineInput.value);

        if (isNaN(cash) || isNaN(online)) {
            showCustomAlert('Invalid Input', 'Please enter valid numbers.', 'warning');
            return;
        }

        const totalEntered = cash + online;

        // Block Overpayment
        if (totalEntered > currentTotalPayable + 1) {
            showCustomAlert('Overpayment', 'Payment amounts cannot exceed the total payable amount.', 'warning');
            return;
        }

        // Implicitly Partial Payment is allowed now.
        // Backend handles "Remaining Due" automatically.

        const btn = btnElement || event.target;
        const ogText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Processing...';

        App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', {
            action: 'mark_paid',
            session_id: sid,
            payment_method: 'SPLIT',
            cash_amount: cash,
            online_amount: online,
            csrf_token: csrfToken
        }).then(res => {
            if (res.success) {
                App.showToast('Split Payment Recorded');
                closeModal();
                refresh();
                printReceipt(sid);
            } else {
                App.showToast(res.error || 'Failed', 'error');
                btn.disabled = false;
                btn.innerText = ogText;
            }
        }).catch(e => {
            App.showToast('Error: ' + e, 'error');
            btn.disabled = false;
            btn.innerText = ogText;
        });
    }

    // CREDIT LOGIC
    let customersList = [];

    function toggleCreditPanel() {
        const panel = document.getElementById('credit-panel');
        const btnInitial = document.getElementById('btn-initial-process');
        const btnConfirm = document.getElementById('btn-confirm-credit');
        const btnCancel = document.getElementById('btn-cancel-credit');

        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            // Hide initial, show credit confirm buttons
            btnInitial.style.display = 'none';

            btnConfirm.classList.remove('hidden');
            btnConfirm.style.display = 'block';
            btnConfirm.innerText = "Confirm Payment";

            btnCancel.classList.remove('hidden');
            btnCancel.style.display = 'block';

            loadCustomers();
        } else {
            panel.classList.add('hidden');
            // Restore initial, hide credit buttons
            btnInitial.style.display = 'block';

            btnConfirm.classList.add('hidden');
            btnConfirm.style.display = 'none';

            btnCancel.classList.add('hidden');
            btnCancel.style.display = 'none';
        }
    }

    function toggleNewCustomerForm() {
        document.getElementById('new-customer-form').classList.toggle('hidden');
    }

    function loadCustomers() {
        const select = document.getElementById('credit-customer-select');
        if (select.options.length > 1) return; // Already loaded

        select.innerHTML = '<option>Loading...</option>';

        App.request('<?php echo BASE_URL; ?>api/credit_customers_list.php?type=all&limit=500').then(res => {
            customersList = res.customers || [];
            renderCustomerOptions();
        });
    }

    function renderCustomerOptions(selectedId = null) {
        const select = document.getElementById('credit-customer-select');
        select.innerHTML = '<option value="">-- Choose Customer --</option>' +
            customersList.map(c => `<option value="${c.id}" ${c.id == selectedId ? 'selected' : ''}>${c.full_name} (${c.phone || 'No Phone'})</option>`).join('');

        if (selectedId) {
            const c = customersList.find(c => c.id == selectedId);
            if (c) {
                const triggerText = document.getElementById('cust-combo-trigger-text');
                triggerText.textContent = `${c.full_name} (${c.phone || 'No Phone'})`;
                triggerText.classList.remove('placeholder');
            }
        }
    }

    function renderCustomerComboList(filter = '') {
        const list = document.getElementById('cust-combo-list');
        const q = filter.trim().toLowerCase();

        const matches = customersList.filter(c =>
            !q || c.full_name.toLowerCase().includes(q) || (c.phone || '').includes(q)
        );

        if (matches.length === 0) {
            list.innerHTML = '<div class="cust-combo-empty">No matching customer</div>';
            return;
        }

        list.innerHTML = matches.map(c => `
            <div class="cust-combo-item" data-id="${c.id}">
                <span class="cust-name"></span>
                <span class="cust-phone"></span>
            </div>
        `).join('');

        // Set text via textContent (not innerHTML) so names with quotes/HTML chars can't break rendering
        const itemEls = list.querySelectorAll('.cust-combo-item');
        matches.forEach((c, i) => {
            itemEls[i].querySelector('.cust-name').textContent = c.full_name;
            itemEls[i].querySelector('.cust-phone').textContent = c.phone || 'No Phone';
        });
    }

    // Event delegation: works even if names contain quotes/special characters
    document.addEventListener('click', function (e) {
        const item = e.target.closest('.cust-combo-item');
        if (!item || !item.closest('#cust-combo-list')) return;

        const id = item.getAttribute('data-id');
        const c = customersList.find(c => String(c.id) === String(id));
        if (c) selectCustomerCombo(c.id, c.full_name, c.phone);
    });

    let pendingCustomerSelection = null;

    function openCustomerCombo() {
        pendingCustomerSelection = null;
        document.getElementById('cust-picker-confirm-btn').disabled = true;
        document.getElementById('cust-picker-modal').classList.remove('hidden');

        const search = document.getElementById('credit-customer-search');
        search.value = '';
        document.getElementById('cust-combo-clear').classList.add('hidden');
        renderCustomerComboList('');
        search.focus();

        // Always refresh from server so customers created moments ago (same session) show up
        App.request('<?php echo BASE_URL; ?>api/credit_customers_list.php?type=all&limit=500&t=' + new Date().getTime()).then(res => {
            customersList = res.customers || [];
            renderCustomerComboList(search.value);
        });
    }

    function closeCustomerCombo() {
        document.getElementById('cust-picker-modal').classList.add('hidden');
        pendingCustomerSelection = null;
    }

    function filterCustomerCombo(value) {
        document.getElementById('cust-combo-clear').classList.toggle('hidden', !value);
        renderCustomerComboList(value);
    }

    function selectCustomerCombo(id, name, phone) {
        pendingCustomerSelection = { id, name, phone };
        document.getElementById('cust-picker-confirm-btn').disabled = false;

        document.querySelectorAll('#cust-combo-list .cust-combo-item').forEach(el => el.classList.remove('active'));
        const picked = document.querySelector(`#cust-combo-list .cust-combo-item[data-id="${id}"]`);
        if (picked) picked.classList.add('active');
    }

    function confirmCustomerCombo() {
        if (!pendingCustomerSelection) return;
        const { id, name, phone } = pendingCustomerSelection;

        document.getElementById('credit-customer-select').value = id;

        const triggerText = document.getElementById('cust-combo-trigger-text');
        triggerText.textContent = `${name} (${phone || 'No Phone'})`;
        triggerText.classList.remove('placeholder');

        closeCustomerCombo();
    }

    function clearCustomerCombo() {
        const search = document.getElementById('credit-customer-search');
        search.value = '';
        document.getElementById('cust-combo-clear').classList.add('hidden');
        renderCustomerComboList('');
        search.focus();
    }

    function saveNewCustomer() {
        const name = document.getElementById('new-cust-name').value;
        const phone = document.getElementById('new-cust-phone').value;
        const address = document.getElementById('new-cust-address').value;

        if (!name) { App.showToast('Name is required'); return; }
        if (!phone) { App.showToast('Phone is required'); return; }
        if (!address) { App.showToast('Address is required'); return; }

        App.request('<?php echo BASE_URL; ?>api/create_credit_customer.php', 'POST', {
            full_name: name,
            phone: phone,
            address: address,
            csrf_token: csrfToken
        }).then(res => {
            if (res.success) {
                App.showToast('Customer Created');
                customersList.push(res.customer);
                renderCustomerOptions(res.customer.id);
                toggleNewCustomerForm();
            } else {
                App.showToast(res.error, 'error');
            }
        });
    }

    function confirmCredit(sessionId) {
        const customerId = document.getElementById('credit-customer-select').value;
        if (!customerId) {
            App.showToast('Please select a customer');
            return;
        }

        const selText = document.getElementById('credit-customer-select').options[document.getElementById('credit-customer-select').selectedIndex].text;

        const creditType = document.querySelector('input[name="credit_type"]:checked').value;
        let partialCash = 0;
        let partialOnline = 0;

        if (creditType === 'PARTIAL') {
            partialCash = parseFloat(document.getElementById('partial_cash').value) || 0;
            partialOnline = parseFloat(document.getElementById('partial_online').value) || 0;

            if (partialCash === 0 && partialOnline === 0) {
                App.showToast('Please enter at least one partial payment amount');
                return;
            }
        }

        showCustomConfirm('Confirm Credit?', `Mark bill as ${creditType === 'PARTIAL' ? 'PARTIAL ' : ''}CREDIT for ${selText}?`, () => {
            const payload = {
                action: 'mark_credit',
                session_id: sessionId,
                customer_id: customerId,
                credit_type: creditType,
                csrf_token: csrfToken
            };

            if (creditType === 'PARTIAL') {
                payload.partial_cash = partialCash;
                payload.partial_online = partialOnline;
            }

            App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', payload).then(res => {
                if (res.success) {
                    App.showToast('Credit Recorded Successfully');

                    // User Request: Show "Verified Paid" state and allow closing session
                    // Do NOT close modal, instead refresh it to show new status
                    refresh(); // Update background grid

                    // Reload modal to show "Items Marked Paid" and "Clean Table" button
                    // Use global var to ensure we have the code and Correct Table ID (not Session ID)
                    if (window.currentTableCode && window.currentTableId) {
                        openBilling(window.currentTableCode, window.currentTableId, 'OCCUPIED');
                    } else {
                        closeModal(); // Fallback
                    }

                    // Delay print to allow UI to update
                    setTimeout(() => printReceipt(sessionId), 500);

                    // Also refresh credit lists in background if needed
                    loadCreditCustomersList(true);
                } else {
                    App.showToast(res.error || 'Failed', 'error');
                }
            });
        });
    }



    function adminAction(action, sessionId, paymentMethod = null) {
        let title = 'Are you sure?';
        let confirmMsg = 'Confirm action?';

        if (action === 'mark_paid') {
            title = 'Confirm Payment';
            if (paymentMethod) {
                confirmMsg = `Recieve payment via ${paymentMethod} and mark bill as PAID?`;
            } else {
                confirmMsg = 'Mark bill as PAID?';
            }
        }
        if (action === 'clear_table') {
            title = 'Close Session?';
            confirmMsg = 'This will close the session and free the table.\nCannot be undone.';
        }

        const btn = event.target;
        const originalText = btn ? btn.innerText : '';

        showCustomConfirm(title, confirmMsg, () => {
            // Show loading info (inside callback as it runs later)
            if (btn && btn.tagName === 'BUTTON') {
                btn.innerText = 'Processing...';
                btn.disabled = true;
            }

            const payload = {
                action: action,
                session_id: sessionId,
                csrf_token: csrfToken
            };
            if (paymentMethod) payload.payment_method = paymentMethod;

            App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', payload).then(res => {
                if (res.success) {
                    App.showToast('Action Successful');
                    closeModal();
                    refresh();
                    if (action === 'mark_paid') {
                        printReceipt(sessionId);
                    }
                } else {
                    App.showToast(res.error || 'Failed', 'error');
                    if (btn && btn.tagName === 'BUTTON') {
                        btn.innerText = originalText;
                        btn.disabled = false;
                    }
                }
            });
        });
    }

    function closeModal() {
        document.getElementById('billing-modal').style.display = 'none';
        toggleNoScroll(false);
        currentTableId = null;
    }

function loadSales(isSilent = false) {
        const date = document.getElementById('sales-date').value;
        const totalEl = document.getElementById('sales-total');
        const listBody = document.getElementById('sales-list');
        const listContainer = document.getElementById('sales-list-container');
        const detailsPrompt = document.getElementById('sales-details-prompt');
        const loadMoreBtn = document.getElementById('sales-load-more');

        // Cancel previous request
        if (salesController) salesController.abort();
        salesController = new AbortController();

        if (!isSilent) {
            totalEl.innerHTML = '<div style="padding:10px;">Loading Summary...</div>';
            // Reset detail view on date change
            listContainer.style.display = 'none';
            detailsPrompt.style.display = 'block';
            listBody.innerHTML = '';
            loadMoreBtn.style.display = 'none';
        }

        return App.request('<?php echo BASE_URL; ?>api/sales_report.php?date=' + date + '&type=summary', 'GET', null, { signal: salesController.signal }).then(res => {
            if (!res || res.error) return;
            const stats = res.summary;
            totalEl.style.display = 'grid';
            totalEl.className = 'sales-stats-grid';

            totalEl.innerHTML = `
                <div class="stat-card total">
                    <div class="stat-icon">📈</div>
                    <div>
                        <div class="stat-label">Total Collected</div>
                        <div class="stat-value">${App.formatMoney(stats.lifetime || 0)}</div>
                        <div style="font-size:0.7rem; opacity:0.7; margin-top:2px;">(Upto Yesterday)</div>
                    </div>
                </div>
                <div class="stat-card cash">
                    <div class="stat-icon" style="background:#e8f5e9; color:#2e7d32;">💵</div>
                    <div>
                        <div class="stat-label">Cash Collected</div>
                        <div class="stat-value cash-text">${App.formatMoney(stats.cash || 0)}</div>
                    </div>
                </div>
                <div class="stat-card online">
                    <div class="stat-icon" style="background:transparent; padding:0;">
                        <img src="../assets/fonepaylogo.png" style="width:38px; height:auto; object-fit:contain;">
                    </div>
                    <div>
                        <div class="stat-label">Online Sales</div>
                        <div class="stat-value online-text">${App.formatMoney(stats.online || 0)}</div>
                    </div>
                </div>
                 <div class="stat-card credit">
                    <div class="stat-icon" style="background:#fff3e0; color:#f57c00;">📒</div>
                    <div>
                        <div class="stat-label">Credit Given</div>
                        <div class="stat-value credit-text" style="color:#e65100;">${App.formatMoney(stats.credit || 0)}</div>
                    </div>
                </div>
            `;

            // If the detailed list is currently open, refresh it silently
            if (listContainer.style.display === 'block') {
                loadDetailedSales(false, isSilent);
            }
        });
    }

    let salesOffset = 0;
    function loadDetailedSales(append = false, isSilent = false) {
        const date = document.getElementById('sales-date').value;
        const listBody = document.getElementById('sales-list');
        const listContainer = document.getElementById('sales-list-container');
        const detailsPrompt = document.getElementById('sales-details-prompt');
        const loadMoreBtn = document.getElementById('sales-load-more');

        if (!append) {
            salesOffset = 0;
            if (!isSilent) {
                listBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading transactions...</td></tr>';
                listContainer.style.display = 'block';
                detailsPrompt.style.display = 'none';
            }
        }

        App.request('<?php echo BASE_URL; ?>api/sales_report.php?date=' + date + '&type=list&offset=' + salesOffset).then(res => {
            if (!res || !res.sessions) return;

            const html = res.sessions.map(s => {
                let methodHtml = '';
                const c = parseFloat(s.paid_amount_cash || 0);
                const o = parseFloat(s.paid_amount_online || 0);

                if (s.credit_amount > 0) { // Credit (could be partial credit too, but primary is credit here)
                    const cust = s.customer_name ? ` <span style="font-weight:400; font-size:0.75rem;">(${s.customer_name})</span>` : '';
                    methodHtml = `<div class="split-pill credit" style="background:#fff3e0; border:1px solid #ffe0b2; color:#e65100; padding:4px 8px;border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                        <span class="lbl" style="font-weight:700;">CREDIT</span>
                        <span class="val" style="font-size:0.9rem; font-weight:800;">Rs. ${parseFloat(s.credit_amount).toFixed(0)}</span>
                        ${cust}
                    </div>`;
                } else if (c > 0 && o === 0) {
                    // FULL CASH
                    methodHtml = `<div class="split-pill cash" style="padding:4px 10px; font-size:0.8rem; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                        <span class="lbl" style="font-weight:700;">CASH</span> <span class="val" style="font-size:0.95rem; font-weight:800;">Rs. ${c.toFixed(0)}</span>
                     </div>`;
                } else if (o > 0 && c === 0) {
                    // FULL ONLINE
                    methodHtml = `<div class="split-pill online" style="padding:4px 10px; font-size:0.8rem; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                        <span class="lbl" style="font-weight:700;">ONLINE</span> <span class="val" style="font-size:0.95rem; font-weight:800;">Rs. ${o.toFixed(0)}</span>
                     </div>`;
                } else {
                    // SPLIT
                    methodHtml = `<div class="split-container" style="display:flex; gap:8px; justify-content:flex-start; flex-wrap:wrap;">
                        <div class="split-pill cash" style="padding:4px 10px; font-size:0.8rem; border-radius:6px; display:inline-flex; align-items:center; gap:5px;"><span class="lbl" style="font-weight:700;">CASH</span> <span class="val" style="font-size:0.95rem; font-weight:800;">Rs. ${c.toFixed(0)}</span></div>
                        <div class="split-pill online" style="padding:4px 10px; font-size:0.8rem; border-radius:6px; display:inline-flex; align-items:center; gap:5px;"><span class="lbl" style="font-weight:700;">ONLINE</span> <span class="val" style="font-size:0.95rem; font-weight:800;">Rs. ${o.toFixed(0)}</span></div>
                     </div>`;
                }


                // Total for this Collection View is strictly Cash + Online (Credit acts as separate event if full credit, or mixed)
                // Actually if full credit, paid is 0.
                const totalPaid = c + o;

                return `
                <tr>
                    <td data-label="Time" style="color:#222; font-weight:700; font-size:0.85rem;">${new Date(s.paid_at.replace(' ', 'T') + '+05:45').toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true })}</td>
                    <td data-label="Cottage"><span class="badge badge-secondary" style="background:#f5f5f5; color:#444;">${s.code}</span></td>
                    <td data-label="Method"><span class="method-label" style="display:none; font-size:0.8rem; color:#888; margin-right:5px;">Method:</span> ${methodHtml}</td>
                    <td data-label="Amount" style="text-align:right; font-weight:700; font-size:1rem; color:#333;">${App.formatMoney(totalPaid)}</td>
                </tr>`;
            }).join('');

            if (!append) listBody.innerHTML = '';
            listBody.innerHTML += html;

            if (res.sessions.length === 0 && !append) {
                listBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No transactions for this date.</td></tr>';
            }

            salesOffset += res.sessions.length;
            loadMoreBtn.style.display = res.has_more ? 'block' : 'none';
        });
    }

    function loadCreditDashboard(keepOpen = false) {
        const dateInput = document.getElementById('credit-audit-date');
        const date = dateInput ? dateInput.value : SERVER_TODAY;

        if (creditController) creditController.abort();
        creditController = new AbortController();

        // 1. Reset Lists (only if not keeping state)
        if (!keepOpen) {
            document.getElementById('audit-new-credit-container').style.display = 'none';
            document.getElementById('audit-new-credit-prompt').style.display = 'block';
            document.getElementById('audit-new-credit-list').innerHTML = '';
            document.getElementById('new-credit-more').style.display = 'none';

            document.getElementById('audit-repay-container').style.display = 'none';
            document.getElementById('audit-repay-prompt').style.display = 'block';
            document.getElementById('audit-repay-list').innerHTML = '';
            document.getElementById('repay-more').style.display = 'none';
        }

        // 2. Fetch Summary
        return App.request('<?php echo BASE_URL; ?>api/credit_repayment_report.php?date=' + date + '&type=summary', 'GET', null, { signal: creditController.signal }).then(res => {
            if (res && res.stats) {
                document.getElementById('audit-total-credit').innerText = App.formatMoney(res.stats.old_outstanding);
                document.getElementById('audit-today-credit').innerText = App.formatMoney(res.stats.today_new_credit);
                document.getElementById('audit-cleared-payment').innerText = App.formatMoney(res.stats.today_cleared_payment);
                document.getElementById('audit-cleared-cash').innerText = App.formatMoney(res.stats.today_cleared_cash || 0);
                document.getElementById('audit-cleared-online').innerText = App.formatMoney(res.stats.today_cleared_online || 0);
            }

            // If the detailed lists are currently open, refresh them silently
            if (document.getElementById('audit-new-credit-container').style.display === 'block') {
                loadDetailedCredit('new_credit', false, keepOpen);
            }
            if (document.getElementById('audit-repay-container').style.display === 'block') {
                loadDetailedCredit('repayment', false, keepOpen);
            }
        });
    }

    let creditOffsets = { new_credit: 0, repayment: 0 };
    function loadDetailedCredit(listType, append = false, isSilent = false) {
        const date = document.getElementById('credit-audit-date').value;
        const container = document.getElementById('audit-' + (listType === 'new_credit' ? 'new-credit' : 'repay') + '-container');
        const prompt = document.getElementById('audit-' + (listType === 'new_credit' ? 'new-credit' : 'repay') + '-prompt');
        const listBody = document.getElementById('audit-' + (listType === 'new_credit' ? 'new-credit' : 'repay') + '-list');
        const loadMore = document.getElementById((listType === 'new_credit' ? 'new-credit' : 'repay') + '-more');

        if (!append) {
            creditOffsets[listType] = 0;
            if (!isSilent) {
                listBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px;">Loading...</td></tr>';
                container.style.display = 'block';
                prompt.style.display = 'none';
            }
        }

        App.request('<?php echo BASE_URL; ?>api/credit_repayment_report.php?date=' + date + '&type=list&list_type=' + listType + '&offset=' + creditOffsets[listType]).then(res => {
            if (!res || !res.items) return;

            const html = res.items.map(r => `
                <tr>
                    <td style="color:#222; font-weight:700; font-size:0.85rem; padding:8px 10px;">${new Date(r.created_at.replace(' ', 'T') + '+05:45').toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true })}</td>
                    <td style="font-size:0.85rem; padding:8px 10px;">
                        <div style="font-weight:600;">${r.customer_name}</div>
                    </td>
                    <td style="color:#777; font-size:0.8rem; padding:8px 10px;">${(listType === 'new_credit' && (!r.note || r.note === '-')) ? 'Full Credit Given' : (r.note || '-')}</td>
                    <td style="text-align:right; font-weight:700; color:${listType === 'new_credit' ? '#e65100' : '#2e7d32'}; font-size:0.9rem; padding:8px 10px;">
                        ${App.formatMoney(Math.abs(r.amount))}
                        <div style="margin-top:4px;"><button onclick="printCreditReceipt(${r.id})" style="font-size:0.7rem; padding:2px 5px; cursor:pointer;">🖨 Print</button></div>
                    </td>
                </tr>
            `).join('');

            if (!append) listBody.innerHTML = '';
            listBody.innerHTML += html;

            if (res.items.length === 0 && !append) {
                listBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px; color:#999;">No transactions found.</td></tr>';
            }

            creditOffsets[listType] += res.items.length;
            loadMore.style.display = res.has_more ? 'block' : 'none';
        });
    }

    function filterCustomers() {
        const query = document.getElementById('cust-search').value.toLowerCase().trim();

        // If empty, reload original top list
        if (query.length === 0) {
            renderCreditCustomers(allCreditCustomers);
            return;
        }

        // Tokenized Search (handles last name, middle name, or phone number in any order)
        const tokens = query.split(/\s+/);
        const filtered = allCreditCustomers.filter(cust => {
            const fullName = (cust.full_name || '').toLowerCase();
            const phone = (cust.phone || '').toLowerCase();
            const combined = fullName + ' ' + phone;
            return tokens.every(token => combined.includes(token));
        });

        renderCreditCustomers(filtered);
    }

    function exportSalesPDF() {
        const date = document.getElementById('sales-date').value;
        const width = 1000;
        const height = 800;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        window.open(
            'print_sales.php?date=' + date,
            'Statement',
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );
    }

    function exportAllCreditCustomers() {
        const width = 1000;
        const height = 800;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        window.open(
            'print_all_credit_customers.php',
            'AllCreditCustomers',
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );
    }

    function exportCustomerAudit(customerId) {
        const id = customerId || currentSelectedCustId;
        if (!id) {
            App.showToast('Please select a customer first');
            return;
        }
        const width = 1000;
        const height = 800;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        window.open(
            'print_customer_audit.php?customer_id=' + encodeURIComponent(id) + '&t=' + new Date().getTime(),
            'CustomerAudit_' + id,
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );
    }

    function printReceipt(sessionId, txnId = null) {
        if (!sessionId) return;
        const width = 450;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);
        let url = 'print_receipt.php?session_id=' + sessionId + '&t=' + new Date().getTime();
        if (txnId) url += '&txn_id=' + txnId;
        window.open(
            url,
            'Receipt',
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );
    }

    function printKOT(sessionId, type) {
        if (!sessionId || !type) return;
        const kotType = type === 'counter' ? 'counter' : 'kitchen';
        const width = 400;
        const height = 650;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);
        const url = 'print_kot.php?session_id=' + sessionId + '&kot=' + kotType + '&t=' + new Date().getTime();
        const popup = window.open(
            url,
            kotType === 'counter' ? 'CounterKOT' : 'KitchenKOT',
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );

        if (!popup) {
            App.showToast('Allow popups to print KOT', 'error');
            return;
        }

        // KOT marks items on page load — refresh billing buttons without full page reload
        const poll = setInterval(() => {
            if (popup.closed) {
                clearInterval(poll);
                refreshBillingKotButtons();
                return;
            }
            try {
                if (popup.document && popup.document.readyState === 'complete') {
                    refreshBillingKotButtons();
                }
            } catch (e) {
                // Ignore until popup document is accessible
            }
        }, 350);
    }

    function printCreditReceipt(txnId) {
        if (!txnId || txnId <= 0) {
            console.error("Invalid Transaction ID for printing:", txnId);
            return;
        }
        const width = 450;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        const popup = window.open(
            'print_credit_receipt.php?transaction_id=' + txnId + '&t=' + new Date().getTime(),
            'CreditReceipt_' + txnId,
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );

        if (!popup || popup.closed || typeof popup.closed == 'undefined') {
            alert('Popup blocker detected. Please allow popups for this site to print receipts.');
        }
    }

    /* ================= CREDIT MODULE JS (NEW) ================= */
    let allCreditCustomers = [];
    let currentSelectedCustId = null;

    function loadCreditCustomersList(isSilent = false) {
        const container = document.getElementById('credit-customer-list');

        // Only show loading if not a silent background refresh
        if (!isSilent) {
            container.innerHTML = '<div style="padding:10px;text-align:center;">Loading...</div>';
        }

        return App.request('<?php echo BASE_URL; ?>api/credit_customers_list.php?type=all&limit=500&t=' + new Date().getTime()).then(res => {
            // FIX: Show ALL customers, including 0 balance (newly created ones).
            allCreditCustomers = (res.customers || []);

            // Restore Selection if valid
            const savedCustId = localStorage.getItem('admin_active_credit_cust');
            if (savedCustId && allCreditCustomers.some(c => c.id == savedCustId)) {
                currentSelectedCustId = savedCustId;
                // Only load profile if this is a fresh load, otherwise profile refreshes separately?
                // Actually, if we refresh list, we might want to ensure profile is still valid.
                if (!isSilent) loadCreditProfile(savedCustId);
            }

            renderCreditCustomers(allCreditCustomers);

            // Scroll to active ONLY if it's NOT a silent refresh (to prevent auto-jumping while reading)
            if (!isSilent) {
                setTimeout(() => {
                    const activeEl = document.querySelector('.cust-card.active');
                    if (activeEl) activeEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }, 100);
            }
        });
    }

    function renderCreditCustomers(list) {
        const container = document.getElementById('credit-customer-list');
        if (list.length === 0) {
            container.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">No customers found.</div>';
            return;
        }

        container.innerHTML = list.map(c => `
            <div class="cust-card ${c.id == currentSelectedCustId ? 'active' : ''}" onclick="selectCreditCustomer(${c.id})" style="position:relative; display:flex; justify-content:space-between; align-items:center;">
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                         <span class="cust-name">${c.full_name}</span>
                         <span style="font-size:0.8rem; font-weight:700; color:#d32f2f; margin-right:10px;">${App.formatMoney(parseFloat(c.outstanding))}</span>
                    </div>
                    <span class="cust-phone">${c.phone || 'No phone'}</span>
                </div>
                <div style="display:flex; align-items:center; gap:10px; margin-left:10px;">
                    <button class="btn-sm" 
                            style="background:#2e7d32; color:white; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.75rem; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                            onclick="event.stopPropagation(); openPaymentModal(${c.id}, ${JSON.stringify(c.full_name || '').replace(/"/g, '&quot;')}, ${c.outstanding})">
                        Pay
                    </button>
                    <div style="font-size:1.2rem; color:#ccc;">›</div>
                </div>
            </div>
        `).join('');
    }


    function selectCreditCustomer(id) {
        currentSelectedCustId = id;
        localStorage.setItem('admin_active_credit_cust', id); // Save Selection
        renderCreditCustomers(allCreditCustomers); // Re-render to update active state
        loadCreditProfile(id);
    }



    function deselectCreditCustomer() {
        currentSelectedCustId = null;
        localStorage.removeItem('admin_active_credit_cust');
        // Toggle Visibility
        document.getElementById('credit-dashboard-overview').style.display = 'flex';
        document.getElementById('credit-customer-profile-container').style.display = 'none';

        renderCreditCustomers(allCreditCustomers); // clear active class
        loadCreditDashboard();
    }

    function loadCreditProfile(id) {
        const dashboard = document.getElementById('credit-dashboard-overview');
        const profileContainer = document.getElementById('credit-customer-profile-container');

        // Toggle Visibility
        dashboard.style.display = 'none';
        profileContainer.style.display = 'flex';
        profileContainer.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100%; color:#999;">Loading Profile...</div>';

        App.request('<?php echo BASE_URL; ?>api/customer_history.php?customer_id=' + id).then(res => {
            if (res.error) {
                profileContainer.innerHTML = `<div class="text-error">${res.error}</div>`;
                return;
            }

            const c = res.customer;
            const history = res.history;
            const total = res.total_credit;

            let tableRows = history.length > 0 ? history.map(h => {
                let printBtn = ''; if (parseFloat(h.amount) > 0 && h.session_id) {
                    printBtn = `
                        <button onclick="printReceipt(${h.session_id})" 
                                style="display:inline-flex; align-items:center; gap:4px; margin-top:4px; font-size:0.75rem; padding:4px 8px; border:1px solid #ddd; background:#fff; border-radius:12px; cursor:pointer; color:#555;" 
                                title="Print Evidence Receipt">
                            🖨 Receipt
                        </button>`;
                }

                return `
                <tr style="border-bottom:1px solid #f1f1f1;">
                    <td data-label="Type/Time" style="padding:15px 10px;">
                        <span style="font-weight:600; display:block; font-size:0.95rem;">${h.amount > 0 ? 'CREDIT' : 'PAYMENT'}</span>
                        <span style="font-size:0.75rem; color:#888;">${h.created_at.substring(0, 16)}</span>
                    </td>
                    <!-- Source Column Removed -->
                    <td data-label="Note" style="padding:15px 10px; color:#555;">
                        <div>${h.note || '-'}</div>
                        ${printBtn}
                    </td>
                    <td data-label="Amount" style="padding:15px 10px; text-align:right; font-weight:700; color:var(--primary-accent);">
                        ${App.formatMoney(h.amount)}
                    </td>
                </tr>
            `;
            }).join('') : '<tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No transaction history.</td></tr>';

            profileContainer.innerHTML = `
                <!-- Fixed Header Section -->
                <div style="padding:15px 25px 0 25px; flex-shrink:0; background:white; z-index:20;">
                    <div class="profile-header" style="margin-bottom:0; border-bottom:1px solid #eee; padding-bottom:15px; display:flex; justify-content:space-between;align-items: flex-end;">
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <button onclick="deselectCreditCustomer()" style="width:fit-content; background:#f5f5f5; border:1px solid #eee; color:#666; cursor:pointer; font-size:0.85rem; padding:6px 15px; border-radius:20px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='#eee'" onmouseout="this.style.background='#f5f5f5'">
                                ← Back </button>
                            <h2 style="margin:0; font-size:1.8rem; line-height:1.2;">${c.full_name}</h2>
                            <div style="color:#666;">${c.phone || 'No phone'} ${c.address ? ' • ' + c.address : ''}</div>
                            <div style="font-size:0.75rem; color:#aaa; margin-top:4px;">Since ${c.created_at ? c.created_at.substring(0, 10) : 'N/A'}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.8rem; color:#888;">Current Balance</div>
                            <div style="font-size:2rem; font-weight:800; color:${res.stats.outstanding > 0 ? '#d32f2f' : '#388e3c'};">
                                ${App.formatMoney(res.stats.outstanding)}
                            </div>
                        </div>
                    </div>

                    <!-- 4 Key Customer Stats -->
                    <div class="profile-stats-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px;">
                        <div class="stat-card" style="padding:15px; background:#fff3e0; border:none; border-radius:12px;">
                             <div style="font-size:0.75rem; font-weight:700; color:#e65100; margin-bottom:5px;">TOTAL CREDIT</div>
                             <div style="font-size:1.1rem; font-weight:800; color:#e65100;">${App.formatMoney(res.stats.total_accrued)}</div>
                             <div style="font-size:0.65rem; color:#ef6c00;">Lifetime Given</div>
                        </div>
                        <div class="stat-card" style="padding:15px; background:#e8f5e9; border:none; border-radius:12px;">
                             <div style="font-size:0.75rem; font-weight:700; color:#2e7d32; margin-bottom:5px;">TOTAL PAID</div>
                             <div style="font-size:1.1rem; font-weight:800; color:#2e7d32;">${App.formatMoney(res.stats.total_paid)}</div>
                             <div style="font-size:0.65rem; color:#4caf50;">Lifetime Paid</div>
                        </div>
                         <div class="stat-card" style="padding:15px; background:#e3f2fd; border:none; border-radius:12px;">
                             <div style="font-size:0.75rem; font-weight:700; color:#1565c0; margin-bottom:5px;">PAID TODAY</div>
                             <div style="font-size:1.1rem; font-weight:800; color:#1565c0;">${App.formatMoney(res.stats.paid_today)}</div>
                             <div style="font-size:0.65rem; color:#1976d2;">Collected Today</div>
                        </div>
                        <div class="stat-card" style="padding:15px; background:#fce4ec; border:none; border-radius:12px;">
                             <div style="font-size:0.75rem; font-weight:700; color:#c2185b; margin-bottom:5px;">REMAINING</div>
                             <div style="font-size:1.1rem; font-weight:800; color:#c2185b;">${App.formatMoney(res.stats.outstanding)}</div>
                             <div style="font-size:0.65rem; color:#e91e63;">To Pay</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="profile-actions">
                            ${res.stats.outstanding > 0 ? `
                                <button onclick="openPaymentModal(${c.id}, ${JSON.stringify(c.full_name || '').replace(/"/g, '&quot;')}, ${res.stats.outstanding})" class="btn-modern" style="background:#2e7d32; color:white; margin-right:10px; border:none; padding:8px 15px; border-radius:6px; cursor:pointer;">
                                    <span>+</span> Payment
                                </button>
                                <button onclick="openSettlementModal(${c.id}, ${JSON.stringify(c.full_name || '').replace(/"/g, '&quot;')}, ${res.stats.outstanding})" class="btn-modern btn-modern-paid">
                                    <span>✓</span> Mark as Full Payment
                                </button>
                            ` : `
                                <button onclick="deleteCustomer(${c.id})" class="btn-modern btn-modern-delete">
                                    <span>🗑</span> Delete Mistake Profile
                                </button>
                            `}
                            <button onclick="exportCustomerAudit(${c.id})" class="btn-modern"
                                style="background:#3e2723; color:white; border:none; padding:8px 15px; border-radius:6px; cursor:pointer; margin-left:10px;">
                                <span>⤓</span> Export Audit
                            </button>
                    </div>

</div>
</div>

<!-- Scrollable Body Section -->
<div style="flex:1; overflow-y:auto; padding:20px 25px 25px 25px;">

    <h3 style="margin-top:0; color:#555; font-size:1rem; border-bottom:2px solid #eee; padding-bottom:10px; margin-bottom:15px;">Transaction History</h3>
    <table class="pos-table" style="width:100%;">
        <thead>
            <tr>
                <th style="color:#888; font-weight:600;">Type / Date</th>
                <th style="color:#888; font-weight:600;">Note</th>
                <th style="text-align:right; color:#888; font-weight:600;">Amount</th>
                <th style="text-align:center; color:#888; font-weight:600; width:80px;">Receipt</th>
            </tr>
        </thead>
        <tbody>
            ${history.length > 0 ? history.map(h => {
                let printBtn = `
                        <button onclick="printCreditReceipt(${h.id})" 
                                style="background:#fff; border:1px solid #ddd; padding:6px 10px; border-radius:6px; cursor:pointer; color:#555; font-size:0.8rem; display:flex; align-items:center; gap:5px; transition:0.2s;"
                                onmouseover="this.style.borderColor='#333'; this.style.color='#000';"
                                onmouseout="this.style.borderColor='#ddd'; this.style.color='#555';">
                            <span>📄</span> DET
                        </button>`;
                return `
                <tr style="border-bottom:1px solid #f1f1f1;">
                    <td data-label="Type/Time" style="padding:15px 10px;">
                        <span style="font-weight:600; display:block; font-size:0.95rem;">${h.amount > 0 ? 'CREDIT' : 'PAYMENT'}</span>
                        <span style="font-size:0.75rem; color:#888;">${h.created_at.substring(0, 16).replace('T', ' ')}</span>
                    </td>
                    <td data-label="Note" style="padding:15px 10px; color:#555;">
                        <div>${(h.amount > 0 && (!h.note || h.note === '-')) ? 'Full Credit Given' : (h.note || '-')}</div>
                    </td>
                    <td data-label="Amount" style="padding:15px 10px; text-align:right; font-weight:700; color:${h.amount > 0 ? '#d32f2f' : '#2e7d32'};">
                        ${App.formatMoney(h.amount)}
                    </td>
                    <td data-label="Receipt" style="padding:15px 10px; text-align:center;">
                        ${printBtn}
                    </td>
                </tr>
            `;
            }).join('') : '<tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">No transaction history.</td></tr>'}
        </tbody>
    </table>
</div>
`;
        });
    }

    function openAddCustomerModal() {
        document.getElementById('add-customer-modal').style.display = 'flex';
        toggleNoScroll(true);
    }

    function submitGlobalCustomer() {
        const name = document.getElementById('global-cust-name').value;
        const phone = document.getElementById('global-cust-phone').value;
        const address = document.getElementById('global-cust-address').value;
        const note = document.getElementById('global-cust-note').value;

        if (!name) { App.showToast('Name is required'); return; }
        if (!phone) { App.showToast('Phone is required'); return; }
        if (!address) { App.showToast('Address is required'); return; }

        App.request('<?php echo BASE_URL; ?>api/create_credit_customer.php', 'POST', {
            full_name: name,
            phone: phone,
            address: address,
            note: note,
            csrf_token: csrfToken
        }).then(res => {
            if (res.success) {
                App.showToast('Customer Created');
                document.getElementById('add-customer-modal').style.display = 'none';
                toggleNoScroll(false);

                // Auto-select the new customer
                if (res.customer && res.customer.id) {
                    localStorage.setItem('admin_active_credit_cust', res.customer.id);
                    currentSelectedCustId = res.customer.id;
                    loadCreditProfile(res.customer.id); // DIRECT LOAD
                }

                // Keep the payment modal's customer dropdown in sync too
                if (res.customer && !customersList.some(c => c.id == res.customer.id)) {
                    customersList.push(res.customer);
                }

                loadCreditCustomersList(false); // Force reload to show new customer and scroll
            } else {
                App.showToast(res.error, 'error');
            }
        });
    }



    let modernConfirmCallback = null;

    function showModernConfirm(title, message, confirmText, icon, callback) {
        document.getElementById('m-confirm-title').innerText = title;
        document.getElementById('m-confirm-msg').innerText = message;
        document.getElementById('m-confirm-btn').innerText = confirmText;
        document.getElementById('m-confirm-icon').innerText = icon || '⚠️';
        document.getElementById('modern-confirm-modal').style.display = 'flex';
        toggleNoScroll(true);
        modernConfirmCallback = callback;

        document.getElementById('m-confirm-btn').onclick = () => {
            const cb = modernConfirmCallback;
            closeModernConfirm();
            if (cb) cb();
        };
    }

    function closeModernConfirm() {
        document.getElementById('modern-confirm-modal').style.display = 'none';
        toggleNoScroll(false);
        modernConfirmCallback = null;
    }

    function deleteCustomer(id) {
        showModernConfirm(
            'Delete Profile?',
            'This person will be removed from the list. Past records will be kept for audit.',
            'Remove Profile',
            '👤',
            () => {
                App.request('<?php echo BASE_URL; ?>api/delete_credit_customer.php', 'POST', {
                    customer_id: id,
                    csrf_token: csrfToken
                }).then(res => {
                    if (res.success) {
                        App.showToast('Customer Removed');
                        location.reload();
                    } else {
                        App.showToast(res.error, 'error');
                    }
                });
            }
        );
    }


    // --- Custom Confirmation Logic ---
    let confirmCallback = null;

    function showCustomConfirm(title, message, callback) {
        document.getElementById('c-modal-title').innerText = title;
        document.getElementById('c-modal-msg').innerText = message;
        document.getElementById('custom-confirm-modal').style.display = 'flex';
        toggleNoScroll(true);
        confirmCallback = callback;
    }

    function closeConfirmModal(result) {
        document.getElementById('custom-confirm-modal').style.display = 'none';
        toggleNoScroll(false);
        if (result && confirmCallback) confirmCallback();
        confirmCallback = null;
    }

    function cancelItem(itemId) {
        showCustomConfirm('Clear this item?', 'If it was already served, stock will go back and a return note is added to Sold history.', () => {
            App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', {
                action: 'cancel_item',
                item_id: itemId,
                csrf_token: csrfToken
            }).then(res => {
                if (res.success) {
                    App.showToast('Item removed');
                    refresh();
                } else {
                    App.showToast(res.error || 'Failed to remove', 'error');
                }
            });
        }

        );
    }

    function cancelTableOrders(tableCode) {
        showCustomConfirm(
            `Clear Table ${tableCode}?`,
            'This clears all active orders and frees the table.\nServed stock items are returned with a note in Sold history.',
            () => {
                App.request('<?php echo BASE_URL; ?>api/admin_action.php', 'POST', {
                    action: 'cancel_table_orders',
                    table_code: tableCode,
                    csrf_token: csrfToken
                }).then(res => {
                    if (res.success) {
                        App.showToast(`Table ${tableCode} cleared`);
                        refresh();
                    } else {
                        App.showToast(res.error || 'Failed to clear', 'error');
                    }
                });
            }
        );
    }

    function promptPayment(id) {
        const amount = prompt("Enter payment amount:");
        if (!amount) return;

        const note = prompt("Enter optional note (e.g. Cash, Bank Transfer):");

        App.request('<?php echo BASE_URL; ?>api/record_payment.php', 'POST', {
            customer_id: id,
            amount: amount,
            note: note,
            csrf_token: csrfToken
        }).then(res => {
            if (res.success) {
                App.showToast('Payment Recorded');
                loadCreditProfile(id); // Reload profile
            } else {
                App.showToast(res.error, 'error');
            }
        });
    }

    /* --- MENU MANAGEMENT --- */
    function loadMenuItems(isSilent = false) {
        if (menuController) menuController.abort();
        menuController = new AbortController();

        const body = document.getElementById('menu-list-body');
        if (!body) return;

        if (!isSilent) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px;"><div class="spinner" style="margin:0 auto 10px;"></div>Fetching Menu...</td></tr>';
        }

        return App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=list', 'GET', null, { signal: menuController.signal })
            .then(data => {
                if (data && data.success) {
                    if (data.items.length === 0) {
                        body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No items found.</td></tr>';
                        return;
                    }
                    body.innerHTML = data.items.map(item => {
                        const counterBadge = parseInt(item.is_counter_item) === 1
                            ? ' <span class="badge-pill" style="background:#e0f7fa; color:#006064; font-size:0.7rem; padding:2px 6px; border-radius:4px; margin-left:5px;">Counter</span>'
                            : '';
                        return `
                        <tr>
                            <td data-label="Item" style="font-weight:600;">${item.name}${counterBadge}</td>
                            <td data-label="Price">Rs. ${parseFloat(item.price).toFixed(0)}</td>
                            <td data-label="Status">
                                <span class="badge ${parseInt(item.is_available) === 1 ? 'badge-success' : 'badge-danger'}" 
                                      style="cursor:pointer;" onclick="toggleMenuStatus(${item.id})">
                                    ${parseInt(item.is_available) === 1 ? 'Available' : 'Out of Stock'}
                                </span>
                            </td>
                            <td data-label="Actions" style="text-align:right;">
                                <div style="display:flex; gap:10px; justify-content:flex-end;">
                                    <button class="btn-sm" style="background:#0288d1; color:white; border:none; padding:5px 12px; border-radius:4px; cursor:pointer;" 
                                             onclick="openMenuModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">Edit</button>
                                    <button class="btn-sm" style="background:#d32f2f; color:white; border:none; padding:5px 12px; border-radius:4px; cursor:pointer;" 
                                             onclick="deleteMenuItem(${item.id})">Delete</button>
                                </div>
                            </td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    if (!isSilent) body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Error loading menu.</td></tr>';
                }
            }).catch(err => {
                if (err.name === 'AbortError') return;
                if (!isSilent) body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Connection error.</td></tr>';
            });
    }

    function openMenuModal(item = null) {
        const modal = document.getElementById('menu-item-modal');
        const title = document.getElementById('menu-modal-title');
        const idInput = document.getElementById('menu-item-id');
        const nameInput = document.getElementById('menu-item-name');
        const priceInput = document.getElementById('menu-item-price');
        const availInput = document.getElementById('menu-item-available');
        const counterInput = document.getElementById('menu-item-counter');

        if (item) {
            title.innerText = 'Edit Menu Item';
            idInput.value = item.id;
            nameInput.value = item.name;
            priceInput.value = item.price;
            availInput.checked = parseInt(item.is_available) === 1;
            counterInput.checked = parseInt(item.is_counter_item) === 1;
        } else {
            title.innerText = 'Add New Item';
            idInput.value = '';
            nameInput.value = '';
            priceInput.value = '';
            availInput.checked = true;
            counterInput.checked = false;
        }

        modal.style.display = 'flex';
        toggleNoScroll(true);
        setTimeout(() => nameInput.focus(), 100);
    }

    function submitMenuSave() {
        const id = document.getElementById('menu-item-id').value;
        const name = document.getElementById('menu-item-name').value;
        const price = document.getElementById('menu-item-price').value;
        const available = document.getElementById('menu-item-available').checked ? 1 : 0;
        const counter = document.getElementById('menu-item-counter').checked ? 1 : 0;

        if (!name || !price) {
            App.showToast('Name and price are required', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('name', name);
        formData.append('price', price);
        formData.append('is_available', available);
        formData.append('is_counter_item', counter);
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=save', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    App.showToast('Menu item saved');
                    document.getElementById('menu-item-modal').style.display = 'none';
                    toggleNoScroll(false);
                    loadMenuItems();
                } else {
                    App.showToast(data.error || 'Failed to save', 'error');
                }
            });
    }

    function toggleMenuStatus(id) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=toggle_status', 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    loadMenuItems();
                }
            });
    }

    function deleteMenuItem(id) {
        showCustomConfirm('Delete Item?', 'Are you sure you want to remove this dish from the menu?', () => {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);

            App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=delete', 'POST', formData)
                .then(data => {
                    if (data && data.success) {
                        App.showToast('Item deleted');
                        loadMenuItems();
                    } else {
                        App.showToast(data.error || 'Failed to delete', 'error');

                    }
                });
        });
    }

</script>

<!-- Customer Picker Modal -->
<div id="cust-picker-modal" class="cust-picker-backdrop hidden">
    <div class="cust-picker-card">
        <div class="cust-picker-header">
            <span class="cust-picker-title"><span class="cust-picker-title-icon">👤</span> Select Customer</span>
            <span class="cust-picker-close" onclick="closeCustomerCombo()">✕</span>
        </div>
        <div class="cust-combo-input-wrap">
            <span class="cust-combo-icon-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </span>
            <input type="text" id="credit-customer-search" class="cust-combo-input"
                   placeholder="Search customer by name or phone..."
                   autocomplete="off"
                   oninput="filterCustomerCombo(this.value)">
            <span class="cust-combo-clear hidden" id="cust-combo-clear" onclick="clearCustomerCombo()">✕</span>
        </div>
        <div class="cust-combo-list" id="cust-combo-list"></div>
        <div class="cust-picker-footer">
            <button type="button" class="cust-picker-btn cust-picker-btn-cancel" onclick="closeCustomerCombo()">Cancel</button>
            <button type="button" class="cust-picker-btn cust-picker-btn-confirm" id="cust-picker-confirm-btn" onclick="confirmCustomerCombo()" disabled>Confirm Selection</button>
        </div>
    </div>
</div>

<!-- Payment Modal (Animated Multi-step) -->
<div id="payment-modal" class="modal"
    style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
    <div class="modal-content"
        style="background-color:#fff; margin:10% auto; padding:25px; border:none; width:90%; max-width:420px; border-radius:16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">

        <span class="close-modal" onclick="closePaymentModal()"
            style="color:#ccc; float:right; font-size:24px; cursor:pointer;">&times;</span>

        <!-- Step 1: Input -->
        <div id="pay-step-1">
            <h2 style="margin-top:0; color:#333; font-size:1.4rem;">Record Payment</h2>
            <p id="payment-cust-name" style="color:#666; margin-bottom:25px; font-size:0.9rem;"></p>
            <input type="hidden" id="payment-cust-id">
            <input type="hidden" id="payment-max-limit" value="0">

            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#444;">Payment Method</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <label style="cursor:pointer; margin:0;">
                        <input type="radio" name="pay_deduct_mode" value="Cash" checked style="display:none;"
                            onchange="updatePayDeductUI()">
                        <div id="btn-pay-cash"
                            style="padding:12px; border:2px solid var(--primary-accent); border-radius:10px; text-align:center; font-weight:700; color:var(--primary-accent); background:rgba(191, 54, 12, 0.05);">
                            💵 Cash
                        </div>
                    </label>
                    <label style="cursor:pointer; margin:0;">
                        <input type="radio" name="pay_deduct_mode" value="Online" style="display:none;"
                            onchange="updatePayDeductUI()">
                        <div id="btn-pay-online"
                            style="padding:12px; border:2px solid #eee; border-radius:10px; text-align:center; font-weight:700; color:#666;">
                            📱 Online
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#444;">Amount to Deduct
                    (Rs.)</label>
                <input type="number" id="payment-amount" placeholder="0.00" min="1" oninput="checkOverpayment()"
                    style="width:100%; padding:14px; border:2px solid #eee; border-radius:10px; font-size:1.2rem; font-weight:bold; outline:none; transition:0.2s;">
                <div id="payment-help-text" style="font-size:0.8rem; color:#888; margin-top:5px;">This will reduce the
                    customer's
                    outstanding
                    credit.</div>
            </div>

            <div class="form-group" style="margin-bottom:25px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#444;">Note
                    (Optional)</label>
                <input type="text" id="payment-note" placeholder="e.g. Cleared half, Cash"
                    style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:1rem;">
            </div>

            <button onclick="goToConfirmation()" class="btn-primary"
                style="width:100%; padding:14px; background:var(--primary-accent); color:white; border:none; border-radius:10px; cursor:pointer; font-weight:700; font-size:1rem; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                Review Deduction
            </button>
        </div>

        <!-- Step 2: Confirmation -->
        <div id="pay-step-2" style="display:none;">
            <h2 style="margin-top:0; color:#c62828; font-size:1.4rem;">Confirm Deduction?</h2>
            <p style="color:#555;">Are you sure you want to deduct this amount?</p>

            <div class="payment-confirm-box">
                <div style="font-size:0.9rem; color:#777; margin-bottom:5px;">Deducting Amount</div>
                <div style="font-size:2rem; font-weight:800; color:#c62828;"><span id="conf-amt">0.00</span>
                </div>
                <div style="font-size:0.85rem; color:#555; margin-top:5px;">Note: <span id="conf-note"
                        style="font-weight:600;">-</span></div>
            </div>

            <div class="action-buttons" style="display:flex; gap:10px; margin-top:25px;">
                <button onclick="backToStep1()"
                    style="flex:1; padding:12px; background:#f0f0f0; color:#555; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Back</button>
                <button onclick="submitPaymentFinal()"
                    style="flex:2; padding:12px; background:#c62828; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; box-shadow:0 4px 12px rgba(198,40,40,0.2);">Yes,
                    Confirm</button>
            </div>
        </div>

    </div>
</div>

<script>
    function updatePayDeductUI() {
        const mode = document.querySelector('input[name="pay_deduct_mode"]:checked').value;
        const btnCash = document.getElementById('btn-pay-cash');
        const btnOnline = document.getElementById('btn-pay-online');

        if (mode === 'Cash') {
            btnCash.style.border = '2px solid var(--primary-accent)';
            btnCash.style.color = 'var(--primary-accent)';
            btnCash.style.background = 'rgba(191, 54, 12, 0.05)';
            btnOnline.style.border = '2px solid #eee';
            btnOnline.style.color = '#666';
            btnOnline.style.background = 'none';
        } else {
            btnOnline.style.border = '2px solid var(--primary-accent)';
            btnOnline.style.color = 'var(--primary-accent)';
            btnOnline.style.background = 'rgba(191, 54, 12, 0.05)';
            btnCash.style.border = '2px solid #eee';
            btnCash.style.color = '#666';
            btnCash.style.background = 'none';
        }
    }

    function checkOverpayment() {
        const amountInput = document.getElementById('payment-amount');
        const helpText = document.getElementById('payment-help-text');
        const maxLimit = parseFloat(document.getElementById('payment-max-limit').value) || 0;
        const currentVal = parseFloat(amountInput.value) || 0;

        if (maxLimit > 0 && currentVal > maxLimit + 1) {
            helpText.innerHTML = "Overpriced! Max: " + maxLimit;
            helpText.style.color = "red";
            helpText.style.fontWeight = "bold";
            amountInput.style.borderColor = "red";
            amountInput.style.color = "red";
        } else {
            helpText.innerText = "This will reduce the customer's outstanding credit.";
            helpText.style.color = "#888";
            helpText.style.fontWeight = "normal";
            amountInput.style.borderColor = "#eee";
            amountInput.style.color = "black";
        }
    }

    function openPaymentModal(id, name, maxAmount) {
        document.getElementById('payment-cust-id').value = id;
        document.getElementById('payment-cust-name').innerText = 'Customer: ' + name;
        document.getElementById('payment-amount').value = '';
        document.getElementById('payment-note').value = '';

        // Set Max Limit
        const limit = parseFloat(maxAmount) || 0;
        document.getElementById('payment-max-limit').value = limit;
        document.getElementById('payment-amount').setAttribute('placeholder', 'Max: ' + limit);

        // Reset mode to Cash
        const cashRadio = document.querySelector('input[name="pay_deduct_mode"][value="Cash"]');
        if (cashRadio) {
            cashRadio.checked = true;
            updatePayDeductUI();
        }

        // Reset to Step 1
        document.getElementById('pay-step-1').style.display = 'block';
        document.getElementById('pay-step-2').style.display = 'none';
        document.getElementById('payment-modal').style.display = 'block';
        toggleNoScroll(true);

        // Focus amount input
        setTimeout(() => document.getElementById('payment-amount').focus(), 100);
    }

    function closePaymentModal() {
        document.getElementById('payment-modal').style.display = 'none';
        toggleNoScroll(false);
    }

    function goToConfirmation() {
        const amount = document.getElementById('payment-amount').value;
        const note = document.getElementById('payment-note').value;
        const mode = document.querySelector('input[name="pay_deduct_mode"]:checked').value;

        if (!amount || amount <= 0) {
            App.showToast('Please enter a valid amount', 'error');
            return;
        }

        // Overpayment Check
        const max = parseFloat(document.getElementById('payment-max-limit').value) || 0;
        if (max > 0 && parseFloat(amount) > max + 1) { // Allow small epsilon for float issues
            App.showToast('Amount exceeds outstanding credit (Rs.' + max + ')', 'error');
            return;
        }

        document.getElementById('conf-amt').innerText = App.formatMoney(amount);
        document.getElementById('conf-note').innerText = (note || 'None') + ' (' + mode + ')';

        document.getElementById('pay-step-1').style.display = 'none';
        document.getElementById('pay-step-2').style.display = 'block';
    }

    function backToStep1() {
        document.getElementById('pay-step-2').style.display = 'none';
        document.getElementById('pay-step-1').style.display = 'block';
    }

    function submitPaymentFinal() {
        const id = document.getElementById('payment-cust-id').value;
        const amount = document.getElementById('payment-amount').value;
        const note = document.getElementById('payment-note').value;
        const mode = document.querySelector('input[name="pay_deduct_mode"]:checked').value;

        if (!amount || amount <= 0) {
            App.showToast('Please enter a valid amount', 'error');
            return;
        }

        // Show loading state
        const btn = event.target;
        const originalText = btn.innerText;
        btn.innerText = 'Processing...';
        btn.disabled = true;

        App.request('<?php echo BASE_URL; ?>api/record_payment.php', 'POST', {
            customer_id: id,
            amount: amount,
            note: note,
            payment_mode: mode,
            csrf_token: csrfToken
        }).then(res => {
            btn.innerText = originalText;
            btn.disabled = false;

            if (res.success) {
                App.showToast('Payment Recorded Successfully');
                closePaymentModal();
                if (typeof loadCreditProfile === 'function') {
                    loadCreditProfile(id);
                }
                if (typeof loadCreditCustomersList === 'function') {
                    loadCreditCustomersList(true); // Refresh sidebar list to remove cleared customers
                }
                // Print Receipt for the credit payment
                if (res.transaction_id) {
                    printCreditReceipt(res.transaction_id);
                }
            } else {
                App.showToast(res.error || 'Failed', 'error');
            }
        }).catch(err => {
            console.error(err);
            btn.innerText = originalText;
            btn.disabled = false;
            App.showToast('Error recording payment', 'error');
        });
    }

    function updateCreditRemaining() {
        const totalEl = document.getElementById('bill-grand-total');
        if (!totalEl) return;

        const total = parseFloat(totalEl.getAttribute('data-val')) || 0;
        const cash = parseFloat(document.getElementById('partial_cash').value) || 0;
        const online = parseFloat(document.getElementById('partial_online').value) || 0;

        let remaining = total - (cash + online);
        // Avoid negative zeroes or precision issues
        remaining = Math.round(remaining * 100) / 100;

        const disp = document.getElementById('credit-remaining-disp');
        if (disp) {
            if (remaining < 0) {
                disp.innerHTML = "<span style='color:red'>Overpaid: Rs. " + Math.abs(remaining).toFixed(0) + "</span>";
            } else {
                disp.textContent = "Rs. " + remaining.toFixed(0);
                disp.style.color = '#d84315';
            }
        }
    }


    function togglePartialInput() {
        const type = document.querySelector('input[name="credit_type"]:checked').value;
        const panel = document.getElementById('partial-pay-input');
        if (type === 'PARTIAL') {
            panel.classList.remove('hidden');
            // Reset values to 0 to avoid confusion
            if (document.getElementById('partial_cash')) document.getElementById('partial_cash').value = '';
            if (document.getElementById('partial_online')) document.getElementById('partial_online').value = '';
            setTimeout(updateCreditRemaining, 50); // Calc initial state
        } else {
            panel.classList.add('hidden');
            if (document.getElementById('partial_cash')) document.getElementById('partial_cash').value = '';
            if (document.getElementById('partial_online')) document.getElementById('partial_online').value = '';
        }
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        const pModal = document.getElementById('payment-modal');
        const sModal = document.getElementById('settle-modal');
        const addCustModal = document.getElementById('add-customer-modal');
        const bModal = document.getElementById('billing-modal');        const menuModal = document.getElementById('menu-item-modal');

        if (event.target == pModal) closePaymentModal();
        if (event.target == sModal) closeSettlementModal();
        if (event.target == addCustModal) {
            addCustModal.style.display = 'none';
            toggleNoScroll(false);
        }
        if (event.target == bModal) closeModal();        if (event.target == menuModal) {
            menuModal.style.display = 'none';
            toggleNoScroll(false);
        }
    }
    // --- MENU MANAGEMENT JS ---
    let allMenuItems = [];

    function loadMenuItems() {
        App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=list')
            .then(data => {
                if (data && data.items) {
                    allMenuItems = data.items;
                    // Preserve search filter if exists
                    const searchInput = document.getElementById('menu-search-input');
                    if (searchInput && searchInput.value.trim() !== '') {
                        filterMenu();
                    } else {
                        renderMenuItems(allMenuItems);
                    }
                } else {
                    document.getElementById('menu-list-body').innerHTML = '<tr><td colspan="5" class="error-text">Failed to load menu</td></tr>';
                }
            });
    }

    function renderMenuItems(items) {
        const body = document.getElementById('menu-list-body');
        if (!items || items.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="empty-state" style="padding:40px; text-align:center; color:#999;">No dishes found matching that search.</td></tr>';
            return;
        }
        body.innerHTML = items.map((item, index) => {
            const availBadge = item.is_available == 1
                ? '<span class="badge-pill badge-success">Available</span>'
                : '<span class="badge-pill badge-hidden">Hidden</span>';

            const counterBadge = item.is_counter_item == 1
                ? ' <span class="badge-pill" style="background:#e0f7fa; color:#006064; font-size:0.7rem; padding:2px 6px; border-radius:4px; margin-left:5px;">Counter</span>'
                : '';

            return `
            <tr>
                <td class="idx-col">${index + 1}</td>
                <td class="item-name-col">${item.name}${counterBadge}</td>
                <td style="text-align:right;" class="price-col">Rs. ${parseFloat(item.price).toFixed(0)}</td>
                <td style="text-align:center;">${availBadge}</td>
                <td style="text-align:right;">
                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button onclick="openMenuModal(${item.id}, ${JSON.stringify(item.name || '').replace(/"/g, '&quot;')}, ${item.price}, ${item.is_available}, ${item.is_counter_item || 0})" 
                            class="btn-edit-action">Edit</button>
                        <button onclick="deleteMenuItem(${item.id}, ${JSON.stringify(item.name || '').replace(/"/g, '&quot;')})" 
                            class="btn-delete-action" style="background:#fff5f5; color:#e53935; border:1px solid #ffcdd2; padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.75rem; transition:0.2s;">Delete</button>
                    </div>
                </td>
            </tr>
            `;
        }).join('');
    }

    function filterMenu() {
        const query = document.getElementById('menu-search-input').value.toLowerCase().trim();
        if (!query) {
            renderMenuItems(allMenuItems);
            return;
        }

        // Tokenized Search (handles words in any order, like searching "Chicken Biryani" as "Biryani Chicken")
        const tokens = query.split(/\s+/);
        const filtered = allMenuItems.filter(item => {
            const itemName = item.name.toLowerCase();
            return tokens.every(token => itemName.includes(token));
        });

        renderMenuItems(filtered);
    }

    function openMenuModal(id = null, name = '', price = '', available = 1, counter = 0) {
        document.getElementById('menu-item-id').value = id || '';
        document.getElementById('menu-item-name').value = name;
        document.getElementById('menu-item-price').value = price;
        document.getElementById('menu-item-available').checked = (available == 1);
        document.getElementById('menu-item-counter').checked = (counter == 1);

        const title = id ? 'Edit Menu Item' : 'Add Menu Item';
        document.getElementById('menu-modal-title').innerText = title;

        document.getElementById('menu-item-modal').style.display = 'flex';
        toggleNoScroll(true);
    }

    function submitMenuSave() {
        const id = document.getElementById('menu-item-id').value;
        const name = document.getElementById('menu-item-name').value;
        const price = document.getElementById('menu-item-price').value;
        const available = document.getElementById('menu-item-available').checked ? 1 : 0;
        const counter = document.getElementById('menu-item-counter').checked ? 1 : 0;

        if (!name || !price) {
            App.showToast('Please enter name and price', 'error');
            return;
        }

        const action = 'save';

        const formData = new FormData();
        formData.append('id', id);
        formData.append('name', name);
        formData.append('price', price);
        formData.append('is_available', available);
        formData.append('is_counter_item', counter);
        // Include CSRF token for security
        if (typeof csrfToken !== 'undefined') {
            formData.append('csrf_token', csrfToken);
        }

        App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=' + action, 'POST', formData)
            .then(data => {
                if (data && data.success) {
                    App.showToast(data.message || 'Item saved successfully');
                    document.getElementById('menu-item-modal').style.display = 'none';
                    toggleNoScroll(false);
                    loadMenuItems();
                } else {
                    App.showToast(data.error || 'Failed to save item', 'error');
                }
            });
    }

    function deleteMenuItem(id, name) {
        showModernConfirm(
            'Delete Menu Item?',
            `Permanently remove "${name}" from your menu?`,
            'Delete Item',
            '🍔',
            () => {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('csrf_token', csrfToken);

                App.request('<?php echo BASE_URL; ?>api/menu_action.php?action=delete', 'POST', formData)
                    .then(data => {
                        if (data && data.success) {
                            App.showToast('Item deleted successfully');
                            loadMenuItems();
                        } else {
                            App.showToast(data.error || 'Failed to delete item', 'error');
                        }
                    });
            }
        );
    }
</script>

<!-- Full Settlement Modal -->
<div id="settle-modal" class="modal"
    style="display:none; position:fixed; z-index:110; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.6);">
    <div class="modal-content"
        style="background-color:#fff; margin:15% auto; padding:30px; border:none; width:90%; max-width:450px; border-radius:18px; text-align:center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">

        <span class="close-modal" onclick="closeSettlementModal()"
            style="color:#ccc; float:right; font-size:24px; cursor:pointer;">&times;</span>

        <h2 style="margin:0 0 10px 0; color:#2e7d32;">Full Settlement</h2>
        <p style="color:#666; margin-bottom:25px;">Settling outstanding balance for <span id="settle-name"
                style="font-weight:700;"></span></p>

        <!-- Step 1: Select Method -->
        <div id="settle-step-1">
            <div style="background:#f1f8e9; padding:20px; border-radius:12px; margin-bottom:30px;">
                <div
                    style="font-size:0.85rem; color:#558b2f; text-transform:uppercase; letter-spacing:1px; font-weight:700;">
                    Total To Pay</div>
                <div style="font-size:2.5rem; font-weight:800; color:#2e7d32; margin-top:5px;" id="settle-display-amt">
                    Rs. 0.00</div>
            </div>

            <p style="font-weight:600; color:#444; margin-bottom:15px;">Select Payment Method:</p>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <button onclick="confirmSettlement('Cash')" class="btn-modern"
                    style="justify-content:center; background:#fff; border:2px solid #ddd; color:#333; height:50px; font-size:1rem;">
                    💵 Cash
                </button>
                <button onclick="confirmSettlement('Online/QR')" class="btn-modern"
                    style="justify-content:center; background:#fff; border:2px solid #ddd; color:#333; height:50px; font-size:1rem;">
                    📱 Online / QR
                </button>
            </div>
        </div>

        <!-- Step 2: Confirmation -->
        <div id="settle-step-2" style="display:none;">
            <h3 style="color:#2e7d32; margin-top:0;">Confirm Payment?</h3>
            <p>Receiving <strong id="settle-conf-amt" style="font-size:1.2rem;"></strong> via <strong
                    id="settle-conf-mode"></strong></p>

            <div style="display:flex; gap:10px; margin-top:25px;">
                <button onclick="backToSettleStep1()"
                    style="flex:1; padding:12px; background:#f0f0f0; border:none; border-radius:8px; cursor:pointer;">Back</button>
                <button onclick="submitSettlementFinal()"
                    style="flex:2; padding:12px; background:#2e7d32; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">Yes,
                    Confirm</button>
            </div>
        </div>

        <input type="hidden" id="settle-cust-id">
        <input type="hidden" id="settle-amount">
        <input type="hidden" id="settle-mode">
    </div>
</div>

<script>
    /* --- Settlement Modal Functions --- */
    function openSettlementModal(id, name, amount) {
        if (amount <= 0) {
            App.showToast('No outstanding balance to settle.', 'info');
            return;
        }
        document.getElementById('settle-cust-id').value = id;
        document.getElementById('settle-amount').value = amount;
        document.getElementById('settle-name').innerText = name;
        document.getElementById('settle-display-amt').innerText = App.formatMoney(amount);

        document.getElementById('settle-modal').style.display = 'block';
        toggleNoScroll(true);
    }

    function closeSettlementModal() {
        document.getElementById('settle-modal').style.display = 'none';
        toggleNoScroll(false);
    }

    function confirmSettlement(mode) {
        const amount = document.getElementById('settle-amount').value;
        document.getElementById('settle-mode').value = mode;

        document.getElementById('settle-conf-amt').innerText = App.formatMoney(amount);
        document.getElementById('settle-conf-mode').innerText = mode;

        document.getElementById('settle-step-1').style.display = 'none';
        document.getElementById('settle-step-2').style.display = 'block';

        // Update Back button to close modal since Step 1 is skipped
        const backBtn = document.querySelector('#settle-step-2 button:first-child');
        backBtn.onclick = closeSettlementModal;
    }

    // backToSettleStep1 is no longer used but kept if we revert
    function backToSettleStep1() {
        document.getElementById('settle-step-2').style.display = 'none';
        document.getElementById('settle-step-1').style.display = 'block';
    }

    function submitSettlementFinal() {
        const id = document.getElementById('settle-cust-id').value;
        const amount = document.getElementById('settle-amount').value;
        const mode = document.getElementById('settle-mode').value;

        // Validation
        if (!id || !amount) {
            App.showToast('Invalid settlement data', 'error');
            return;
        }

        const btn = document.querySelector('#settle-step-2 button:last-child');
        const originalText = btn.innerText;
        btn.innerText = 'Processing...';
        btn.disabled = true;

        App.request('<?php echo BASE_URL; ?>api/add_manual_payment.php', 'POST', {
            customer_id: id,
            amount: amount,
            note: 'Full Settlement - ' + mode
            ,
            payment_mode: mode,
            csrf_token: csrfToken
        }).then(res => {
            btn.innerText = originalText;
            btn.disabled = false;
            closeSettlementModal(); // Close only after response

            if (res.error) {
                App.showToast(res.error, 'error');
            } else {
                showSuccessModal();
                // Print Receipt
                if (res.transaction_id) {
                    printCreditReceipt(res.transaction_id);
                }

                localStorage.removeItem('admin_active_credit_cust');
                currentSelectedCustId = null;

                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        }).catch(err => {
            console.error('Settlement error:', err);
            btn.innerText = originalText;
            btn.disabled = false;
            App.showToast('Failed to contact server', 'error');
        });
    }

    function showSuccessModal() {
        const modal = document.getElementById('success-modal');
        modal.style.display = 'block';
        toggleNoScroll(true);
        setTimeout(() => {
            modal.style.display = 'none';
            toggleNoScroll(false);
        }, 2500); // Auto close after 2.5s
    }
</script>




<!-- Success Modal (Animated Checkmark) -->
<div id="success-modal" class="modal"
    style="display:none; position:fixed; z-index:120; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.6);">
    <div class="modal-content"
        style="background-color:#fff; margin:20% auto; padding:40px; border:none; width:90%; max-width:320px; border-radius:20px; text-align:center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div
            style="width:80px; height:80px; background:#e8f5e9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto;">
            <span style="font-size:40px; color:#2e7d32;">✓</span>
        </div>
        <h2 style="margin:0 0 10px 0; color:#2e7d32; font-size:1.5rem;">Payment Successful!</h2>
        <p style="color:#666; margin:0;">The credit has been settled.</p>
    </div>
</div>

<!-- Custom Confirmation Modal HTML -->
<div id="custom-confirm-modal" class="modal"
    style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:hidden; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); align-items:center; justify-content:center;">
    <div class="confirm-content"
        style="background:#fff; padding:35px 30px; border-radius:20px; width:90%; max-width:380px; text-align:center; box-shadow:0 25px 60px rgba(0,0,0,0.4); animation: popIn 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);">

        <!-- Animated Icon -->
        <div
            style="width:70px; height:70px; background:#ffebee; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto; box-shadow:inset 0 4px 6px rgba(0,0,0,0.05);">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none"
                stroke="#d32f2f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z">
                </path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>

        <h3 id="c-modal-title" style="margin:0 0 12px 0; color:#222; font-size:1.4rem; font-weight:800;">Are you
            sure?
        </h3>
        <p id="c-modal-msg" style="color:#666; margin:0 0 30px 0; line-height:1.6; font-size:1rem;">This action
            cannot
            be undone.</p>

        <div style="display:flex; gap:12px; justify-content:center;">
            <button onclick="closeConfirmModal(false)" style="
                    flex:1;
                    padding:14px;
                    background:#f1f3f5;
                    border:none;
                    color:#555;
                    border-radius:12px;
                    font-weight:700;
                    cursor:pointer;
                    box-shadow:0 4px 0 #dee2e6;
                    transition:all 0.1s;
                    font-size:0.95rem;
                " onmousedown="this.style.transform='translateY(4px)'; this.style.boxShadow='0 0 0 #dee2e6';"
                onmouseup="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 0 #dee2e6';">Cancel</button>

            <button onclick="closeConfirmModal(true)" style="
                    flex:1;
                    padding:14px;
                    background:linear-gradient(to bottom, #ff5252, #d32f2f);
                    border:none;
                    color:white;
                    border-radius:12px;
                    font-weight:700;
                    cursor:pointer;
                    box-shadow:0 4px 0 #b71c1c;
                    transition:all 0.1s;
                    font-size:0.95rem;
                " onmousedown="this.style.transform='translateY(4px)'; this.style.boxShadow='0 0 0 #b71c1c';"
                onmouseup="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 0 #b71c1c';">Confirm</button>
        </div>
    </div>
</div>

<script>
    function showCustomAlert(title, message, type = 'info') {
        const modal = document.getElementById('custom-alert-modal');
        document.getElementById('c-alert-title').innerText = title;
        document.getElementById('c-alert-msg').innerText = message;

        // Colors
        let color = '#2e7d32'; // green
        let bg = '#e8f5e9';
        let icon = '✓';

        if (type === 'warning') {
            color = '#f57c00'; // orange
            bg = '#fff3e0';
            icon = '!';
        } else if (type === 'error') {
            color = '#d32f2f'; // red
            bg = '#ffebee';
            icon = '✕';
        }

        const iconEl = modal.querySelector('.alert-icon-box');
        iconEl.style.color = color;
        iconEl.style.background = bg;
        iconEl.innerHTML = `<span style="font-size:32px; font-weight:700;">${icon}</span>`;

        document.getElementById('c-alert-title').style.color = color;
        modal.style.display = 'flex';
        toggleNoScroll(true);
    }

    // --- TRANSFER TABLE LOGIC ---
    function openTransferModal(currentCode) {
        if (!window.allTables) {
            App.showToast('Please wait for table data to load.', 'error');
            return;
        }

        document.getElementById('transfer-src-code').innerText = currentCode;
        // Reset Selection
        document.getElementById('transfer-target-select').value = "";
        document.getElementById('transfer-dropdown-text').innerText = "-- Choose Free Table --";
        document.getElementById('transfer-dropdown-options').classList.remove('open');
        document.getElementById('transfer-dropdown-trigger').classList.remove('active');

        // Populate Custom Options
        const optionsContainer = document.getElementById('transfer-dropdown-options');
        optionsContainer.innerHTML = '';

        // Filter FREE tables (excluding current)
        const freeTables = window.allTables.filter(t => t.status === 'FREE' && t.code !== currentCode)
            .sort((a, b) => a.code.localeCompare(b.code, undefined, { numeric: true }));

        if (freeTables.length === 0) {
            optionsContainer.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">No free tables available</div>';
        } else {
            freeTables.forEach(t => {
                const div = document.createElement('div');
                div.className = 'custom-option';
                div.onclick = function () { selectTransferOption(t.code); };

                // Icon logic
                let icon = '🏡';
                if (t.code.toLowerCase().includes('table')) icon = '🪑';
                if (t.code.toLowerCase().includes('garden')) icon = '🌳';

                div.innerHTML = `<span class="custom-option-icon">${icon}</span> ${t.code}`;
                optionsContainer.appendChild(div);
            });
        }

        document.getElementById('transfer-modal').style.display = 'flex';
        toggleNoScroll(true);
    }

    function toggleTransferDropdown() {
        const list = document.getElementById('transfer-dropdown-options');
        const trigger = document.getElementById('transfer-dropdown-trigger');
        list.classList.toggle('open');
        trigger.classList.toggle('active');
    }

    function selectTransferOption(code) {
        document.getElementById('transfer-target-select').value = code;
        document.getElementById('transfer-dropdown-text').innerText = "Selected: " + code;
        document.getElementById('transfer-dropdown-text').style.color = "#333";
        document.getElementById('transfer-dropdown-text').style.fontWeight = "bold";

        toggleTransferDropdown(); // Close
    }

    // Close dropdown when clicking outside
    window.onclick = function (event) {
        // ... existing modal close logic ...
        const pModal = document.getElementById('payment-modal');
        const sModal = document.getElementById('settle-modal');
        const addCustModal = document.getElementById('add-customer-modal');
        const bModal = document.getElementById('billing-modal');        const menuModal = document.getElementById('menu-item-modal');
        const tModal = document.getElementById('transfer-modal'); // New

        if (event.target == pModal) closePaymentModal();
        if (event.target == sModal) closeSettlementModal();
        if (event.target == addCustModal) {
            addCustModal.style.display = 'none';
            toggleNoScroll(false);
        }
        if (event.target == bModal) closeModal();        if (event.target == menuModal) {
            menuModal.style.display = 'none';
            toggleNoScroll(false);
        }
        if (event.target == tModal) closeTransferModal();

        // Close Custom Dropdown if clicked outside
        if (!event.target.closest('.custom-dropdown-container')) {
            const list = document.getElementById('transfer-dropdown-options');
            const trigger = document.getElementById('transfer-dropdown-trigger');
            if (list && list.classList.contains('open')) {
                list.classList.remove('open');
                trigger.classList.remove('active');
            }
        }
    }

    function closeTransferModal() {
        document.getElementById('transfer-modal').style.display = 'none';
        toggleNoScroll(false);
    }

    function submitTransfer() {
        const sourceCode = document.getElementById('transfer-src-code').innerText.trim();
        const targetCode = document.getElementById('transfer-target-select').value.trim();

        if (!targetCode) {
            App.showToast('Please select a target table.', 'error');
            return;
        }

        const btn = event.target;
        const originalText = btn.innerText;
        btn.innerText = 'Moving...';
        btn.disabled = true;

        // Backend expects JSON stream (php://input), so we must send JSON.
        // App.request(url, method, data, options)
        // We pass null for data and provide custom body/headers in options.
        App.request('<?php echo BASE_URL; ?>api/transfer_table.php', 'POST', null, {
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                source_table_code: sourceCode,
                target_table_code: targetCode,
                csrf_token: csrfToken
            })
        }).then(res => {
            btn.innerText = originalText;
            btn.disabled = false;

            if (res.success) {
                App.showToast('Order Transferred Successfully');
                closeTransferModal();
                closeModal(); // Close billing modal

                // Immediate UI Update
                refresh();
            } else {
                App.showToast(res.error || 'Transfer Failed', 'error');
            }
        }).catch(err => {
            console.error(err);
            btn.innerText = originalText;
            btn.disabled = false;
            App.showToast('Network Error', 'error');
        });
    }

    function closeAlertModal() {
        document.getElementById('custom-alert-modal').style.display = 'none';
        toggleNoScroll(false);
    }
</script>

<!-- Custom Alert Modal -->
<div id="custom-alert-modal" class="modal"
    style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:hidden; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); align-items:center; justify-content:center;">
    <div class="confirm-content"
        style="background:#fff; padding:35px 30px; border-radius:20px; width:90%; max-width:350px; text-align:center; box-shadow:0 25px 60px rgba(0,0,0,0.4); animation: popIn 0.2s ease-out;">

        <!-- Animated Icon -->
        <div class="alert-icon-box"
            style="width:70px; height:70px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto; box-shadow:inset 0 4px 6px rgba(0,0,0,0.05);">
            <!-- Icon Injected JS -->
        </div>

        <h3 id="c-alert-title" style="margin:0 0 12px 0; font-size:1.4rem; font-weight:800;">Alert</h3>
        <p id="c-alert-msg" style="color:#666; margin:0 0 30px 0; line-height:1.6; font-size:1rem;">Message</p>

        <button onclick="closeAlertModal()" style="
                width:100%;
                padding:12px;
                background:#333;
                border:none;
                color:white;
                border-radius:12px;
                font-weight:700;
                cursor:pointer;
                box-shadow:0 4px 15px rgba(0,0,0,0.2);
                transition:all 0.1s;
                font-size:1rem;
            " onmousedown="this.style.transform='scale(0.98)'" onmouseup="this.style.transform='scale(1)'">OK</button>
    </div>
</div>

<style>
    /* New Table Styling */
    .pay-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .pay-tag.cash {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .pay-tag.fonepay {
        background: #f3e5f5;
        color: #7b1fa2;
        border: 1px solid #e1bee7;
    }

    .pay-tag img {
        height: 14px;
        width: auto;
    }

    /* Split Payment Badges */
    .split-container {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .split-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        /* Ensure clean font if available */
    }

    .split-pill.cash {
        background: #e8f5e9;
        border: 1px solid #c8e6c9;
        color: #1b5e20;
    }

    .split-pill.online {
        background: #f3e5f5;
        border: 1px solid #e1bee7;
        color: #4a148c;
    }

    .split-pill .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: 700;
        opacity: 0.8;
        letter-spacing: 0.5px;
    }

    .split-pill .val {
        font-size: 0.9rem;
        font-weight: 800;
    }

    /* Sales Stats Grid */
    .sales-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
        width: 100%;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.2s;
    }

    /* Improved Badge Styling */
    .billing-badges {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 5px;
    }

    .badge-success {
        background: #e8f5e9;
        color: #1b5e20;
        border: 1px solid #c8e6c9;
        padding: 4px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.8rem;
    }

    /* Mobile Transaction Layout Fixes - COMPACT GRID CARD */
    @media (max-width: 768px) {
        .billing-header {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .billing-title {
            font-size: 1.5rem !important;
            margin-bottom: 5px;
        }

        .billing-badges {
            margin-bottom: 15px;
        }

        /* Generic Dashboard Tables (Credit Audit, Menu, etc.) */
        .tab-content .pos-table,
        .tab-content .pos-table tbody {
            display: block;
            width: 100%;
        }

        .tab-content .pos-table thead {
            display: none;
        }

        .tab-content .pos-table tr {
            display: flex !important;
            flex-direction: column;
            gap: 2px;
            padding: 15px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .tab-content .pos-table td {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border: none !important;
            padding: 10px 0 !important;
            width: 100% !important;
            text-align: right !important;
            border-bottom: 1px solid #f9f9f9 !important;
        }

        .tab-content .pos-table td:last-child {
            border-bottom: none !important;
        }

        .tab-content .pos-table td::before {
            content: attr(data-label);
            font-weight: 800;
            color: #999;
            /* Professional Muted Color */
            font-size: 0.7rem;
            text-transform: uppercase;
            margin-right: 15px;
            flex-shrink: 0;
            text-align: left;
            letter-spacing: 0.5px;
        }

        .item-history-list {
            display: none !important;
        }

        /* 1. Reset Table to Block for Total Sales (Special Layout) */
        #tab-daily-sales .pos-table,
        #tab-daily-sales .pos-table tbody {
            display: block;
            width: 100%;
        }

        #tab-daily-sales .pos-table thead {
            display: none;
        }

        /* 2. Transaction Card Config - Realistic & Spaced */
        #tab-daily-sales .pos-table tr {
            display: grid !important;
            grid-template-columns: 1.2fr 1fr;
            grid-template-rows: auto auto auto;
            grid-template-areas:
                "time amount"
                "cottage amount"
                "method method";
            gap: 12px 15px;
            margin-bottom: 25px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
            align-items: center;
        }

        /* 3. Cell Styling for Total Sales */

        /* TIME: Top Left */
        #tab-daily-sales .pos-table td:nth-child(1) {
            grid-area: time;
            font-size: 0.9rem;
            font-weight: 700;
            color: #3e2723;
            border: none;
            padding: 0 !important;
            text-align: left;
        }

        /* COTTAGE: Top Right */
        #tab-daily-sales .pos-table td:nth-child(2) {
            grid-area: cottage;
            border: none;
            padding: 0 !important;
            text-align: right;
        }

        /* AMOUNT BOX: Top Right Area - Bold & Large */
        #tab-daily-sales .pos-table td:nth-child(4) {
            grid-area: amount;
            background: #fdfaf5;
            border: 1px solid #f3eee8;
            border-radius: 14px;
            padding: 15px 20px !important;
            text-align: right;
            font-size: 1.3rem;
            /* Larger font */
            font-weight: 800;
            color: #3e2723;
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            min-width: 130px;
            align-self: center;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
            margin: 0;
        }

        /* METHOD: Bottom Row */
        #tab-daily-sales .pos-table td:nth-child(3) {
            grid-area: method;
            border-top: 1px solid #f5f5f5 !important;
            padding: 12px 0 0 0 !important;
            margin-top: 8px;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100% !important;
        }

        #tab-daily-sales .pos-table td:nth-child(3) .method-label {
            display: inline-block !important;
            font-weight: 700;
            color: #555;
            margin-right: 8px;
        }

        /* Hide Labels */
        #tab-daily-sales .pos-table td::before {
            display: none !important;
        }

        /* Improve badge scaling */
        .split-pill {
            padding: 5px 10px !important;
            font-size: 0.8rem !important;
            border-radius: 8px !important;
        }

        .split-pill .val {
            font-size: 0.95rem !important;
        }
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .stat-card.total {
        background: linear-gradient(135deg, #263238, #37474f);
        color: white;
        border: none;
    }

    .stat-card.total .stat-label {
        color: rgba(255, 255, 255, 0.7);
    }

    .stat-card.total .stat-value {
        color: white;
    }

    .stat-card.total .stat-icon {
        background: rgba(255, 255, 255, 0.1);
    }

    .stat-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #888;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: #222;
    }

    .stat-value.cash-text {
        color: #2e7d32;
    }

    .stat-value.online-text {
        color: #7b1fa2;
    }

    @media (max-width: 768px) {
        .sales-stats-grid {
            grid-template-columns: 1fr;
        }

        /* Credit Audit stat cards: 2x2 square grid on mobile (overrides inline repeat(4,1fr)) */
        .profile-stats-grid {
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
        }

        .profile-stats-grid .stat-card {
            aspect-ratio: 1 / 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px !important;
        }
    }

    @keyframes popIn {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }

        40% {
            transform: scale(1.05);
            opacity: 1;
        }

        100% {
            transform: scale(1);
        }
    }

    /* Payment Options New Design */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        width: 100%;
    }

    .pay-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    }

    .pay-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
        border-color: #bbb;
    }

    .pay-card:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .pay-icon {
        font-size: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        width: 100%;
    }

    .pay-icon img {
        height: 40px;
        /* Controlled Fonepay logo size */
        width: auto;
        object-fit: contain;
    }

    .pay-label {
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        color: #555;
        letter-spacing: 0.5px;
    }

    /* Specific Button Accents */
    .pay-card.btn-cash:hover {
        border-color: #2e7d32;
        background: #f1f8e9;
    }

    .pay-card.btn-cash:hover .pay-label {
        color: #2e7d32;
    }

    .pay-card.btn-fonepay:hover {
        border-color: #d32f2f;
        /* Fonepay Red */
        background: #fff5f5;
    }

    .pay-card.btn-fonepay:hover .pay-label {
        color: #d32f2f;
    }

    .pay-card.btn-split:hover {
        border-color: #546e7a;
        background: #eceff1;
    }

    .pay-card.btn-split:hover .pay-label {
        color: #455a64;
    }

    .pay-card.btn-credit-new:hover {
        border-color: #f57c00;
        background: #fff3e0;
    }

    .pay-card.btn-credit-new:hover .pay-label {
        color: #e65100;
    }

    .btn-cancel-payment {
        width: 100%;
        padding: 12px;
        background: #f5f5f5;
        border: none;
        border-radius: 8px;
        color: #777;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-cancel-payment:hover {
        background: #e0e0e0;
        color: #333;
    }

    /* --- PROFESSIONAL TRANSFER MODAL CSS (REDESIGNED) --- */
    .transfer-modal-backdrop {
        position: fixed;
        z-index: 9999;
        /* Highest priority */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.65);
        /* Darker, modern slate overlay */
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        /* Center Vertically */
        justify-content: center;
        /* Center Horizontally */
        opacity: 0;
        animation: fadeInBackdrop 0.2s forwards;
    }

    @keyframes fadeInBackdrop {
        to {
            opacity: 1;
        }
    }

    .transfer-card {
        background: #ffffff;
        width: 90%;
        max-width: 420px;
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        overflow: visible;
        /* Allow dropdowns to slightly overflow if needed (though native selects handle this) */
        transform: scale(0.95);
        opacity: 0;
        animation: popInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        display: flex;
        flex-direction: column;
    }

    @keyframes popInModal {
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Modern Header */
    .transfer-header {
        padding: 24px 24px 0;
        text-align: center;
    }

    .transfer-icon-circle {
        width: 56px;
        height: 56px;
        background: #eff6ff;
        color: #3b82f6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
    }

    .transfer-icon-circle svg {
        width: 28px;
        height: 28px;
        stroke-width: 2;
    }

    .transfer-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }

    .transfer-header p {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 0.95rem;
    }

    /* Body */
    .transfer-body {
        padding: 24px;
    }

    .transfer-field {
        position: relative;
    }

    .transfer-field label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    /* --- CUSTOM DROPDOWN STYLING --- */
    .custom-dropdown-container {
        position: relative;
        width: 100%;
        font-family: inherit;
    }

    .custom-dropdown-trigger {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 1rem;
        color: #64748b;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s;
        font-weight: 500;
    }

    .custom-dropdown-trigger:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
        color: #334155;
    }

    .custom-dropdown-trigger.active {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        color: #1e293b;
    }

    .custom-dropdown-options {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border: 1px solid #f1f5f9;
        z-index: 10;
        max-height: 240px;
        overflow-y: auto;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        /* Scrollbar Styling */
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .custom-dropdown-options.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .custom-dropdown-options::-webkit-scrollbar {
        width: 6px;
    }

    .custom-dropdown-options::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-dropdown-options::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }

    .custom-option {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.15s;
        color: #334155;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #f8fafc;
    }

    .custom-option:last-child {
        border-bottom: none;
    }

    .custom-option:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    .custom-option.selected {
        background-color: #eff6ff;
        color: #2563eb;
        font-weight: 600;
    }

    .custom-option-icon {
        font-size: 1.1rem;
        opacity: 0.7;
    }

    /* Footer Section */
    .transfer-footer {
        padding: 0 24px 24px;
        display: flex;
        gap: 12px;
        flex-direction: row;
        /* Always side-by-side */
    }

    .btn-transfer-cancel {
        flex: 1;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.95rem;
    }

    .btn-transfer-cancel:hover {
        background: #f8fafc;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    .btn-transfer-confirm {
        flex: 1;
        padding: 12px 20px;
        border-radius: 8px;
        border: none;
        background: #2563eb;
        /* Strong Blue */
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        font-size: 0.95rem;
    }

    .btn-transfer-confirm:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
    }

    .btn-transfer-confirm:active {
        transform: translateY(0);
    }

    /* Mobile Optimization: Keep it centered but slightly higher */
    @media (max-width: 640px) {
        .transfer-modal-backdrop {
            align-items: flex-start;
            padding-top: 20vh;
            /* Push down 20% from top */
        }

        .transfer-card {
            width: 92%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            /* Stronger shadow on mobile */
        }
    }

    /* Customer Select Trigger (sits inline in the credit panel) */
    .cust-combo-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 11px 14px;
        background: #fff;
        border: 1.5px solid #d9cfc4;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.95rem;
        color: #3e2723;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .cust-combo-trigger:hover {
        border-color: #8d6e63;
    }

    .cust-combo-trigger-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cust-combo-trigger-text.placeholder {
        color: #999;
    }

    .cust-combo-arrow {
        margin-left: 10px;
        color: #8d6e63;
        flex-shrink: 0;
    }

    /* Customer Picker Modal (separate overlay, not clipped by the credit panel) */
    .cust-picker-backdrop {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.45);
        z-index: 9600;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(2px);
    }

    .cust-picker-backdrop.hidden {
        display: none;
    }

    .cust-picker-card {
        background: #fff;
        width: 92%;
        max-width: 460px;
        max-height: 80vh;
        border-radius: 14px;
        box-shadow: 0 24px 50px -10px rgba(0, 0, 0, 0.35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .cust-picker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        font-weight: 700;
        font-size: 1.05rem;
        color: #3e2723;
        background: linear-gradient(135deg, #fff7ed 0%, #fef0e0 100%);
        border-bottom: 1px solid #f0e0c9;
        flex-shrink: 0;
    }

    .cust-picker-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cust-picker-title-icon {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        border-radius: 9px;
        background: linear-gradient(135deg, #f5a623 0%, #e8743b 100%);
        box-shadow: 0 3px 8px -2px rgba(232, 116, 59, 0.5);
    }

    .cust-picker-close {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        cursor: pointer;
        color: #8d6e63;
        font-size: 0.9rem;
        transition: background 0.15s, color 0.15s;
    }

    .cust-picker-close:hover {
        background: #efe6da;
        color: #d32f2f;
    }

    .cust-combo-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 16px 18px;
        padding: 6px 14px 6px 8px;
        border: 1.5px solid #e3d9cb;
        border-radius: 12px;
        background: #fff;
        flex-shrink: 0;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .cust-combo-input-wrap:focus-within {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .cust-combo-icon-badge {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 9px;
        color: #fff;
        background: linear-gradient(135deg, #38bdf8 0%, #2563eb 100%);
        box-shadow: 0 3px 8px -2px rgba(37, 99, 235, 0.45);
    }

    .cust-combo-input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        padding: 10px 0;
        font-size: 0.95rem;
        color: #3e2723;
    }

    .cust-combo-clear {
        cursor: pointer;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #f1ede7;
        color: #999;
        font-size: 0.75rem;
        flex-shrink: 0;
        transition: background 0.15s, color 0.15s;
    }

    .cust-combo-clear:hover {
        background: #fde2e2;
        color: #d32f2f;
    }

    .cust-combo-list {
        flex: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    .cust-combo-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f3ede4;
        font-size: 0.92rem;
    }

    .cust-combo-item:last-child {
        border-bottom: none;
    }

    .cust-combo-item:hover {
        background: #fbeee0;
    }

    .cust-combo-item.active {
        background: #f0ad7a33;
        box-shadow: inset 3px 0 0 #8d6e63;
    }

    .cust-combo-item .cust-name {
        font-weight: 600;
        color: #3e2723;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cust-combo-item .cust-phone {
        font-size: 0.8rem;
        color: #8d6e63;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .cust-combo-empty {
        padding: 16px;
        text-align: center;
        color: #999;
        font-size: 0.88rem;
    }

    .cust-picker-footer {
        display: flex;
        gap: 10px;
        padding: 14px 18px;
        border-top: 1px solid #f0e8dc;
        flex-shrink: 0;
    }

    .cust-picker-btn {
        flex: 1;
        padding: 11px 16px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.92rem;
        cursor: pointer;
    }

    .cust-picker-btn-cancel {
        background: #f1ede7;
        color: #5d4037;
    }

    .cust-picker-btn-cancel:hover {
        background: #e6ded4;
    }

    .cust-picker-btn-confirm {
        background: #8d6e63;
        color: #fff;
    }

    .cust-picker-btn-confirm:hover {
        background: #6d4c41;
    }

    .cust-picker-btn-confirm:disabled {
        background: #d9cfc4;
        cursor: not-allowed;
    }

    /* PC / wider screens: roomier picker */
    @media (min-width: 768px) {
        .cust-picker-card {
            max-width: 520px;
        }

        .cust-combo-list {
            min-height: 320px;
        }
    }
</style>

<!-- CHANGE PIN MODAL -->
<div id="change-pin-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.25); z-index:9100; align-items:center; justify-content:center; backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); animation: fadeIn 0.2s ease-out;">
    <div class="card change-pin-card" style="width:90%; max-width:320px; border-radius:20px; padding:24px 20px; text-align:center; background:rgba(255,255,255,0.96); box-shadow:0 24px 50px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.5) inset; transform:scale(0.95); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1) forwards;">

        <div style="width:44px; height:44px; background:linear-gradient(135deg, var(--primary-accent) 0%, #5d4037 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; box-shadow:0 5px 14px rgba(62, 39, 35, 0.25);">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>

        <h3 style="margin:0 0 4px; font-size:1.2rem; font-weight:800; color:#1a1a1a;">Change PIN</h3>
        <p style="color:#888; font-size:0.82rem; margin:0 0 18px; font-weight:500;">Set a new 4-digit PIN for Menu Management.</p>

        <div style="text-align:left; margin-bottom:12px;">
            <label style="display:block; font-size:0.8rem; font-weight:700; color:#444; margin-bottom:5px; margin-left:4px;">New PIN</label>
            <div style="position:relative;">
                <input type="password" id="new-menu-pin" maxlength="4" inputmode="numeric" placeholder="••••" style="width:100%; padding:11px 44px 11px 14px; border:2px solid #e0e0e0; border-radius:11px; font-size:1.4rem; text-align:center; letter-spacing:12px; font-weight:bold; transition:all 0.2s; outline:none; background:#f9f9f9; color:#3e2723;" onfocus="this.style.borderColor='var(--primary-accent)'; this.style.background='#fff';" onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                <button type="button" class="pin-eye-toggle" data-target="new-menu-pin" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:8px; color:#9a948c; line-height:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <div style="text-align:left; margin-bottom:14px;">
            <label style="display:block; font-size:0.8rem; font-weight:700; color:#444; margin-bottom:5px; margin-left:4px;">Confirm New PIN</label>
            <div style="position:relative;">
                <input type="password" id="confirm-menu-pin" maxlength="4" inputmode="numeric" placeholder="••••" style="width:100%; padding:11px 44px 11px 14px; border:2px solid #e0e0e0; border-radius:11px; font-size:1.4rem; text-align:center; letter-spacing:12px; font-weight:bold; transition:all 0.2s; outline:none; background:#f9f9f9; color:#3e2723;" onfocus="this.style.borderColor='var(--primary-accent)'; this.style.background='#fff';" onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                <button type="button" class="pin-eye-toggle" data-target="confirm-menu-pin" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:8px; color:#9a948c; line-height:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <p id="change-pin-error" style="color:#ff3b30; font-size:0.82rem; display:none; margin:0 0 14px; font-weight:600; padding:7px; background:rgba(255, 59, 48, 0.1); border-radius:8px;">PIN must be 4 digits.</p>

        <div style="display:flex; gap:10px;">
            <button onclick="document.getElementById('change-pin-modal').style.display='none'" class="btn" style="flex:1; background:#f0f0f0; color:#555; border-radius:11px; padding:11px; font-weight:700; border:none; transition:0.2s;" onmouseover="this.style.background='#e0e0e0'" onmouseout="this.style.background='#f0f0f0'">Cancel</button>
            <button onclick="saveNewMenuPin()" class="btn" style="flex:1; background:linear-gradient(135deg, var(--primary-accent) 0%, #5d4037 100%); color:white; border-radius:11px; padding:11px; font-weight:700; border:none; box-shadow:0 6px 15px rgba(62, 39, 35, 0.25); transition:0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">Update PIN</button>
        </div>
    </div>
</div>

<script>
function openChangePinModal() {
    document.getElementById('new-menu-pin').value = '';
    document.getElementById('confirm-menu-pin').value = '';
    document.getElementById('change-pin-error').style.display = 'none';

    // Reset PIN fields to hidden each time the modal opens.
    ['new-menu-pin', 'confirm-menu-pin'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.type = 'password';
    });

    document.getElementById('change-pin-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('new-menu-pin').focus(), 100);
}

// Eye toggle for the Change PIN inputs (bound once).
document.querySelectorAll('#change-pin-modal .pin-eye-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.getAttribute('data-target'));
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('svg').innerHTML = show
            ? '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.12 9.12 0 0 0 5.39-1.61"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="2" y1="2" x2="22" y2="22"/>'
            : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
    });
});

function saveNewMenuPin() {
    const newPin = document.getElementById('new-menu-pin').value.trim();
    const confirmPin = document.getElementById('confirm-menu-pin').value.trim();
    const errorEl = document.getElementById('change-pin-error');
    
    if (newPin.length !== 4 || !/^\d+$/.test(newPin)) {
        errorEl.textContent = 'PIN must be exactly 4 digits.';
        errorEl.style.display = 'block';
        return;
    }

    if (newPin !== confirmPin) {
        errorEl.textContent = 'PINs do not match. Try again.';
        errorEl.style.display = 'block';
        return;
    }
    
    errorEl.style.display = 'none';
    
    const formData = new FormData();
    formData.append('new_pin', newPin);
    formData.append('csrf_token', csrfToken);

    App.request('<?php echo BASE_URL; ?>api/user_action.php?action=change_menu_pin', 'POST', formData)
        .then(res => {
            if(res.success) {
                App.showToast('PIN updated successfully! Reloading...');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                App.showToast(res.error || 'Failed to update PIN', 'error');
            }
        })
        .catch(err => {
            App.showToast('Error updating PIN', 'error');
        });
}
</script>


<?php require_once '../includes/footer.php'; ?>
