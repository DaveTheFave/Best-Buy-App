<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['animal_choice'])) {
        echo json_encode(['success' => false, 'error' => 'User ID and animal choice are required']);
        exit;
    }
    
    $userId = $data['user_id'];
    $animalChoice = $data['animal_choice'];
    
    // Validate animal choice
    $validAnimals = ['cat', 'dog', 'bird', 'rabbit', 'hamster', 'fish'];
    if (!in_array(strtolower($animalChoice), $validAnimals)) {
        echo json_encode(['success' => false, 'error' => 'Invalid animal choice']);
        exit;
    }
    
    $conn = getDBConnection();

    $stmt = $conn->prepare("UPDATE users SET animal_choice = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed (change pet): ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("si", $animalChoice, $userId);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB execute failed (change pet): ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    echo json_encode([
        'success' => true,
        'animal_choice' => $animalChoice,
        'message' => 'Pet changed successfully!'
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
