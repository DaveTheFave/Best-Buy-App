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
    $overriddenHighValue = isset($data['overridden_high_value']) ? $data['overridden_high_value'] : false;
    $sessionDate = date('Y-m-d');
    
    $conn = getDBConnection();

    // Record the sale with its special features
    $saleStmt = $conn->prepare("INSERT INTO sales (user_id, session_date, revenue, has_credit_card, has_paid_membership, has_warranty, overridden_high_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$saleStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (insert sale): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $saleStmt->bind_param("isdiiii", $userId, $sessionDate, $revenue, $hasCreditCard, $hasPaidMembership, $hasWarranty, $overriddenHighValue);
    if (!$saleStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (insert sale): ' . $saleStmt->error]);
        $saleStmt->close();
        $conn->close();
        exit;
    }
    $saleStmt->close();
    
    // Update revenue in today's work session
    $sessionStmt = $conn->prepare("UPDATE work_sessions SET revenue = revenue + ? WHERE user_id = ? AND session_date = ?");
    if (!$sessionStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (update session revenue): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $sessionStmt->bind_param("dis", $revenue, $userId, $sessionDate);
    if (!$sessionStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (update session revenue): ' . $sessionStmt->error]);
        $sessionStmt->close();
        $conn->close();
        exit;
    }
    $sessionStmt->close();
    
    // Update credit card and paid membership counts
    if ($hasCreditCard) {
        $ccStmt = $conn->prepare("UPDATE work_sessions SET current_credit_cards = current_credit_cards + 1 WHERE user_id = ? AND session_date = ?");
        if (!$ccStmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed (update credit cards): ' . $conn->error]);
            $conn->close();
            exit;
        }
        $ccStmt->bind_param("is", $userId, $sessionDate);
        if (!$ccStmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB execute failed (update credit cards): ' . $ccStmt->error]);
            $ccStmt->close();
            $conn->close();
            exit;
        }
        $ccStmt->close();
    }
    
    if ($hasPaidMembership) {
        $pmStmt = $conn->prepare("UPDATE work_sessions SET current_paid_memberships = current_paid_memberships + 1 WHERE user_id = ? AND session_date = ?");
        if (!$pmStmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed (update paid memberships): ' . $conn->error]);
            $conn->close();
            exit;
        }
        $pmStmt->bind_param("is", $userId, $sessionDate);
        if (!$pmStmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB execute failed (update paid memberships): ' . $pmStmt->error]);
            $pmStmt->close();
            $conn->close();
            exit;
        }
        $pmStmt->close();
    }
    
    // Check if goal is met (primary: memberships and credit cards, secondary: revenue)
    $checkStmt = $conn->prepare("SELECT revenue, goal_amount, current_paid_memberships, goal_paid_memberships, current_credit_cards, goal_credit_cards FROM work_sessions WHERE user_id = ? AND session_date = ?");
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (check session after sale): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $checkStmt->bind_param("is", $userId, $sessionDate);
    if (!$checkStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (check session after sale): ' . $checkStmt->error]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $result = $checkStmt->get_result();
    
    $goalMet = false;
    $currentPM = 0;
    $goalPM = 1;
    $currentCC = 0;
    $goalCC = 1;
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        $currentPM = $session['current_paid_memberships'];
        $goalPM = $session['goal_paid_memberships'];
        $currentCC = $session['current_credit_cards'];
        $goalCC = $session['goal_credit_cards'];
        
        // Goal is met if they have both required memberships and credit cards
        $goalMet = ($currentPM >= $goalPM) && ($currentCC >= $goalCC);
        
        // Update goal_met status
        $updateGoalStmt = $conn->prepare("UPDATE work_sessions SET goal_met = ? WHERE user_id = ? AND session_date = ?");
        if (!$updateGoalStmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed (update goal): ' . $conn->error]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $updateGoalStmt->bind_param("iis", $goalMet, $userId, $sessionDate);
        if (!$updateGoalStmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB execute failed (update goal): ' . $updateGoalStmt->error]);
            $updateGoalStmt->close();
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $updateGoalStmt->close();
    }
    
    // Calculate health increase based on revenue (health is connected to revenue)
    $healthIncrease = 5; // Base for any sale
    
    // Revenue-based health bonus
    if ($revenue >= 100) {
        $healthIncrease += 5;
    }
    if ($revenue >= 500) {
        $healthIncrease += 5;
    }
    
    // Apply special bonuses
    $bonusMessage = '';
    
    // Bonus for Paid Membership - affects health
    if ($hasPaidMembership) {
        $healthIncrease += 20;
        $bonusMessage = '⭐ EXCELLENT! Paid Membership! +20 Health!';
    }
    
    // Bonus for Credit Card - affects health
    if ($hasCreditCard) {
        $healthIncrease += 20;
        if ($bonusMessage) {
            $bonusMessage .= ' 💳 Plus Credit Card! +20 Health!';
        } else {
            $bonusMessage = '💳 EXCELLENT! Credit Card! +20 Health!';
        }
    }
    
    // Extra bonus for the combo
    if ($hasCreditCard && $hasPaidMembership) {
        $healthIncrease += 10;
        $bonusMessage = '🎉 AMAZING COMBO! Credit Card + Paid Membership! Total: +50 Health!';
    }
    
    // Bonus for Warranty
    if ($hasWarranty) {
        $healthIncrease += 10;
        if ($bonusMessage) {
            $bonusMessage .= ' 🛡️ Plus Warranty! +10 Health!';
        } else {
            $bonusMessage = '🛡️ Great job with the Warranty! +10 Health!';
        }
    }
    
    // Calculate NEW happiness based ONLY on Credit Card and Paid Membership goal progress
    // Happiness = 100 when goals met, 0 when none achieved, proportional in between
    $pmProgress = ($goalPM > 0) ? min(100, ($currentPM / $goalPM) * 100) : 0;
    $ccProgress = ($goalCC > 0) ? min(100, ($currentCC / $goalCC) * 100) : 0;
    $newHappiness = round(($pmProgress + $ccProgress) / 2);
    
    // Update animal stats - set happiness to calculated value, add to health
    $animalStmt = $conn->prepare("UPDATE animal_stats 
                                   SET health = LEAST(100, health + ?), 
                                       happiness = ?,
                                       last_fed = NOW(),
                                       total_revenue = total_revenue + ?
                                   WHERE user_id = ?");
    if (!$animalStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (update animal stats): ' . $conn->error]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $animalStmt->bind_param("iidi", $healthIncrease, $newHappiness, $revenue, $userId);
    
    if (!$animalStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (update animal stats): ' . $animalStmt->error]);
        $animalStmt->close();
        $checkStmt->close();
        $conn->close();
        exit;
    }

    // Get updated stats
    $statsStmt = $conn->prepare("SELECT health, happiness, total_revenue FROM animal_stats WHERE user_id = ?");
    if (!$statsStmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (select stats): ' . $conn->error]);
        $animalStmt->close();
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $statsStmt->bind_param("i", $userId);
    if (!$statsStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (select stats): ' . $statsStmt->error]);
        $statsStmt->close();
        $animalStmt->close();
        $checkStmt->close();
        $conn->close();
        exit;
    }
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
    $animalStmt->close();
    
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
