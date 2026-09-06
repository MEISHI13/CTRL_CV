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
    $address = $_POST['address'] ?? '';
    $weight = $_POST['weight'] ?? 0;
    $pickupDate = $_POST['pickup_date'] ?? '';
    $pickupTime = $_POST['pickup_time'] ?? '';
    $maxParticipants = $_POST['max_participants'] ?? 5;
    $meetingPoint = $_POST['meeting_point'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // Validate required fields
    if (empty($centerId)) {
        echo json_encode(['error' => 'Please select a recycling center']);
        exit();
    }
    if (empty($address)) {
        echo json_encode(['error' => 'Please enter your pickup address']);
        exit();
    }
    if (empty($weight) || $weight <= 0) {
        echo json_encode(['error' => 'Please enter a valid estimated weight']);
        exit();
    }
    if (empty($pickupDate)) {
        echo json_encode(['error' => 'Please select a pickup date']);
        exit();
    }
    if (empty($pickupTime)) {
        echo json_encode(['error' => 'Please select a pickup time']);
        exit();
    }
    
    // Validate that center exists
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM recycling_centers WHERE id = :id");
        $stmt->execute(['id' => $centerId]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Invalid recycling center selected']);
            exit();
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    
    try {
        $pdo = getDBConnection();
        
        $pdo->beginTransaction();
        
        // Create group
        $stmt = $pdo->prepare("
            INSERT INTO group_pickups (creator_id, center_id, pickup_date, pickup_time, max_participants, meeting_point, notes)
            VALUES (:creator_id, :center_id, :pickup_date, :pickup_time, :max_participants, :meeting_point, :notes)
        ");
        $stmt->execute([
            'creator_id' => $_SESSION['user_id'],
            'center_id' => $centerId,
            'pickup_date' => $pickupDate,
            'pickup_time' => $pickupTime,
            'max_participants' => $maxParticipants,
            'meeting_point' => $meetingPoint,
            'notes' => $notes
        ]);
        
        $groupId = $pdo->lastInsertId();
        
        // Add creator as first participant WITH address and weight
        $stmt = $pdo->prepare("
            INSERT INTO group_pickup_participants (group_pickup_id, user_id, address, estimated_weight, status)
            VALUES (:group_id, :user_id, :address, :weight, 'accepted')
        ");
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $_SESSION['user_id'],
            'address' => $address,
            'weight' => $weight
        ]);
        
        // Update estimated total weight
        $stmt = $pdo->prepare("
            UPDATE group_pickups 
            SET current_participants = 1,
                estimated_total_weight = :weight
            WHERE id = :id
        ");
        $stmt->execute([
            'weight' => $weight,
            'id' => $groupId
        ]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'group_id' => $groupId]);
        
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Create group error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to create group: ' . $e->getMessage()]);
    }
}
?>
