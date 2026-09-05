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
    
    try {
        $pdo = getDBConnection();
        
        // Check if user is creator
        $stmt = $pdo->prepare("
            SELECT creator_id FROM group_pickups WHERE id = :id
        ");
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch();
        
        if (!$group || $group['creator_id'] != $_SESSION['user_id']) {
            echo json_encode(['error' => 'Only the creator can cancel this group']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE group_pickups SET status = 'cancelled' WHERE id = :id
        ");
        $stmt->execute(['id' => $groupId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to cancel group']);
    }
}
?>