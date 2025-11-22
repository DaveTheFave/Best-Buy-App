<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['revenue'])) {
        echo json_encode(['success' => false, 'error' => 'User ID and revenue are required']);
        exit;
    }
    
    $userId = $data['user_id'];
    $revenue = $data['revenue'];
    $sessionDate = date('Y-m-d');
    
    $conn = getDBConnection();
    
    // Update revenue in today's work session
    $sessionStmt = $conn->prepare("UPDATE work_sessions SET revenue = revenue + ? WHERE user_id = ? AND session_date = ?");
    $sessionStmt->bind_param("dis", $revenue, $userId, $sessionDate);
    $sessionStmt->execute();
    
    // Check if goal is met
    $checkStmt = $conn->prepare("SELECT revenue, goal_amount FROM work_sessions WHERE user_id = ? AND session_date = ?");
    $checkStmt->bind_param("is", $userId, $sessionDate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    $goalMet = false;
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        $goalMet = $session['revenue'] >= $session['goal_amount'];
        
        // Update goal_met status
        $updateGoalStmt = $conn->prepare("UPDATE work_sessions SET goal_met = ? WHERE user_id = ? AND session_date = ?");
        $updateGoalStmt->bind_param("iis", $goalMet, $userId, $sessionDate);
        $updateGoalStmt->execute();
        $updateGoalStmt->close();
    }
    
    // Calculate health increase based on revenue (e.g., $50 = 10 health, max 100)
    $healthIncrease = min(20, floor($revenue / 50) * 10);
    
    // Update animal stats
    $animalStmt = $conn->prepare("UPDATE animal_stats 
                                   SET health = LEAST(100, health + ?), 
                                       happiness = LEAST(100, happiness + ?),
                                       last_fed = NOW(),
                                       total_revenue = total_revenue + ?
                                   WHERE user_id = ?");
    $happinessIncrease = $healthIncrease;
    $animalStmt->bind_param("iidi", $healthIncrease, $happinessIncrease, $revenue, $userId);
    
    if ($animalStmt->execute()) {
        // Get updated stats
        $statsStmt = $conn->prepare("SELECT health, happiness, total_revenue FROM animal_stats WHERE user_id = ?");
        $statsStmt->bind_param("i", $userId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'health' => $stats['health'],
            'happiness' => $stats['happiness'],
            'total_revenue' => $stats['total_revenue'],
            'goal_met' => $goalMet,
            'message' => 'Animal fed successfully!'
        ]);
        
        $statsStmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to feed animal']);
    }
    
    $sessionStmt->close();
    $checkStmt->close();
    $animalStmt->close();
    $conn->close();
} else if ($method === 'GET') {
    if (!isset($_GET['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit;
    }
    
    $userId = $_GET['user_id'];
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT health, happiness, last_fed, total_revenue FROM animal_stats WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        echo json_encode(['success' => true, 'stats' => $stats]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Animal stats not found']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
