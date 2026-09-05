<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(null);
    exit();
}

$groupId = $_GET['id'] ?? 0;

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            rc.name as center_name,
            rc.address as center_address,
            COUNT(p.id) as participant_count
        FROM group_pickups g
        JOIN recycling_centers rc ON g.center_id = rc.id
        LEFT JOIN group_pickup_participants p ON g.id = p.group_pickup_id AND p.status != 'cancelled'
        WHERE g.id = :id
        GROUP BY g.id
    ");
    $stmt->execute(['id' => $groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        echo json_encode(null);
        exit();
    }
    
    // Get participants
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            CASE WHEN p.user_id = g.creator_id THEN 'creator' ELSE 'participant' END as role
        FROM group_pickup_participants p
        JOIN users u ON p.user_id = u.id
        JOIN group_pickups g ON p.group_pickup_id = g.id
        WHERE p.group_pickup_id = :group_id
        AND p.status != 'cancelled'
    ");
    $stmt->execute(['group_id' => $groupId]);
    $participants = $stmt->fetchAll();
    
    // Calculate cost sharing
    $costData = calculateSharedCost($groupId);
    
    echo json_encode([
        'id' => $group['id'],
        'center_name' => $group['center_name'],
        'center_address' => $group['center_address'],
        'pickup_date' => $group['pickup_date'],
        'pickup_time' => $group['pickup_time'],
        'max_participants' => $group['max_participants'],
        'current_participants' => $group['participant_count'],
        'meeting_point' => $group['meeting_point'],
        'notes' => $group['notes'],
        'status' => $group['status'],
        'cost_per_person' => $costData ? 'RM ' . number_format($costData['share_per_person'], 2) : null,
        'participants' => $participants
    ]);
} catch (PDOException $e) {
    echo json_encode(null);
}
?>