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
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT u.id, u.username, u.name, u.animal_choice, 
                            COALESCE(a.health, 100) as health, 
                            COALESCE(a.happiness, 100) as happiness,
                            COALESCE(a.last_fed, NOW()) as last_fed,
                            COALESCE(a.total_revenue, 0) as total_revenue
                            FROM users u
                            LEFT JOIN animal_stats a ON u.id = a.user_id
                            WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update health based on time since last fed
        $lastFed = strtotime($user['last_fed']);
        $now = time();
        $hoursSinceLastFed = ($now - $lastFed) / 3600;
        
        // Decrease health by 5 per hour not fed (max decrease to 0)
        $healthDecrease = min($user['health'], floor($hoursSinceLastFed * 5));
        $currentHealth = max(0, $user['health'] - $healthDecrease);
        
        // Update animal stats if they exist
        if ($user['health'] !== null) {
            $updateStmt = $conn->prepare("UPDATE animal_stats SET health = ? WHERE user_id = ?");
            $updateStmt->bind_param("ii", $currentHealth, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Create animal stats if they don't exist
            $insertStmt = $conn->prepare("INSERT INTO animal_stats (user_id, health, happiness) VALUES (?, ?, 100)");
            $insertStmt->bind_param("ii", $user['id'], $currentHealth);
            $insertStmt->execute();
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
