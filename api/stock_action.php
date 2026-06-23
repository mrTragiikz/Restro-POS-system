<?php
// api/stock_action.php — Stock management (counter / kitchen)
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/stock_schema.php';
require_once '../includes/stock_helpers.php';
require_once '../includes/stock_deduct.php';
require_once '../includes/menu_pin.php';

header('Content-Type: application/json');

if (isset($pdo) && $pdo instanceof PDO) {
    ensure_stock_schema($pdo);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    menu_pin_require_unlocked();
}

const STOCK_AREAS = ['counter', 'kitchen'];
const STOCK_UNITS = [
    'piece', 'kg', 'gram', 'liter',
    'bora', 'packet', 'crate', 'plastic', 'poka', 'glass',
];

function stock_is_integer_unit(string $unit): bool
{
    return in_array($unit, ['piece', 'gram', 'bora', 'packet', 'crate', 'plastic', 'poka', 'glass'], true);
}

function stock_validate_price_string(string $raw): float
{
    $raw = trim($raw);
    if ($raw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        throw new Exception('Price must be a whole number or up to 2 decimal places (e.g. 55 or 55.50)');
    }
    return round((float) $raw, 2);
}

/**
 * @return int|float
 */
function stock_validate_amount_string(string $unit, string $raw)
{
    $raw = trim($raw);
    if ($raw === '') {
        throw new Exception('Enter a valid amount');
    }
    if (stock_is_integer_unit($unit)) {
        if (!preg_match('/^\d+$/', $raw)) {
            throw new Exception('Amount must be a whole number (no decimals)');
        }
        return (int) $raw;
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        throw new Exception('Amount must be a whole number or up to 2 decimal places only');
    }
    return round((float) $raw, 2);
}

function stock_map_row(array $row): array
{
    $area = $row['stock_area'];
    $unitType = $row['unit_type'];
    $unitValue = $row['unit_value'];

    if ($unitValue !== null && $unitValue !== '') {
        $unitValue = stock_is_integer_unit((string) $unitType)
            ? (int) $unitValue
            : (float) $unitValue;
    } else {
        $unitValue = null;
    }

    return [
        'id' => (int) $row['menu_item_id'],
        'stockId' => (int) $row['id'],
        'name' => $row['name'],
        'price' => (float) $row['price'],
        'area' => $area,
        'unit' => $unitType,
        'unitValue' => $unitValue,
        'trackStock' => $area === 'counter' ? (int) $row['track_stock'] : null,
        'stockQty' => (int) $row['stock_qty'],
        'addedAt' => $row['created_at'] ?? null,
        'totalSoldPieces' => $area === 'counter' ? (int) ($row['total_sold_pieces'] ?? 0) : 0,
        'totalUsedUnits' => $area === 'kitchen' ? (float) ($row['total_used_units'] ?? 0) : null,
        'trackSold' => $area === 'counter' ? (int) ($row['track_sold'] ?? 0) : 0,
        'stockTrackingSince' => $row['stock_tracking_started_at'] ?? null,
        'soldTrackingSince' => $row['sold_tracking_started_at'] ?? null,
        'historySince' => stock_movements_since($row),
    ];
}

function stock_validate_area(string $area): void
{
    if (!in_array($area, STOCK_AREAS, true)) {
        throw new Exception('Invalid stock area');
    }
}

function stock_get_item(int $menuItemId, string $area): ?array
{
    return db_query_one(
        "SELECT si.*, mi.name, mi.price
         FROM stock_items si
         INNER JOIN menu_items mi ON mi.id = si.menu_item_id
         WHERE si.menu_item_id = ? AND si.stock_area = ?",
        [$menuItemId, $area]
    ) ?: null;
}

function stock_get_item_mapped(int $menuItemId, string $area): ?array
{
    $sql = stock_item_select_sql() . "
         FROM stock_items si
         INNER JOIN menu_items mi ON mi.id = si.menu_item_id
         WHERE si.menu_item_id = ? AND si.stock_area = ?";
    $row = db_query_one($sql, [$menuItemId, $area]);
    if (!$row) {
        return null;
    }

    // OPTIMIZATION: bulk fetch totals for this single row
    $rows = stock_enrich_list_totals([$row]);
    $row = $rows[0];

    $mapped = stock_map_row($row);
    return stock_enrich_item($mapped, (int) $row['id'], $row);
}

function stock_log_movement(int $stockItemId, string $type, float $qtyChange, float $qtyAfter, ?string $note = null): void
{
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    db_execute(
        "INSERT INTO stock_movements (stock_item_id, movement_type, qty_change, qty_after, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$stockItemId, $type, $qtyChange, $qtyAfter, $note, $userId]
    );
}

function stock_unit_display(string $unit): string
{
    $labels = [
        'piece' => 'pieces',
        'kg' => 'kg',
        'gram' => 'gram',
        'liter' => 'liter',
        'bora' => 'bora',
        'packet' => 'packet',
        'crate' => 'crate',
        'plastic' => 'plastic',
        'poka' => 'poka',
        'glass' => 'glass',
    ];
    return $labels[$unit] ?? $unit;
}

function stock_edit_correct_note($before, $after, string $unit = 'piece', ?string $itemName = null): string
{
    $beforeDisp = stock_is_integer_unit($unit) ? (int) $before : (float) $before;
    $afterDisp = stock_is_integer_unit($unit) ? (int) $after : (float) $after;
    $nameSuffix = ($itemName !== null && $itemName !== '') ? ' of ' . $itemName : '';

    if ((float) $beforeDisp === (float) $afterDisp) {
        if ($unit === 'piece') {
            $pieceWord = $afterDisp === 1 ? 'piece' : 'pieces';
            return sprintf('Checked stock, still %d %s.', $afterDisp, $pieceWord);
        }
        return 'Checked stock, still ' . $afterDisp . ' ' . stock_unit_display($unit) . '.';
    }

    if ((float) $beforeDisp === 0.0 && (float) $afterDisp > 0.0) {
        if ($unit === 'piece') {
            $pieceWord = $afterDisp === 1 ? 'piece' : 'pieces';
            return sprintf('Added %d %s%s.', $afterDisp, $pieceWord, $nameSuffix);
        }
        return sprintf(
            'Added %s %s%s.',
            $afterDisp,
            stock_unit_display($unit),
            $nameSuffix
        );
    }

    if ($unit === 'piece') {
        $beforeWord = $beforeDisp === 1 ? 'piece' : 'pieces';
        $afterWord = $afterDisp === 1 ? 'piece' : 'pieces';
        return sprintf(
            'Stock was %d %s. You edited and corrected to %d %s.',
            $beforeDisp,
            $beforeWord,
            $afterDisp,
            $afterWord
        );
    }

    return sprintf(
        'Stock was %s %s. You edited and corrected to %s %s.',
        $beforeDisp,
        stock_unit_display($unit),
        $afterDisp,
        stock_unit_display($unit)
    );
}

function stock_kitchen_qty_note(string $mode, $before, $after, string $unit, ?string $itemName = null): string
{
    $beforeDisp = stock_is_integer_unit($unit) ? (int) $before : (float) $before;
    $afterDisp = stock_is_integer_unit($unit) ? (int) $after : (float) $after;

    $phrase = static function ($amount) use ($unit): string {
        $n = stock_is_integer_unit($unit) ? (int) $amount : (float) $amount;
        return $n . ' ' . stock_unit_display($unit);
    };

    if ($mode === 'setup') {
        return 'Started with ' . $phrase($afterDisp) . ' in kitchen.';
    }
    if ($mode === 'add') {
        $added = $afterDisp - $beforeDisp;
        return 'Added ' . $phrase($added) . '. Now ' . $phrase($afterDisp) . ' in stock.';
    }
    if ($mode === 'subtract') {
        $used = $beforeDisp - $afterDisp;
        return 'Used ' . $phrase($used) . '. Now ' . $phrase($afterDisp) . ' left.';
    }
    if ($mode === 'set' || $mode === 'count') {
        return stock_edit_correct_note($before, $after, $unit, $itemName);
    }
    if ((float) $beforeDisp === (float) $afterDisp) {
        return 'Checked stock, still ' . $phrase($afterDisp) . '.';
    }
    return stock_edit_correct_note($before, $after, $unit, $itemName);
}

function stock_values_equal($a, $b): bool
{
    if ($a === null || $a === '') {
        return $b === null || $b === '';
    }
    if ($b === null || $b === '') {
        return false;
    }
    return (float) $a === (float) $b;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $rows = db_query_all(
            stock_item_select_sql() . "
             FROM stock_items si
             INNER JOIN menu_items mi ON mi.id = si.menu_item_id
             ORDER BY si.stock_area ASC, mi.name ASC"
        );
        
        // OPTIMIZATION: Enrich all rows with their totals in one fast query
        $rows = stock_enrich_list_totals($rows);

        $items = array_map('stock_map_row', $rows);
        echo json_encode(['success' => true, 'items' => $items]);

    } elseif ($action === 'add') {
        verify_csrf();
        $menuItemId = intval($_POST['menu_item_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');

        stock_validate_area($area);
        if ($menuItemId <= 0) {
            throw new Exception('Invalid menu item');
        }

        $menuItem = db_query_one("SELECT id, name FROM menu_items WHERE id = ?", [$menuItemId]);
        if (!$menuItem) {
            throw new Exception('Menu item not found');
        }

        $existing = stock_get_item($menuItemId, $area);
        if ($existing) {
            throw new Exception('Item already in this stock list');
        }

        try {
            db_execute(
                "INSERT INTO stock_items (menu_item_id, stock_area, track_stock, stock_qty)
                 VALUES (?, ?, 0, 0)",
                [$menuItemId, $area]
            );
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new Exception('Item already in this stock list');
            }
            throw $e;
        }

        $item = stock_get_item_mapped($menuItemId, $area);
        echo json_encode(['success' => true, 'item' => $item]);

    } elseif ($action === 'add_kitchen_manual') {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $priceRaw = trim($_POST['price'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $unitValueRaw = trim($_POST['unit_value'] ?? '');

        if ($name === '') {
            throw new Exception('Item name is required');
        }
        $price = stock_validate_price_string($priceRaw);
        if (!in_array($unit, STOCK_UNITS, true)) {
            throw new Exception('Invalid unit type');
        }
        $unitValue = stock_validate_amount_string($unit, $unitValueRaw);

        $dup = db_query_one(
            "SELECT si.id FROM stock_items si
             INNER JOIN menu_items mi ON mi.id = si.menu_item_id
             WHERE si.stock_area = 'kitchen' AND LOWER(mi.name) = LOWER(?)",
            [$name]
        );
        if ($dup) {
            throw new Exception('This item is already in kitchen stock');
        }

        db_execute(
            "INSERT INTO menu_items (name, price, is_available, is_counter_item) VALUES (?, ?, 0, 0)",
            [$name, $price]
        );
        $menuItemId = (int) db_last_insert_id();

        db_execute(
            "INSERT INTO stock_items (menu_item_id, stock_area, unit_type, unit_value, track_stock, stock_qty)
             VALUES (?, 'kitchen', ?, ?, 0, 0)",
            [$menuItemId, $unit, $unitValue]
        );
        $stockItemId = (int) db_last_insert_id();

        stock_log_movement(
            $stockItemId,
            'setup',
            (float) $unitValue,
            (float) $unitValue,
            stock_kitchen_qty_note('setup', 0, $unitValue, $unit)
        );

        $item = stock_get_item_mapped($menuItemId, 'kitchen');
        echo json_encode(['success' => true, 'item' => $item]);

    } elseif ($action === 'update') {
        verify_csrf();
        $menuItemId = intval($_POST['menu_item_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $unitValueRaw = trim($_POST['unit_value'] ?? '');

        stock_validate_area($area);
        if ($menuItemId <= 0) {
            throw new Exception('Invalid menu item');
        }
        if (!in_array($unit, STOCK_UNITS, true)) {
            throw new Exception('Invalid unit type');
        }

        $row = stock_get_item($menuItemId, $area);
        if (!$row) {
            throw new Exception('Stock item not found');
        }

        $trackStock = ($area === 'counter' && !empty($_POST['track_stock'])) ? 1 : 0;
        $trackSold = ($area === 'counter' && !empty($_POST['track_sold'])) ? 1 : 0;
        [$stockSince, $soldSince] = stock_apply_tracking_flags($row, $trackStock, $trackSold);

        $syncQty = ($area === 'counter' && $trackStock === 1 && $unit === 'piece');
        $flagsChanged = $trackStock !== (int) $row['track_stock']
            || $trackSold !== (int) ($row['track_sold'] ?? 0);
        $unitChanged = $unit !== ($row['unit_type'] ?? '');

        if ($syncQty) {
            $currentQty = (int) $row['stock_qty'];
            db_execute(
                "UPDATE stock_items SET unit_type = ?, unit_value = ?, stock_qty = ?,
                    track_stock = ?, track_sold = ?,
                    stock_tracking_started_at = ?, sold_tracking_started_at = ?
                 WHERE id = ?",
                [$unit, $currentQty, $currentQty, $trackStock, $trackSold, $stockSince, $soldSince, $row['id']]
            );

        } else {
            $unitValue = stock_validate_amount_string($unit, $unitValueRaw);
            $valueChanged = !stock_values_equal($unitValue, $row['unit_value'] ?? null);
            $typeChanged = $unit !== ($row['unit_type'] ?? '');
            db_execute(
                "UPDATE stock_items SET unit_type = ?, unit_value = ?,
                    track_stock = ?, track_sold = ?,
                    stock_tracking_started_at = ?, sold_tracking_started_at = ?
                 WHERE id = ?",
                [$unit, $unitValue, $trackStock, $trackSold, $stockSince, $soldSince, $row['id']]
            );
            if ($area === 'kitchen' && ($valueChanged || $typeChanged)) {
                $before = $row['unit_value'] ?? 0;
                $after = $unitValue;
                $change = (float) $after - (float) $before;
                if ($change !== 0.0 || $typeChanged) {
                    if ($change > 0 && (float) $before <= 0) {
                        $moveType = 'setup';
                        $noteMode = 'setup';
                    } elseif ($change > 0) {
                        $moveType = 'add';
                        $noteMode = 'add';
                    } elseif ($change < 0) {
                        $moveType = 'adjust';
                        $noteMode = 'subtract';
                    } else {
                        $moveType = 'count';
                        $noteMode = 'set';
                    }
                    stock_log_movement(
                        (int) $row['id'],
                        $moveType,
                        $change,
                        (float) $after,
                        stock_kitchen_qty_note($noteMode, $before, $after, $unit, trim((string) ($row['name'] ?? '')))
                    );
                }
            }
        }

        $item = stock_get_item_mapped($menuItemId, $area);
        echo json_encode(['success' => true, 'item' => $item]);

    } elseif ($action === 'adjust_qty') {
        verify_csrf();
        $menuItemId = intval($_POST['menu_item_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');
        $mode = trim($_POST['mode'] ?? '');
        $qtyRaw = trim($_POST['qty'] ?? '');

        stock_validate_area($area);
        if ($menuItemId <= 0) {
            throw new Exception('Invalid menu item');
        }
        if (!in_array($mode, ['add', 'set', 'subtract'], true)) {
            throw new Exception('Invalid adjust mode');
        }
        if (!preg_match('/^\d+$/', $qtyRaw)) {
            throw new Exception('Enter a whole number');
        }
        $qty = (int) $qtyRaw;
        if ($mode === 'add' && $qty <= 0) {
            throw new Exception('Enter how many to add');
        }
        if ($mode === 'subtract' && $qty <= 0) {
            throw new Exception('Enter how much was used');
        }
        if ($mode === 'set' && $qty < 0) {
            throw new Exception('Enter a valid total');
        }

        $row = stock_get_item($menuItemId, $area);
        if (!$row) {
            throw new Exception('Stock item not found');
        }

        $unit = $row['unit_type'] ?: 'piece';
        $itemName = trim((string) ($row['name'] ?? ''));

        if ($area === 'counter') {
            if ($unit !== 'piece') {
                throw new Exception('Quick add / edit is only for piece items');
            }

            $before = (int) $row['stock_qty'];
            if ($mode === 'add') {
                $after = $before + $qty;
                db_execute(
                    "UPDATE stock_items SET stock_qty = ?, unit_value = ?, unit_type = 'piece' WHERE id = ?",
                    [$after, $after, $row['id']]
                );
                $nameSuffix = $itemName !== '' ? ' of ' . $itemName : '';
                stock_log_movement(
                    (int) $row['id'],
                    'add',
                    (float) $qty,
                    (float) $after,
                    sprintf('Added %d %s%s.', $qty, $qty === 1 ? 'piece' : 'pieces', $nameSuffix)
                );
            } else {
                $after = $qty;
                db_execute(
                    "UPDATE stock_items SET stock_qty = ?, unit_value = ?, unit_type = 'piece' WHERE id = ?",
                    [$after, $after, $row['id']]
                );
                if ($before === $after) {
                    $note = sprintf('Checked stock, still %d %s.', $after, $after === 1 ? 'piece' : 'pieces');
                    $moveType = 'count';
                } else {
                    $note = stock_edit_correct_note($before, $after, 'piece', $itemName);
                    $moveType = ($before === 0 && $after > 0) ? 'add' : 'count';
                }
                stock_log_movement(
                    (int) $row['id'],
                    $moveType,
                    (float) ($after - $before),
                    (float) $after,
                    $note
                );
            }
        } elseif ($area === 'kitchen') {
            if (!stock_is_integer_unit($unit)) {
                throw new Exception('Quick add / edit is only for count units. Use Save for kg, gram, or liter.');
            }

            $before = (int) ($row['unit_value'] ?? 0);
            if ($mode === 'add') {
                $after = $before + $qty;
                db_execute(
                    "UPDATE stock_items SET unit_value = ? WHERE id = ?",
                    [$after, $row['id']]
                );
                stock_log_movement(
                    (int) $row['id'],
                    'add',
                    (float) $qty,
                    (float) $after,
                    stock_kitchen_qty_note('add', $before, $after, $unit, $itemName)
                );
            } elseif ($mode === 'subtract') {
                $after = $before - $qty;
                if ($after < 0) {
                    throw new Exception(
                        'You only have ' . $before . ' ' . stock_unit_display($unit) . ' in stock'
                    );
                }
                db_execute(
                    "UPDATE stock_items SET unit_value = ? WHERE id = ?",
                    [$after, $row['id']]
                );
                stock_log_movement(
                    (int) $row['id'],
                    'adjust',
                    -(float) $qty,
                    (float) $after,
                    stock_kitchen_qty_note('subtract', $before, $after, $unit, $itemName)
                );
            } else {
                $after = $qty;
                if ($before !== $after) {
                    db_execute(
                        "UPDATE stock_items SET unit_value = ? WHERE id = ?",
                        [$after, $row['id']]
                    );
                    $moveType = ($before === 0 && $after > 0) ? 'add' : 'count';
                    stock_log_movement(
                        (int) $row['id'],
                        $moveType,
                        (float) ($after - $before),
                        (float) $after,
                        stock_kitchen_qty_note('set', $before, $after, $unit, $itemName)
                    );
                }
            }
        } else {
            throw new Exception('Invalid stock area');
        }

        stock_sync_tracking_from_request($row);

        $item = stock_get_item_mapped($menuItemId, $area);
        echo json_encode(['success' => true, 'item' => $item]);

    } elseif ($action === 'detail') {
        $menuItemId = intval($_GET['menu_item_id'] ?? 0);
        $area = trim($_GET['area'] ?? '');
        stock_validate_area($area);
        if ($menuItemId <= 0) {
            throw new Exception('Invalid menu item');
        }
        $row = stock_get_item($menuItemId, $area);
        if (!$row) {
            throw new Exception('Stock item not found');
        }

        $historyOnly = !empty($_GET['history_only']);
        $movementsSince = stock_movements_since($row);
        $soldSince = stock_sold_stats_since($row);
        $dateFilter = trim($_GET['date'] ?? '');
        $dayStats = null;
        $item = null;

        if (!$historyOnly) {
            $item = stock_get_item_mapped($menuItemId, $area);
        }

        if ($dateFilter !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $dateFilter);
            if (!$dt || $dt->format('Y-m-d') !== $dateFilter) {
                throw new Exception('Invalid date');
            }
            $dayStats = stock_day_stats(
                (int) $row['id'],
                $dateFilter,
                $soldSince,
                $movementsSince
            );
        }

        $movements = db_query_all(
            "SELECT id, movement_type, qty_change, qty_after, note, created_at
             FROM stock_movements
             WHERE stock_item_id = ?
               AND created_at >= ?
             ORDER BY created_at DESC, id DESC
             LIMIT 200",
            [(int) $row['id'], $movementsSince]
        );

        $payload = [
            'success' => true,
            'movements' => $movements,
            'dayStats' => $dayStats,
            'date' => $dateFilter !== '' ? $dateFilter : null,
        ];
        if ($item !== null) {
            $payload['item'] = $item;
        }
        echo json_encode($payload);

    } elseif ($action === 'remove') {
        verify_csrf();
        $menuItemId = intval($_POST['menu_item_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');

        stock_validate_area($area);
        if ($menuItemId <= 0) {
            throw new Exception('Invalid menu item');
        }

        $row = stock_get_item($menuItemId, $area);
        if (!$row) {
            throw new Exception('Stock item not found');
        }

        db_execute("DELETE FROM stock_movements WHERE stock_item_id = ?", [$row['id']]);
        db_execute("DELETE FROM stock_items WHERE id = ?", [$row['id']]);

        echo json_encode(['success' => true]);

    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
