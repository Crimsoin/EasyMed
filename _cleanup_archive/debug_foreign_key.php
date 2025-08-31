<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>Debugging Foreign Key Constraint Issue</h2>";
    
    // Check foreign key constraints
    echo "<h3>Foreign Key Constraints:</h3>";
    $constraints = $db->fetchAll("PRAGMA foreign_key_list(appointments)");
    echo "<pre>";
    print_r($constraints);
    echo "</pre>";
    
    // Check appointments table structure
    echo "<h3>Appointments Table Structure:</h3>";
    $columns = $db->fetchAll("PRAGMA table_info(appointments)");
    echo "<pre>";
    foreach($columns as $col) {
        echo $col['name'] . " - " . $col['type'] . " - " . ($col['notnull'] ? 'NOT NULL' : 'NULL') . " - " . ($col['pk'] ? 'PRIMARY KEY' : '') . "\n";
    }
    echo "</pre>";
    
    // Check users table for patients
    echo "<h3>Patient Users (first 5):</h3>";
    $patients = $db->fetchAll("
        SELECT id, first_name, last_name, email, role, is_active 
        FROM users 
        WHERE role = 'patient' 
        LIMIT 5
    ");
    echo "<pre>";
    print_r($patients);
    echo "</pre>";
    
    // Check users table for doctors
    echo "<h3>Doctor Users (first 5):</h3>";
    $doctors = $db->fetchAll("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.is_active,
               d.specialty, d.is_available
        FROM users u 
        LEFT JOIN doctors d ON u.id = d.user_id 
        WHERE u.role = 'doctor' 
        LIMIT 5
    ");
    echo "<pre>";
    print_r($doctors);
    echo "</pre>";
    
    // Test with specific IDs that we know exist
    echo "<h3>Testing Insert with Known IDs:</h3>";
    
    // Get a valid patient and doctor
    $patient = $db->fetch("SELECT id FROM users WHERE role = 'patient' AND is_active = 1 LIMIT 1");
    $doctor = $db->fetch("SELECT u.id FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1 LIMIT 1");
    
    if ($patient && $doctor) {
        echo "Patient ID: " . $patient['id'] . "<br>";
        echo "Doctor ID: " . $doctor['id'] . "<br>";
        
        // Try a test insert
        try {
            $test_data = [
                'patient_id' => $patient['id'],
                'doctor_id' => $doctor['id'],
                'appointment_date' => '2025-08-25',
                'appointment_time' => '10:00:00',
                'reason_for_visit' => 'Test appointment',
                'status' => 'scheduled',
                'patient_info' => '{"test": "data"}',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            echo "<h4>Test appointment data:</h4>";
            echo "<pre>";
            print_r($test_data);
            echo "</pre>";
            
            $appointment_id = $db->insert('appointments', $test_data);
            echo "<h4>SUCCESS: Test appointment created with ID: " . $appointment_id . "</h4>";
            
            // Clean up test appointment
            $db->delete('appointments', 'id = ?', [$appointment_id]);
            echo "<p>Test appointment cleaned up.</p>";
            
        } catch (Exception $e) {
            echo "<h4>FAILED: " . htmlspecialchars($e->getMessage()) . "</h4>";
        }
    } else {
        echo "<h4>No valid patient or doctor found!</h4>";
        echo "Patient found: " . ($patient ? 'Yes' : 'No') . "<br>";
        echo "Doctor found: " . ($doctor ? 'Yes' : 'No') . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
