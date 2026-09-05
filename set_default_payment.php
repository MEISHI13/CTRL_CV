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
        $pdo->beginTransaction();
        
        // Reset all defaults for this user
        $stmt = $pdo->prepare("UPDATE payment_methods SET is_default = FALSE WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        
        // Set this one as default
        $stmt = $pdo->prepare("UPDATE payment_methods SET is_default = TRUE WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $methodId, 'user_id' => $_SESSION['user_id']]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Database error']);
    }
}
?>