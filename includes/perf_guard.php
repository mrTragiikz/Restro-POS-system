<?php
// includes/perf_guard.php
/**
 * Chiya Sansar - Performance Guard Utility
 * 
 * Provides standardized helpers for high-performance MySQL queries.
 */

class PerfGuard
{
    private static $startTime = 0;

    /**
     * Standard Date Range Generator
     * Avoids DATE(column) by using index-friendly boundaries.
     */
    public static function makeRange($dateString)
    {
        $start = $dateString . ' 00:00:00';
        $end = date('Y-m-d', strtotime($dateString . ' +1 day')) . ' 00:00:00';
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Clamp Limit for Performance
     */
    public static function clampLimit($limit, $min = 1, $max = 200)
    {
        $l = (int) $limit;
        if ($l < $min)
            return $min;
        if ($l > $max)
            return $max;
        return $l;
    }

    /**
     * Enforce Search Minimums
     */
    public static function minSearchLength($q, $len = 3)
    {
        $val = trim($q);
        return (strlen($val) >= $len) ? $val : null;
    }

    /**
     * Standard Prefix Search (Index-Friendly)
     */
    public static function prefixLike($q)
    {
        return trim($q) . '%';
    }

    /**
     * Standard Contains Search (Broad)
     */
    public static function containsLike($q)
    {
        return '%' . trim($q) . '%';
    }

    /**
     * Execution Profiling
     */
    public static function timerStart()
    {
        self::$startTime = microtime(true);
    }

    public static function timerEnd()
    {
        return round((microtime(true) - self::$startTime) * 1000, 2); // ms
    }

    /**
     * Refreshes one day of data in daily_summary table
     * USES summary_helper.php for consistent logic across reports.
     */
    public static function syncDailySummary($pdo, $date, $force = false)
    {
        if (!function_exists('rebuild_daily_summary')) {
            require_once __DIR__ . '/summary_helper.php';
        }
        return rebuild_daily_summary($date, $pdo, $force);
    }
}
