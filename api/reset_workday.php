<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if user is admin
    if (!isset($data['admin_user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Admin user ID is required']);
        exit;
    }
    
    $adminUserId = $data['admin_user_id'];
    $conn = getDBConnection();
    
    // Verify admin privileges
    $authStmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    if (!$authStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $authStmt->bind_param("i", $adminUserId);
    if (!$authStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed: ' . $authStmt->error]);
        $authStmt->close();
        $conn->close();
        exit;
    }
    
    $authResult = $authStmt->get_result();
    if ($authResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $authStmt->close();
        $conn->close();
        exit;
    }
    
    $user = $authResult->fetch_assoc();
    if (!$user['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Access denied. Admin privileges required.']);
        $authStmt->close();
        $conn->close();
        exit;
    }
    $authStmt->close();
    
    // Reset all employee stats
    $today = date('Y-m-d');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Reset animal_stats: health to 100, happiness to 0, update last_fed, reset last_health_reset
        $resetStatsStmt = $conn->prepare("UPDATE animal_stats SET health = 100, happiness = 0, last_fed = NOW(), last_health_reset = ?");
        if (!$resetStatsStmt) {
            throw new Exception('DB prepare failed (reset stats): ' . $conn->error);
        }
        $resetStatsStmt->bind_param("s", $today);
        if (!$resetStatsStmt->execute()) {
            throw new Exception('DB execute failed (reset stats): ' . $resetStatsStmt->error);
        }
        $resetStatsStmt->close();
        
        // 2. Delete today's work sessions (if any exist)
        $deleteSessionsStmt = $conn->prepare("DELETE FROM work_sessions WHERE session_date = ?");
        if (!$deleteSessionsStmt) {
            throw new Exception('DB prepare failed (delete sessions): ' . $conn->error);
        }
        $deleteSessionsStmt->bind_param("s", $today);
        if (!$deleteSessionsStmt->execute()) {
            throw new Exception('DB execute failed (delete sessions): ' . $deleteSessionsStmt->error);
        }
        $deleteSessionsStmt->close();
        
        // 3. Delete today's sales records
        $deleteSalesStmt = $conn->prepare("DELETE FROM sales WHERE session_date = ?");
        if (!$deleteSalesStmt) {
            throw new Exception('DB prepare failed (delete sales): ' . $conn->error);
        }
        $deleteSalesStmt->bind_param("s", $today);
        if (!$deleteSalesStmt->execute()) {
            throw new Exception('DB execute failed (delete sales): ' . $deleteSalesStmt->error);
        }
        $deleteSalesStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'All employee stats have been reset for the new workday',
            'reset_date' => $today
        ]);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
