<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Check if user_id is provided for authentication check
    if (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        $conn = getDBConnection();
        
        // Check if user is admin
        $authStmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        if (!$authStmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
            $conn->close();
            exit;
        }
        
        $authStmt->bind_param("i", $userId);
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
        
        // User is authenticated as admin, proceed with fetching data
        $today = date('Y-m-d');
        
        $stmt = $conn->prepare("SELECT 
                                u.id,
                                u.username,
                                u.name,
                                u.animal_choice,
                                COALESCE(a.health, 100) as health,
                                COALESCE(a.happiness, 100) as happiness,
                                COALESCE(a.total_revenue, 0) as total_revenue,
                                COALESCE(a.last_fed, NOW()) as last_fed,
                                a.last_health_reset,
                                CASE WHEN ws.id IS NOT NULL THEN 1 ELSE 0 END as has_session_today
                                FROM users u
                                LEFT JOIN animal_stats a ON u.id = a.user_id
                                LEFT JOIN work_sessions ws ON u.id = ws.user_id AND ws.session_date = ?
                                ORDER BY u.name ASC");
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
            $conn->close();
            exit;
        }
        
        $stmt->bind_param("s", $today);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB execute failed: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        $result = $stmt->get_result();
        $pets = [];
        $totalHealth = 0;
        $totalHappiness = 0;
        $activeToday = 0;
        
        while ($row = $result->fetch_assoc()) {
            $pets[] = $row;
            $totalHealth += $row['health'];
            $totalHappiness += $row['happiness'];
            if ($row['has_session_today']) {
                $activeToday++;
            }
        }
        
        $totalEmployees = count($pets);
        $avgHealth = $totalEmployees > 0 ? $totalHealth / $totalEmployees : 0;
        $avgHappiness = $totalEmployees > 0 ? $totalHappiness / $totalEmployees : 0;
        
        echo json_encode([
            'success' => true,
            'pets' => $pets,
            'summary' => [
                'total_employees' => $totalEmployees,
                'active_today' => $activeToday,
                'avg_health' => $avgHealth,
                'avg_happiness' => $avgHappiness
            ]
        ]);
        
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'User ID required for authentication']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
