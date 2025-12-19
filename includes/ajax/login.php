<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $auth = new Auth();
    
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    
    // Debug logging
    error_log("Login attempt - Username: $username, Role: $role, Has password: " . (!empty($password) ? 'yes' : 'no'));
    
    if (empty($username) || empty($password) || empty($role)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required',
            'debug' => [
                'username' => $username,
                'has_password' => !empty($password),
                'role' => $role
            ]
        ]);
        exit();
    }
    
    // Validate role
    if (!in_array($role, ['admin', 'doctor', 'patient'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid role selected'
        ]);
        exit();
    }
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        // Check if the user's role matches the selected role
        if ($result['user']['role'] !== $role) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials for the selected role'
            ]);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $result['redirect']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Login AJAX error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during login. Please try again.'
    ]);
}
?>
