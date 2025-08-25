<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $auth = new Auth();
    
    // Sanitize and validate input data
    $userData = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'username' => sanitize($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'phone' => sanitize($_POST['phone'] ?? ''),
        'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'role' => sanitize($_POST['role'] ?? 'patient')
    ];
    
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($userData['first_name'])) {
        $errors[] = 'First name is required';
    }
    
    if (empty($userData['last_name'])) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    if (empty($userData['username']) || strlen($userData['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters long';
    }
    
    if (empty($userData['password']) || strlen($userData['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    
    if ($userData['password'] !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!empty($userData['phone']) && !preg_match('/^[\+]?[0-9\-\s\(\)]{10,}$/', $userData['phone'])) {
        $errors[] = 'Invalid phone number format';
    }
    
    if (!empty($userData['date_of_birth']) && !isValidDate($userData['date_of_birth'])) {
        $errors[] = 'Invalid date of birth';
    }
    
    if (!in_array($userData['gender'], ['', 'male', 'female', 'other'])) {
        $errors[] = 'Invalid gender selection';
    }
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        exit();
    }
    
    // Remove empty optional fields
    if (empty($userData['phone'])) unset($userData['phone']);
    if (empty($userData['date_of_birth'])) unset($userData['date_of_birth']);
    if (empty($userData['gender'])) unset($userData['gender']);
    
    // Set default values
    $userData['is_active'] = true;
    $userData['email_verified'] = false;
    
    $result = $auth->register($userData);
    
    if ($result['success']) {
        // Send welcome notification
        createNotification(
            $result['user_id'],
            'Welcome to EasyMed!',
            'Thank you for registering with EasyMed. You can now book appointments with our doctors.',
            'success'
        );
        
        // Send welcome email (if email functionality is configured)
        $emailSubject = 'Welcome to EasyMed Private Clinic';
        $emailMessage = "
            <h2>Welcome to EasyMed!</h2>
            <p>Dear {$userData['first_name']} {$userData['last_name']},</p>
            <p>Thank you for registering with EasyMed Private Clinic. Your account has been successfully created.</p>
            <p>You can now:</p>
            <ul>
                <li>Book appointments with our doctors</li>
                <li>View your medical history</li>
                <li>Manage your profile</li>
                <li>Submit feedback and reviews</li>
            </ul>
            <p>To get started, please log in to your account using your username and password.</p>
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <br>
            <p>Best regards,<br>EasyMed Team</p>
        ";
        
        @sendEmail($userData['email'], $emailSubject, $emailMessage, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please login to continue.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Registration AJAX error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during registration. Please try again.'
    ]);
}
?>
