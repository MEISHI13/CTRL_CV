<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = $_POST['group_id'] ?? 0;
    $address = $_POST['address'] ?? '';
    $weight = $_POST['weight'] ?? null;
    $description = $_POST['description'] ?? null;
    
    if (empty($address)) {
        echo json_encode(['error' => 'Address is required']);
        exit();
    }
    
    if (empty($weight) || $weight <= 0) {
        echo json_encode(['error' => 'Please enter a valid estimated weight']);
        exit();
    }
    
    $result = joinGroupPickup(
        $_SESSION['user_id'],
        $groupId,
        $address,
        $weight,
        $description
    );
    
    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']]);
    } else {
        echo json_encode(['success' => true]);
    }
}
?>
