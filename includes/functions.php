<?php
require_once 'config.php';
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($username, $password) {
        try {
            // Find user by username or email
            $user = $this->db->fetch(
                "SELECT u.*, d.id as doctor_id, d.specialty, d.is_available 
                 FROM users u 
                 LEFT JOIN doctors d ON u.id = d.user_id 
                 WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
                [$username, $username]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                
                if ($user['role'] === 'doctor') {
                    $_SESSION['doctor_id'] = $user['doctor_id'];
                    $_SESSION['specialty'] = $user['specialty'];
                }
                
                $_SESSION['last_activity'] = time();
                
                // Log the login activity
                logActivity($user['id'], 'login', 'User logged in successfully');
                
                return [
                    'success' => true,
                    'user' => $user,
                    'redirect' => $this->getRedirectUrl($user['role'])
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        if ($_SESSION['role'] !== $role) {
            header('Location: ' . SITE_URL . '/index.php');
            exit();
        }
    }
    
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    public function register($userData) {
        try {
            // Check if username or email already exists
            $existing = $this->db->fetch(
                "SELECT id, status FROM users WHERE username = ? OR email = ?",
                [$userData['username'], $userData['email']]
            );
            
            if ($existing) {
                // If the user exists but is still pending, allow them to refresh their OTP
                if ($existing['status'] === 'pending') {
                    $userId = $existing['id'];
                    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                    $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $this->db->update('users', [
                        'password' => $hashedPassword,
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'otp_code' => $otpCode,
                        'otp_expires_at' => $otpExpiresAt
                    ], 'id = ?', [$userId]);
                    
                    return [
                        'success' => true,
                        'user_id' => $userId,
                        'otp_code' => $otpCode,
                        'message' => 'Your OTP has been refreshed. Please verify with the new code.'
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Generate 6-digit OTP
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Prepare data for insertion (exclude confirm_password)
            $insertData = [
                'username'       => $userData['username'],
                'email'          => $userData['email'],
                'password'       => $hashedPassword,
                'first_name'     => $userData['first_name'],
                'last_name'      => $userData['last_name'],
                'role'           => $userData['role'] ?? 'patient',
                'is_active'      => 0, // Inactive until OTP is verified
                'status'         => 'pending',
                'email_verified' => 0,
                'otp_code'       => $otpCode,
                'otp_expires_at' => $otpExpiresAt
            ];

            // Include optional profile fields if provided
            if (!empty($userData['phone']))         $insertData['phone']         = $userData['phone'];
            if (!empty($userData['address']))       $insertData['address']       = $userData['address'];
            if (!empty($userData['date_of_birth'])) $insertData['date_of_birth'] = $userData['date_of_birth'];
            if (!empty($userData['gender']))        $insertData['gender']        = $userData['gender'];
            
            // Insert user
            $userId = $this->db->insert('users', $insertData);
            error_log("Insert result - User ID: " . $userId);
            
            if ($userId) {
                // Create patient record
                try {
                    error_log("Creating patient record for user ID: " . $userId);
                    $patientData = [
                        'user_id' => $userId,
                        'status' => 'active'
                    ];

                    // Sync demographic fields to patients table
                    if (!empty($userData['date_of_birth'])) $patientData['date_of_birth'] = $userData['date_of_birth'];
                    if (!empty($userData['gender']))        $patientData['gender']        = $userData['gender'];
                    if (!empty($userData['phone']))         $patientData['phone']         = $userData['phone'];
                    if (!empty($userData['address']))       $patientData['address']       = $userData['address'];

                    $patientId = $this->db->insert('patients', $patientData);
                    error_log("Patient record created with ID: " . $patientId);
                    
                    if (!$patientId) {
                        error_log("WARNING: Patient record creation returned no ID");
                    }
                } catch (Exception $e) {
                    error_log("Error creating patient record: " . $e->getMessage());
                    error_log("Patient creation error trace: " . $e->getTraceAsString());
                    // Don't fail registration - patient record can be created later
                }
                
                error_log("User inserted successfully, logging activity");
                try {
                    logActivity($userId, 'register', 'New user registered');
                    error_log("Activity logged successfully");
                } catch (Exception $e) {
                    error_log("Error logging activity: " . $e->getMessage());
                    // Don't fail registration if activity logging fails
                }
                
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'otp_code' => $otpCode, // Include OTP for sending email
                    'message' => 'Registration initiated. Please verify with OTP.'
                ];
            } else {
                error_log("Database insert failed - no user ID returned");
                return [
                    'success' => false,
                    'message' => 'Registration failed'
                ];
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'An error occurred during registration. Please try again.'
            ];
        }
    }

    public function verifyOTP($email, $otp) {
        try {
            $user = $this->db->fetch(
                "SELECT * FROM users WHERE email = ? AND otp_code = ? AND otp_expires_at > CURRENT_TIMESTAMP",
                [$email, $otp]
            );
            
            if ($user) {
                // Update user status
                $this->db->update(
                    'users',
                    [
                        'is_active' => 1,
                        'status' => 'active',
                        'email_verified' => 1,
                        'otp_code' => null,
                        'otp_expires_at' => null
                    ],
                    'id = ?',
                    [$user['id']]
                );
                
                // Log activity
                logActivity($user['id'], 'verify_email', 'Email verified with OTP');
                
                return [
                    'success' => true,
                    'user_id' => $user['id'],
                    'message' => 'Email verified successfully! You can now login.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP. Please try again.'
                ];
            }
        } catch (Exception $e) {
            error_log("OTP Verification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during verification.'
            ];
        }
    }

    public function initiatePasswordReset($identity) {
        try {
            // Find user by email or username - RESTRICT TO PATIENTS ONLY
            $user = $this->db->fetch(
                "SELECT id, email, first_name, last_name FROM users WHERE (email = ? OR username = ?) AND role = 'patient' AND is_active = 1",
                [$identity, $identity]
            );

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'No active account found with that email or username.'
                ];
            }

            // Generate 6-digit OTP
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Store OTP in user record
            $this->db->update('users', [
                'otp_code' => $otpCode,
                'otp_expires_at' => $otpExpiresAt
            ], 'id = ?', [$user['id']]);

            // Send OTP via email
            require_once __DIR__ . '/email.php';
            $emailService = new EmailService();
            $fullName = $user['first_name'] . ' ' . $user['last_name'];
            $emailResult = $emailService->sendPasswordResetOTP($user['email'], $fullName, $otpCode);

            if ($emailResult['success']) {
                // Log activity
                logActivity($user['id'], 'password_reset_request', 'Password reset initiated via OTP');
                
                return [
                    'success' => true,
                    'email' => $user['email'],
                    'message' => 'A verification code has been sent to your email.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again later.'
                ];
            }
        } catch (Exception $e) {
            error_log("Password reset init error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ];
        }
    }

    public function verifyResetOTP($identity, $otp) {
        try {
            $user = $this->db->fetch(
                "SELECT id FROM users WHERE (email = ? OR username = ?) AND otp_code = ? AND otp_expires_at > CURRENT_TIMESTAMP",
                [$identity, $identity, $otp]
            );

            if ($user) {
                return [
                    'success' => true,
                    'message' => 'Code verified successfully. You can now set your new password.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification code.'
                ];
            }
        } catch (Exception $e) {
            error_log("Reset OTP verification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Verification failed.'
            ];
        }
    }

    public function resetPassword($identity, $otp, $newPassword) {
        try {
            // Verify code again to ensure sequence
            $user = $this->db->fetch(
                "SELECT id FROM users WHERE (email = ? OR username = ?) AND otp_code = ? AND otp_expires_at > CURRENT_TIMESTAMP",
                [$identity, $identity, $otp]
            );

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Session expired. Please start over.'
                ];
            }

            // Hash and update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->update('users', [
                'password' => $hashedPassword,
                'otp_code' => null,
                'otp_expires_at' => null
            ], 'id = ?', [$user['id']]);

            // Log activity
            logActivity($user['id'], 'password_reset_complete', 'Password reset successfully completed');

            return [
                'success' => true,
                'message' => 'Your password has been reset successfully. You can now login with your new password.'
            ];
        } catch (Exception $e) {
            error_log("Password reset final error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to reset password.'
            ];
        }
    }
    
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'admin':
                return SITE_URL . '/admin/Dashboard/dashboard.php';
            case 'doctor':
                return SITE_URL . '/doctor/dashboard_doctor.php';
            case 'patient':
                return SITE_URL . '/patient/dashboard_patients.php';
            default:
                return SITE_URL . '/index.php';
        }
    }
}

// Utility functions
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'F j, Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    return date($format, $timestamp);
}

function formatTime($time, $format = 'g:i A') {
    if (empty($time) || $time === '00:00:00') {
        return '';
    }
    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return '';
    }
    return date($format, $timestamp);
}

function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }
    return date($format, $timestamp);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sendEmail($to, $subject, $message, $isHTML = true) {
    global $site_url;
    
    // Check if we can use the advanced EmailService
    $email_file = __DIR__ . '/email.php';
    if (file_exists($email_file)) {
        require_once $email_file;
        if (class_exists('EmailService')) {
            $emailService = new EmailService();
            // sendEmail($to_email, $to_name, $subject, $body, $is_html = true)
            $result = $emailService->sendEmail($to, '', $subject, $message, $isHTML);
            
            if ($result['success']) {
                return true;
            } else {
                error_log("EmailService failed to send to $to: " . $result['message']);
                // Fallback to basic mail if SMTP fails
            }
        }
    }

    // Basic email function fallback
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    if ($isHTML) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    $result = mail($to, $subject, $message, $headers);
    if (!$result) {
        error_log("Fallback mail() failed to send to $to");
    }
    return $result;
}

function createNotification($userId, $title, $message, $type = 'info') {
    try {
        $db = Database::getInstance();
        
        // Check if notifications table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
        $tableExists = $tableCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$tableExists) {
            error_log("Notifications table does not exist - skipping notification creation");
            return false;
        }
        
        return $db->insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

function getClinicSetting($key, $default = '') {
    $db = Database::getInstance();
    $setting = $db->fetch(
        "SELECT setting_value FROM clinic_settings WHERE setting_key = ?",
        [$key]
    );
    return $setting ? $setting['setting_value'] : $default;
}

function updateClinicSetting($key, $value) {
    $db = Database::getInstance();
    return $db->update(
        'clinic_settings',
        ['setting_value' => $value],
        'setting_key = ?',
        [$key]
    );
}

function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function isValidTime($time, $format = 'H:i') {
    $t = DateTime::createFromFormat($format, $time);
    return $t && $t->format($format) === $time;
}

function calculateAge($birthDate) {
    if (empty($birthDate)) return 'N/A';
    
    try {
        $today = new DateTime();
        $birth = new DateTime($birthDate);
        
        if ($birth > $today) {
             return 'N/A'; // Invalid future date
        }
        
        return $today->diff($birth)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

function uploadFile($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $newFileName = uniqid() . '.' . $fileExt;
    $uploadPath = $uploadDir . '/' . $newFileName;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return [
            'success' => true,
            'filename' => $newFileName,
            'path' => $uploadPath
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
}

function logActivity($userId, $type, $description) {
    try {
        $db = Database::getInstance();
        
        // Check if activity_logs table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='activity_logs'");
        $tableExists = $tableCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$tableExists) {
            error_log("Activity_logs table does not exist - skipping activity logging");
            return false;
        }
        
        return $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $type,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}
?>
