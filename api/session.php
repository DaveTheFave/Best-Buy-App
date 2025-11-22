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
    
    // Calculate goal based on work hours ($1000 per hour)
    $goalAmount = $workHours * 1000;
    
    $conn = getDBConnection();
    
    // Check if session already exists for today
    $checkStmt = $conn->prepare("SELECT id FROM work_sessions WHERE user_id = ? AND session_date = ?");
    $checkStmt->bind_param("is", $userId, $sessionDate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing session
        $stmt = $conn->prepare("UPDATE work_sessions SET work_hours = ?, goal_amount = ? WHERE user_id = ? AND session_date = ?");
        $stmt->bind_param("ddis", $workHours, $goalAmount, $userId, $sessionDate);
    } else {
        // Create new session
        $stmt = $conn->prepare("INSERT INTO work_sessions (user_id, work_hours, session_date, goal_amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idsd", $userId, $workHours, $sessionDate, $goalAmount);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'goal_amount' => $goalAmount,
            'work_hours' => $workHours,
            'message' => 'Work session created successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create work session']);
    }
    
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
    $stmt->bind_param("is", $userId, $sessionDate);
    $stmt->execute();
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
