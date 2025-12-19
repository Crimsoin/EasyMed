<?php
/**
 * EasyMed Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to 'config.php'
 * 2. Update all values with your production settings
 * 3. Never commit config.php to version control
 */

// Database configuration
define('DB_TYPE', 'sqlite'); // Change to 'mysql' if you want to use MySQL
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'easymed');
define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');

// Site configuration
// IMPORTANT: Update these for production
define('SITE_URL', 'https://yourdomain.com'); // Change to your domain
define('SITE_NAME', 'EasyMed - Patient Appointment Management System');

// Base URL (without trailing slash)
define('BASE_URL', 'https://yourdomain.com');

// Email configuration (for notifications)
// IMPORTANT: Use environment variables or secure storage for credentials
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Change this
define('SMTP_PASSWORD', 'your-app-password'); // Change this - Use App Password for Gmail
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com'); // Change this
define('SMTP_FROM_NAME', 'EasyMed Clinic'); // Change this
define('SMTP_ENCRYPTION', 'tls');

// Security settings
// IMPORTANT: Generate a secure random key for production
// Use: openssl rand -base64 32
define('ENCRYPTION_KEY', 'CHANGE-THIS-TO-A-SECURE-RANDOM-KEY');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_PATIENT', 'patient');

// Appointment status
define('STATUS_PENDING', 'pending');
define('STATUS_RESCHEDULED', 'rescheduled');
define('STATUS_SCHEDULED', 'scheduled');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_NO_SHOW', 'no_show');

// Payment status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_CONFIRMED', 'confirmed');
define('PAYMENT_REJECTED', 'rejected');

// Environment (development or production)
define('ENVIRONMENT', 'production'); // Change to 'production' when deploying

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Set timezone
date_default_timezone_set('Asia/Manila'); // Change to your timezone

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (ENVIRONMENT === 'production'), // Only use secure cookies in production
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime' => SESSION_TIMEOUT,
    ]);
}
?>
