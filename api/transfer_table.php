<?php
// api/transfer_table.php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/realtime.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Get Input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

verify_csrf_token($input['csrf_token'] ?? null);
$sourceCode = $input['source_table_code'] ?? '';
$targetCode = $input['target_table_code'] ?? '';

if (!$sourceCode || !$targetCode) {
    echo json_encode(['error' => 'Missing source or target table.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get Source Table ID and Active Session
    // We only transfer if it has an OPEN, UNPAID (or partially paid but active) session.
    // If closed_at is NOT NULL, it's history.
    $stmt = $pdo->prepare("
        SELECT t.id as table_id, s.id as session_id, s.is_paid 
        FROM tables t 
        JOIN table_sessions s ON t.id = s.table_id 
        WHERE t.code = ? AND s.closed_at IS NULL
    ");
    $stmt->execute([$sourceCode]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        throw new Exception("Source table has no active order to transfer.");
    }

    // Strict Rule: "Transfer allowed ONLY if Source table has an active unpaid order"
    // If it's fully paid (is_paid=1), we generally don't transfer, we close it.
    if ($source['is_paid'] == 1) {
        throw new Exception("Cannot transfer a PAID order. Details are locked.");
    }

    // 2. Get Target Table ID and Status
    $stmt = $pdo->prepare("SELECT id, status FROM tables WHERE code = ?");
    $stmt->execute([$targetCode]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        throw new Exception("Target table not found.");
    }

    // 3. Check if Target is truly FREE (No active session)
    // We check table_sessions, not just 'status' flag, for safety.
    $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id = ? AND closed_at IS NULL");
    $stmt->execute([$target['id']]);
    $targetSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($targetSession) {
        throw new Exception("Target table {$targetCode} is already occupied.");
    }

    // 4. Perform Transfer

    // A. Update the Session's Table ID
    $stmt = $pdo->prepare("UPDATE table_sessions SET table_id = ? WHERE id = ?");
    $stmt->execute([$target['id'], $source['session_id']]);

    // B. Update Tables Status Flags
    // Set Source to FREE
    $stmt = $pdo->prepare("UPDATE tables SET status = 'FREE' WHERE id = ?");
    $stmt->execute([$source['table_id']]);

    // Set Target to OCCUPIED
    $stmt = $pdo->prepare("UPDATE tables SET status = 'OCCUPIED' WHERE id = ?");
    $stmt->execute([$target['id']]);



    $pdo->commit();
    realtime_notify_orders();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
?>