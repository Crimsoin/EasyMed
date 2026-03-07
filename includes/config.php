<?php
// Database configuration
define('DB_TYPE', 'sqlite'); // Change to 'mysql' if you want to use MySQL
define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'easymed');
define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');

// Site configuration
// Auto-detect if running on development server or Apache
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detect the base path from the current script
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = '';
if (preg_match('#^(/[^/]+)/#', $script_name, $matches)) {
    $base_path = $matches[1];
}

// Build the site URL
if (strpos($host, 'localhost') !== false) {
    // Keep helpful default for local dev but allow override via environment
    $site_host = getenv('EASYMED_SITE_HOST') ?: $host;
    define('SITE_URL', $protocol . $site_host . $base_path);
} else {
    define('SITE_URL', $protocol . $host);
}

define('SITE_NAME', 'EasyMed - Patient Appointment Management System');
define('BASE_URL', SITE_URL);

// Email configuration (for notifications)
// IMPORTANT: Update these for production - use environment variables for security
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'easymed.notifications@gmail.com');
define('SMTP_PASSWORD', 'knar lflg menl ljoc'); // TODO: Move to environment variable before deployment
define('SMTP_FROM_EMAIL', 'easymed.notifications@gmail.com');
define('SMTP_FROM_NAME', 'EasyMed Clinic');
define('SMTP_ENCRYPTION', 'tls');

// Security settings
// CRITICAL: Generate a secure random key and keep it fixed
// Generate with: openssl rand -hex 32
// For production, use environment variable or secure config file
define('ENCRYPTION_KEY', ''); // TODO: Add your secure 64-character hex key here
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Environment (development or production)
define('ENVIRONMENT', 'development'); // Change to 'production' when deploying

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

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (ENVIRONMENT === 'production'),
        'cookie_samesite' => 'Strict',
        'gc_maxlifetime' => SESSION_TIMEOUT,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'name' => 'EASYMED_SESSION',
    ]);
}
?>