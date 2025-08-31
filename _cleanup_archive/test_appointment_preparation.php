<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>Testing Appointment Creation Process</h2>";
    
    // Check appointments table structure
    echo "<h3>Appointments Table Structure:</h3>";
    $columns = $db->fetchAll("PRAGMA table_info(appointments)");
    echo "<pre>";
    foreach($columns as $col) {
        echo $col['name'] . " - " . $col['type'] . "\n";
    }
    echo "</pre>";
    
    // Check if we have any doctors
    echo "<h3>Available Doctors:</h3>";
    $doctors = $db->fetchAll("
        SELECT u.id, u.first_name, u.last_name, d.specialty, d.schedule_days 
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1
    ");
    echo "<pre>";
    print_r($doctors);
    echo "</pre>";
    
    // Check if we have any patients
    echo "<h3>Available Patients:</h3>";
    $patients = $db->fetchAll("
        SELECT id, first_name, last_name, email 
        FROM users 
        WHERE role = 'patient' AND is_active = 1 
        LIMIT 5
    ");
    echo "<pre>";
    print_r($patients);
    echo "</pre>";
    
    echo "<h3>Test Complete</h3>";
    
} catch (Exception $e) {
    echo "<h3>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
