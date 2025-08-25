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
            error_log("Auth::register called with data: " . print_r($userData, true));
            
            // Check if username or email already exists
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$userData['username'], $userData['email']]
            );
            
            error_log("Existing user check result: " . print_r($existing, true));
            
            if ($existing) {
                error_log("User already exists - returning error");
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            error_log("Password hashed successfully");
            
            // Prepare data for insertion (exclude confirm_password)
            $insertData = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => $hashedPassword,
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'role' => 'patient',
                'is_active' => 1,
                'status' => 'active',
                'email_verified' => 0
            ];
            
            // Insert user
            error_log("Attempting to insert user into database with data: " . print_r($insertData, true));
            $userId = $this->db->insert('users', $insertData);
            error_log("Insert result - User ID: " . $userId);
            
            if ($userId) {
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
                    'message' => 'Registration successful'
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
    return date($format, strtotime($date));
}

function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    return date($format, strtotime($datetime));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sendEmail($to, $subject, $message, $isHTML = true) {
    // Basic email function - can be enhanced with PHPMailer
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    
    if ($isHTML) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $message, $headers);
}

function createNotification($userId, $title, $message, $type = 'info') {
    $db = Database::getInstance();
    return $db->insert('notifications', [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type
    ]);
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
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    return $today->diff($birth)->y;
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
