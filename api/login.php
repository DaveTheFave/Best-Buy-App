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
    
    // ============================================
    // Auto-reset at 8am daily check
    // ============================================
    // Check if we need to do an automatic 8am reset
    $currentHour = (int)date('G'); // 0-23 hour format
    $today = date('Y-m-d');
    
    // Create a marker table to track last reset if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS daily_reset_marker (
        id INT PRIMARY KEY DEFAULT 1,
        last_reset_date DATE NOT NULL,
        last_reset_time DATETIME NOT NULL
    )");
    
    // Check when the last reset was performed
    $markerResult = $conn->query("SELECT last_reset_date FROM daily_reset_marker WHERE id = 1");
    $shouldReset = false;
    
    if ($markerResult && $markerResult->num_rows > 0) {
        $marker = $markerResult->fetch_assoc();
        $lastResetDate = $marker['last_reset_date'];
        
        // Reset if it's a new day and current time is 8am or later
        if ($lastResetDate < $today && $currentHour >= 8) {
            $shouldReset = true;
        }
    } else {
        // No marker exists yet, create one if it's 8am or later
        if ($currentHour >= 8) {
            $shouldReset = true;
        }
    }
    
    // Perform the auto-reset if needed
    if ($shouldReset) {
        $conn->begin_transaction();
        try {
            // Reset all employee stats
            $conn->query("UPDATE animal_stats SET health = 100, happiness = 0, last_fed = NOW(), last_health_reset = '$today'");
            
            // Delete today's work sessions
            $conn->query("DELETE FROM work_sessions WHERE session_date = '$today'");
            
            // Delete today's sales records
            $conn->query("DELETE FROM sales WHERE session_date = '$today'");
            
            // Update the reset marker
            $conn->query("INSERT INTO daily_reset_marker (id, last_reset_date, last_reset_time) 
                         VALUES (1, '$today', NOW()) 
                         ON DUPLICATE KEY UPDATE last_reset_date = '$today', last_reset_time = NOW()");
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            // Log error but continue with login
            error_log("Auto-reset failed: " . $e->getMessage());
        }
    }

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
