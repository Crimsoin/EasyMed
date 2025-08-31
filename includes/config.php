<?php
// Database configuration
define('DB_TYPE', 'sqlite'); // Change to 'mysql' if you want to use MySQL
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'easymed');
define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');

// Site configuration
// Auto-detect if running on development server or Apache
$protocol = 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($host === 'localhost:8080') {
    define('SITE_URL', 'http://localhost:8080');
} else {
    define('SITE_URL', 'http://localhost/Project_EasyMed');
}
define('SITE_NAME', 'EasyMed - Patient Appointment Management System');

// Email configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@easymed.com');
define('FROM_NAME', 'EasyMed Clinic');

// Security settings
define('ENCRYPTION_KEY', 'your-secret-encryption-key-here');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_PATIENT', 'patient');

// Appointment status
define('STATUS_PENDING', 'pending');
define('STATUS_CONFIRMED', 'confirmed');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_NO_SHOW', 'no_show');

// Payment status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_CONFIRMED', 'confirmed');
define('PAYMENT_REJECTED', 'rejected');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
