<?php
// includes/stock_schema.php — Idempotent stock tables/columns for live deploy

/** Bump when adding new migrations; delete config/.stock_schema_version on server to re-run. */
const STOCK_SCHEMA_VERSION = 1;

/**
 * Ensure stock tracking schema exists. Heavy checks/migrations run once per version
 * (see config/.stock_schema_version), not on every HTTP request.
 */
function ensure_stock_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $versionFile = dirname(__DIR__) . '/config/.stock_schema_version';
    $storedVersion = is_file($versionFile) ? (int) trim((string) file_get_contents($versionFile)) : 0;

    if ($storedVersion >= STOCK_SCHEMA_VERSION) {
        $ensured = true;
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_items` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `menu_item_id` int(11) NOT NULL,
              `stock_area` enum('counter','kitchen') NOT NULL,
              `unit_type` enum(
                'piece','kg','gram','liter',
                'bora','packet','crate','plastic','poka','glass'
              ) DEFAULT NULL,
              `unit_value` decimal(12,3) DEFAULT NULL,
              `track_stock` tinyint(1) NOT NULL DEFAULT 0,
              `stock_qty` int(11) NOT NULL DEFAULT 0,
              `created_at` datetime DEFAULT current_timestamp(),
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_stock_menu_area` (`menu_item_id`,`stock_area`),
              KEY `idx_stock_area` (`stock_area`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_movements` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `stock_item_id` int(11) NOT NULL,
              `movement_type` enum('setup','add','adjust','sale','count') NOT NULL DEFAULT 'setup',
              `qty_change` decimal(12,3) NOT NULL DEFAULT 0.000,
              `qty_after` decimal(12,3) NOT NULL DEFAULT 0.000,
              `note` varchar(255) DEFAULT NULL,
              `created_by` int(11) DEFAULT NULL,
              `created_at` datetime DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_stock_mov_created` (`created_at`),
              KEY `idx_stock_mov_item_created` (`stock_item_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmtIdx = $pdo->query("SHOW INDEX FROM `stock_movements` WHERE Key_name = 'idx_stock_mov_item_created'");
        if (!$stmtIdx || !$stmtIdx->fetch()) {
            $pdo->exec("ALTER TABLE `stock_movements` ADD KEY `idx_stock_mov_item_created` (`stock_item_id`, `created_at`)");
        }

        $stmtIdx2 = $pdo->query("SHOW INDEX FROM `stock_movements` WHERE Key_name = 'idx_stock_mov_item_type_created'");
        if (!$stmtIdx2 || !$stmtIdx2->fetch()) {
            $pdo->exec("ALTER TABLE `stock_movements` ADD KEY `idx_stock_mov_item_type_created` (`stock_item_id`, `movement_type`, `created_at`)");
        }

        $addedStockDeducted = false;
        $stmt = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'stock_deducted'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec("
                ALTER TABLE `order_items`
                ADD COLUMN `stock_deducted` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_paid`
            ");
            $addedStockDeducted = true;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'kot_printed_at'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec("
                ALTER TABLE `order_items`
                ADD COLUMN `kot_printed_at` datetime DEFAULT NULL AFTER `stock_deducted`
            ");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `stock_items` LIKE 'track_sold'");
        if (!$stmt || !$stmt->fetch()) {
            $stmtCig = $pdo->query("SHOW COLUMNS FROM `stock_items` LIKE 'is_cigarette'");
            if ($stmtCig && $stmtCig->fetch()) {
                $pdo->exec("
                    ALTER TABLE `stock_items`
                    CHANGE COLUMN `is_cigarette` `track_sold` tinyint(1) NOT NULL DEFAULT 0
                ");
            } else {
                $pdo->exec("
                    ALTER TABLE `stock_items`
                    ADD COLUMN `track_sold` tinyint(1) NOT NULL DEFAULT 0 AFTER `track_stock`
                ");
            }
        }

        $addedTrackingCols = false;
        $stmt = $pdo->query("SHOW COLUMNS FROM `stock_items` LIKE 'stock_tracking_started_at'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec("
                ALTER TABLE `stock_items`
                ADD COLUMN `stock_tracking_started_at` datetime DEFAULT NULL AFTER `track_sold`,
                ADD COLUMN `sold_tracking_started_at` datetime DEFAULT NULL AFTER `stock_tracking_started_at`
            ");
            $addedTrackingCols = true;
        }

        // One-time backfills only during migration (not every request)
        if ($addedTrackingCols) {
            $pdo->exec("
                UPDATE `stock_items`
                SET stock_tracking_started_at = COALESCE(stock_tracking_started_at, created_at)
                WHERE track_stock = 1 AND stock_tracking_started_at IS NULL
            ");
            $pdo->exec("
                UPDATE `stock_items`
                SET sold_tracking_started_at = COALESCE(sold_tracking_started_at, stock_tracking_started_at, created_at)
                WHERE track_sold = 1 AND sold_tracking_started_at IS NULL
            ");
        }

        if ($addedStockDeducted) {
            $pdo->exec("
                UPDATE `order_items` oi
                INNER JOIN `stock_items` si ON si.menu_item_id = oi.menu_item_id AND si.stock_area = 'counter'
                SET oi.stock_deducted = 0
                WHERE oi.stock_deducted = 1
                  AND oi.kitchen_status = 'PENDING'
            ");
        }

        @file_put_contents($versionFile, (string) STOCK_SCHEMA_VERSION);
    } catch (Exception $e) {
        error_log('ensure_stock_schema: ' . $e->getMessage());
    }

    $ensured = true;
}
