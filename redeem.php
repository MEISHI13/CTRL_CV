<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    $rewardId = $_POST['reward_id'] ?? 0;
    $result = redeemPartnerReward($_SESSION['user_id'], $rewardId);
    
    if (isset($result['success'])) {
        echo json_encode([
            'success' => true,
            'redemption_code' => $result['redemption_code'],
            'partner_name' => $result['partner_name'],
            'discount' => $result['discount'],
            'points_spent' => $result['points_spent'],
            'expires_at' => $result['expires_at']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unable to redeem'
        ]);
    }
}
?>