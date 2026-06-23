<?php
// includes/summary_helper.php

/**
 * Rebuilds (or returns the cached) daily_summary row for $date.
 *
 * Recompute is expensive (scans payment_transactions + credit_transactions for
 * the day), so by default we skip it if the row was refreshed within the last
 * 60s. Pass $force = true right after writing a payment/credit transaction so
 * the caller always sees up-to-date totals instead of a stale cached value.
 */
function rebuild_daily_summary($date, $pdo = null, $force = false)
{
    if (!$force) {
        $cached = db_query_one("
            SELECT total_cash, total_online, total_credit_given, total_credit_paid, total_sales, total_lifetime_collected
            FROM daily_summary
            WHERE daily_date = ? AND updated_at >= (NOW() - INTERVAL 60 SECOND)
        ", [$date]);

        if ($cached) {
            return [
                'cash' => floatval($cached['total_cash']),
                'online' => floatval($cached['total_online']),
                'credit_given' => floatval($cached['total_credit_given']),
                'credit_paid' => floatval($cached['total_credit_paid']),
                'sales' => floatval($cached['total_sales']),
                'lifetime_upto_yesterday' => floatval($cached['total_lifetime_collected']),
            ];
        }
    }

    // OPTIMIZATION: Use Range Query
    $start = $date . ' 00:00:00';
    $end = $date . ' 23:59:59';

    // 1. Calculate Today's Totals from payment_transactions (POS Sales)
    $pos_sales = db_query_one("
        SELECT 
            COALESCE(SUM(cash_amount), 0) as cash,
            COALESCE(SUM(online_amount), 0) as online
        FROM payment_transactions
        WHERE created_at >= ? AND created_at <= ?
    ", [$start, $end]);

    // 2. Calculate Credit Repayments
    $repayments = db_query_one("
        SELECT 
            ABS(COALESCE(SUM(CASE WHEN payment_mode = 'Online' THEN amount ELSE 0 END), 0)) as online_paid,
            ABS(COALESCE(SUM(CASE WHEN payment_mode != 'Online' THEN amount ELSE 0 END), 0)) as cash_paid
        FROM credit_transactions 
        WHERE created_at >= ? AND created_at <= ? AND amount < 0
    ", [$start, $end]);

    $total_cash = $pos_sales['cash'];
    $total_online = $pos_sales['online'];

    // 3. Calculate Credit Totals (Fresh debt given today)
    $credit_res = db_query_one("
        SELECT COALESCE(SUM(amount), 0) as val 
        FROM credit_transactions 
        WHERE created_at >= ? AND created_at <= ? AND amount > 0
    ", [$start, $end]);
    $credit_given = $credit_res['val'];

    // 4. Calculate Total Summary
    $credit_paid = $repayments['cash_paid'] + $repayments['online_paid'];
    $total_sales = $total_cash + $total_online;

    // 5. Calculate Lifetime POS Sales (OPTIMIZED)
    // We try to get the running total from the previous day's summary to avoid full table scans.
    $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
    $prev = db_query_one("SELECT total_lifetime_collected, total_sales FROM daily_summary WHERE daily_date = ?", [$yesterday]);

    if ($prev) {
        $lifetime_upto_yesterday = floatval($prev['total_lifetime_collected']) + floatval($prev['total_sales']);
    } else {
        // Fallback to slow query if summary for yesterday doesn't exist (e.g. first run or gap)
        $raw_life = db_query_one("
            SELECT COALESCE(SUM(cash_amount + online_amount), 0) as val 
            FROM payment_transactions 
            WHERE created_at < ?
        ", [$start]);
        $lifetime_upto_yesterday = floatval($raw_life['val']);
    }

    // 6. UPSERT into daily_summary
    db_execute("
        INSERT INTO daily_summary 
        (daily_date, total_cash, total_online, total_credit_given, total_credit_paid, total_sales, total_lifetime_collected, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        total_cash = VALUES(total_cash),
        total_online = VALUES(total_online),
        total_credit_given = VALUES(total_credit_given),
        total_credit_paid = VALUES(total_credit_paid),
        total_sales = VALUES(total_sales),
        total_lifetime_collected = VALUES(total_lifetime_collected),
        updated_at = NOW()
    ", [
        $date,
        $total_cash,
        $total_online,
        $credit_given,
        $credit_paid,
        $total_sales,
        $lifetime_upto_yesterday
    ]);

    return [
        'cash' => $total_cash,
        'online' => $total_online,
        'credit_given' => $credit_given,
        'credit_paid' => $credit_paid,
        'sales' => $total_sales,
        'lifetime_upto_yesterday' => $lifetime_upto_yesterday
    ];
}
?>