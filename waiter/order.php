<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_role('WAITER');
require_waiter_portal_enabled();

require_once '../includes/stock_availability.php';

$table_code = $_GET['table'] ?? '';
$table_id = $_GET['id'] ?? null;

if (!$table_id && $table_code) {
    // Attempt lookup by code
    $s_lookup = $pdo->prepare("SELECT id FROM tables WHERE code = ? LIMIT 1");
    $s_lookup->execute([$table_code]);
    $res_table = $s_lookup->fetch();
    if ($res_table) {
        $table_id = $res_table['id'];
    }
}

if (!$table_id) {
    header("Location: index.php");
    exit;
}

// Fetch available menu items
$stmt = $pdo->query("SELECT * FROM menu_items WHERE is_available=1 ORDER BY name ASC");
$menu_items = enrich_menu_items_with_stock($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Fetch current session for this table if exists
$stmt = $pdo->prepare("SELECT * FROM table_sessions WHERE table_id = ? AND closed_at IS NULL LIMIT 1");
$stmt->execute([$table_id]);
$active_session = $stmt->fetch();

require_once '../includes/header.php';
?>

<div
    style="position: relative; display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; padding: 10px 0;">
    <!-- Left: Back Button -->
    <a href="index.php" class="btn"
        style="background-color: #ffa726; color: black; font-weight: 600; z-index: 2;">Back</a>

    <!-- Center: Title -->
    <div style="position: absolute; left: 50%; transform: translateX(-50%); text-align: center; width: 100%;">
        <span style="font-size: 1.4rem; font-weight: 800; color: #3e2723;">
            <?php echo htmlspecialchars($table_code); ?>
        </span>
    </div>

    <!-- Right: Status -->
    <div style="z-index: 2;">
        <?php if ($active_session): ?>
            <span class="badge badge-preparing"
                style="font-size: 0.8rem; padding: 6px 12px; border-radius: 8px;">OCCUPIED</span>
        <?php else: ?>
            <span class="badge badge-ready" style="font-size: 0.8rem; padding: 6px 12px; border-radius: 8px;">FREE</span>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Menu Section -->
    <div>
        <div class="flex flex-between" style="align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px;">
            <h3 style="margin: 0; font-weight:800; color:#3e2723; font-size:1.5rem; letter-spacing:-0.5px;">Menu</h3>

            <!-- Compact Modern Search Bar -->
            <style>
                .search-wrapper {
                    position: relative;
                    width: 100%;
                    max-width: 260px;
                    /* Compact width */
                    height: 40px;
                    /* Compact height */
                    background: #ffffff;
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    padding: 0 15px;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
                    border: 1px solid #f0f0f0;
                    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
                }

                .search-wrapper:focus-within {
                    box-shadow: 0 4px 12px rgba(255, 167, 38, 0.15);
                    border-color: #ffbc6e;
                    transform: translateY(-1px);
                }

                .search-icon {
                    width: 16px;
                    height: 16px;
                    color: #9e9e9e;
                    margin-right: 10px;
                    flex-shrink: 0;
                    transition: color 0.3s ease;
                }

                .search-wrapper:focus-within .search-icon {
                    color: #ffa726;
                }

                .search-input-field {
                    border: none;
                    outline: none;
                    background: transparent;
                    width: 100%;
                    font-size: 0.9rem;
                    font-weight: 600;
                    color: #444;
                    padding: 0;
                    height: 100%;
                }

                .search-input-field::placeholder {
                    color: #bdbdbd;
                    font-weight: 500;
                }

                @media (max-width: 600px) {
                    .search-wrapper {
                        max-width: 100%;
                        /* Full width on mobile */
                    }
                }
            </style>

            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="menu-search" class="search-input-field" placeholder="Search menu..."
                    oninput="filterMenu(this.value)" autocomplete="off">
            </div>
        </div>
        <div class="menu-grid">
            <?php foreach ($menu_items as $item):
                $isOut = !empty($item['out_of_stock']);
                ?>
                <div class="card menu-item-card" style="cursor: default;" data-item-id="<?php echo (int) $item['id']; ?>">
                    <div style="flex:1;">
                        <div class="food-title">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>
                        <div class="food-price">
                            <?php echo htmlspecialchars($item['price']); ?>
                        </div>
                    </div>
                    <div class="menu-item-action">
                        <?php if ($isOut): ?>
                            <span class="menu-out-of-stock">out of stock</span>
                        <?php else: ?>
                            <div class="add-btn-3d" onclick="addToCart(<?php echo (int) $item['id']; ?>)" style="cursor: pointer;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        /* Fix scrolling for Mobile Order Page */
        body {
            overflow-x: hidden !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            touch-action: manipulation;
        }

        /* Bold HD Mobile Design */
        .menu-grid {
            display: grid;
            grid-template-columns: 1fr;
            /* Default mobile single column */
            gap: 16px;
            padding-bottom: 80px;
            /* Space for bottom cart */
        }

        @media(min-width: 768px) {
            .menu-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        .menu-item-card {
            background: #ffffff;
            border-radius: 24px !important;
            /* Super rounded */
            padding: 20px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow:
                0 10px 25px rgba(139, 69, 19, 0.08),
                0 4px 6px rgba(0, 0, 0, 0.02) !important;
            border: 1px solid rgba(139, 69, 19, 0.05) !important;
            transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
            animation: fadeInUp 0.5s ease backwards;
        }

        /* Stagger animation for items */
        .menu-item-card:nth-child(1) {
            animation-delay: 0.05s;
        }

        .menu-item-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .menu-item-card:nth-child(3) {
            animation-delay: 0.15s;
        }

        .menu-item-card:nth-child(4) {
            animation-delay: 0.2s;
        }

        .menu-item-card:nth-child(5) {
            animation-delay: 0.25s;
        }

        /* REMOVED BLINKING EFFECT
        .menu-item-card:active {
            transform: scale(0.96);
            background: #fafafa;
        } */

        .food-title {
            font-size: 1.15rem;
            font-weight: 800;
            /* Boldy type */
            color: #2d2d2d;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .food-price {
            font-size: 1rem;
            font-weight: 700;
            color: #8d6e63;
            /* Soft brown accent */
            background: #efebe9;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
        }

        /* HD 3D Add Button */
        .add-btn-3d {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #ffffff, #f5f5f5);
            border-radius: 18px;
            /* Squircle */
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3e2723;
            box-shadow:
                5px 5px 10px #d1d1d1,
                -5px -5px 10px #ffffff;
            transition: all 0.2s ease;
        }

        .add-btn-3d:active {
            box-shadow:
                inset 5px 5px 10px #d1d1d1,
                inset -5px -5px 10px #ffffff;
            transform: scale(0.95);
            color: #d84315;
            /* Highlight on press */
        }

        .menu-item-action {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
        }

        .menu-out-of-stock {
            color: #d32f2f;
            font-weight: 700;
            font-size: 0.72rem;
            line-height: 1.2;
            text-align: right;
            max-width: 72px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge-paid-custom {
            background-color: #66bb6a !important;
            /* Vibrant Green */
            color: #1b5e20 !important;
            /* Dark Green/Black text for high contrast */
            font-weight: 900 !important;
            border: 1px solid #4caf50;
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .badge-served {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-ready {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            /* Fallback similar to served, usually overridden in global if present */
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-pending {
            background-color: #f5f5f5;
            color: #616161;
            border: 1px solid #e0e0e0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-cooking {
            background-color: #fff3e0;
            color: #d84315;
            border: 1px solid #ffcc80;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 800;
            animation: pulse 2s infinite;
        }

        .badge-counter {
            background-color: #e0f7fa;
            color: #006064;
            border: 1px solid #b2ebf2;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-preparing {
            /* Fallback if logic misses */
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        @keyframes pulse {
            0% {
                opacity: 0.8;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.8;
            }
        }

        /* Mobile Mobile Grid & Cart Tweaks */
        @media (max-width: 900px) {
            .dashboard-grid {
                display: flex !important;
                flex-direction: column !important;
            }

            .dashboard-grid>div:last-child {
                order: -1;
                /* Move cart above menu or keep below? Usually below is fine but let's see */
                margin-bottom: 20px;
            }

            .card[style*="sticky"] {
                position: relative !important;
                top: 0 !important;
            }
        }

        @media (max-width: 600px) {
            .menu-item-card {
                padding: 15px !important;
            }

            .food-title {
                font-size: 1rem;
            }

            .add-btn-3d {
                width: 40px;
                height: 40px;
            }
        }
    </style>

    <!-- Details Section / Cart -->
    <div>
        <div class="card" style="position:sticky; top: 100px;">
            <h3>Current Order</h3>
            <div id="cart-items" style="max-height: 400px; overflow-y: auto; margin: 20px 0;">
                <p class="text-center text-muted">No items selected</p>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                <div class="flex flex-between mb-4">
                    <strong>Total:</strong>
                    <strong id="cart-total">0.00</strong>
                </div>
                <button onclick="submitOrder()" class="btn btn-primary btn-block btn-lg">Confirm Order</button>
            </div>
        </div>

        <div class="card mt-4" id="previous-order-card" style="<?php echo $active_session ? '' : 'display:none;'; ?>">
            <h3>Previous Orders (This Session)</h3>
            <div id="previous-orders">Loading...</div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-title">Confirm Order</div>
        <div class="modal-body" id="confirmModalBody">
            Are you sure you want to place this order?
        </div>
        <div class="modal-actions">
            <button class="btn-modal btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-modal btn-confirm" id="confirmBtnAction">Confirm Order</button>
        </div>
    </div>
</div>

<script>
    let cart = {};
    const tableId = <?php echo $table_id; ?>;
    const csrfToken = '<?php echo csrf_token(); ?>';
    const allMenuItems = <?php echo json_encode($menu_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?> || [];
    let orderController = null; // For cancelling pending requests
    let lastOrdersHtml = ''; // For DOM diffing


    function filterMenu(query) {
        // Helper to normalize text: lowercase and remove special chars (e.g. "Mo:Mo:" -> "momo")
        const normalize = (str) => str.toLowerCase().replace(/[^a-z0-9\s]/g, '');

        // Split query into terms
        const terms = normalize(query).split(/\s+/).filter(t => t.length > 0);
        const cards = document.querySelectorAll('.menu-item-card');

        cards.forEach(card => {
            // Get title and normalize it for search comparison
            const rawTitle = card.querySelector('.food-title').textContent;
            const title = normalize(rawTitle);

            // detailed search: Check if EVERY term in the query exists in the cleaned title
            const isMatch = terms.every(term => title.includes(term));

            if (isMatch) {
                // If it was hidden, animate it in. If already visible, leave it (prevents flicker)
                if (card.style.display === 'none') {
                    card.style.display = 'flex';
                    card.style.animation = 'fadeInUp 0.3s ease backwards';
                } else {
                    card.style.display = 'flex';
                }
            } else {
                card.style.display = 'none';
            }
        });
    }

    function stockAvailableForItem(item) {
        if (!item || item.stock_tracked != 1) return null;
        const base = item.stock_available != null ? Number(item.stock_available) : Number(item.stock_qty || 0);
        const inCart = cart[item.id] ? Number(cart[item.id].qty) : 0;
        return Math.max(0, base - inCart);
    }

    function showStockLimitAlert(item, requestedQty, availableQty) {
        const name = item.name || 'This item';
        let msg;
        if (availableQty <= 0) {
            msg = name + ' is out of stock right now, you can\'t order ' + requestedQty + '.';
        } else {
            msg = 'Stock for ' + name + ' is ' + availableQty + ', can\'t take an order of ' + requestedQty + '.';
        }
        showCustomAlert('Not enough stock', msg);
    }

    function addToCart(id) {
        if (!Array.isArray(allMenuItems)) {
            console.error("Menu items not loaded correctly", allMenuItems);
            App.showToast("System Error: Menu not loaded");
            return;
        }

        ensureFreshStock(STOCK_STALE_MS).then(() => {
            const item = allMenuItems.find(i => i.id == id);
            if (!item) {
                console.error("Item not found for ID:", id);
                return;
            }

            if (item.out_of_stock == 1) {
                showStockLimitAlert(item, 1, 0);
                return;
            }

            const nextQty = (cart[item.id] ? cart[item.id].qty : 0) + 1;
            if (item.stock_tracked == 1) {
                const roomLeft = stockAvailableForItem(item);
                if (roomLeft < 1) {
                    const totalAvail = roomLeft + (cart[item.id]?.qty || 0);
                    showStockLimitAlert(item, nextQty, totalAvail);
                    return;
                }
            }

            if (!cart[item.id]) {
                cart[item.id] = { ...item, qty: 0, note: '' };
            }
            cart[item.id].qty++;
            renderCart();

            App.showToast(`Added ${item.name}`);
        });
    }

    const ADD_BTN_SVG = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>`;

    function renderMenuItemAction(card, itemId, isOut) {
        const actionEl = card.querySelector('.menu-item-action');
        if (!actionEl) return;
        if (isOut) {
            actionEl.innerHTML = '<span class="menu-out-of-stock">out of stock</span>';
        } else {
            actionEl.innerHTML = `<div class="add-btn-3d" onclick="addToCart(${itemId})" style="cursor: pointer;">${ADD_BTN_SVG}</div>`;
        }
    }

    let stockLastRefreshAt = 0;
    let stockRefreshPromise = null;
    const STOCK_REFRESH_MS = 8000;
    const STOCK_STALE_MS = 1500;

    function applyMenuStockData(data) {
        if (!data || !data.success) return;
        const outSet = new Set((data.out_of_stock || []).map(String));
        allMenuItems.forEach(item => {
            const tracked = data.stock && Object.prototype.hasOwnProperty.call(data.stock, item.id);
            if (tracked) {
                item.stock_tracked = 1;
                item.stock_qty = data.stock[item.id];
                item.stock_available = data.available && Object.prototype.hasOwnProperty.call(data.available, item.id)
                    ? data.available[item.id]
                    : data.stock[item.id];
                item.out_of_stock = data.stock[item.id] <= 0 ? 1 : 0;
            }
        });
        document.querySelectorAll('.menu-item-card[data-item-id]').forEach(card => {
            const itemId = card.getAttribute('data-item-id');
            renderMenuItemAction(card, itemId, outSet.has(String(itemId)));
        });
    }

    function refreshMenuStockStatus(force) {
        if (document.hidden && !force) {
            return Promise.resolve();
        }
        if (stockRefreshPromise && !force) {
            return stockRefreshPromise;
        }

        stockRefreshPromise = App.request(
            '<?php echo BASE_URL; ?>api/stock_availability.php?_t=' + Date.now(),
            'GET',
            null,
            { cache: 'no-store' }
        )
            .then(data => {
                applyMenuStockData(data);
                stockLastRefreshAt = Date.now();
            })
            .catch(() => {})
            .finally(() => {
                stockRefreshPromise = null;
            });

        return stockRefreshPromise;
    }

    function ensureFreshStock(maxAgeMs) {
        if (Date.now() - stockLastRefreshAt <= maxAgeMs) {
            return Promise.resolve();
        }
        return refreshMenuStockStatus(true);
    }

    function updateQty(id, delta) {
        if (!cart[id]) return;

        const applyUpdate = () => {
            if (delta > 0) {
                const menuItem = allMenuItems.find(i => i.id == id);
                if (menuItem && menuItem.stock_tracked == 1) {
                    const roomLeft = stockAvailableForItem(menuItem);
                    if (delta > roomLeft) {
                        showStockLimitAlert(menuItem, cart[id].qty + delta, roomLeft + cart[id].qty);
                        return;
                    }
                } else if (menuItem && menuItem.out_of_stock == 1) {
                    showStockLimitAlert(menuItem, cart[id].qty + delta, 0);
                    return;
                }
            }
            cart[id].qty += delta;
            if (cart[id].qty <= 0) delete cart[id];
            renderCart();
        };

        if (delta > 0) {
            ensureFreshStock(STOCK_STALE_MS).then(applyUpdate);
        } else {
            applyUpdate();
        }
    }

    function updateNote(id, note) {
        if (cart[id]) cart[id].note = note;
    }

    function renderCart() {
        const container = document.getElementById('cart-items');
        let html = '';
        let total = 0;

        const ids = Object.keys(cart);
        if (ids.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">No items selected</p>';
            document.getElementById('cart-total').textContent = '0.00';
            return;
        }

        ids.forEach(id => {
            const item = cart[id];
            const itemTotal = item.price * item.qty;
            total += itemTotal;
            html += `
            <div class="order-list-item">
                <div style="flex:1;">
                    <div style="font-weight:500;">${item.name}</div>
                    <input type="text" placeholder="Note..." 
                        style="width:100%; padding:5px; font-size:0.8rem; border:1px solid #eee; margin-top:5px;"
                        onchange="updateNote(${id}, this.value)" value="${item.note || ''}">
                </div>
                <div class="qty-control" style="margin-left:10px;">
                    <div class="qty-btn" onclick="updateQty(${id}, -1)">-</div>
                    <span class="qty-val">${item.qty}</span>
                    <div class="qty-btn" onclick="updateQty(${id}, 1)">+</div>
                </div>
                <div style="margin-left:10px; min-width:60px; text-align:right;">
                    ${itemTotal.toFixed(2)}
                </div>
            </div>
        `;
        });

        container.innerHTML = html;
        document.getElementById('cart-total').textContent = App.formatMoney(total);
    }

    function submitOrder() {
        const items = Object.values(cart);
        if (items.length === 0) {
            App.showToast('Please add items first');
            return;
        }

        ensureFreshStock(0).then(() => {
            for (const item of items) {
                if (item.stock_tracked != 1) continue;
                const menuItem = allMenuItems.find(i => i.id == item.id) || item;
                const available = menuItem.stock_available != null
                    ? Number(menuItem.stock_available)
                    : Number(menuItem.stock_qty || 0);
                if (item.qty > available) {
                    showStockLimitAlert(menuItem, item.qty, available);
                    return;
                }
            }

            const total = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
            document.getElementById('confirmModalBody').innerHTML = `
            Place order for <b>${items.length} items</b>?<br>
            <span style="font-size: 1.1em; color: var(--primary); font-weight:600;">Total: ${App.formatMoney(total)}</span>
        `;
            document.getElementById('confirmModal').classList.add('active');
            document.getElementById('confirmBtnAction').onclick = () => executeOrder(items);
        });
    }

    function closeModal() {
        document.getElementById('confirmModal').classList.remove('active');
    }

    function executeOrder(items) {
        // Show loading on button
        const confirmBtn = document.getElementById('confirmBtnAction');
        const originalText = confirmBtn.innerText;
        confirmBtn.innerText = 'Processing...';
        confirmBtn.disabled = true;

        const payload = {
            table_id: tableId,
            items: JSON.stringify(items),
            csrf_token: csrfToken
        };

        App.request('<?php echo BASE_URL; ?>api/save_order.php', 'POST', payload)
            .then(res => {
                confirmBtn.innerText = originalText;
                confirmBtn.disabled = false;
                closeModal();

                if (res && res.success) {
                    App.showToast('Order Placed Successfully');
                    cart = {};
                    renderCart();
                    loadPreviousOrders();
                    refreshMenuStockStatus(true);

                    const statusBadge = document.querySelector('.badge-ready');
                    if (statusBadge) {
                        statusBadge.className = 'badge badge-preparing';
                        statusBadge.innerText = 'OCCUPIED';
                        // Refresh session state logic if needed, but UI update is sufficient for waiter view
                    }

                    // Force refresh of orders immediately
                    setTimeout(loadPreviousOrders, 500);

                } else {
                    const errMsg = res.error || 'Failed to place order';
                    if (res.stock_error) {
                        showCustomAlert('Not enough stock', errMsg);
                    } else {
                        App.showToast(errMsg, 'error');
                    }
                }
            })
            .catch(err => {
                confirmBtn.innerText = originalText;
                confirmBtn.disabled = false;
                closeModal();
                App.showToast('Something went wrong', 'error');
            });
    }

    // --- Custom Confirmation Logic ---
    let confirmCallback = null;

    function showCustomConfirm(title, msg, callback) {
        document.getElementById('confirm-title').innerText = title;
        document.getElementById('confirm-msg').innerText = msg;
        document.getElementById('custom-confirm-modal').classList.add('active');
        confirmCallback = callback;
    }

    function closeCustomConfirm() {
        document.getElementById('custom-confirm-modal').classList.remove('active');
        confirmCallback = null;
    }

    function confirmAction() {
        if (confirmCallback) {
            confirmCallback();
        }
        closeCustomConfirm();
    }

    function deleteOrderItem(id, status) {
        if (status !== 'PENDING') {
            showCustomAlert("Oops!", "Order cannot be deleted as it is already being prepared.");
            return;
        }

        showCustomConfirm("Delete Item?", "Are you sure you want to remove this item?", () => {
            App.request('<?php echo BASE_URL; ?>api/waiter_delete_item.php', 'POST', {
                item_id: id,
                csrf_token: csrfToken
            }).then(res => {
                if (res.success) {
                    App.showToast("Item deleted");
                    loadPreviousOrders();
                    refreshMenuStockStatus(true);
                } else {
                    App.showToast(res.error || "Failed to delete", 'error');
                }
            });
        });
    }

    /* ... existing alert logic ... */

    function showCustomAlert(title, msg) {
        document.getElementById('alert-title').innerText = title;
        document.getElementById('alert-msg').innerText = msg;
        document.getElementById('custom-alert-modal').classList.add('active');
    }

    function closeCustomAlert() {
        document.getElementById('custom-alert-modal').classList.remove('active');
    }

    function serveItems(ids) {
        const idList = Array.isArray(ids) ? ids : [ids];
        Promise.all(idList.map(id =>
            App.request('<?php echo BASE_URL; ?>api/waiter_action.php', 'POST', {
                action: 'serve_item',
                item_id: id,
                csrf_token: csrfToken
            })
        )).then(results => {
            if (results.every(r => r && r.success)) {
                App.showToast("Served!");
                loadPreviousOrders();
                refreshMenuStockStatus(true);
            } else {
                App.showToast("Failed to update", 'error');
            }
        });
    }

    function loadPreviousOrders() {
        const container = document.getElementById('previous-orders');
        const card = document.getElementById('previous-order-card');
        if (!container) return;

        if (orderController) orderController.abort();
        orderController = new AbortController();

        App.request('<?php echo BASE_URL; ?>api/get_orders.php?table_id=' + tableId, 'GET', null, { signal: orderController.signal })
            .then(res => {
                if (res && res.orders) {
                    if (res.orders.length > 0) {
                        if (card) card.style.display = 'block';

                        let html = '';
                        const isPaid = (res.session && res.session.is_paid == 1);

                        // --- Consolidate Orders Logic ---
                        const grouped = {};
                        res.orders.forEach(o => {
                            const key = `${o.item_name_snapshot}_${o.unit_price_snapshot}_${o.kitchen_status}_${o.is_paid}_${o.note || ''}`;
                            if (!grouped[key]) {
                                grouped[key] = { ...o, qty: 0, ids: [] };
                            }
                            grouped[key].qty += parseInt(o.qty || 1);
                            grouped[key].ids.push(o.id);
                        });
                        const groupedOrders = Object.values(grouped);
                        // ------------------------------

                        groupedOrders.forEach(o => {
                            let actionBtn = '';
                            let statusBadge = '';

                            const itemIsPaid = (o.is_paid == 1) || isPaid;
                            const isServed = o.kitchen_status === 'SERVED';
                            const idsJson = JSON.stringify(o.ids);

                            if (itemIsPaid) {
                                statusBadge = '<span class="badge badge-paid-custom">PAID</span>';
                            } else if (isServed) {
                                statusBadge = '<span class="badge badge-served">SERVED</span>';
                            } else {
                                actionBtn = `
                                <button onclick='serveItems(${idsJson})' style="background: #43a047; color: white; border: none; border-radius: 8px; padding: 8px 12px; margin-right: 8px; font-weight: 700; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; gap: 5px; box-shadow: 0 4px 10px rgba(67, 160, 71, 0.2);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Served
                                </button>`;
                            }

                            const deleteBtn = (!itemIsPaid && o.kitchen_status === 'PENDING') ? `
                                <button onclick="deleteOrderItem(${o.ids[0]}, '${o.kitchen_status}')" style="background: #ffebee; border: none; border-radius: 8px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #d32f2f; transition: all 0.2s;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>` : '';

                            html += `
                            <div class="order-list-item" style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="flex:1;">
                                    <div style="font-weight:700; color:#3e2723;">${o.item_name_snapshot} <span style="font-size:0.9em; color:#666;">x${o.qty}</span></div>
                                    <div class="item-meta" style="display:flex; align-items:center; gap:8px; font-size:0.85rem; margin-top:2px;">
                                        ${statusBadge}
                                        ${o.note ? '<span style="color:#d84315;">' + (statusBadge ? '| ' : '') + o.note + '</span>' : ''}
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <div class="price-tag" style="font-weight:700; color:#388e3c; margin-right:10px;">${App.formatMoney(o.unit_price_snapshot * o.qty)}</div>
                                    ${actionBtn}
                                    ${deleteBtn}
                                </div>
                            </div>`;
                        });

                        if (lastOrdersHtml !== html) {
                            container.innerHTML = html;
                            lastOrdersHtml = html;
                        }
                    } else {
                        container.innerHTML = '<p class="text-center text-muted">No orders found.</p>';
                        lastOrdersHtml = '';
                    }
                }
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error(err);
            });
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        loadPreviousOrders();
        App.startLiveSync('<?php echo BASE_URL; ?>api/realtime_ping.php', loadPreviousOrders);
        refreshMenuStockStatus(true);
        setInterval(() => refreshMenuStockStatus(false), STOCK_REFRESH_MS);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadPreviousOrders();
                refreshMenuStockStatus(true);
            }
        });
    });
</script>

<!-- Custom Fancy Alert Modal -->
<div id="custom-alert-modal" class="modal-overlay" style="z-index: 9999;">
    <div class="modal-card" style="text-align: center; border-radius: 24px; padding: 30px;">
        <div
            style="width: 60px; height: 60px; background: #fff3e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                stroke="#f57c00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
        <div id="alert-title" style="font-size: 1.4rem; font-weight: 800; color: #333; margin-bottom: 8px;">Title</div>
        <div id="alert-msg" style="font-size: 1rem; color: #666; margin-bottom: 25px; line-height: 1.4;">Message goes
            here</div>
        <button onclick="closeCustomAlert()" style="
            background: linear-gradient(135deg, #c19a6b, #a67c52);
            border: none;
            color: #ffffff;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 800;
            border-radius: 12px;
            width: 100%;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(166, 124, 82, 0.3);
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        ">Okay, Got it</button>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="custom-confirm-modal" class="modal-overlay" style="z-index: 9999;">
    <div class="modal-card" style="text-align: center; border-radius: 24px; padding: 30px;">
        <div
            style="width: 60px; height: 60px; background: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                stroke="#d32f2f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"></path>
                <path d="M19 6v14c0 1.1-.9 2-2 2H7c-1.1 0-2-.9-2-2V6"></path>
                <path d="M8 6V4c0-1.1.9-2 2-2h4c1.1 0 2 .9 2 2v2"></path>
            </svg>
        </div>
        <div id="confirm-title" style="font-size: 1.4rem; font-weight: 800; color: #333; margin-bottom: 8px;">Confirm
        </div>
        <div id="confirm-msg" style="font-size: 1rem; color: #666; margin-bottom: 25px; line-height: 1.4;">Are you sure?
        </div>

        <div style="display:flex; gap:10px;">
            <button onclick="closeCustomConfirm()" style="
                flex: 1;
                background: #f5f5f5;
                border: none;
                color: #555;
                padding: 12px;
                font-size: 1rem;
                font-weight: 700;
                border-radius: 12px;
                cursor: pointer;
            ">Cancel</button>
            <button onclick="confirmAction()" style="
                flex: 1;
                background: linear-gradient(135deg, #ef5350, #c62828);
                border: none;
                color: #ffffff;
                padding: 12px;
                font-size: 1rem;
                font-weight: 800;
                border-radius: 12px;
                cursor: pointer;
                box-shadow: 0 4px 10px rgba(198, 40, 40, 0.3);
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            ">Yes, Delete</button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>