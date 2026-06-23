<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/db.php';
require_once '../includes/menu_pin.php';

require_role('ADMIN');
menu_pin_require_unlocked_or_redirect(BASE_URL . 'admin/index.php');

require_once '../includes/header.php';
?>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="">☰</i>
</button>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">Admin Panel</div>
    </div>

    <div class="sidebar-menu">
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-tables"
            onclick="localStorage.setItem('admin_active_tab','tables')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></span>
            <span>Tables & Billing</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-kitchen"
            onclick="localStorage.setItem('admin_active_tab','kitchen')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l1.5 1.5L3 5"></path><path d="M7 2l1.5 1.5L7 5"></path><path d="M11 2l1.5 1.5L11 5"></path><path d="M5 8h14a2 2 0 0 1 2 2v1a7 7 0 0 1-7 7h-4a7 7 0 0 1-7-7v-1a2 2 0 0 1 2-2z"></path><line x1="2" y1="21" x2="22" y2="21"></line></svg></span>
            <span>Kitchen Monitor</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-menu"
            onclick="localStorage.setItem('admin_active_tab','menu')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></span>
            <span>Menu</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/stockmanagement.php" class="nav-item active" id="nav-stock">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></span>
            <span>Stock Management</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-daily-sales"
            onclick="localStorage.setItem('admin_active_tab','daily-sales')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg></span>
            <span>Total Sales</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-sales"
            onclick="localStorage.setItem('admin_active_tab','sales')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
            <span>Credit Audit</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-users"
            onclick="localStorage.setItem('admin_active_tab','users')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
            <span>Users</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item" id="nav-manage-waiter"
            onclick="localStorage.setItem('admin_active_tab','manage-waiter')">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 19h20"></path><path d="M4 19c0-6 3.5-10 8-10s8 4 8 10"></path><path d="M12 9V5"></path><path d="M9.5 5h5"></path></svg></span>
            <span>Manage Waiter</span>
            <?php if ($manage_waiter_promo_seconds_left > 0): ?>
                <span id="nav-manage-waiter-countdown" class="nav-manage-waiter-countdown" data-seconds-left="<?php echo (int) $manage_waiter_promo_seconds_left; ?>">--h --m --s</span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>logout.php?portal=ADMIN" class="nav-item nav-item-logout" id="nav-logout"
            onclick="return confirmLogout(event)">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
            <span>Logout</span>
        </a>
    </div>

    <div style="padding: 24px; font-size: 0.8rem; color: #aaa; text-align: center;">
        &copy; Chiya Sansar
    </div>
</aside>

<div class="admin-shell" id="stock-pin-gated">
    <div class="admin-page-header flex flex-between stock-page-header">
        <div>
            <h2 class="admin-page-title">Stock Management</h2>
            <div id="clock" class="admin-clock">--:--</div>
        </div>
        <button type="button" class="btn-shine" onclick="openAddStockCount()">
            <span>+</span> Add Stock Count
        </button>
    </div>

    <div class="stock-view-tabs">
        <button type="button" class="stock-view-tab active" data-area="counter" onclick="switchStockView('counter')">
            <span class="stock-view-tab-icon">🛒</span> Tracking stock automatic
        </button>
        <button type="button" class="stock-view-tab" data-area="kitchen" onclick="switchStockView('kitchen')">
            <span class="stock-view-tab-icon">🍳</span> Kitchen Stock
        </button>
    </div>

    <div id="stock-main-area" class="stock-main-area">
        <p class="stock-main-hint" id="stock-main-hint">Click <strong>Add Stock Count</strong> to add items for tracking.</p>

        <div class="card menu-card stock-list-card">
            <div class="stock-list-search-wrap">
                <input type="search" id="stock-list-search" class="stock-list-search"
                    placeholder="Search items (e.g. MoMo, Tea, Rice)…"
                    oninput="filterStockList()" autocomplete="off" aria-label="Search stock items">
            </div>

            <section id="stock-view-counter" class="stock-view-panel active">
                <div class="menu-list-container">
                    <table class="styled-table stock-table">
                        <colgroup>
                            <col class="col-idx">
                            <col class="col-name">
                            <col class="col-price">
                            <col class="col-date">
                            <col class="col-stock">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="col-idx">#</th>
                                <th class="col-name">Item Name</th>
                                <th class="col-price">Price</th>
                                <th class="col-date">Date Added</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="stock-list-counter"></tbody>
                    </table>
                </div>
            </section>

            <section id="stock-view-kitchen" class="stock-view-panel">
                <div class="menu-list-container">
                    <table class="styled-table stock-table">
                        <colgroup>
                            <col class="col-idx">
                            <col class="col-name">
                            <col class="col-price">
                            <col class="col-date">
                            <col class="col-stock">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="col-idx">#</th>
                                <th class="col-name">Item Name</th>
                                <th class="col-price">Price</th>
                                <th class="col-date">Date Added</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="stock-list-kitchen"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Add Stock Modal -->
<div id="stock-add-modal" class="stock-modal-overlay" style="display:none;" onclick="onStockModalBackdrop(event)">
    <div class="stock-modal-card" role="dialog" aria-labelledby="stock-modal-title">
        <div class="stock-modal-header">
            <div>
                <h3 id="stock-modal-title">Add Stock</h3>
                <p class="stock-modal-sub" id="stock-add-modal-sub">Tap an item to select · tap again to undo</p>
            </div>
            <button type="button" class="stock-modal-close" onclick="closeAddStockCount()" aria-label="Close">&times;</button>
        </div>

        <div class="stock-area-select-wrap">
            <p class="stock-area-select-label">Add to</p>
            <div class="stock-area-chips">
                <button type="button" class="stock-area-chip active" data-area="counter" onclick="selectStockArea('counter')">Tracking stock automatic</button>
                <button type="button" class="stock-area-chip" data-area="kitchen" onclick="selectStockArea('kitchen')">Kitchen Stock</button>
            </div>
        </div>

        <!-- Counter: pick from menu -->
        <div id="stock-add-counter-panel" class="stock-add-mode-panel">
            <div class="stock-modal-search-wrap">
                <input type="search" id="stock-item-search" class="stock-modal-search" placeholder="Search menu items…"
                    autocomplete="off" oninput="filterStockPicker()">
            </div>
            <div class="stock-picker-loading" id="stock-picker-loading" style="display:none;" role="status"
                onclick="retryLoadMenuItemsForStock()">Loading menu items…</div>
            <div class="stock-picker-grid" id="stock-picker-grid"></div>
            <div class="stock-picker-empty" id="stock-picker-empty" style="display:none;">No items match your search.</div>
            <div class="stock-modal-footer" id="stock-add-counter-footer">
                <div class="stock-footer-selected">
                    <div class="stock-footer-row">
                        <div class="stock-footer-detail">
                            <span class="stock-footer-name is-empty" id="stock-footer-name">Select an item from the list</span>
                            <span class="stock-footer-price" id="stock-footer-price"></span>
                        </div>
                        <span class="stock-footer-area-badge counter" id="stock-footer-area">Counter</span>
                    </div>
                </div>
                <button type="button" class="stock-confirm-btn" id="stock-confirm-btn" onclick="confirmAddStock()" disabled>
                    Confirm
                </button>
            </div>
        </div>

        <!-- Kitchen: manual entry only -->
        <div id="stock-add-kitchen-panel" class="stock-add-mode-panel" style="display:none;">
            <div id="kitchen-manual-intro" class="kitchen-manual-intro">
                <p class="kitchen-manual-hint">Kitchen stock items are added manually — ingredients and supplies not sold on the menu.</p>
                <button type="button" class="btn-kitchen-manual-open" onclick="showKitchenManualForm()">
                    <span>+</span> Add Manually
                </button>
            </div>

            <div id="kitchen-manual-form" class="kitchen-manual-form" style="display:none;">
                <div class="kitchen-manual-form-scroll">
                    <button type="button" class="kitchen-manual-back" onclick="hideKitchenManualForm()">← Back</button>

                    <div class="kitchen-field-group">
                        <label class="kitchen-field-label" for="kitchen-manual-name">Item name</label>
                        <input type="text" id="kitchen-manual-name" class="kitchen-field-input" placeholder="e.g. Sugar, Oil, Milk" autocomplete="off">
                    </div>

                    <div class="kitchen-field-group">
                        <label class="kitchen-field-label" for="kitchen-manual-price">Price (Rs.)</label>
                        <input type="number" id="kitchen-manual-price" class="kitchen-field-input" min="0" step="0.01" inputmode="decimal" placeholder="0">
                    </div>

                    <div class="kitchen-field-group">
                        <span class="kitchen-field-label">Unit type</span>
                        <div class="stock-unit-chips kitchen-unit-chips" id="kitchen-unit-chips"></div>
                    </div>

                    <div class="kitchen-field-group kitchen-unit-panels">
                        <div class="kitchen-unit-panel active" data-unit-group="count">
                            <label class="kitchen-field-label kitchen-qty-label" id="kitchen-count-label" for="kitchen-input-count">Total Quantity</label>
                            <input type="number" id="kitchen-input-count" class="kitchen-field-input" min="0" step="1" inputmode="numeric" placeholder="e.g. 10">
                        </div>
                        <div class="kitchen-unit-panel" data-unit-group="kg">
                            <label class="kitchen-field-label kitchen-qty-label" for="kitchen-input-kg">Total Quantity</label>
                            <input type="number" id="kitchen-input-kg" class="kitchen-field-input" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 2.5">
                        </div>
                        <div class="kitchen-unit-panel" data-unit-group="gram">
                            <label class="kitchen-field-label kitchen-qty-label" for="kitchen-input-gram">Total Quantity</label>
                            <input type="number" id="kitchen-input-gram" class="kitchen-field-input" min="0" step="1" inputmode="numeric" placeholder="e.g. 500">
                        </div>
                        <div class="kitchen-unit-panel" data-unit-group="liter">
                            <label class="kitchen-field-label kitchen-qty-label" for="kitchen-input-liter">Total Quantity</label>
                            <input type="number" id="kitchen-input-liter" class="kitchen-field-input" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 1.5">
                        </div>
                    </div>
                </div>

                <div class="kitchen-manual-form-footer">
                    <button type="button" class="stock-confirm-btn kitchen-confirm-btn" id="kitchen-manual-confirm-btn" onclick="confirmKitchenManualAdd()">
                        Add to Kitchen Stock
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Stock Unit Modal -->
<div id="stock-edit-modal" class="stock-modal-overlay" style="display:none;" onclick="onStockEditBackdrop(event)">
    <div class="stock-modal-card stock-edit-modal-card" role="dialog" aria-labelledby="stock-edit-title">
        <div class="stock-modal-header">
            <div>
                <h3 id="stock-edit-title">Stock Setup</h3>
                <p class="stock-modal-sub" id="stock-edit-item-name">—</p>
            </div>
            <button type="button" class="stock-modal-close" onclick="closeStockEditModal()" aria-label="Close">&times;</button>
        </div>

        <div class="stock-edit-body">
            <p class="stock-edit-section-label">Unit type</p>
            <div class="stock-unit-chips" id="stock-unit-chips"></div>

            <div class="stock-unit-panels">
                <div class="stock-unit-panel active" data-unit-group="count">
                    <div id="stock-piece-adjust-panel" class="stock-piece-adjust-panel">
                        <div class="stock-current-qty-block">
                            <p class="stock-edit-section-label">Available stock</p>
                            <div id="stock-current-qty-view" class="stock-current-qty-view">
                                <p class="stock-current-qty-value" id="stock-edit-current-qty">0 pieces</p>
                                <button type="button" class="btn-stock-qty-edit" onclick="startStockQtyEdit()">Edit</button>
                            </div>
                            <div id="stock-current-qty-edit" class="stock-current-qty-edit" style="display:none;">
                                <label class="stock-field-label" for="stock-qty-edit-input" id="stock-qty-edit-label">Total quantity</label>
                                <input type="number" id="stock-qty-edit-input" min="0" step="1" inputmode="numeric" placeholder="e.g. 80">
                                <div class="stock-qty-edit-actions">
                                    <button type="button" class="btn-stock-qty-update" onclick="applyStockQtyUpdate()">Update</button>
                                    <button type="button" class="btn-stock-qty-cancel" onclick="cancelStockQtyEdit()">Cancel</button>
                                </div>
                            </div>
                        </div>
                        <div class="stock-qty-add-fields">
                            <div id="stock-qty-action-view" class="stock-qty-action-view">
                                <button type="button" class="btn-stock-qty-add-start" onclick="startStockQtyAdd()">Add stock</button>
                                <button type="button" class="btn-stock-qty-remove-start" id="btn-stock-kitchen-use" style="display:none;" onclick="startStockQtyRemove()">Use stock</button>
                            </div>
                            <div id="stock-qty-add-form" class="stock-qty-add-form" style="display:none;">
                                <label class="stock-field-label" for="stock-qty-add-input" id="stock-qty-add-label">How much to add?</label>
                                <input type="number" id="stock-qty-add-input" min="0" step="1" inputmode="numeric" placeholder="e.g. 10">
                                <div class="stock-qty-add-actions">
                                    <button type="button" class="btn-stock-qty-add" onclick="applyStockQtyAdd()">Add</button>
                                    <button type="button" class="btn-stock-qty-cancel" onclick="cancelStockQtyAdd()">Cancel</button>
                                </div>
                            </div>
                            <div id="stock-qty-remove-form" class="stock-qty-remove-form" style="display:none;">
                                <label class="stock-field-label" for="stock-qty-remove-input" id="stock-qty-remove-label">How much was used?</label>
                                <input type="number" id="stock-qty-remove-input" min="0" step="1" inputmode="numeric" placeholder="e.g. 2">
                                <div class="stock-qty-remove-actions">
                                    <button type="button" class="btn-stock-qty-remove" onclick="applyStockQtyRemove()">Use</button>
                                    <button type="button" class="btn-stock-qty-cancel" onclick="cancelStockQtyRemove()">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="stock-other-count-panel" class="stock-other-count-panel" style="display:none;">
                        <label for="stock-input-count" id="stock-count-label">Amount</label>
                        <input type="number" id="stock-input-count" min="0" step="1" inputmode="numeric" placeholder="e.g. 20">
                    </div>
                </div>
                <div class="stock-unit-panel" data-unit-group="kg">
                    <label for="stock-input-kg">Weight (kg)</label>
                    <input type="number" id="stock-input-kg" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 2.5">
                    <span class="stock-unit-hint">Stock amount in kilograms</span>
                </div>
                <div class="stock-unit-panel" data-unit-group="gram">
                    <label for="stock-input-gram">Weight (gram)</label>
                    <input type="number" id="stock-input-gram" min="0" step="1" inputmode="numeric" placeholder="e.g. 500">
                    <span class="stock-unit-hint">Stock amount in grams</span>
                </div>
                <div class="stock-unit-panel" data-unit-group="liter">
                    <label for="stock-input-liter">Volume (liter)</label>
                    <input type="number" id="stock-input-liter" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 1.5">
                    <span class="stock-unit-hint">Stock amount in liters</span>
                </div>
            </div>

            <div id="stock-edit-tracking-wrap" class="stock-edit-tracking-wrap" style="display:none;">
                <label class="stock-edit-track-option">
                    <input type="checkbox" id="stock-edit-track-stock">
                    <span>Track automatic stock</span>
                </label>
                <label class="stock-edit-track-option">
                    <input type="checkbox" id="stock-edit-track-sold">
                    <span>After tick, the stock sold will be counted as customer after served</span>
                </label>
            </div>
        </div>

        <div class="stock-modal-footer stock-edit-modal-footer">
            <button type="button" class="stock-confirm-btn" onclick="saveStockEdit()">Save</button>
        </div>
    </div>
</div>

<!-- Stock detail view -->
<div id="stock-view-modal" class="stock-modal-overlay" style="display:none;" onclick="onStockViewBackdrop(event)">
    <div class="stock-modal-card stock-view-modal-card" role="dialog" aria-labelledby="stock-view-title">
        <div class="stock-modal-header">
            <div>
                <h3 id="stock-view-title">Stock details</h3>
                <p class="stock-modal-sub" id="stock-view-item-name">—</p>
            </div>
            <button type="button" class="stock-modal-close" onclick="closeStockViewModal()" aria-label="Close">&times;</button>
        </div>
        <div class="stock-view-body">
            <div class="stock-view-stats" id="stock-view-stats"></div>
            <div class="stock-view-date-box" id="stock-view-date-box" style="display:none;">
                <p class="stock-view-date-title">View by date</p>
                <div class="stock-view-date-picker-row">
                    <input type="date" id="stock-view-date-input" class="stock-view-date-input" max="" onchange="onStockViewDateChange(this.value)">
                </div>
                <p class="stock-view-day-heading" id="stock-view-day-heading">Today</p>
                <div class="stock-view-day-grid" id="stock-view-day-grid">

                    <div class="stock-view-day-stat day-total-added" id="stock-view-day-total-added-wrap" style="display:none;">
                        <span class="stock-view-day-label">Total added</span>
                        <span class="stock-view-day-value" id="stock-view-day-total-added">0</span>
                        <span class="stock-view-day-unit stock-view-day-unit-added">pieces</span>
                    </div>
                    <div class="stock-view-day-stat sold" id="stock-view-day-sold-wrap">
                        <span class="stock-view-day-label" id="stock-view-day-sold-label">Sold</span>
                        <span class="stock-view-day-value" id="stock-view-day-sold">0</span>
                        <span class="stock-view-day-unit stock-view-day-unit-sold">pieces</span>
                    </div>
                    <div class="stock-view-day-stat added" id="stock-view-day-added-wrap">
                        <span class="stock-view-day-label" id="stock-view-day-added-label">Added</span>
                        <span class="stock-view-day-value" id="stock-view-day-added">0</span>
                        <span class="stock-view-day-unit stock-view-day-unit-remaining">pieces</span>
                    </div>
                </div>
                <p class="stock-view-day-formula" id="stock-view-day-formula" style="display:none;"></p>
            </div>
            <p class="stock-view-section-label">History <span class="stock-view-history-date-hint" id="stock-view-history-date-hint"></span></p>
            <p class="stock-view-loading" id="stock-view-loading">Loading…</p>
            <p class="stock-view-empty" id="stock-view-history-empty" style="display:none;">No history yet.</p>
            <div class="stock-view-history-sections" id="stock-view-history-sections" style="display:none;">
                <div class="stock-view-history-block">
                    <p class="stock-view-history-heading sold">Sold</p>
                    <div class="stock-view-history-wrap">
                        <table class="stock-view-history-table" id="stock-view-sold-table" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sold</th>
                                    <th>After</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody id="stock-view-sold-body"></tbody>
                        </table>
                        <p class="stock-view-empty stock-view-section-empty" id="stock-view-sold-empty">No sales yet.</p>
                    </div>
                </div>
                <div class="stock-view-history-block" id="stock-view-used-block" style="display:none;">
                    <p class="stock-view-history-heading used">Used</p>
                    <div class="stock-view-history-wrap">
                        <table class="stock-view-history-table" id="stock-view-used-table" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Used</th>
                                    <th>Left</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody id="stock-view-used-body"></tbody>
                        </table>
                        <p class="stock-view-empty stock-view-section-empty" id="stock-view-used-empty">Nothing used yet.</p>
                    </div>
                </div>
                <div class="stock-view-history-block">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <p class="stock-view-history-heading added" style="margin-bottom: 0;">Added</p>
                        <button type="button" class="stock-view-all-btn" onclick="openStockViewAllAddedModal()" style="background: none; border: none; color: var(--primary-color); font-size: 0.85rem; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 4px;">View All</button>
                    </div>
                    <div class="stock-view-history-wrap">
                        <table class="stock-view-history-table" id="stock-view-added-table" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Change</th>
                                    <th>After</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody id="stock-view-added-body"></tbody>
                        </table>
                        <p class="stock-view-empty stock-view-section-empty" id="stock-view-added-empty">No stock added yet.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="stock-modal-footer stock-view-modal-footer">
            <button type="button" class="stock-confirm-btn" onclick="closeStockViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- View All Added History Modal -->
<div id="stock-view-all-added-modal" class="stock-modal-overlay" style="display:none; z-index: 999999 !important;" onclick="onStockViewAllAddedBackdrop(event)">
    <div class="stock-modal-card" style="width: 900px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; z-index: 999999 !important; overflow: hidden;" role="dialog" aria-modal="true" aria-labelledby="stock-view-all-added-title" onclick="event.stopPropagation()">
        <div class="stock-modal-header">
            <div>
                <h3 id="stock-view-all-added-title">All Added History</h3>
            </div>
            <button type="button" class="stock-modal-close" aria-label="Close" onclick="closeStockViewAllAddedModal()">&times;</button>
        </div>
        <div class="stock-view-body" style="flex: 1; overflow-y: auto; padding-top: 0;">
            <div class="stock-view-history-block" style="margin-top: 10px;">
                <div class="stock-view-history-wrap">
                    <table class="stock-view-history-table" id="stock-view-all-added-table" style="display:table; width: 100%;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Change</th>
                                <th>After</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody id="stock-view-all-added-body"></tbody>
                    </table>
                    <p class="stock-view-empty stock-view-section-empty" id="stock-view-all-added-empty" style="display:none;">No stock added yet.</p>
                </div>
            </div>
        </div>
        <div class="stock-modal-footer">
            <button type="button" class="stock-confirm-btn" onclick="closeStockViewAllAddedModal()">Back</button>
        </div>
    </div>
</div>

<!-- Remove stock item confirmation -->
<div id="stock-confirm-modal" class="stock-modal-overlay stock-confirm-overlay" style="display:none;" onclick="onStockConfirmBackdrop(event)">
    <div class="stock-confirm-card" role="alertdialog" aria-labelledby="stock-confirm-title" aria-describedby="stock-confirm-msg" onclick="event.stopPropagation()">
        <div class="stock-confirm-icon-wrap" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
        </div>
        <h3 id="stock-confirm-title" class="stock-confirm-title">Remove from stock?</h3>
        <p id="stock-confirm-msg" class="stock-confirm-msg">This item will be removed from tracking.</p>
        <div class="stock-confirm-actions">
            <button type="button" class="stock-confirm-cancel" onclick="closeStockConfirm(false)">Cancel</button>
            <button type="button" class="stock-confirm-danger" id="stock-confirm-ok-btn" onclick="closeStockConfirm(true)">Remove</button>
        </div>
    </div>
</div>




<?php require_once __DIR__ . '/includes/stock_management_assets.php'; ?>

<?php require_once '../includes/footer.php'; ?>
