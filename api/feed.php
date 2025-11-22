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
    $hasCreditCard = isset($data['has_credit_card']) ? $data['has_credit_card'] : false;
    $hasPaidMembership = isset($data['has_paid_membership']) ? $data['has_paid_membership'] : false;
    $hasWarranty = isset($data['has_warranty']) ? $data['has_warranty'] : false;
    $sessionDate = date('Y-m-d');
    
    $conn = getDBConnection();
    
    // Record the sale with its special features
    $saleStmt = $conn->prepare("INSERT INTO sales (user_id, session_date, revenue, has_credit_card, has_paid_membership, has_warranty) VALUES (?, ?, ?, ?, ?, ?)");
    $saleStmt->bind_param("isdiiii", $userId, $sessionDate, $revenue, $hasCreditCard, $hasPaidMembership, $hasWarranty);
    $saleStmt->execute();
    $saleStmt->close();
    
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
    
    // Calculate health increase based on revenue (e.g., $50 = 10 health, max 20 base)
    $healthIncrease = min(20, floor($revenue / 50) * 10);
    $happinessIncrease = $healthIncrease;
    
    // Apply special bonuses
    $bonusMessage = '';
    
    // Bonus for Credit Card + Paid Membership combo
    if ($hasCreditCard && $hasPaidMembership) {
        $healthIncrease += 15;
        $happinessIncrease += 20;
        $bonusMessage = '🎉 AMAZING! Credit Card + Paid Membership combo! +15 Health, +20 Happiness!';
    }
    
    // Bonus for Warranty
    if ($hasWarranty) {
        $healthIncrease += 10;
        $happinessIncrease += 10;
        if ($bonusMessage) {
            $bonusMessage .= ' 🛡️ Plus Warranty bonus! +10 Health, +10 Happiness!';
        } else {
            $bonusMessage = '🛡️ Great job with the Warranty! +10 Health, +10 Happiness!';
        }
    }
    
    // Update animal stats
    $animalStmt = $conn->prepare("UPDATE animal_stats 
                                   SET health = LEAST(100, health + ?), 
                                       happiness = LEAST(100, happiness + ?),
                                       last_fed = NOW(),
                                       total_revenue = total_revenue + ?
                                   WHERE user_id = ?");
    $animalStmt->bind_param("iidi", $healthIncrease, $happinessIncrease, $revenue, $userId);
    
    if ($animalStmt->execute()) {
        // Get updated stats
        $statsStmt = $conn->prepare("SELECT health, happiness, total_revenue FROM animal_stats WHERE user_id = ?");
        $statsStmt->bind_param("i", $userId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc();
        
        $message = 'Animal fed successfully!';
        if ($bonusMessage) {
            $message .= ' ' . $bonusMessage;
        }
        
        echo json_encode([
            'success' => true,
            'health' => $stats['health'],
            'happiness' => $stats['happiness'],
            'total_revenue' => $stats['total_revenue'],
            'goal_met' => $goalMet,
            'message' => $message
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
