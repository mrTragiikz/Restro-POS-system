<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role('ADMIN');

$date = $_GET['date'] ?? date('Y-m-d');
$formattedDate = date('d M, Y', strtotime($date));

// Fetch Statistics (Exact Logic from API)

// 1. OLD OUTSTANDING (Before Date)
$stmt = $pdo->prepare("
    SELECT SUM(amount) as old_outstanding
    FROM credit_transactions
    WHERE created_at < ?
");
$stmt->execute([$date]);
$oldOutstanding = floatval($stmt->fetchColumn() ?: 0);

// 2. TODAY NEW CREDIT (Positive Amount)
$stmt = $pdo->prepare("
    SELECT SUM(amount) as today_credit 
    FROM credit_transactions 
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND amount > 0
");
$stmt->execute([$date, $date]);
$todayNewCredit = floatval($stmt->fetchColumn() ?: 0);

// 3. TODAY CLEARED PAYMENT (Negative Amount)
// 3. TODAY CLEARED PAYMENT (Negative Amount)
$stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as cleared_payment,
        SUM(CASE WHEN note LIKE '%Online%' THEN amount ELSE 0 END) as cleared_online,
        SUM(CASE WHEN note LIKE '%Cash%' OR (note NOT LIKE '%Online%' AND note NOT LIKE '%Split%') THEN amount ELSE 0 END) as cleared_cash
    FROM credit_transactions 
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND amount < 0
");
$stmt->execute([$date, $date]);
$clearedData = $stmt->fetch(PDO::FETCH_ASSOC);
$todayCleared = abs(floatval($clearedData['cleared_payment'] ?? 0));
$todayClearedCash = abs(floatval($clearedData['cleared_cash'] ?? 0));
$todayClearedOnline = abs(floatval($clearedData['cleared_online'] ?? 0));


// Fetch Lists

// 4. Repayment List (Who Paid)
$stmt = $pdo->prepare("
    SELECT ct.id, ct.amount, ct.note, ct.created_at, COALESCE(ct.customer_name, c.name) as customer_name, ct.session_id
    FROM credit_transactions ct
    LEFT JOIN credit_customers c ON ct.customer_id = c.id
    WHERE ct.created_at >= ? AND ct.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND ct.amount < 0
    ORDER BY ct.created_at DESC
");
$stmt->execute([$date, $date]);
$repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. New Credit List (Who Took)
$stmt = $pdo->prepare("
    SELECT ct.id, ct.amount, ct.note, ct.created_at, COALESCE(ct.customer_name, c.name) as customer_name, ct.session_id
    FROM credit_transactions ct
    LEFT JOIN credit_customers c ON ct.customer_id = c.id
    WHERE ct.created_at >= ? AND ct.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND ct.amount > 0
    ORDER BY ct.created_at DESC
");
$stmt->execute([$date, $date]);
$newCredits = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Credit Report - <?php echo $formattedDate; ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            padding: 40px;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }

        .brand h1 {
            margin: 0;
            color: #3e2723;
        }

        .details {
            text-align: right;
        }

        .stat-box {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            flex: 1;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card .val {
            font-size: 1.4rem;
            font-weight: bold;
            color: #222;
        }

        .note {
            font-size: 0.7rem;
            color: #888;
            margin-top: 5px;
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
            font-size: 0.75rem;
            color: #555;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .right {
            text-align: right;
        }

        .section-title {
            margin-top: 40px;
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: bold;
            color: #3e2723;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header">
        <div class="brand">
            <h1>Chiya Sansar</h1>
            <p style="margin: 5px 0 0; color: #777;">Daily Credit Report</p>
        </div>
        <div class="details">
            <p><strong>Date:</strong> <?php echo $formattedDate; ?></p>
            <p><strong>Generated:</strong> <?php echo date('h:i A'); ?></p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stat-box">
        <div class="card">
            <h3>Old Outstanding</h3>
            <div class="val">Rs. <?php echo number_format($oldOutstanding, 2); ?></div>
            <div class="note">Balance before today</div>
        </div>
        <div class="card" style="background:#fff3e0; border-color:#ffe0b2;">
            <h3>New Credit Given</h3>
            <div class="val" style="color:#e65100;">Rs. <?php echo number_format($todayNewCredit, 2); ?></div>
            <div class="note">Fresh credit today</div>
        </div>
        <div class="card" style="background:#e8f5e9; border-color:#c8e6c9;">
            <h3>Credit Repaid</h3>
            <div class="val" style="color:#2e7d32;">Rs. <?php echo number_format($todayCleared, 2); ?></div>
            <div class="note" style="display:flex; justify-content:center; gap:10px; margin-top:5px;">
                <span
                    style="background:#fff; padding:2px 6px; border-radius:4px; font-weight:bold; color:#2e7d32; font-size:0.7rem;">Cash:
                    <?php echo number_format($todayClearedCash, 0); ?></span>
                <span
                    style="background:#fff; padding:2px 6px; border-radius:4px; font-weight:bold; color:#1565c0; font-size:0.7rem;">Online:
                    <?php echo number_format($todayClearedOnline, 0); ?></span>
            </div>
            <div class="note" style="margin-top:2px;">Collected today</div>
        </div>
    </div>

    <!-- 1. New Credit Table -->
    <div class="section-title">Fresh Credit Given Today</div>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Customer</th>
                <th>Note</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($newCredits) > 0): ?>
                <?php foreach ($newCredits as $row): ?>
                    <tr>
                        <td style="font-weight:bold; color:#333;"><?php echo date('h:i A', strtotime($row['created_at'])); ?>
                        </td>
                        <td style="font-weight:600;">
                            <?php echo htmlspecialchars($row['customer_name']); ?>
                            <?php if (!empty($row['session_id'])): ?>
                                <div
                                    style="font-size: 0.75rem; color: #666; font-weight: normal; margin-top: 4px; font-style: italic;">
                                    <?php
                                    $istmt = $pdo->prepare("SELECT item_name_snapshot as name, qty 
                                                           FROM order_items 
                                                           WHERE order_id IN (SELECT id FROM orders WHERE session_id = ?)");
                                    $istmt->execute([$row['session_id']]);
                                    $items = $istmt->fetchAll(PDO::FETCH_ASSOC);
                                    if ($items) {
                                        $itemList = [];
                                        foreach ($items as $item) {
                                            $itemList[] = $item['name'] . ' x ' . $item['qty'];
                                        }
                                        echo "Items: " . htmlspecialchars(implode(', ', $itemList));
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#666;"><?php echo htmlspecialchars($row['note'] ?? '-'); ?></td>
                        <td class="right" style="color:#e65100; font-weight:bold;">Rs.
                            <?php echo number_format($row['amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#fffbf0; font-weight:bold;">
                    <td colspan="3" class="right">Total Given</td>
                    <td class="right">Rs. <?php echo number_format($todayNewCredit, 2); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="empty">No new credit given today.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 2. Repayment Table -->
    <div class="section-title">Credit Payments Collected Today</div>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Customer</th>
                <th>Note</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($repayments) > 0): ?>
                <?php foreach ($repayments as $row): ?>
                    <tr>
                        <td style="font-weight:bold; color:#333;"><?php echo date('h:i A', strtotime($row['created_at'])); ?>
                        </td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td style="color:#666;"><?php echo htmlspecialchars($row['note'] ?? '-'); ?></td>
                        <td class="right" style="color:#2e7d32; font-weight:bold;">Rs.
                            <?php echo number_format(abs($row['amount']), 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f1f8e9; font-weight:bold;">
                    <td colspan="3" class="right">Total Collected</td>
                    <td class="right">Rs. <?php echo number_format($todayCleared, 2); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="empty">No credit repayments received today.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div
        style="margin-top: 50px; border-top: 1px dashed #ccc; padding-top: 10px; text-align: center; color: #999; font-size: 0.8rem;">
        <p>This is a system generated credit report.</p>
    </div>

    <button class="no-print" onclick="window.print()"
        style="position:fixed; bottom:20px; right:20px; padding:15px 30px; background:#333; color:white; border:none; border-radius:50px; cursor:pointer; font-weight:bold; box-shadow:0 4px 10px rgba(0,0,0,0.3);">Print
        / Save PDF</button>

</body>

</html>