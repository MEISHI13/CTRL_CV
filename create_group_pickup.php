<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $centerId = $_POST['center_id'] ?? 0;
    $pickupDate = $_POST['pickup_date'] ?? '';
    $pickupTime = $_POST['pickup_time'] ?? '';
    $maxParticipants = $_POST['max_participants'] ?? 5;
    $meetingPoint = $_POST['meeting_point'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    $groupId = createGroupPickup(
        $_SESSION['user_id'],
        $centerId,
        $pickupDate,
        $pickupTime,
        $maxParticipants,
        $meetingPoint,
        $notes
    );
    
    if ($groupId) {
        echo json_encode(['success' => true, 'group_id' => $groupId]);
    } else {
        echo json_encode(['error' => 'Failed to create group']);
    }
}
?>