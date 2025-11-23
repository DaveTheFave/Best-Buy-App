<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username'])) {
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        exit;
    }
    
    $username = $data['username'];
    $conn = getDBConnection();

    // Check if user exists. Also select the animal_stats.id so we can detect if stats exist.
    $stmt = $conn->prepare("SELECT u.id, u.username, u.name, u.animal_choice, u.is_admin,
                            a.id AS stat_id,
                            COALESCE(a.health, 100) as health, 
                            COALESCE(a.happiness, 100) as happiness,
                            COALESCE(a.last_fed, NOW()) as last_fed,
                            COALESCE(a.total_revenue, 0) as total_revenue,
                            a.last_health_reset
                            FROM users u
                            LEFT JOIN animal_stats a ON u.id = a.user_id
                            WHERE u.username = ?");

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if we need to reset health for a new day
        $today = date('Y-m-d');
        $lastResetDate = $user['last_health_reset'];
        $currentHealth = $user['health'];
        
        // If last_health_reset is NULL or it's a new day, reset health decay calculation
        if ($lastResetDate === NULL || $lastResetDate < $today) {
            // New day - reset the health decay timer
            // Check if there's an active work session for today
            $sessionCheckStmt = $conn->prepare("SELECT id FROM work_sessions WHERE user_id = ? AND session_date = ?");
            if ($sessionCheckStmt) {
                $sessionCheckStmt->bind_param("is", $user['id'], $today);
                $sessionCheckStmt->execute();
                $sessionResult = $sessionCheckStmt->get_result();
                
                if ($sessionResult->num_rows > 0) {
                    // There's a work session today, apply health decay since last fed TODAY
                    $lastFed = strtotime($user['last_fed']);
                    $lastFedDate = date('Y-m-d', $lastFed);
                    
                    // Only decay health if last fed was today
                    if ($lastFedDate === $today) {
                        $now = time();
                        $hoursSinceLastFed = ($now - $lastFed) / 3600;
                        $healthDecrease = min($currentHealth, floor($hoursSinceLastFed * 5));
                        $currentHealth = max(0, $currentHealth - $healthDecrease);
                    }
                    // If last fed was a previous day, don't decay (new day starts fresh)
                } else {
                    // No work session today, no health decay (pet is "resting")
                }
                $sessionCheckStmt->close();
            }
            
            // Update the last_health_reset to today
            $lastResetDate = $today;
        } else {
            // Same day - apply normal health decay
            $lastFed = strtotime($user['last_fed']);
            $lastFedDate = date('Y-m-d', $lastFed);
            
            // Only decay if last fed was today
            if ($lastFedDate === $today) {
                $now = time();
                $hoursSinceLastFed = ($now - $lastFed) / 3600;
                $healthDecrease = min($currentHealth, floor($hoursSinceLastFed * 5));
                $currentHealth = max(0, $currentHealth - $healthDecrease);
            }
        }
        
        // Update animal stats if they exist (detect via stat_id), otherwise create them
        if (!empty($user['stat_id'])) {
            $updateStmt = $conn->prepare("UPDATE animal_stats SET health = ?, last_health_reset = ? WHERE user_id = ?");
            if (!$updateStmt) {
                echo json_encode(['success' => false, 'error' => 'DB prepare failed (update): ' . $conn->error]);
                $stmt->close();
                $conn->close();
                exit;
            }
            $updateStmt->bind_param("isi", $currentHealth, $lastResetDate, $user['id']);
            if (!$updateStmt->execute()) {
                echo json_encode(['success' => false, 'error' => 'DB execute failed (update): ' . $updateStmt->error]);
                $updateStmt->close();
                $stmt->close();
                $conn->close();
                exit;
            }
            $updateStmt->close();
        } else {
            // Create animal stats if they don't exist
            $insertStmt = $conn->prepare("INSERT INTO animal_stats (user_id, health, happiness, last_health_reset) VALUES (?, ?, 100, ?)");
            if (!$insertStmt) {
                echo json_encode(['success' => false, 'error' => 'DB prepare failed (insert): ' . $conn->error]);
                $stmt->close();
                $conn->close();
                exit;
            }
            $insertStmt->bind_param("iis", $user['id'], $currentHealth, $lastResetDate);
            if (!$insertStmt->execute()) {
                echo json_encode(['success' => false, 'error' => 'DB execute failed (insert): ' . $insertStmt->error]);
                $insertStmt->close();
                $stmt->close();
                $conn->close();
                exit;
            }
            $insertStmt->close();
        }
        
        $user['health'] = $currentHealth;
        
        echo json_encode([
            'success' => true, 
            'user' => $user,
            'message' => 'Login successful'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'User not found. Please contact your manager to set up your account.'
        ]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
