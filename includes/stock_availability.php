<?php
// includes/stock_availability.php

require_once __DIR__ . '/stock_schema.php';

/**
 * Attach counter stock tracking info to menu item rows.
 *
 * @param PDO $pdo
 * @param array<int, array<string, mixed>> $menuItems
 * @return array<int, array<string, mixed>>
 */
function enrich_menu_items_with_stock(PDO $pdo, array $menuItems): array
{
    ensure_stock_schema($pdo);

    $tracked = [];

    try {
        $stmt = $pdo->query("
            SELECT menu_item_id, stock_qty
            FROM stock_items
            WHERE stock_area = 'counter'
              AND track_stock = 1
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tracked[(int) $row['menu_item_id']] = (int) $row['stock_qty'];
        }
    } catch (Exception $e) {
        // stock_items table may not exist on older installs
    }

    $reserved = get_pending_counter_stock_reservations($pdo);

    foreach ($menuItems as &$item) {
        $id = (int) $item['id'];
        if (array_key_exists($id, $tracked)) {
            $qty = $tracked[$id];
            $available = max(0, $qty - (int) ($reserved[$id] ?? 0));
            $item['stock_tracked'] = 1;
            $item['stock_qty'] = $qty;
            $item['stock_available'] = $available;
            $item['out_of_stock'] = $qty <= 0 ? 1 : 0;
        } else {
            $item['stock_tracked'] = 0;
            $item['stock_qty'] = null;
            $item['stock_available'] = null;
            $item['out_of_stock'] = 0;
        }
    }
    unset($item);

    return $menuItems;
}

/**
 * Qty on open tables not yet served (still waiting — stock not deducted until serve).
 *
 * @return array<int, int> menu_item_id => reserved qty
 */
function get_pending_counter_stock_reservations(PDO $pdo): array
{
    $reserved = [];

    try {
        $stmt = $pdo->query("
            SELECT oi.menu_item_id, COALESCE(SUM(oi.qty), 0) AS reserved
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN table_sessions ts ON o.session_id = ts.id AND ts.closed_at IS NULL
            INNER JOIN stock_items si ON si.menu_item_id = oi.menu_item_id
                AND si.stock_area = 'counter'
                AND si.track_stock = 1
                AND (si.unit_type IS NULL OR si.unit_type = 'piece')
            WHERE oi.stock_deducted = 0
              AND oi.kitchen_status = 'PENDING'
            GROUP BY oi.menu_item_id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reserved[(int) $row['menu_item_id']] = (int) $row['reserved'];
        }
    } catch (Exception $e) {
    }

    return $reserved;
}

/**
 * @return array{out_of_stock: int[], stock: array<int, int>, available: array<int, int>}
 */
function get_counter_stock_availability(PDO $pdo): array
{
    ensure_stock_schema($pdo);

    $stock = [];
    $available = [];
    $outOfStock = [];
    $reserved = get_pending_counter_stock_reservations($pdo);

    try {
        $stmt = $pdo->query("
            SELECT menu_item_id, stock_qty
            FROM stock_items
            WHERE stock_area = 'counter'
              AND track_stock = 1
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['menu_item_id'];
            $qty = (int) $row['stock_qty'];
            $avail = max(0, $qty - (int) ($reserved[$id] ?? 0));
            $stock[$id] = $qty;
            $available[$id] = $avail;
            if ($qty <= 0) {
                $outOfStock[] = $id;
            }
        }
    } catch (Exception $e) {
    }

    return ['out_of_stock' => $outOfStock, 'stock' => $stock, 'available' => $available];
}

/**
 * Block orders that exceed counter stock (inside an open transaction).
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<string, mixed>|null Error payload, or null if OK
 */
function validate_counter_stock_order_items(PDO $pdo, array $items): ?array
{
    ensure_stock_schema($pdo);

    $requested = [];
    $names = [];
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? $item['menu_item_id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) {
            continue;
        }
        $requested[$id] = ($requested[$id] ?? 0) + $qty;
        if (!empty($item['name'])) {
            $names[$id] = (string) $item['name'];
        }
    }

    if ($requested === []) {
        return null;
    }

    $ids = array_keys($requested);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT si.menu_item_id, si.stock_qty, mi.name
        FROM stock_items si
        INNER JOIN menu_items mi ON mi.id = si.menu_item_id
        WHERE si.stock_area = 'counter'
          AND si.track_stock = 1
          AND (si.unit_type IS NULL OR si.unit_type = 'piece')
          AND si.menu_item_id IN ({$placeholders})
        FOR UPDATE
    ");
    $stmt->execute($ids);

    $tracked = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tracked[(int) $row['menu_item_id']] = $row;
    }

    if ($tracked === []) {
        return null;
    }

    $reserved = get_pending_counter_stock_reservations($pdo);

    foreach ($requested as $menuItemId => $qty) {
        if (!isset($tracked[$menuItemId])) {
            continue;
        }
        $stockQty = (int) $tracked[$menuItemId]['stock_qty'];
        $available = max(0, $stockQty - (int) ($reserved[$menuItemId] ?? 0));
        if ($qty > $available) {
            $name = $names[$menuItemId] ?? (string) ($tracked[$menuItemId]['name'] ?? 'Item');
            return [
                'menu_item_id' => $menuItemId,
                'name' => $name,
                'available' => $available,
                'requested' => $qty,
                'message' => stock_order_limit_message($name, $available, $qty),
            ];
        }
    }

    return null;
}

function stock_order_limit_message(string $name, int $available, int $requested): string
{
    if ($available <= 0) {
        return sprintf('%s is out of stock right now, you can\'t order %d.', $name, $requested);
    }

    return sprintf(
        'Stock for %s is %d, can\'t take an order of %d.',
        $name,
        $available,
        $requested
    );
}
