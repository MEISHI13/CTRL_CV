<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['payment_type'] ?? '';
    
    // Validate
    if (empty($type)) {
        echo json_encode(['error' => 'Payment type is required']);
        exit();
    }
    
    $data = [
        'account_name' => $_POST['account_name'] ?? null,
        'account_number' => $_POST['account_number'] ?? null,
        'bank_name' => $_POST['bank_name'] ?? null,
        'tng_phone' => $_POST['tng_phone'] ?? null,
        'is_default' => isset($_POST['is_default']) && $_POST['is_default'] == '1'
    ];
    
    // Validate based on payment type
    if ($type == 'bank') {
        if (empty($data['bank_name'])) {
            echo json_encode(['error' => 'Bank name is required']);
            exit();
        }
        if (empty($data['account_name'])) {
            echo json_encode(['error' => 'Account name is required']);
            exit();
        }
        if (empty($data['account_number'])) {
            echo json_encode(['error' => 'Account number is required']);
            exit();
        }
        if (!preg_match('/^[0-9]{8,16}$/', $data['account_number'])) {
            echo json_encode(['error' => 'Account number must be 8-16 digits']);
            exit();
        }
    } elseif ($type == 'tng' || $type == 'grabpay') {
        if (empty($data['tng_phone'])) {
            echo json_encode(['error' => 'Phone number is required']);
            exit();
        }
        $phone = preg_replace('/[^0-9]/', '', $data['tng_phone']);
        if (!preg_match('/^01[0-9]{8,9}$/', $phone)) {
            echo json_encode(['error' => 'Please enter a valid Malaysian phone number (e.g., 0123456789)']);
            exit();
        }
        $data['tng_phone'] = $phone;
    } else {
        echo json_encode(['error' => 'Invalid payment type']);
        exit();
    }
    
    $result = addPaymentMethod($_SESSION['user_id'], $type, $data);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to add payment method. Please try again.']);
    }
}
?>
