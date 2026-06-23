<?php
// includes/stock_helpers.php — Shared stock SQL helpers

/** When this stock list row was created (re-add starts a new history window for Added). */
function stock_movements_since(array $row): string
{
    return $row['created_at'] ?? date('Y-m-d H:i:s');
}

/** Earliest datetime for sold stats / order stock (respects tracking toggles). */
function stock_sold_stats_since(array $row): string
{
    $candidates = array_filter([
        $row['created_at'] ?? null,
        $row['sold_tracking_started_at'] ?? null,
    ], static fn($v) => $v !== null && $v !== '');

    return $candidates !== [] ? max($candidates) : stock_movements_since($row);
}

/** For order deduct/restore — order must belong to the active stock + tracking window. */
function stock_order_lifecycle_since(array $row): string
{
    $created = $row['created_at'] ?? '1970-01-01 00:00:00';
    $stock = ($row['stock_tracking_started_at'] ?? null) ?: $created;
    $sold = ($row['sold_tracking_started_at'] ?? null) ?: $created;

    return max($created, $stock, $sold);
}

/** SQL expression: movement/order timestamps on or after the stock item lifecycle start. */
function stock_sql_lifecycle_since(string $stockAlias = 'si'): string
{
    $s = $stockAlias;
    return "GREATEST(
        {$s}.created_at,
        COALESCE({$s}.stock_tracking_started_at, {$s}.created_at),
        COALESCE({$s}.sold_tracking_started_at, {$s}.created_at)
    )";
}

function stock_item_select_sql(): string
{
    // OPTIMIZATION: Removed slow correlated subqueries.
    // total_sold_pieces and total_used_units are bulk-fetched via stock_enrich_list_totals()
    return "SELECT si.*, mi.name, mi.price, 0 AS total_sold_pieces, 0 AS total_used_units";
}

function stock_enrich_list_totals(array $rows): array
{
    if (empty($rows)) {
        return [];
    }

    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT 
            sm.stock_item_id,
            SUM(
                CASE WHEN sm.movement_type = 'sale' 
                     AND si.track_sold = 1 
                     AND si.sold_tracking_started_at IS NOT NULL 
                     AND sm.created_at >= si.sold_tracking_started_at 
                     AND sm.created_at >= GREATEST(si.created_at, COALESCE(si.stock_tracking_started_at, si.created_at), COALESCE(si.sold_tracking_started_at, si.created_at))
                THEN -sm.qty_change ELSE 0 END
            ) as total_sold,
            SUM(
                CASE WHEN sm.movement_type = 'adjust' 
                     AND sm.qty_change < 0 
                     AND sm.created_at >= GREATEST(si.created_at, COALESCE(si.stock_tracking_started_at, si.created_at), COALESCE(si.sold_tracking_started_at, si.created_at))
                THEN ABS(sm.qty_change) ELSE 0 END
            ) as total_used
        FROM stock_movements sm
        INNER JOIN stock_items si ON si.id = sm.stock_item_id
        WHERE sm.stock_item_id IN ($placeholders)
        GROUP BY sm.stock_item_id
    ";

    $totals = db_query_all($sql, $ids);
    $totalsMap = [];
    foreach ($totals as $t) {
        $totalsMap[$t['stock_item_id']] = $t;
    }

    foreach ($rows as &$row) {
        $id = $row['id'];
        if (isset($totalsMap[$id])) {
            $row['total_sold_pieces'] = $totalsMap[$id]['total_sold'];
            $row['total_used_units'] = $totalsMap[$id]['total_used'];
        }
    }
    
    return $rows;
}

function stock_apply_tracking_flags(array $row, int $trackStock, int $trackSold): array
{
    $prevTrackStock = (int) ($row['track_stock'] ?? 0);
    $prevTrackSold = (int) ($row['track_sold'] ?? 0);

    $stockSince = $row['stock_tracking_started_at'] ?? null;
    $soldSince = $row['sold_tracking_started_at'] ?? null;
    $now = date('Y-m-d H:i:s');

    if ($trackStock && !$prevTrackStock) {
        $stockSince = $row['created_at'] ?? $now;
    }
    if (!$trackStock) {
        $stockSince = null;
    }

    if ($trackSold && !$prevTrackSold) {
        $soldSince = $row['stock_tracking_started_at'] ?? $row['created_at'] ?? $now;
    }
    if (!$trackSold) {
        $soldSince = null;
    }

    return [$stockSince, $soldSince];
}

/** Persist counter tracking toggles when sent with adjust_qty or other stock actions. */
function stock_sync_tracking_from_request(array $row): void
{
    if (($row['stock_area'] ?? '') !== 'counter') {
        return;
    }
    if (!array_key_exists('track_stock', $_POST) && !array_key_exists('track_sold', $_POST)) {
        return;
    }

    $trackStock = !empty($_POST['track_stock']) ? 1 : 0;
    $trackSold = !empty($_POST['track_sold']) ? 1 : 0;
    [$stockSince, $soldSince] = stock_apply_tracking_flags($row, $trackStock, $trackSold);

    db_execute(
        "UPDATE stock_items SET track_stock = ?, track_sold = ?,
            stock_tracking_started_at = ?, sold_tracking_started_at = ?
         WHERE id = ?",
        [$trackStock, $trackSold, $stockSince, $soldSince, $row['id']]
    );
}

function stock_month_start(): string
{
    return date('Y-m-01 00:00:00');
}

function stock_month_stats(int $stockItemId, ?string $soldSince, ?string $movementsSince): array
{
    $monthStart = stock_month_start();

    $soldFrom = $monthStart;
    if ($soldSince !== null && $soldSince !== '' && $soldSince > $monthStart) {
        $soldFrom = $soldSince;
    }

    $addedFrom = $monthStart;
    if ($movementsSince !== null && $movementsSince !== '' && $movementsSince > $monthStart) {
        $addedFrom = $movementsSince;
    }

    $soldRow = db_query_one(
        "SELECT COALESCE(SUM(-qty_change), 0) AS total
         FROM stock_movements
         WHERE stock_item_id = ?
           AND movement_type = 'sale'
           AND created_at >= ?",
        [$stockItemId, $soldFrom]
    );

    $addedRow = db_query_one(
        "SELECT COALESCE(SUM(
            CASE
                WHEN qty_change > 0 THEN qty_change
                WHEN movement_type = 'setup' AND qty_change = 0 AND qty_after > 0 THEN qty_after
                ELSE 0
            END
        ), 0) AS total
         FROM stock_movements
         WHERE stock_item_id = ?
           AND movement_type IN ('count', 'add', 'setup', 'adjust')
           AND created_at >= ?",
        [$stockItemId, $addedFrom]
    );

    return [
        'sold' => (int) ($soldRow['total'] ?? 0),
        'added' => (int) ($addedRow['total'] ?? 0),
        'monthLabel' => date('F Y'),
    ];
}

function stock_day_opening_qty(int $stockItemId, string $dayStart, ?string $movementsSince = null): int
{
    $effectiveStart = $dayStart;
    if ($movementsSince !== null && $movementsSince !== '' && $movementsSince > $dayStart) {
        $effectiveStart = $movementsSince;
    }

    $row = db_query_one(
        "SELECT qty_after FROM stock_movements
         WHERE stock_item_id = ?
           AND created_at < ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1",
        [$stockItemId, $effectiveStart]
    );
    if ($row !== null) {
        return (int) (float) ($row['qty_after'] ?? 0);
    }

    if ($movementsSince !== null && $movementsSince !== '' && $movementsSince > $dayStart) {
        return 0;
    }

    return 0;
}

function stock_day_closing_qty(int $stockItemId, string $dayEnd, ?string $movementsSince = null): int
{
    $params = [$stockItemId, $dayEnd];
    $sql = "SELECT qty_after FROM stock_movements
         WHERE stock_item_id = ?
           AND created_at <= ?";
    if ($movementsSince !== null && $movementsSince !== '') {
        $sql .= " AND created_at >= ?";
        $params[] = $movementsSince;
    }
    $sql .= " ORDER BY created_at DESC, id DESC LIMIT 1";

    $row = db_query_one($sql, $params);
    return $row !== null ? (int) (float) ($row['qty_after'] ?? 0) : 0;
}

function stock_day_stats(int $stockItemId, string $dateYmd, ?string $soldSince, ?string $movementsSince): array
{
    $dayStart = $dateYmd . ' 00:00:00';
    $dayEnd = $dateYmd . ' 23:59:59';

    if ($movementsSince !== null && $movementsSince !== '' && $movementsSince > $dayEnd) {
        $dt = DateTime::createFromFormat('Y-m-d', $dateYmd);
        return [
            'sold' => 0,
            'added' => 0,
            'used' => 0,
            'correctedDown' => 0,
            'opening' => 0,
            'remaining' => 0,
            'dateLabel' => $dt ? $dt->format('j M Y') : $dateYmd,
        ];
    }

    $addedDayStart = $dayStart;
    if ($movementsSince !== null && $movementsSince !== '' && $movementsSince > $dayStart) {
        $addedDayStart = $movementsSince;
    }

    $soldDayStart = $dayStart;
    if ($soldSince !== null && $soldSince !== '' && $soldSince > $dayStart) {
        $soldDayStart = $soldSince;
    }

    $scanStart = $addedDayStart < $soldDayStart ? $addedDayStart : $soldDayStart;

    // Single indexed scan for the day window (fast even with years of history on other dates).
    $aggRow = db_query_one(
        "SELECT
            COALESCE(SUM(CASE
                WHEN movement_type = 'sale'
                    AND created_at >= ?
                    AND created_at <= ?
                THEN -qty_change ELSE 0 END), 0) AS sold,
            COALESCE(SUM(CASE
                WHEN movement_type IN ('add', 'setup') AND qty_change > 0
                    AND created_at >= ?
                    AND created_at <= ?
                THEN qty_change
                WHEN movement_type = 'count' AND qty_change > 0
                    AND created_at >= ?
                    AND created_at <= ?
                THEN qty_change
                WHEN movement_type = 'adjust' AND qty_change > 0
                    AND created_at >= ?
                    AND created_at <= ?
                THEN qty_change
                ELSE 0 END), 0) AS added,
            COALESCE(SUM(CASE
                WHEN movement_type = 'adjust' AND qty_change < 0
                    AND created_at >= ?
                    AND created_at <= ?
                THEN ABS(qty_change) ELSE 0 END), 0) AS used,
            COALESCE(SUM(CASE
                WHEN movement_type = 'count' AND qty_change < 0
                    AND created_at >= ?
                    AND created_at <= ?
                THEN ABS(qty_change) ELSE 0 END), 0) AS corrected_down
         FROM stock_movements
         WHERE stock_item_id = ?
           AND created_at >= ?
           AND created_at <= ?",
        [
            $soldDayStart, $dayEnd,
            $addedDayStart, $dayEnd,
            $addedDayStart, $dayEnd,
            $addedDayStart, $dayEnd,
            $addedDayStart, $dayEnd,
            $addedDayStart, $dayEnd,
            $stockItemId, $scanStart, $dayEnd,
        ]
    );

    $opening = stock_day_opening_qty($stockItemId, $dayStart, $movementsSince);
    $closing = stock_day_closing_qty($stockItemId, $dayEnd, $movementsSince);

    $dt = DateTime::createFromFormat('Y-m-d', $dateYmd);
    $dateLabel = $dt ? $dt->format('j M Y') : $dateYmd;

    return [
        'sold' => (int) ($aggRow['sold'] ?? 0),
        'added' => (int) ($aggRow['added'] ?? 0),
        'used' => (int) ($aggRow['used'] ?? 0),
        'correctedDown' => (int) ($aggRow['corrected_down'] ?? 0),
        'opening' => $opening,
        'remaining' => $closing,
        'dateLabel' => $dateLabel,
    ];
}

function stock_enrich_item(array $mapped, int $stockItemId, array $row): array
{
    $soldSince = stock_sold_stats_since($row);
    $movementsSince = stock_movements_since($row);
    $stats = stock_month_stats($stockItemId, $soldSince, $movementsSince);
    $mapped['monthSold'] = $stats['sold'];
    $mapped['monthAdded'] = $stats['added'];
    $mapped['monthLabel'] = $stats['monthLabel'];
    $mapped['historySince'] = $movementsSince;
    return $mapped;
}
