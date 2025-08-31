<?php
session_start();
require_once 'includes/database.php';

$db = Database::getInstance();

echo "Creating patient record for current user...\n";

if (!isset($_SESSION['user_id'])) {
    echo "No user logged in. Please log in first.\n";
    exit;
}

// Check if patient record exists
$patient = $db->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);

if ($patient) {
    echo "Patient record already exists:\n";
    echo "- Patient ID: {$patient['id']}\n";
    echo "- User ID: {$patient['user_id']}\n";
} else {
    echo "Creating new patient record...\n";
    
    // Create patient record
    $patient_data = [
        'user_id' => $_SESSION['user_id'],
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $patient_id = $db->insert('patients', $patient_data);
    
    if ($patient_id) {
        echo "Patient record created successfully!\n";
        echo "- Patient ID: {$patient_id}\n";
        echo "- User ID: {$_SESSION['user_id']}\n";
    } else {
        echo "Failed to create patient record.\n";
    }
}
?>
