<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/perf_guard.php';
require_role('ADMIN');

$date = $_GET['date'] ?? date('Y-m-d');
$formattedDate = date('d M, Y', strtotime($date));
$range = PerfGuard::makeRange($date);
$limit = 200; // Performance limit for print reports

// 1. Calculate POS Collections
$stmt = $pdo->prepare("
    SELECT SUM(cash_amount) as cash, SUM(online_amount) as online 
    FROM payment_transactions 
    WHERE created_at >= ? AND created_at < ?
");
$stmt->execute([$range['start'], $range['end']]);
$pos = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Calculate Repayments (Debt collected)
$stmt = $pdo->prepare("
    SELECT 
        ABS(SUM(CASE WHEN payment_mode = 'Online' THEN amount ELSE 0 END)) as online_paid,
        ABS(SUM(CASE WHEN payment_mode != 'Online' THEN amount ELSE 0 END)) as cash_paid
    FROM credit_transactions 
    WHERE created_at >= ? AND created_at < ? AND amount < 0
");
$stmt->execute([$range['start'], $range['end']]);
$rep = $stmt->fetch(PDO::FETCH_ASSOC);

$total_cash = ($pos['cash'] ?? 0) + ($rep['cash_paid'] ?? 0);
$total_online = ($pos['online'] ?? 0) + ($rep['online_paid'] ?? 0);
$grand_total = $total_cash + $total_online;

// 3. Get Credit Given (Revenue from debt)
$stmt = $pdo->prepare("
    SELECT SUM(amount) as credit_total 
    FROM credit_transactions
    WHERE created_at >= ? AND created_at < ? AND amount > 0
");
$stmt->execute([$range['start'], $range['end']]);
$credit_given = $stmt->fetchColumn() ?: 0;

// 4. Transactions List (3-Way UNION)
$stmt = $pdo->prepare("
    (SELECT 
        pt.created_at as paid_at,
        pt.table_name as code,
        pt.payment_method,
        pt.cash_amount as paid_amount_cash,
        pt.online_amount as paid_amount_online,
        0 as credit_amount,
        NULL as customer_name,
        'POS' as type
    FROM payment_transactions pt
    WHERE pt.created_at >= ? AND pt.created_at < ?)

    UNION ALL

    (SELECT 
        created_at as paid_at,
        'CREDIT SALES' as code,
        'CREDIT' as payment_method,
        0 as paid_amount_cash,
        0 as paid_amount_online,
        amount as credit_amount,
        customer_name,
        'CREDIT' as type
    FROM credit_transactions
    WHERE created_at >= ? AND created_at < ? AND amount > 0)

    UNION ALL

    (SELECT 
        created_at as paid_at,
        CONCAT('[REP] ', COALESCE(customer_name, 'Unknown')) as code,
        CASE WHEN payment_mode = 'Online' THEN 'FONEPAY' ELSE 'CASH' END as payment_method,
        CASE WHEN payment_mode != 'Online' THEN ABS(amount) ELSE 0 END as paid_amount_cash,
        CASE WHEN payment_mode = 'Online' THEN ABS(amount) ELSE 0 END as paid_amount_online,
        0 as credit_amount,
        customer_name,
        'REPAY' as type
    FROM credit_transactions
    WHERE created_at >= ? AND created_at < ? AND amount < 0)

    ORDER BY paid_at DESC
    LIMIT ?
");

$stmt->bindValue(1, $range['start']);
$stmt->bindValue(2, $range['end']);
$stmt->bindValue(3, $range['start']);
$stmt->bindValue(4, $range['end']);
$stmt->bindValue(5, $range['start']);
$stmt->bindValue(6, $range['end']);
$stmt->bindValue(7, $limit, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback to legacy structure (if no transactions found in new tables)
if (empty($transactions)) {
    $stmt = $pdo->prepare("
        SELECT s.id, c.code, s.opened_at as paid_at, s.payment_method, s.paid_amount_cash, s.paid_amount_online, 0 as credit_amount 
        FROM table_sessions s
        LEFT JOIN tables c ON s.table_id = c.id
        WHERE s.is_paid = 1 AND s.opened_at >= ? AND s.opened_at < ?
        ORDER BY s.opened_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $range['start']);
    $stmt->bindValue(2, $range['end']);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sales Statement - <?php echo $formattedDate; ?></title>
    <style>
        :root {
            --primary: #3e2723;
            --cash: #2e7d32;
            --online: #7b1fa2;
            --credit: #e65100;
            --bg: #fdfbf7;
        }

        body {
            font-family: 'Outfit', 'Helvetica Neue', Arial, sans-serif;
            padding: 40px;
            color: #2d2a26;
            background: #fff;
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .brand h1 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .details {
            text-align: right;
            font-size: 0.9rem;
            color: #666;
        }

        .details strong {
            color: #222;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eaeaea;
            position: relative;
        }

        .stat-card.total {
            background: #2d3436;
            color: white;
            border: none;
        }

        .stat-card.total h3 {
            color: rgba(255, 255, 255, 0.7);
        }

        .stat-card.total .val {
            color: white;
        }

        .stat-card h3 {
            margin: 0 0 8px 0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
        }

        .stat-card .val {
            font-size: 1.4rem;
            font-weight: 800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            background: #f8f9fa;
            padding: 12px 15px;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #888;
            border-bottom: 1px solid #eee;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .right {
            text-align: right;
        }

        /* Pills - Same as Dashbaord */
        .pill-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .pill {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
        }

        .pill.cash {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .pill.online {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }

        .pill.credit {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
        }

        .pill .val {
            font-size: 0.75rem;
            margin-left: 3px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }

            .stat-card {
                border: 1px solid #ddd !important;
                -webkit-print-color-adjust: exact;
            }

            .pill {
                -webkit-print-color-adjust: exact;
            }

            thead {
                display: table-header-group;
            }

            .final-total {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header">
        <div class="brand">
            <h1>Chiya Sansar</h1>
            <p style="margin: 5px 0 0; color: #666; font-weight: 500;">Daily Sales Statement</p>
        </div>
        <div class="details">
            <p>Date: <strong><?php echo $formattedDate; ?></strong></p>
            <p>Generated: <strong><?php echo date('h:i A'); ?></strong></p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card total">
            <h3>Total Collected</h3>
            <div class="val">Rs. <?php echo number_format($grand_total, 0); ?></div>
        </div>
        <div class="stat-card">
            <h3>Cash Collected</h3>
            <div class="val" style="color: var(--cash);">Rs. <?php echo number_format($total_cash, 0); ?></div>
        </div>
        <div class="stat-card">
            <h3>Online Sales</h3>
            <div class="val" style="color: var(--online);">Rs. <?php echo number_format($total_online, 0); ?></div>
        </div>
        <div class="stat-card">
            <h3>Credit Given</h3>
            <div class="val" style="color: var(--credit);">Rs. <?php echo number_format($credit_given, 0); ?></div>
        </div>
    </div>

    <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Transactions History</h3>
    <table>
        <thead>
            <tr>
                <th width="100">Time</th>
                <th width="100">Cottage</th>
                <th>Method / Breakdown</th>
                <th class="right" width="150">Amount (Collected)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $t): ?>
                    <?php
                    $time = date('h:i A', strtotime($t['paid_at']));
                    // Fix: Amount column only shows Cash + Online (Collected Money)
                    $amt_collected = $t['paid_amount_cash'] + $t['paid_amount_online'];

                    // Detailed Method Pills
                    $pills = [];
                    if ($t['payment_method'] === 'FONEPAY') {
                        $pills[] = '<div class="pill online">FULL ONLINE <span class="val">' . number_format($t['paid_amount_online'], 0) . '</span></div>';
                    } else if ($t['payment_method'] === 'SPLIT') {
                        if ($t['paid_amount_cash'] > 0)
                            $pills[] = '<div class="pill cash">CASH <span class="val">' . number_format($t['paid_amount_cash'], 0) . '</span></div>';
                        if ($t['paid_amount_online'] > 0)
                            $pills[] = '<div class="pill online">ONLINE <span class="val">' . number_format($t['paid_amount_online'], 0) . '</span></div>';
                    } else if ($t['payment_method'] === 'CREDIT' || ($t['credit_amount'] ?? 0) > 0) {
                        if ($t['paid_amount_cash'] > 0)
                            $pills[] = '<div class="pill cash">CASH <span class="val">' . number_format($t['paid_amount_cash'], 0) . '</span></div>';
                        if ($t['paid_amount_online'] > 0)
                            $pills[] = '<div class="pill online">ONLINE <span class="val">' . number_format($t['paid_amount_online'], 0) . '</span></div>';

                        $credit_val = $t['credit_amount'] ?: ($t['session_total'] ?? 0);
                        $cust = $t['customer_name'] ? ' (' . $t['customer_name'] . ')' : '';
                        $pills[] = '<div class="pill credit">CREDIT <span class="val">' . number_format($credit_val, 0) . '</span>' . $cust . '</div>';
                    } else {
                        $pills[] = '<div class="pill cash">FULL CASH <span class="val">' . number_format($t['paid_amount_cash'], 0) . '</span></div>';
                    }
                    ?>
                    <tr>
                        <td style="font-family: monospace; font-weight: 700; color: #444;"><?php echo $time; ?></td>
                        <td><strong style="color: #333;"><?php echo htmlspecialchars($t['code']); ?></strong></td>
                        <td>
                            <div class="pill-container"><?php echo implode('', $pills); ?></div>
                        </td>
                        <td class="right" style="font-weight: 800; font-size: 1rem;">
                            <?php if ($amt_collected > 0): ?>
                                Rs. <?php echo number_format($amt_collected, 0); ?>
                            <?php else: ?>
                                <span style="color: #ccc;">Rs. 0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding: 40px; color:#999;">No transactions found for this
                        date.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="final-total"
        style="background: #f8f9fa; font-weight: 800; padding: 20px; display: flex; justify-content: flex-end; align-items: center; border-bottom: 1px solid #eee; margin-top: 10px;">
        <span style="margin-right: 30px;">Total Collected (Cash + Online)</span>
        <span style="font-size: 1.2rem; color: var(--cash);">Rs. <?php echo number_format($grand_total, 0); ?></span>
    </div>

    <div
        style="margin-top: 60px; border-top: 1px dashed #ddd; padding-top: 20px; text-align: center; color: #999; font-size: 0.8rem;">
        <p>This is an official system generated sales statement from Chiya Sansar.</p>
    </div>

    <button class="no-print" onclick="window.print()"
        style="position:fixed; bottom:30px; right:30px; padding:15px 35px; background:var(--primary); color:white; border:none; border-radius:12px; cursor:pointer; font-weight:bold; box-shadow:0 8px 25px rgba(0,0,0,0.2); font-family: inherit;">Confirm
        Print / Save as PDF</button>

</body>

</html>