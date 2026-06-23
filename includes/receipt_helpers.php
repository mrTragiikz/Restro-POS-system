<?php
// includes/receipt_helpers.php — shared helpers for receipt/report rendering

/**
 * Merge order_items rows that share the same name + unit price into one row,
 * summing their quantities. Used by receipt and credit report templates.
 *
 * @param array $items rows with item_name_snapshot, unit_price_snapshot, qty
 * @return array
 */
function consolidate_receipt_items(array $items)
{
    $grouped = [];
    foreach ($items as $item) {
        $key = $item['item_name_snapshot'] . '-' . $item['unit_price_snapshot'];
        if (isset($grouped[$key])) {
            $grouped[$key]['qty'] += $item['qty'];
        } else {
            $grouped[$key] = $item;
        }
    }
    return array_values($grouped);
}
