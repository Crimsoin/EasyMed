<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$auth = new Auth();

try {
    switch ($action) {
        case 'initiate':
            $identity = $_POST['identity'] ?? '';
            if (empty($identity)) {
                echo json_encode(['success' => false, 'message' => 'Username or email is required.']);
                exit();
            }
            $result = $auth->initiatePasswordReset($identity);
            echo json_encode($result);
            break;

        case 'verify':
            $identity = $_POST['identity'] ?? '';
            $otp = $_POST['otp'] ?? '';
            if (empty($identity) || empty($otp)) {
                echo json_encode(['success' => false, 'message' => 'Identity and code are required.']);
                exit();
            }
            $result = $auth->verifyResetOTP($identity, $otp);
            echo json_encode($result);
            break;

        case 'reset':
            $identity = $_POST['identity'] ?? '';
            $otp = $_POST['otp'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($identity) || empty($otp) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit();
            }

            if ($password !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
                exit();
            }

            if (strlen($password) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
                exit();
            }

            $result = $auth->resetPassword($identity, $otp, $password);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Exception $e) {
    error_log("Forgot Password AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal error occurred.']);
}
