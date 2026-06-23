<?php
// includes/settings.php — Database-backed global application settings (key/value).
//
// Professional, stable mechanism: a single `app_settings` table holds global
// on/off switches and other shop-wide settings. The table self-creates on first
// use (idempotent), so no manual SQL migration is required.
//
// Current keys:
//   waiter_portal_enabled : '1' = waiter portal ON, '0' = OFF (remote kill-switch)

require_once __DIR__ . '/db.php';

/**
 * Ensure the app_settings table exists. Runs once per request (static guard),
 * and the CREATE is IF NOT EXISTS so it is safe to call repeatedly.
 */
function ensure_app_settings_schema(): void
{
    global $pdo;
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!$pdo) {
        return;
    }

    // Persistent guard across requests: once we know the table exists, skip
    // the CREATE TABLE/INSERT IGNORE entirely on future requests. Re-running
    // DDL on every single page load that touches this tab was hitting InnoDB
    // metadata locks on some hosts, causing the Manage Waiter tab specifically
    // to feel slow even though the table only ever needs to be created once.
    $marker = __DIR__ . '/../config/.app_settings_ready';
    if (is_file($marker)) {
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `app_settings` (
              `setting_key`   VARCHAR(64) NOT NULL,
              `setting_value` VARCHAR(255) NOT NULL,
              `updated_by`    INT(11) DEFAULT NULL,
              `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed the waiter portal switch as ENABLED on first creation only.
        // INSERT IGNORE keeps an existing value untouched on later runs.
        $pdo->exec("
            INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`)
            VALUES ('waiter_portal_enabled', '1')
        ");

        @file_put_contents($marker, '1');
    } catch (Exception $e) {
        error_log('ensure_app_settings_schema: ' . $e->getMessage());
    }
}

/**
 * Read a setting value as a string, or $default if missing/unavailable.
 */
function app_setting_get(string $key, ?string $default = null): ?string
{
    global $pdo;
    ensure_app_settings_schema();

    if (!$pdo) {
        return $default;
    }

    try {
        $row = db_query_one(
            "SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1",
            [$key]
        );
        if ($row && isset($row['setting_value'])) {
            return (string) $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log('app_setting_get(' . $key . '): ' . $e->getMessage());
    }

    return $default;
}

/**
 * Write a setting value (UPSERT). Records who changed it.
 * Returns true on success.
 */
function app_setting_set(string $key, string $value, ?int $userId = null): bool
{
    global $pdo;
    ensure_app_settings_schema();

    if (!$pdo) {
        return false;
    }

    try {
        db_execute(
            "INSERT INTO app_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
            [$key, $value, $userId]
        );
        return true;
    } catch (Exception $e) {
        error_log('app_setting_set(' . $key . '): ' . $e->getMessage());
        return false;
    }
}

/**
 * Is the waiter portal currently enabled?
 *
 * If the Manage Waiter trial has expired, any admin-set lock (manual OFF or
 * a timer) is ignored and the portal is forced back open — a trial ending
 * must never strand a client's waiters unable to log in. The admin regains
 * control only after paying to unlock Manage Waiter again.
 *
 * Otherwise, if an opening/closing timer is set (both times present), the
 * schedule is the sole source of truth: enabled only while the current time
 * falls inside the window (handles overnight windows like 18:00-02:00 too).
 * Otherwise falls back to the manual on/off switch.
 *
 * FAIL-CLOSED: if the setting cannot be read (DB error, missing row), this
 * returns false (portal treated as DISABLED) for maximum security.
 */
function waiter_portal_is_enabled(): bool
{
    if (manage_waiter_trial_expired()) {
        return true;
    }

    $schedule = waiter_portal_get_schedule();
    if ($schedule !== null) {
        return waiter_portal_time_in_window(date('H:i'), $schedule['open'], $schedule['close']);
    }

    $val = app_setting_get('waiter_portal_enabled', null);
    if ($val === null) {
        return false; // fail-closed
    }
    return $val === '1';
}

/**
 * Set the waiter portal on/off. $enabled true = ON, false = OFF.
 */
function waiter_portal_set_enabled(bool $enabled, ?int $userId = null): bool
{
    return app_setting_set('waiter_portal_enabled', $enabled ? '1' : '0', $userId);
}

/**
 * Get the currently configured opening/closing timer, or null if not set.
 * Times are stored as 24h 'HH:MM' strings, packed into one setting value
 * ("HH:MM-HH:MM") so the pair is always written/read atomically — a single
 * key can never end up half-set the way two independent keys could.
 */
function waiter_portal_get_schedule(): ?array
{
    $raw = app_setting_get('waiter_portal_schedule', null);
    if (!$raw || strpos($raw, '-') === false) {
        return null;
    }
    [$open, $close] = explode('-', $raw, 2);
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $close)) {
        return null;
    }
    return ['open' => $open, 'close' => $close];
}

/**
 * Save an opening/closing schedule. Times must be 24h 'HH:MM' strings.
 */
function waiter_portal_set_schedule(string $open, string $close, ?int $userId = null): bool
{
    return app_setting_set('waiter_portal_schedule', $open . '-' . $close, $userId);
}

/**
 * Remove the schedule, reverting to manual on/off control.
 */
function waiter_portal_clear_schedule(?int $userId = null): bool
{
    return app_setting_set('waiter_portal_schedule', '', $userId);
}

/**
 * Is $now ('HH:MM') within [$open, $close)? Supports overnight windows
 * where close time is earlier than open time (e.g. 18:00 -> 02:00).
 */
function waiter_portal_time_in_window(string $now, string $open, string $close): bool
{
    if ($open === $close) {
        return true; // 24-hour window
    }
    if ($open < $close) {
        return $now >= $open && $now < $close;
    }
    // Overnight window (wraps past midnight)
    return $now >= $open || $now < $close;
}

/**
 * "Try the Manage Waiter feature" trial. Starts counting down the first time
 * any admin loads a page after this feature shipped. Once it expires, the
 * Manage Waiter section locks (paywall) until the client pays to unlock it
 * permanently — see manage_waiter_is_unlocked().
 *
 * The start timestamp is anchored to a flat file (config/.manage_waiter_promo_started_at),
 * not just the app_settings DB row. A failed/silently-swallowed DB write on some
 * hosts was making every fresh login look like "first load" again, resetting the
 * countdown back to the full 72 hours instead of continuing it. The file is the
 * source of truth; the DB row is kept in sync only for visibility/debugging.
 */
function manage_waiter_promo_seconds_left(): int
{
    $durationSeconds = 72 * 3600; // 72-hour trial window

    $marker = __DIR__ . '/../config/.manage_waiter_promo_started_at';
    $startedAt = is_file($marker) ? trim((string) file_get_contents($marker)) : '';

    if ($startedAt === '' || !ctype_digit($startedAt)) {
        // Fall back to whatever the DB has (covers upgrades from before this
        // file-based fix existed) before treating this as a true first load.
        $dbStartedAt = app_setting_get('manage_waiter_promo_started_at', null);
        $startedAt = ($dbStartedAt !== null && ctype_digit($dbStartedAt)) ? $dbStartedAt : (string) time();
        @file_put_contents($marker, $startedAt, LOCK_EX);
        app_setting_set('manage_waiter_promo_started_at', $startedAt);
    }

    $elapsed = time() - (int) $startedAt;
    $left = $durationSeconds - $elapsed;
    return $left > 0 ? $left : 0;
}

/**
 * Has the Manage Waiter trial expired? A manual override
 * ('manage_waiter_unlocked' = '1', set once the client pays) always wins,
 * permanently re-enabling the section regardless of the trial clock.
 */
function manage_waiter_trial_expired(): bool
{
    if (app_setting_get('manage_waiter_unlocked', '0') === '1') {
        return false;
    }
    return manage_waiter_promo_seconds_left() <= 0;
}

/**
 * Is the admin currently allowed to use the Manage Waiter section?
 */
function manage_waiter_is_unlocked(): bool
{
    return !manage_waiter_trial_expired();
}

/**
 * Permanently unlock Manage Waiter after the client pays. Call this from a
 * dedicated admin tool once payment is confirmed.
 */
function manage_waiter_unlock(?int $userId = null): bool
{
    return app_setting_set('manage_waiter_unlocked', '1', $userId);
}
