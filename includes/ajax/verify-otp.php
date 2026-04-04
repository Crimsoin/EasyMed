<?php
require_once '../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$email = sanitize($_POST['email'] ?? '');
$otp = sanitize($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
    exit();
}

$auth = new Auth();
$result = $auth->verifyOTP($email, $otp);

echo json_encode($result);
exit();
