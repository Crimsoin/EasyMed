<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "=== Checking Foreign Key References ===\n";
    
    // Check users table (for patient_id reference)
    $users = $db->fetchAll("SELECT id, username, role FROM users LIMIT 5");
    echo "Users table:\n";
    foreach ($users as $user) {
        echo "- ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}\n";
    }
    
    // Check doctors table (for doctor_id reference)
    $doctors = $db->fetchAll("SELECT id, user_id, specialty FROM doctors LIMIT 5");
    echo "\nDoctors table:\n";
    foreach ($doctors as $doctor) {
        echo "- ID: {$doctor['id']}, User ID: {$doctor['user_id']}, Specialty: {$doctor['specialty']}\n";
    }
    
    // Check current session
    session_start();
    echo "\nCurrent session:\n";
    echo "- User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
    echo "- Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
