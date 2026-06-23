<?php
// includes/datetime_helper.php — Nepal (Asia/Kathmandu) wall-clock formatting for prints/reports

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Kathmandu');
}

/**
 * Parse a MySQL datetime or "now" in Nepal local time.
 *
 * @return array{date: string, time: string, datetime: string}
 */
function format_nepal_datetime($datetime = 'now')
{
    $tz = new DateTimeZone(APP_TIMEZONE);

    if ($datetime === null || $datetime === '' || strtolower((string) $datetime) === 'now') {
        $dt = new DateTime('now', $tz);
    } else {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string) $datetime, $tz);
        if (!$dt) {
            $dt = new DateTime((string) $datetime, $tz);
        }
    }

    return [
        'date' => $dt->format('d M, Y'),
        'time' => $dt->format('h:i A'),
        'datetime' => $dt->format('Y-m-d H:i:s'),
    ];
}

/** Best datetime to show on a sales receipt for a table session. */
function receipt_session_datetime(array $session)
{
    if (!empty($session['paid_at'])) {
        return $session['paid_at'];
    }
    // Unpaid / partial: time when receipt is printed (not old opened_at)
    return date('Y-m-d H:i:s');
}
