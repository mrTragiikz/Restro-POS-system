<?php
// includes/db.php
require_once __DIR__ . '/../config/config.php';

// Global DB Connection Variables
global $pdo;
global $mysqli;
global $db_mode; // 'PDO' or 'MYSQLI'

$pdo = null;
$mysqli = null;
$db_mode = null;

// 1. Attempt PDO Connection (Preferred)
if (class_exists('PDO') && extension_loaded('pdo_mysql')) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // Timezone
        try {
            $pdo->exec("SET time_zone = '+05:45'");
        } catch (Exception $e) {
        }

        $db_mode = 'PDO';

        require_once __DIR__ . '/stock_schema.php';
        ensure_stock_schema($pdo);
    } catch (PDOException $e) {
        // Fallback to MySQLi if PDO fails (e.g. connection error)
        $pdo = null;
    }
}

// 2. Fallback to MySQLi
if ($db_mode === null && function_exists('mysqli_connect')) {
    try {
        // Suppress warnings to handle them manually
        $mysqli = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($mysqli) {
            $mysqli->set_charset("utf8mb4");
            // Strict mode behavior similar to PDO Exception
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            // Timezone
            try {
                $mysqli->query("SET time_zone = '+05:45'");
            } catch (Exception $e) {
            }

            $db_mode = 'MYSQLI';
        } else {
            // Connection failed
            $mysqli_error = mysqli_connect_error();
        }
    } catch (Exception $e) {
        $mysqli = null;
        $db_mode = null;
    }
}

// 3. Critical Failure - No Driver Available
if ($db_mode === null) {
    die("<div style='font-family:sans-serif; padding:20px; border:1px solid red; background:#fff0f0;'>
        <h2>🚨 Critical Database Error</h2>
        <p>Could not connect to the database using either PDO or MySQLi.</p>
        <ul>
            <li><strong>PHP Version:</strong> " . phpversion() . "</li>
            <li><strong>PDO Available:</strong> " . (class_exists('PDO') ? 'Yes' : 'No') . "</li>
            <li><strong>MySQLi Available:</strong> " . (function_exists('mysqli_connect') ? 'Yes' : 'No') . "</li>
        </ul>
        <p>Please check your <code>config/config.php</code> credentials and server extensions.</p>
    </div>");
}

// ============================================================================
// DATABASE ABSTRACTION LAYER (PDO / MySQLi Polyfill)
// ============================================================================

/**
 * Execute a query and return a single row (Associative Array)
 */
function db_query_one($sql, $params = [])
{
    global $pdo, $mysqli, $db_mode;

    if ($db_mode === 'PDO') {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("DB Query Error: " . $e->getMessage());
            throw $e;
        }
    } elseif ($db_mode === 'MYSQLI') {
        return mysqli_query_params($sql, $params, 'one');
    }
}

/**
 * Execute a query and return ALL rows (Array of Associative Arrays)
 */
function db_query_all($sql, $params = [])
{
    global $pdo, $mysqli, $db_mode;

    if ($db_mode === 'PDO') {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Query Error: " . $e->getMessage());
            throw $e;
        }
    } elseif ($db_mode === 'MYSQLI') {
        return mysqli_query_params($sql, $params, 'all');
    }
}

/**
 * Execute an INSERT/UPDATE/DELETE statement
 * Returns true usually, or affected rows if needed (TODO: standardize return)
 * Currently returns true on success, throws Exception on failure.
 */
function db_execute($sql, $params = [])
{
    global $pdo, $mysqli, $db_mode;

    if ($db_mode === 'PDO') {
        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("DB Execute Error: " . $e->getMessage());
            throw $e;
        }
    } elseif ($db_mode === 'MYSQLI') {
        mysqli_query_params($sql, $params, 'execute');
        return true;
    }
}

/**
 * Get the last inserted ID
 */
function db_last_insert_id()
{
    global $pdo, $mysqli, $db_mode;
    if ($db_mode === 'PDO') {
        return $pdo->lastInsertId();
    } else {
        return mysqli_insert_id($mysqli);
    }
}

/**
 * Helper for MySQLi Prepared Statements
 */
function mysqli_query_params($sql, $params, $returnType)
{
    global $mysqli;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("MySQLi Prepare Failed: " . $mysqli->error);
    }

    if (!empty($params)) {
        $types = '';
        $bindParams = [];

        // Determine types and bind by reference
        foreach ($params as $param) {
            if (is_int($param))
                $types .= 'i';
            elseif (is_double($param))
                $types .= 'd';
            else
                $types .= 's'; // Default to string (works for blobs mostly too)
        }

        $bindParams[] = $types;
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key]; // Pass by reference is required for call_user_func_array
        }

        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }

    if (!$stmt->execute()) {
        throw new Exception("MySQLi Execute Failed: " . $stmt->error);
    }

    if ($returnType === 'execute') {
        $stmt->close();
        return true;
    }

    $result = $stmt->get_result(); // Requires mysqlnd
    if ($result === false) {
        // Fallback for systems without mysqlnd (rare now, but possible)
        // Note: Implementing full fetch_assoc fallback is complex; specific extensions usually have get_result
        throw new Exception("MySQLi get_result failed. Ensure mysqlnd driver is enabled.");
    }

    $data = [];
    if ($returnType === 'one') {
        $data = $result->fetch_assoc();
    } elseif ($returnType === 'all') {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    $stmt->close();
    return $data;
}