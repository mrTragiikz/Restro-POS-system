<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/receipt_helpers.php';
require_role('ADMIN');

$transaction_id = $_GET['transaction_id'] ?? null;
if (!$transaction_id) {
    die("Invalid Transaction ID");
}

// Fetch transaction info
$stmt = $pdo->prepare("
    SELECT ct.*, cc.name, cc.phone, 
           tbl.code as table_code,
           COALESCE(ts.paid_amount_cash, 0) as paid_amount_cash, 
           COALESCE(ts.paid_amount_online, 0) as paid_amount_online
    FROM credit_transactions ct
    LEFT JOIN credit_customers cc ON ct.customer_id = cc.id
    LEFT JOIN table_sessions ts ON ct.session_id = ts.id
    LEFT JOIN tables tbl ON ts.table_id = tbl.id
    WHERE ct.id = ?
");
$stmt->execute([$transaction_id]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    die("Transaction #$transaction_id not found in database.");
}

if (empty($txn['name'])) {
    $txn['name'] = $txn['customer_name'] ?? 'Walk-in Customer';
}

// Current balance
$current_balance = 0;
if (!empty($txn['customer_id'])) {
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_transactions WHERE customer_id = ?");
    $stmt->execute([$txn['customer_id']]);
    $current_balance = floatval($stmt->fetchColumn());
}

$receipt_dt = format_nepal_datetime($txn['created_at'] ?? 'now');
$receipt_date = $receipt_dt['date'];
$time_12 = $receipt_dt['time'];

// Fetch items if linked to a session
$items = [];
$session_total = 0;
if (!empty($txn['session_id'])) {
    $stmt = $pdo->prepare("
        SELECT oi.*
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.session_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$txn['session_id']]);
    // For credit receipts, we show all items associated with the session
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = consolidate_receipt_items($all_items);

    foreach ($items as $item) {
        $session_total += ($item['unit_price_snapshot'] * $item['qty']);
    }


    // Adjust session total
    $session_subtotal = $session_total;
}

// Fallback: If no items found (e.g. purged) or session_total is 0, reconstruct from transaction data
if ($session_total <= 0) {
    $credit_amt = isset($txn['amount']) ? abs(floatval($txn['amount'])) : 0;
    $cash_amt = isset($txn['paid_amount_cash']) ? floatval($txn['paid_amount_cash']) : 0;
    $online_amt = isset($txn['paid_amount_online']) ? floatval($txn['paid_amount_online']) : 0;
    $session_total = $credit_amt + $cash_amt + $online_amt;
}

// Fetch all credits for this session to show historical context if table wasn't cleared
$session_credits = [];
if (!empty($txn['session_id'])) {
    $stmt = $pdo->prepare("SELECT id, amount, created_at, customer_name FROM credit_transactions WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$txn['session_id']]);
    $session_credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Logic Fix: If Credit Amount is essentially equal to Session Total, 
// strictly treat this as UNPAID/CREDIT, ignoring any potentially conflicting "paid" flags in the session table.
$credit_val_abs = abs($txn['amount']);
if (abs($session_total - $credit_val_abs) < 1) {
    $txn['paid_amount_cash'] = 0;
    $txn['paid_amount_online'] = 0;
}

// Calculate if fully paid
$paid_amount = ($txn['paid_amount_cash'] ?? 0) + ($txn['paid_amount_online'] ?? 0);
// Use small epsilon for float comparison
$is_fully_paid = ($txn['amount'] < 0) || ($paid_amount >= ($session_total - 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Receipt - #<?php echo $transaction_id; ?></title>
    <style>
        /* STANDARD 80MM THERMAL STYLE - FINAL PRODUCTION CONFIG */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        html,
        body {
            width: 60mm;
            margin: 0 auto;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            font-weight: bold;
            font-size: 11px;
            line-height: 1.3;
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

        .text-brand,
        .text-green {
            color: #000 !important;
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
            height: 35px;
            margin-bottom: 2px;
            filter: grayscale(100%) contrast(200%);
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

        .divider-solid {
            border-bottom: 1px solid #000;
            margin: 2px 0;
        }

        .divider-thin {
            border-bottom: 1px dashed #000;
            margin: 2px 0;
        }

        /* Info Grid */
        .info-grid {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 1px;
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

        .items-container {
            margin-bottom: 2px;
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
        .totals-area {
            margin-top: 3px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 1px;
        }

        .grand-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4px 0;
            margin-top: 4px;
            text-transform: uppercase;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 4mm;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 2mm;
        }

        .footer-email {
            font-size: 9px;
            margin-top: 1px;
        }

        /* Buttons */
        .no-print {
            padding: 8px;
            margin-top: 8px;
            background: #000;
            color: #fff;
            border: none;
            width: 100%;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header text-center">
        <img src="../assets/logo.png" alt="Logo" style="width: 70px; height: auto; margin-bottom: 5px;">
        <p style="font-weight: bold; font-size: 14px; margin: 2px 0;">चिया संसार - स्वादको नयाँ संसार!</p>
        <h1 class="brand-name text-brand">CHIYA SANSAR</h1>
        <p class="brand-sub">Parbatipur, Chitwan</p>
        <p class="brand-sub bold">Tel: +977 9855075930</p>
    </div>

    <div class="divider-solid"></div>
    <div class="text-center bold uppercase" style="font-size: 12px; margin-bottom: 2mm;">
        <?php
        if ($is_fully_paid) {
            echo 'SALES RECEIPT';
        } else {
            echo $txn['amount'] >= 0 ? 'CREDIT RECEIPT' : 'CREDIT PAYMENT RECEIPT';
        }
        ?>
    </div>

    <div class="info-grid">
        <span>Receipt: <strong>#<?php echo $transaction_id; ?></strong></span>
        <span>Date: <?php echo $receipt_date; ?></span>
    </div>
    <div class="info-grid">
        <span>Customer: <strong><?php echo htmlspecialchars($txn['name']); ?></strong></span>
        <span>Time: <?php echo $time_12; ?></span>
    </div>
    <div class="info-grid">
        <span>Table:
            <strong><?php echo !empty($txn['table_code']) ? htmlspecialchars($txn['table_code']) : 'Walk-In'; ?></strong></span>
        <span>Mode: <?php echo htmlspecialchars($txn['payment_mode'] ?? 'CASH'); ?></span>
    </div>
    <?php if ($txn['phone']): ?>
        <div class="info-grid">
            <span>Phone: <?php echo $txn['phone']; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
        <div class="divider-thin"></div>
        <div class="table-header">
            <div class="col-item">Item</div>
            <div class="col-qty">Qty</div>
            <div class="col-rate">Rate</div>
            <div class="col-price">Amount</div>
        </div>
        <div class="items-container">
            <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <div class="item-name">
                        <?php echo htmlspecialchars($item['item_name_snapshot']); ?>
                    </div>
                    <div class="item-qty-price">
                        <div class="col-qty"><?php echo $item['qty']; ?></div>
                        <div class="col-rate"><?php echo number_format($item['unit_price_snapshot'], 0); ?></div>
                        <div class="col-price">
                            <?php echo number_format($item['unit_price_snapshot'] * $item['qty'], 0); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="divider-thin"></div>

    <div class="totals-area">
        <?php if (!empty($txn['session_id']) || $txn['amount'] > 0): ?>
            <div class="total-row bold">
                <span>Total Amount:</span>
                <span><?php echo number_format($session_total, 0); ?></span>
            </div>
            <div class="divider-thin" style="margin: 1mm 0;"></div>
        <?php endif; ?>

        <?php if (!empty($txn['session_id'])): ?>
            <?php if ($txn['paid_amount_cash'] > 0): ?>
                <div class="total-row">
                    <span class="bold">Paid via Cash:</span>
                    <span><?php echo number_format($txn['paid_amount_cash'], 0); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($txn['paid_amount_online'] > 0): ?>
                <div class="total-row">
                    <span class="bold">Paid via Online:</span>
                    <span><?php echo number_format($txn['paid_amount_online'], 0); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>



        <div class="divider-thin"></div>

        <?php if ($txn['amount'] >= 0 && count($session_credits) > 1): ?>
            <div class="divider-thin"></div>
            <div class="text-left bold" style="font-size: 10px; margin-top: 1mm;">SESSION CREDIT BREAKDOWN:</div>
            <?php
            $total_session_credit = 0;
            foreach ($session_credits as $sc):
                $total_session_credit += $sc['amount'];
                $is_current = ($sc['id'] == $transaction_id);
                ?>
                <div class="total-row" style="font-size: 11px; color:#000;">
                    <span>
                        <?php if ($is_current): ?><span
                                style="font-size: 1.1rem; vertical-align: middle; margin-right: 2px;">➤</span><?php endif; ?>
                        <?php echo htmlspecialchars($sc['customer_name']); ?> #<?php echo $sc['id']; ?>
                        (<?php echo format_nepal_datetime($sc['created_at'])['time']; ?>)
                    </span>
                    <span class=" <?php echo $is_current ? 'bold' : ''; ?>">Rs.
                        <?php echo number_format($sc['amount'], 0); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="grand-total-row text-brand" style="margin-top: 2mm;">
                <span>TOTAL SESSION CREDIT</span>
                <span>Rs. <?php echo number_format($total_session_credit, 0); ?></span>
            </div>
        <?php else: ?>
            <?php if (!$is_fully_paid || $txn['amount'] < 0): ?>
                <div class="grand-total-row <?php echo $txn['amount'] >= 0 ? 'text-brand' : 'text-green'; ?>">
                    <span>
                        <?php echo $txn['amount'] >= 0 ? 'CREDIT RECORD' : 'CREDIT PAID'; ?>
                        <?php if ($txn['amount'] >= 0): ?>
                            <span style="font-size: 11px;">(<?php echo htmlspecialchars($txn['name']); ?>)</span>
                        <?php endif; ?>
                    </span>
                    <span>Rs. <?php echo number_format(abs($txn['amount']), 0); ?></span>
                </div>

                <?php if ($txn['amount'] < 0 && $current_balance > 0): ?>
                    <div class="total-row bold"
                        style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed #000; font-size: 12px; color: #000;">
                        <span>REMAINING CREDIT:</span>
                        <span>Rs. <?php echo number_format($current_balance, 0); ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_fully_paid): ?>
            <div class="bold uppercase"
                style="margin-top: 3mm; border: 2px solid #000; padding: 5px; font-weight: 900; color: #000; text-align: center !important; position: relative;">
                <?php 
                if ($txn['amount'] < 0 && $current_balance > 0) {
                    echo "*** PAYMENT RECEIVED ***";
                } else {
                    echo "*** FULLY PAID ***";
                }
                ?>
                <img src="../assets/stamp.png"
                    style="position: absolute; top: -55px; right: 0; transform: rotate(-12deg); width: 100px; opacity: 1;">
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 2mm; font-size: 12px; font-weight: bold;">
            चिया र रमाइलो गफ
        </div>

    </div>

    <div class="footer" style="margin-top: 6mm; border-top: 1px dashed #000; padding-top: 3mm;">
        <p class="bold" style="font-size: 13px;">आउनुहोस्, सँगै चिया पिऔँ।</p>
        <div style="margin-top: 5px; font-size: 10px; color: #000;">
            <span style="font-weight: bold;">Software by:</span> <span
                style="font-weight: bold; color: #000; text-transform: capitalize;">Prabin Sharma</span>
        </div>
        <div style="font-size: 12px; color: #000; margin-top: 2px;">
            sharmaprabin160@gmail.com
        </div>
    </div>

    <button class="no-print" style="background: #28a745; color: white;" onclick="window.print()">PRINT RECEIPT</button>
    <button class="no-print" style="background: #ccc; color: #000;" onclick="window.close()">CLOSE</button>

</body>

</html>