<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role('ADMIN');

$session_id = $_GET['session_id'] ?? null;
$kot = strtolower($_GET['kot'] ?? 'kitchen');

if (!$session_id) {
    die('Invalid Session ID');
}

if (!in_array($kot, ['kitchen', 'counter'], true)) {
    $kot = 'kitchen';
}

$is_counter = ($kot === 'counter') ? 1 : 0;

$stmt = $pdo->prepare("
    SELECT cs.*, c.code AS table_code
    FROM table_sessions cs
    JOIN tables c ON cs.table_id = c.id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    die('Session not found');
}

try {
    $pdo->beginTransaction();

    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT oi.kot_printed_at) AS prior_kots
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE o.session_id = ?
          AND oi.kot_printed_at IS NOT NULL
          AND oi.kitchen_status != 'CANCELLED'
          AND COALESCE(m.is_counter_item, 0) = ?
    ");
    $countStmt->execute([$session_id, $is_counter]);
    $priorKots = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT oi.id, oi.item_name_snapshot, oi.qty, oi.note, oi.kitchen_status,
               COALESCE(m.is_counter_item, 0) AS is_counter_item
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE o.session_id = ?
          AND oi.kitchen_status != 'CANCELLED'
          AND oi.is_paid = 0
          AND oi.kot_printed_at IS NULL
          AND COALESCE(m.is_counter_item, 0) = ?
        ORDER BY oi.id ASC
        FOR UPDATE
    ");
    $stmt->execute([$session_id, $is_counter]);
    $raw_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($raw_items)) {
        $ids = array_column($raw_items, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $mark = $pdo->prepare("UPDATE order_items SET kot_printed_at = NOW() WHERE id IN ($placeholders) AND kot_printed_at IS NULL");
        $mark->execute($ids);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Failed to load KOT items');
}

$grouped = [];
foreach ($raw_items as $item) {
    $key = $item['item_name_snapshot'] . '|' . ($item['note'] ?? '');
    if (!isset($grouped[$key])) {
        $grouped[$key] = $item;
        $grouped[$key]['qty'] = (int) $item['qty'];
    } else {
        $grouped[$key]['qty'] += (int) $item['qty'];
    }
}
$items = array_values($grouped);

$kotRound = !empty($items) ? ($priorKots + 1) : 0;
$isAddonKot = $priorKots > 0;
$tableLabel = $session['table_code'];

if ($isAddonKot) {
    $kotBadgeLabel = 'MORE ITEMS — SAME TABLE';
    $kotSubtitle = 'This table already has an active order. Please also prepare the items listed below.';
    $kotNoteLine = 'Slip ' . $kotRound . ' · added after previous order';
} else {
    $kotBadgeLabel = 'NEW ORDER';
    $kotSubtitle = 'First order for ' . $tableLabel . ' this visit.';
    $kotNoteLine = 'Slip 1 · first kitchen ticket';
}

$kot_title = $is_counter ? 'Counter KOT' : 'Kitchen KOT';
$kot_copy = $is_counter ? 'Counter Copy' : 'Kitchen Copy';

$kot_dt = format_nepal_datetime('now');
$time_12 = $kot_dt['time'];
$date_str = $kot_dt['date'];
$empty_msg = $is_counter ? 'No new counter items to print.' : 'No new kitchen items to print.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($kot_title); ?> - <?php echo htmlspecialchars($session['table_code']); ?></title>
    <style>
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
            color: #000;
            font-weight: bold;
            font-size: 12px;
            line-height: 1.25;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 6px;
        }

        .kot-title {
            font-size: 16px;
            text-transform: uppercase;
            border: 2px solid #000;
            padding: 4px;
            margin: 6px 0;
            color: #000;
        }

        .kot-type-badge {
            display: block;
            font-size: 10px;
            background: #000;
            color: #fff;
            padding: 4px 8px;
            margin-top: 6px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .kot-subtitle {
            font-size: 10px;
            font-weight: normal;
            color: #000;
            margin-top: 5px;
            line-height: 1.35;
            padding: 0 4px;
        }

        .kot-slip-note {
            font-size: 9px;
            font-weight: normal;
            color: #333;
            margin-top: 4px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .divider {
            border-bottom: 1px dashed #000;
            margin: 6px 0;
        }

        .table-header {
            display: flex;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 4px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .col-item {
            width: 70%;
        }

        .col-qty {
            width: 30%;
            text-align: center;
        }

        .item-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px dashed #000;
        }

        .item-note {
            display: block;
            font-size: 10px;
            font-weight: normal;
            margin-top: 2px;
        }

        .btn-print {
            background: #000;
            color: #fff;
            padding: 8px;
            border: 2px solid #000;
            width: 100%;
            margin-top: 8px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
        }

        .btn-close {
            background: #fff;
            color: #000;
            padding: 8px;
            border: 2px solid #000;
            width: 100%;
            margin-top: 4px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin:0; font-size:14px;">CHIYA SANSAR</h1>
        <div class="kot-title"><?php echo htmlspecialchars($kot_title); ?></div>
        <?php if (!empty($items)): ?>
            <div class="kot-type-badge"><?php echo htmlspecialchars($kotBadgeLabel); ?></div>
            <div class="kot-subtitle"><?php echo htmlspecialchars($kotSubtitle); ?></div>
            <div class="kot-slip-note"><?php echo htmlspecialchars($kotNoteLine); ?></div>
        <?php endif; ?>
    </div>

    <div class="info-row">
        <span>Table: <strong><?php echo htmlspecialchars($session['table_code']); ?></strong></span>
        <span>Slip <?php echo (int) $kotRound; ?></span>
    </div>
    <div class="info-row">
        <span><?php echo $date_str; ?></span>
        <span><?php echo $time_12; ?></span>
    </div>

    <div class="divider"></div>

    <div class="table-header">
        <div class="col-item">Item</div>
        <div class="col-qty">Qty</div>
    </div>

    <?php if (empty($items)): ?>
        <p style="text-align:center; font-weight:normal;"><?php echo htmlspecialchars($empty_msg); ?></p>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="item-row">
                <div class="col-item">
                    <?php echo htmlspecialchars($item['item_name_snapshot']); ?>
                    <?php if (!empty($item['note'])): ?>
                        <span class="item-note">Note: <?php echo htmlspecialchars($item['note']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="col-qty"><?php echo (int) $item['qty']; ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="divider"></div>
    <p style="text-align:center; font-size:10px; margin:4px 0;">— <?php echo htmlspecialchars($kot_copy); ?> —</p>

    <button class="btn-print no-print" onclick="window.print()">Print <?php echo htmlspecialchars($kot_title); ?></button>
    <button class="btn-close no-print" onclick="window.close()">Close</button>
</body>

</html>
