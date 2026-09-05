<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methodId = $_POST['method_id'] ?? 0;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $methodId, 'user_id' => $_SESSION['user_id']]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
}
?>
