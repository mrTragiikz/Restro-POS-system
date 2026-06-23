<?php
ob_start();
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/perf_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search_q = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// PERFORMANCE: Skip if searching with too few characters
$search = PerfGuard::minSearchLength($search_q, 3);
if ($search_q !== '' && $search === null) {
    echo json_encode(['customers' => [], 'message' => 'Min 3 chars required']);
    exit;
}

try {
    // Compute the true outstanding balance LIVE from credit_transactions using a
    // correlated subquery (same formula as the detail page: SUM of all amounts,
    // credits positive / payments negative). A subquery avoids GROUP BY so the
    // existing WHERE / HAVING / ORDER BY logic keeps working unchanged.
    $sql = "
        SELECT
            c.id, c.name as full_name, c.phone,
            COALESCE((SELECT SUM(t.amount) FROM credit_transactions t WHERE t.customer_id = c.id), 0) as outstanding
        FROM credit_customers c
    ";

    $where = [];
    $params = [];

    if ($search !== null) {
        $where[] = "c.name LIKE ?";
        $params[] = PerfGuard::containsLike($search);
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    // Filter by type or outstanding amount using HAVING because 'outstanding' is an alias/aggregate
    if ($type !== 'all' && $search === null) {
        // Default view: only show those with debt
        $sql .= " HAVING outstanding > 1";
    }

    // Allow up to 500 items if requested
    $limit = PerfGuard::clampLimit($_GET['limit'] ?? 20, 1, 500);

    // Sort Logic (Standard SQL Compatibility):
    // 1. Debtors appear first (outstanding > 0)
    // 2. Within Debtors: Highest Debt first
    // 3. Non-Debtors: Newest ID first (so newly created ones appear at the top of the non-debtor heap)
    $sql .= " ORDER BY 
        CASE WHEN outstanding > 0 THEN 1 ELSE 0 END DESC, 
        outstanding DESC, 
        c.id DESC 
        LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean(); // Clean any PHP warnings/notices causing invalid JSON
    echo json_encode(['customers' => $customers]);
} catch (Exception $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
