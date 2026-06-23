<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/receipt_helpers.php';
require_role('ADMIN');



// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Cache-Control: post-check=0, pre-check=0", false);
// header("Pragma: no-cache");

$session_id = $_GET['session_id'] ?? null;
$txn_id = $_GET['txn_id'] ?? null;

if (!$session_id) {
    die("Invalid Session ID");
}

// Fetch session info
$stmt = $pdo->prepare("
    SELECT cs.*, c.code as table_code, u.username as opener
    FROM table_sessions cs
    JOIN tables c ON cs.table_id = c.id
    LEFT JOIN users u ON cs.opened_by = u.id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die("Session not found");
}

// 1. Fetch ALL order items first
$stmt = $pdo->prepare("
    SELECT oi.*
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.session_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$session_id]);
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Calculate filtered total via SQL
$stmtSum = $pdo->prepare("
    SELECT SUM(oi.unit_price_snapshot * oi.qty) as filtered_total
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.session_id = ? AND (oi.is_paid = 1 OR oi.kitchen_status = 'SERVED')
");
$stmtSum->execute([$session_id]);
$total = floatval($stmtSum->fetchColumn() ?: 0);


$grand_total = $total;

// Filter items for display
$items = array_filter($all_items, function ($item) {
    return $item['is_paid'] == 1 || $item['kitchen_status'] === 'SERVED';
});

// Payment Status (Settled amounts)
$paid_cash = (float) $session['paid_amount_cash'];
$paid_online = (float) $session['paid_amount_online'];
$total_paid = $paid_cash + $paid_online;

// The remaining due on the receipt now only reflects the balance for SERVED items.
$remaining = $grand_total - $total_paid;

// 1. Fetch total credited amount and customer name for this session regardless of current payment_method
$stmt = $pdo->prepare("
    SELECT SUM(ct.amount) as credit_total, MAX(ct.customer_name) as customer_name
    FROM credit_transactions ct
    WHERE ct.session_id = ?
");
$stmt->execute([$session_id]);
$credit_data = $stmt->fetch(PDO::FETCH_ASSOC);

$credit_amount = (float) ($credit_data['credit_total'] ?? 0);
$current_credit_customer = $credit_data['customer_name'] ?? "";

// 2. Fetch Detailed Credit History for this session
$stmt = $pdo->prepare("SELECT * FROM credit_transactions WHERE session_id = ? ORDER BY created_at ASC");
$stmt->execute([$session_id]);
$credit_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nepal local date/time (paid_at when settled, else print time)
$receipt_dt = format_nepal_datetime(receipt_session_datetime($session));
$receipt_date = $receipt_dt['date'];
$time_12 = $receipt_dt['time'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Session #<?php echo $session_id; ?><?php if ($txn_id)
           echo " (Txn #$txn_id)"; ?></title>
    <style>
        /* STANDARD 80MM THERMAL STYLE - FINAL PRODUCTION CONFIG */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        html,
        body {
            width: 60mm;
            /* Was "Further reduced" */
            margin: 0 auto;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            font-weight: bold;

            /* Reduce scale logic removed */
            font-size: 11px;
            line-height: 1.2;
        }

        @media print {
            body {
                width: 60mm;
                max-width: 60mm;
                margin: 0 auto;
                padding-top: 0;
            }

            .no-print {
                display: none !important;
            }

            /* Prevent breaking inside specific rows */
            .item-row,
            .total-row,
            .info-row,
            .header,
            .logo {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Allow breaking between rows */
            .items-container,
            .totals-section {
                page-break-inside: auto;
                break-inside: auto;
            }
        }

        /* Utilities */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        .uppercase {
            text-transform: uppercase;
        }

        /* Helpers */
        .header {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .logo {
            width: 35px;
            height: auto;
            margin-bottom: 2px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .brand-name {
            font-size: 15px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }

        .brand-sub {
            font-size: 10px;
            margin: 0;
        }

        .divider {
            border-bottom: 1px dashed #000;
            margin: 2px 0;
            width: 100%;
        }

        .divider-solid {
            border-bottom: 1px solid #000;
            margin: 2px 0;
            width: 100%;
        }

        /* Info Grid - 2 cols */
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 2px;
        }

        /* Items Table */
        .table-header {
            display: flex;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 4px;
            font-size: 10px;
            text-transform: uppercase;
        }

        /* Flex Columns */
        .col-item {
            width: 45%;
            text-align: left;
        }

        .col-qty {
            width: 12%;
            text-align: center;
        }

        .col-rate {
            width: 18%;
            text-align: right;
        }

        .col-price {
            width: 25%;
            text-align: right;
        }

        .item-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px dashed #000;
            font-size: 11px;
        }

        .item-name {
            width: 45%;
            word-wrap: break-word;
            padding-right: 2px;
        }

        .item-qty-price {
            width: 55%;
            display: flex;
            justify-content: space-between;
        }

        /* Totals */
        .totals-section {
            margin-top: 3px;
            text-align: right;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 1px;
        }

        .grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4px 0;
            margin-top: 4px;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 5px;
            font-size: 10px;
        }

        /* Screen only buttons */
        .btn-print {
            background: #000;
            color: #fff;
            padding: 8px;
            border: none;
            width: 100%;
            margin-top: 8px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
        }

        .btn-close {
            background: #ccc;
            color: #000;
            padding: 8px;
            border: none;
            width: 100%;
            margin-top: 4px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="header">
        <img src="../assets/logo.png" alt="Logo" style="width: 70px; height: auto; margin-bottom: 5px;">
        <p style="font-weight: bold; font-size: 14px; margin: 2px 0;">चिया संसार - स्वादको नयाँ संसार!</p>
        <h1 class="brand-name">CHIYA SANSAR</h1>
        <p class="brand-sub">Parbatipur, Chitwan</p>
        <p class="brand-sub">Tel: +977 9855075930</p>
    </div>

    <div class="divider-solid"></div>
    <div class="text-center bold uppercase" style="font-size: 14px; margin: 5px 0;">Sales Receipt</div>

    <div class="info-row">
        <span>Session: <strong>#<?php echo $session_id; ?></strong></span>
        <span>Date: <?php echo $receipt_date; ?></span>
    </div>
    <div class="info-row">
        <span>Table: <strong><?php echo $session['table_code']; ?></strong></span>
        <span>Time: <?php echo $time_12; ?></span>
    </div>
    <div class="info-row">
        <span>Server: Admin</span>
        <span>Mode: Dine-In</span>
    </div>

    <div class="divider"></div>

    <div class="table-header">
        <div class="col-item">Item</div>
        <div class="col-qty">Qty</div>
        <div class="col-rate">Rate</div>
        <div class="col-price">Amount</div>
    </div>

    <!-- Items List -->
    <div class="items-container">
        <?php
        $unpaid_items = array_filter($items, function ($i) {
            return $i['is_paid'] == 0;
        });
        $paid_items = array_filter($items, function ($i) {
            return $i['is_paid'] == 1;
        });

        $unpaid_items = consolidate_receipt_items($unpaid_items);
        $paid_items = consolidate_receipt_items($paid_items);
        ?>

        <?php if (!empty($unpaid_items)): ?>
            <div
                style="font-weight:bold; font-size:11px; margin:5px 0; border-bottom:1px solid #000; text-transform:uppercase;">
                Unsettled Items</div>
            <?php foreach ($unpaid_items as $item): ?>
                <div class="item-row">
                    <div class="item-name">
                        <?php echo htmlspecialchars($item['item_name_snapshot']); ?>
                    </div>
                    <div class="item-qty-price">
                        <div class="col-qty"><?php echo $item['qty']; ?></div>
                        <div class="col-rate"><?php echo number_format($item['unit_price_snapshot'], 0); ?></div>
                        <div class="col-price"><?php echo number_format($item['unit_price_snapshot'] * $item['qty'], 0); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($paid_items)): ?>
            <?php if (!empty($unpaid_items)): ?>
                <div
                    style="font-weight:bold; font-size:11px; margin:10px 0 5px 0; border-bottom:1px solid #000; text-transform:uppercase;">
                    Settled Items</div>
            <?php endif; ?>
            <?php foreach ($paid_items as $item): ?>
                <div class="item-row">
                    <div class="item-name">
                        <?php echo htmlspecialchars($item['item_name_snapshot']); ?>
                    </div>
                    <div class="item-qty-price">
                        <div class="col-qty"><?php echo $item['qty']; ?></div>
                        <div class="col-rate"><?php echo number_format($item['unit_price_snapshot'], 0); ?></div>
                        <div class="col-price"><?php echo number_format($item['unit_price_snapshot'] * $item['qty'], 0); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="divider-solid"></div>

    <div class="totals-section">
        <div class="total-row">
            <span>Total:</span>
            <span><?php echo number_format($total, 0); ?></span>
        </div>

        <?php if ($paid_cash > 0): ?>
            <div class="total-row">
                <span>Paid (Cash):</span>
                <span><?php echo number_format($paid_cash, 0); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($paid_online > 0): ?>
            <div class="total-row">
                <span>Paid (Online):</span>
                <span><?php echo number_format($paid_online, 0); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($credit_details)): ?>
            <div class="divider"></div>
            <div class="text-left bold" style="font-size: 11px;">CREDIT RECORD:</div>
            <?php foreach ($credit_details as $cd): ?>
                <div class="total-row" style="font-size: 11px;">
                    <span><?php echo htmlspecialchars($cd['customer_name']); ?></span>
                    <span><?php echo number_format($cd['amount'], 0); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $net_due = $grand_total - ($total_paid + $credit_amount);
        $is_fully_paid = $net_due <= 0.5;
        ?>

        <div class="grand-total total-row">
            <span>GRAND TOTAL:</span>
            <span>Rs. <?php echo number_format($grand_total, 0); ?></span>
        </div>

        <?php if (!$is_fully_paid): ?>
            <div class="total-row bold" style="font-size: 14px; margin-top:5px;">
                <span>DUE AMOUNT:</span>
                <span>Rs. <?php echo number_format($net_due, 0); ?></span>
            </div>
            <?php if ($current_credit_customer): ?>
                <div style="font-size: 11px; margin-top: 2px;">A/C: <?php echo htmlspecialchars($current_credit_customer); ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center bold uppercase"
                style="margin-top: 10px; border: 2px solid #000; padding: 5px; position: relative;">
                *** FULLY PAID ***
                <img src="../assets/stamp.png"
                    style="position: absolute; top: -55px; right: 0; transform: rotate(-12deg); width: 100px; opacity: 1;">
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p class="bold" style="font-size: 13px;">आउनुहोस्, सँगै चिया पिऔँ।</p>
        <div style="margin-top: 5px; font-size: 10px; color: #000;">
            <span style="font-weight: bold;">Software by:</span> <span
                style="font-weight: bold; color: #000; text-transform: capitalize;">Prabin Sharma</span>
        </div>
        <div style="font-size: 12px; color: #000; margin-top: 2px;">
            sharmaprabin160@gmail.com
        </div>
    </div>

    <button class="btn-print no-print" style="background: #28a745 !important; color: white !important;"
        onclick="window.print()">PRINT RECEIPT</button>
    <button class="btn-close no-print" onclick="window.close()">CLOSE</button>

    <script>
        // Auto print logic
        window.onload = function () {
            // Optional: Uncomment for auto-print
            // window.print();
        };
    </script>
</body>

</html>