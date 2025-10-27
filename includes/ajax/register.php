<?php
// Prevent any output before JSON response - MUST BE FIRST
@ini_set('display_errors', '0');
@error_reporting(0);

// Set up error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("FATAL ERROR in register.php: " . print_r($error, true));
        // Try to send a response if headers not sent
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Fatal error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
            ]);
        }
    }
});

// Start output buffering to catch any stray output
ob_start();

try {
    require_once '../config.php';
    require_once '../database.php';
    require_once '../functions.php';
} catch (Error $e) {
    error_log("FATAL: Include error - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    error_log("ERROR: Include exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    exit();
}

// Discard any output from includes and start fresh
ob_end_clean();
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    error_log("=== REGISTRATION START ===");
    error_log("POST data: " . print_r($_POST, true));
    
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
        ob_end_clean();
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
    
    error_log("Calling auth->register()");
    $result = $auth->register($userData);
    error_log("Register result: " . print_r($result, true));
    
    if ($result['success']) {
        error_log("Registration successful, sending response");
        // Try to send welcome notification (non-critical)
        try {
            createNotification(
                $result['user_id'],
                'Welcome to EasyMed!',
                'Thank you for registering with EasyMed. You can now book appointments with our doctors.',
                'success'
            );
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            // Continue anyway - notification failure shouldn't stop registration
        }
        
        // Try to send welcome email (non-critical)
        try {
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
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
            // Continue anyway - email failure shouldn't stop registration
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please login to continue.'
        ]);
        exit();
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Registration AJAX error: " . $e->getMessage());
    error_log("Registration AJAX error trace: " . $e->getTraceAsString());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during registration. Please try again.'
    ]);
    exit();
}

