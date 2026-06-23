<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_role('WAITER');
require_waiter_portal_enabled();
require_once '../includes/header.php';
?>

<!-- Floor Plan CSS (admin_floorplan.css already loaded by header.php) -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/mobile_floorplan.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/mobile_floorplan.css'); ?>">

<style>
    /* DEEP ALIGNMENT WITH ADMIN */
    body {
        background-color: #fbf7f0 !important;
        overflow-x: hidden;
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
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        position: relative;
    }

    .admin-page-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #3e2723;
        margin: 0;
    }

    /* Aggressive Layout Reset */
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

    /* Fix Table 13 area touching edge */
    .yellow-sub-col-right {
        margin-right: 15px !important;
        width: 45% !important;
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

    @media screen and (max-width: 768px) {

        /* RESTORE TOUCH SCROLL: Remove restriction for Pure Scroll behavior */
        html,
        body {
            overflow-x: hidden !important;
            height: auto !important;
            min-height: 100% !important;
            width: 100% !important;
            position: relative !important;
            overscroll-behavior-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            touch-action: manipulation !important;
        }

        .admin-shell {
            height: auto !important;
            min-height: 100vh !important;
            overflow: visible !important;
            display: block !important;
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

        @media screen and (max-width: 450px) {
            .floor-layout {
                transform: scale(0.48) !important;
            }
        }

        /* MODAL CONTAINER - RESET */
        #billing-modal .modal-content {
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

        #modal-container {
            display: block !important;
            /* Allow normal flow */
            height: 100% !important;
            overflow-y: auto !important;
            /* Main Scroll Container */
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
        .billing-header button {
            position: fixed !important;
            /* Pinned to screen */
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
            z-index: 9999 !important;
            /* Super High Z to survive scroll */
            width: 35px !important;
            height: 35px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 10px rgba(211, 47, 47, 0.4) !important;
        }

        /* Ensure text doesn't hit the button */
        .billing-header>div:first-child {
            flex: 1 !important;
            padding-right: 50px !important;
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
        .pos-table {
            display: block !important;
            width: 100% !important;
            border-collapse: separate !important;
        }

        .pos-table thead {
            display: none !important;
        }

        .pos-table tbody {
            display: block !important;
            width: 100% !important;
        }

        /* ROW = CARD */
        .pos-table tr {
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
        .pos-table tr[style*="background:#f9f9f9"] {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 10px 0 5px 0 !important;
            min-height: 0 !important;
        }

        .pos-table tr[style*="background:#f9f9f9"] td {
            color: #95a5a6 !important;
            font-size: 0.75rem !important;
            font-weight: 800 !important;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 0 !important;
        }

        /* CELL 1: ITEM NAME */
        .pos-table td:nth-child(1) {
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
        .pos-table td:nth-child(2) {
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
        .pos-table td:nth-child(3) {
            display: block !important;
            position: absolute !important;
            bottom: 15px !important;
            left: 15px !important;
            font-size: 1rem !important;
            color: #7f8c8d !important;
            font-weight: 600 !important;
        }

        .pos-table td:nth-child(3)::before {
            content: "QTY: ";
            font-size: 0.8rem;
            color: #bdc3c7;
        }

        /* CELL 4: PRICE (HIDDEN) */
        .pos-table td:nth-child(4) {
            display: none !important;
        }

        /* CELL 5: TOTAL PRICE (Bottom Right) */
        .pos-table td:nth-child(5) {
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

<div class="admin-shell">
    <div class="admin-page-header">
        <div>
            <h2 class="admin-page-title">Dashboard</h2>
            <div id="waiter-clock" class="admin-clock">--:--</div>
        </div>
    </div>
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
</div>
<script>    let allTables = [];
    let allQueue = [];
    let statusController = null; // For cancelling pending requests


    function updateClock() {
        const now = new Date();

        const options = {
            weekday: 'short', month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true
        }

            ;
        document.getElementById('waiter-clock').textContent = now.toLocaleDateString('en-US', options);
    }

    setInterval(updateClock, 1000);
    updateClock();

    function renderTables() {
        allTables.forEach(c => {
            const spotId = 'spot-' + c.code;
            const spotEl = document.getElementById(spotId);
            if (!spotEl) return;

            let isFree = c.status === 'FREE';
            let statusText = isFree ? 'Free' : 'Occupied';

            // Exact status class logic from Admin
            spotEl.classList.remove('status-free', 'status-occupied', 'status-paid');
            let cardClass = isFree ? 'status-free' : 'status-occupied';

            if (parseInt(c.is_paid) === 1) {
                statusText = 'Paid';
                cardClass = 'status-paid';
            }

            spotEl.classList.add(cardClass);

            const total = parseFloat(c.current_total || 0);
            let totalHtml = '';

            if (!isFree && parseInt(c.is_paid) === 1) {
                statusText = 'Fully Paid';
            }

            // --- WAITER INDICATORS LOGIC ---
            let distLabelsHtml = '';

            const items = allQueue.filter(q => q.table_code === c.code);

            let countUnserved = 0, countServed = 0, countPaid = 0;
            let hasActivity = false;

            if (items.length > 0) {
                items.forEach(item => {
                    const qty = parseInt(item.qty || 1);
                    const itemPaid = (parseInt(item.is_paid) === 1) || (parseInt(item.session_is_paid) === 1);

                    if (itemPaid) {
                        countPaid += qty;
                    }

                    if (item.kitchen_status === 'SERVED') {
                        countServed += qty;
                    } else if (!itemPaid) {
                        countUnserved += qty;
                    }
                });

                hasActivity = true;
            }

            // Force layout update if activity exists
            if (hasActivity && isFree) {
                isFree = false;
                spotEl.classList.remove('status-free');
                spotEl.classList.add('status-occupied');
            }

            // If Session is Fully Paid formally
            if (parseInt(c.is_paid) === 1) {
                spotEl.classList.remove('status-occupied');
                spotEl.classList.add('status-paid');
                statusText = 'Fully Paid';
                // Clear counts if fully paid? User might still want to see "3 Served"
            }

            // --- GENERATE BADGES ---

            // 1. SERVED (Blue Badge - Top Right)
            // Show if count > 0 AND it's not *just* a fully settled history (optional)
            if (countServed > 0) {
                distLabelsHtml += `<div class="d-label d-top">${countServed} Served</div>`;
            }

            // 2. PAID (Green Badge - Top Left)
            // Only show distinct paid count if not "Fully Paid" session, 
            // OR if user wants to see specifically how many items are paid
            if (countPaid > 0 && parseInt(c.is_paid) !== 1) {
                distLabelsHtml += `<div class="d-label d-left">${countPaid} Paid</div>`;
            }

            // 3. BOTTOM STATUS TEXT
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
            const spotContentEl = spotEl.querySelector('.spot-content');
            const innerHTML = `
                <div class="zone-icon">${icon}</div>
                <div class="mini-code">${c.code}</div>
                <div class="mini-status">${statusText}</div>
                ${totalHtml}
                ${distLabelsHtml}
            `;

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

            // Simple click handler for the entire card (Bind once)
            if (!spotEl.dataset.clickBound) {
                const linkUrl = 'order.php?table=' + encodeURIComponent(c.code);
                spotEl.addEventListener('click', (e) => {
                    // Small delay to allow click sound to trigger clearly
                    setTimeout(() => {
                        window.location.href = linkUrl;
                    }, 50);
                });
                spotEl.dataset.clickBound = "true";
            }
        });
    }



    function refresh() {
        if (statusController) statusController.abort();
        statusController = new AbortController();

        App.request('<?php echo BASE_URL; ?>api/status.php?type=waiter', 'GET', null, { signal: statusController.signal })
            .then(data => {
                if (!data || data.error) return;
                allTables = data.tables || [];
                allQueue = data.queue || [];
                renderTables();
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error(err);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        refresh();
        App.startLiveSync('<?php echo BASE_URL; ?>api/realtime_ping.php', refresh);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh();
        });

        // GLOBAL FALLBACK HANDLER FOR STATIC HTML
        // This ensures that even if 'refresh()' fails or hasn't run yet, 
        // the static HTML elements (which have IDs) are click-able.
        document.body.addEventListener('click', function (e) {
            // Find if we clicked on a spot container or inside it
            let targetParams = null;
            let el = e.target;

            // Traverse up to find ID starting with spot-
            while (el && el !== document.body) {
                if (el.id && el.id.startsWith('spot-')) {
                    // Found it!
                    let code = el.id.replace('spot-', '');

                    // If we are NOT clicking an anchor (which handles itself), redirect manually
                    // But if we clicked inside an anchor, let it handle it.
                    // However, if the anchor is 'spot-link', it has href.
                    // If the JS didn't run, the anchor DOES NOT EXIST yet. The static HTML has no anchor.
                    // So we simply redirect.
                    // Check if there is an anchor logic working?
                    if (!el.querySelector('a.spot-link')) {
                        // Static mode or JS failed mode -> Force Redirect
                        window.location.href = 'order.php?table=' + encodeURIComponent(code);
                    }

                    break;
                }

                el = el.parentElement;
            }
        });
    });
</script><?php require_once '../includes/footer.php'; ?>