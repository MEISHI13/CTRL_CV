<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit();
}

try {
    $pdo = getDBConnection();
    $centers = $pdo->query("
        SELECT id, name, address, latitude, longitude 
        FROM recycling_centers 
        WHERE is_active = TRUE
        ORDER BY name
    ")->fetchAll();
    
    echo json_encode($centers);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>