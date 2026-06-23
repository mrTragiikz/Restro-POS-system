<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/receipt_helpers.php';
require_role('ADMIN');

$customerId = $_GET['customer_id'] ?? null;
if (!$customerId) {
    die("Customer ID required");
}

// Customer
$stmt = $pdo->prepare("SELECT id, name as full_name, phone, address, created_at FROM credit_customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("Customer not found");
}

// Full transaction history
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.amount,
        t.created_at,
        t.note,
        t.session_id,
        COALESCE(tbl.code, '') as table_code,
        t.customer_name
    FROM credit_transactions t
    LEFT JOIN table_sessions ts ON t.session_id = ts.id
    LEFT JOIN tables tbl ON ts.table_id = tbl.id
    WHERE t.customer_id = ?
    ORDER BY t.created_at ASC, t.id ASC
");
$stmt->execute([$customerId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCredit = 0.0;
$totalPaid = 0.0;
foreach ($rows as $r) {
    $amt = (float) ($r['amount'] ?? 0);
    if ($amt >= 0) $totalCredit += $amt;
    else $totalPaid += abs($amt);
}
$balance = $totalCredit - $totalPaid;

function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function format_report_date($adDateTime) {
    $ts = strtotime((string) $adDateTime);
    if (!$ts) return '-';
    return date('d M, Y', $ts);
}

function time_12h($adDateTime) {
    $ts = strtotime((string) $adDateTime);
    if (!$ts) return '-';
    return date('h:i A', $ts);
}

function fetch_session_items(PDO $pdo, $sessionId) {
    if (empty($sessionId)) return [];
    $stmt = $pdo->prepare("
        SELECT 
            oi.item_name_snapshot,
            oi.qty,
            oi.unit_price_snapshot
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.session_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$sessionId]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($all_items)) return [];

    return consolidate_receipt_items($all_items);
}

$generatedAtAd = date('Y-m-d H:i:s');
$periodStartAd = null;
if (!empty($rows)) {
    $periodStartAd = $rows[0]['created_at'] ?? null;
}
$periodStartAd = $periodStartAd ? format_report_date($periodStartAd) : '-';
$periodEndAd = format_report_date($generatedAtAd);
$generatedTime12 = time_12h($generatedAtAd);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Credit Statement - <?php echo esc($customer['full_name']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            padding: 0;
            color: #222;
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 25px;
            border-bottom: 2px solid #3e2723;
            padding-bottom: 15px;
        }

        .brand h1 {
            margin: 0;
            color: #3e2723;
            font-size: 1.7rem;
        }

        .brand p {
            margin: 6px 0 0;
            color: #666;
            font-size: 0.9rem;
        }

        .details {
            text-align: right;
            color: #555;
            font-size: 0.9rem;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 30px;
            margin-top: 10px;
        }

        .kv {
            display: flex;
            gap: 8px;
            align-items: baseline;
        }

        .k {
            color: #777;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            min-width: 120px;
        }

        .v {
            color: #222;
            font-weight: 600;
        }

        .stat-box {
            display: flex;
            gap: 16px;
            margin: 18px 0 25px 0;
        }

        .card {
            flex: 1;
            border: 1px solid #ddd;
            padding: 14px 16px;
            border-radius: 10px;
        }

        .card h3 {
            margin: 0 0 8px 0;
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .card .val {
            font-size: 1.25rem;
            font-weight: 800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        th {
            text-align: left;
            background: #f9f9f9;
            padding: 10px;
            border-bottom: 2px solid #ddd;
            text-transform: uppercase;
            font-size: 0.72rem;
            color: #555;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .right { text-align: right; }
        .muted { color: #777; font-size: 0.78rem; }
        .credit { color: #d32f2f; font-weight: 800; }
        .pay { color: #2e7d32; font-weight: 800; }

        .txn {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 0.8rem;
        }
        .items-table th {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 6px 6px;
            font-size: 0.7rem;
            color: #666;
        }
        .items-table td {
            border-bottom: 1px dotted #eee;
            padding: 6px 6px;
        }
        .nowrap { white-space: nowrap; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div class="brand">
            <h1>Chiya Sansar</h1>
            <p>Customer Credit Statement (AD Date)</p>
        </div>
        <div class="details">
            <div><strong>Period:</strong> <?php echo esc($periodStartAd); ?> → <?php echo esc($periodEndAd); ?></div>
            <div><strong>Generated:</strong> <?php echo esc($periodEndAd); ?> <?php echo esc($generatedTime12); ?></div>
            <div><strong>Customer ID:</strong> #<?php echo esc($customer['id']); ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="kv"><div class="k">Customer</div><div class="v"><?php echo esc($customer['full_name']); ?></div></div>
        <div class="kv"><div class="k">Phone</div><div class="v"><?php echo esc($customer['phone'] ?? '-'); ?></div></div>
        <div class="kv"><div class="k">Address</div><div class="v"><?php echo esc($customer['address'] ?? '-'); ?></div></div>
        <div class="kv"><div class="k">Since</div><div class="v"><?php echo esc(format_report_date($customer['created_at'] ?? '')); ?></div></div>
    </div>

    <div class="stat-box">
        <div class="card" style="background:#fff3e0; border-color:#ffe0b2;">
            <h3>Total Credit</h3>
            <div class="val" style="color:#e65100;">Rs. <?php echo number_format($totalCredit, 0); ?></div>
        </div>
        <div class="card" style="background:#e8f5e9; border-color:#c8e6c9;">
            <h3>Total Paid</h3>
            <div class="val" style="color:#2e7d32;">Rs. <?php echo number_format($totalPaid, 0); ?></div>
        </div>
        <div class="card" style="background:#fce4ec; border-color:#f8bbd0;">
            <h3>Current Balance</h3>
            <div class="val" style="color:<?php echo $balance > 0 ? '#d32f2f' : '#388e3c'; ?>;">Rs. <?php echo number_format($balance, 0); ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:170px;">Type / Date (AD)</th>
                <th>Details (Note + Items)</th>
                <th style="width:90px;">Table</th>
                <th style="width:90px;">Receipt</th>
                <th class="right" style="width:120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) > 0): ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $amt = (float) ($r['amount'] ?? 0);
                $type = $amt >= 0 ? 'CREDIT' : 'PAYMENT';
                $note = $r['note'];
                if ($amt > 0 && (!$note || $note === '-')) $note = 'Full Credit Given';
                $adDate = format_report_date($r['created_at'] ?? '');
                $t12 = time_12h($r['created_at'] ?? '');
                $items = fetch_session_items($pdo, $r['session_id'] ?? null);
                ?>
                <tr class="txn">
                    <td>
                        <div style="font-weight:900; letter-spacing:0.3px;"><?php echo esc($type); ?></div>
                        <div class="muted">
                            <?php echo esc($adDate); ?> • <?php echo esc($t12); ?>
                            <div class="muted" style="margin-top:2px;">
                                AD: <?php echo esc(date('Y-m-d H:i:s', strtotime($r['created_at']))); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?php echo esc($note ?: '-'); ?></div>
                        <?php if (!empty($r['session_id'])): ?>
                            <div class="muted" style="margin-top:4px;">Session: #<?php echo esc($r['session_id']); ?></div>
                        <?php endif; ?>

                        <?php if (count($items) > 0): ?>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="right nowrap" style="width:70px;">Qty</th>
                                        <th class="right nowrap" style="width:90px;">Rate</th>
                                        <th class="right nowrap" style="width:110px;">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $itemsTotal = 0.0;
                                    foreach ($items as $it):
                                        $q = (float) ($it['qty'] ?? 0);
                                        $rate = (float) ($it['unit_price_snapshot'] ?? 0);
                                        $line = $q * $rate;
                                        $itemsTotal += $line;
                                        ?>
                                        <tr>
                                            <td><?php echo esc($it['item_name_snapshot'] ?? ''); ?></td>
                                            <td class="right nowrap"><?php echo esc(rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.')); ?></td>
                                            <td class="right nowrap">Rs. <?php echo esc(number_format($rate, 0)); ?></td>
                                            <td class="right nowrap">Rs. <?php echo esc(number_format($line, 0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="3" class="right" style="font-weight:900;">Items Total</td>
                                        <td class="right nowrap" style="font-weight:900;">Rs. <?php echo esc(number_format($itemsTotal, 0)); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc($r['table_code'] ?: '-'); ?></td>
                    <td>
                        <?php if (!empty($r['id'])): ?>
                            #<?php echo esc($r['id']); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="right <?php echo $amt >= 0 ? 'credit' : 'pay'; ?>">
                        Rs. <?php echo number_format($amt, 0); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">No transaction history.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 40px; border-top: 1px dashed #ccc; padding-top: 10px; text-align: center; color: #999; font-size: 0.8rem;">
        <p>This is a system generated customer statement.</p>
    </div>

    <button class="no-print" onclick="window.print()"
        style="position:fixed; bottom:20px; right:20px; padding:15px 30px; background:#333; color:white; border:none; border-radius:50px; cursor:pointer; font-weight:bold; box-shadow:0 4px 10px rgba(0,0,0,0.3);">
        Print / Save PDF
    </button>

</body>
</html>

