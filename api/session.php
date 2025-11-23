<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['work_hours'])) {
        echo json_encode(['success' => false, 'error' => 'User ID and work hours are required']);
        exit;
    }
    
    $userId = $data['user_id'];
    $workHours = $data['work_hours'];
    $sessionDate = date('Y-m-d');
    
    // Calculate goals: 1 Paid Membership per 4 hours, 1 Credit Card per 7 hours
    $goalPaidMemberships = ceil($workHours / 4);
    $goalCreditCards = ceil($workHours / 7);
    // Revenue goal is secondary (per-hour target)
    // Adjusted multiplier: $1000 per hour (e.g., 8 hours => $8,000)
    $goalAmount = $workHours * 1000;
    
    $conn = getDBConnection();

    // Check if session already exists for today
    $checkStmt = $conn->prepare("SELECT id FROM work_sessions WHERE user_id = ? AND session_date = ?");
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (check session): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $checkStmt->bind_param("is", $userId, $sessionDate);
    if (!$checkStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (check session): ' . $checkStmt->error]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing session
        $stmt = $conn->prepare("UPDATE work_sessions SET work_hours = ?, goal_amount = ?, goal_paid_memberships = ?, goal_credit_cards = ? WHERE user_id = ? AND session_date = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed (update session): ' . $conn->error]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $stmt->bind_param("ddiiis", $workHours, $goalAmount, $goalPaidMemberships, $goalCreditCards, $userId, $sessionDate);
    } else {
        // Create new session
        $stmt = $conn->prepare("INSERT INTO work_sessions (user_id, work_hours, session_date, goal_amount, goal_paid_memberships, goal_credit_cards) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed (insert session): ' . $conn->error]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        // types: i (user_id), d (work_hours), s (session_date), d (goal_amount), i (goal_paid_memberships), i (goal_credit_cards)
        $stmt->bind_param("idsdii", $userId, $workHours, $sessionDate, $goalAmount, $goalPaidMemberships, $goalCreditCards);
    }
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (create/update session): ' . $stmt->error]);
        $stmt->close();
        $checkStmt->close();
        $conn->close();
        exit;
    }

    echo json_encode([
        'success' => true,
        'goal_amount' => $goalAmount,
        'work_hours' => $workHours,
        'goal_paid_memberships' => $goalPaidMemberships,
        'goal_credit_cards' => $goalCreditCards,
        'message' => 'Work session created successfully'
    ]);
    
    $checkStmt->close();
    $stmt->close();
    $conn->close();
} else if ($method === 'GET') {
    if (!isset($_GET['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit;
    }
    
    $userId = $_GET['user_id'];
    $sessionDate = date('Y-m-d');
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM work_sessions WHERE user_id = ? AND session_date = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (get session): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("is", $userId, $sessionDate);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (get session): ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        echo json_encode(['success' => true, 'session' => $session]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No session found for today']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
