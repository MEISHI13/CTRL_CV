<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = $_POST['notification_id'] ?? 0;
    
    $result = markNotificationRead($notificationId, $_SESSION['user_id']);
    echo json_encode(['success' => $result]);
}
?>
