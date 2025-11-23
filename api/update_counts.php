<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['admin_user_id']) || !isset($data['target_user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Admin user ID and target user ID are required']);
        exit;
    }
    
    $adminUserId = $data['admin_user_id'];
    $targetUserId = $data['target_user_id'];
    $creditCards = isset($data['credit_cards']) ? intval($data['credit_cards']) : null;
    $paidMemberships = isset($data['paid_memberships']) ? intval($data['paid_memberships']) : null;
    $sessionDate = date('Y-m-d');
    
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
        echo json_encode(['success' => false, 'error' => 'Admin user not found']);
        $authStmt->close();
        $conn->close();
        exit;
    }
    
    $admin = $authResult->fetch_assoc();
    if (!$admin['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Access denied. Admin privileges required.']);
        $authStmt->close();
        $conn->close();
        exit;
    }
    $authStmt->close();
    
    // Check if work session exists for today
    $sessionStmt = $conn->prepare("SELECT id, goal_credit_cards, goal_paid_memberships FROM work_sessions WHERE user_id = ? AND session_date = ?");
    if (!$sessionStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (check session): ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $sessionStmt->bind_param("is", $targetUserId, $sessionDate);
    if (!$sessionStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (check session): ' . $sessionStmt->error]);
        $sessionStmt->close();
        $conn->close();
        exit;
    }
    
    $sessionResult = $sessionStmt->get_result();
    if ($sessionResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No work session found for today. Employee needs to start their shift first.']);
        $sessionStmt->close();
        $conn->close();
        exit;
    }
    
    $session = $sessionResult->fetch_assoc();
    $goalCC = $session['goal_credit_cards'];
    $goalPM = $session['goal_paid_memberships'];
    $sessionStmt->close();
    
    // Update counts
    $updates = [];
    $params = [];
    $types = '';
    
    if ($creditCards !== null) {
        $updates[] = "current_credit_cards = ?";
        $params[] = $creditCards;
        $types .= 'i';
    }
    
    if ($paidMemberships !== null) {
        $updates[] = "current_paid_memberships = ?";
        $params[] = $paidMemberships;
        $types .= 'i';
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No counts provided to update']);
        $conn->close();
        exit;
    }
    
    $updateSQL = "UPDATE work_sessions SET " . implode(', ', $updates) . " WHERE user_id = ? AND session_date = ?";
    $updateStmt = $conn->prepare($updateSQL);
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (update counts): ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    // Add user_id and session_date to params
    $params[] = $targetUserId;
    $params[] = $sessionDate;
    $types .= 'is';
    
    $updateStmt->bind_param($types, ...$params);
    if (!$updateStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (update counts): ' . $updateStmt->error]);
        $updateStmt->close();
        $conn->close();
        exit;
    }
    $updateStmt->close();
    
    // Get updated counts
    $getStmt = $conn->prepare("SELECT current_credit_cards, current_paid_memberships FROM work_sessions WHERE user_id = ? AND session_date = ?");
    $getStmt->bind_param("is", $targetUserId, $sessionDate);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    $updatedSession = $getResult->fetch_assoc();
    $getStmt->close();
    
    $currentCC = $updatedSession['current_credit_cards'];
    $currentPM = $updatedSession['current_paid_memberships'];
    
    // Calculate NEW happiness based on goal progress
    $pmProgress = ($goalPM > 0) ? min(100, ($currentPM / $goalPM) * 100) : 0;
    $ccProgress = ($goalCC > 0) ? min(100, ($currentCC / $goalCC) * 100) : 0;
    $newHappiness = round(($pmProgress + $ccProgress) / 2);
    
    // Update animal happiness
    $happinessStmt = $conn->prepare("UPDATE animal_stats SET happiness = ? WHERE user_id = ?");
    if (!$happinessStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (update happiness): ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $happinessStmt->bind_param("ii", $newHappiness, $targetUserId);
    if (!$happinessStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (update happiness): ' . $happinessStmt->error]);
        $happinessStmt->close();
        $conn->close();
        exit;
    }
    $happinessStmt->close();
    
    echo json_encode([
        'success' => true,
        'current_credit_cards' => $currentCC,
        'current_paid_memberships' => $currentPM,
        'happiness' => $newHappiness,
        'message' => 'Counts updated successfully'
    ]);
    
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
