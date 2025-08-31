<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>Testing ID Mapping for Appointment Booking</h2>";
    
    // Test patient mapping
    $user_id = 4; // A known patient user ID
    $patient_record = $db->fetch("SELECT id FROM patients WHERE user_id = ? AND status = 'active'", [$user_id]);
    echo "<h3>Patient Mapping:</h3>";
    echo "User ID: $user_id<br>";
    echo "Patient Record ID: " . ($patient_record ? $patient_record['id'] : 'NOT FOUND') . "<br>";
    
    // Test doctor mapping
    $doctor_user_id = 2; // A known doctor user ID
    $doctor = $db->fetch("
        SELECT d.id as doctor_record_id, u.id as user_id, u.first_name, u.last_name
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE u.id = ? AND u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1 AND d.status = 'active'
    ", [$doctor_user_id]);
    
    echo "<h3>Doctor Mapping:</h3>";
    echo "User ID: $doctor_user_id<br>";
    echo "Doctor Record ID: " . ($doctor ? $doctor['doctor_record_id'] : 'NOT FOUND') . "<br>";
    echo "Doctor Name: " . ($doctor ? $doctor['first_name'] . ' ' . $doctor['last_name'] : 'NOT FOUND') . "<br>";
    
    if ($patient_record && $doctor) {
        echo "<h3>✅ Both IDs mapped successfully - appointment booking should work!</h3>";
        
        // Test a sample appointment data structure
        $test_appointment = [
            'patient_id' => $patient_record['id'],
            'doctor_id' => $doctor['doctor_record_id'],
            'appointment_date' => '2025-08-25',
            'appointment_time' => '11:00:00',
            'reason_for_visit' => 'Test booking',
            'status' => 'scheduled',
            'patient_info' => '{"test": "mapping"}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        echo "<h4>Sample appointment data that will be inserted:</h4>";
        echo "<pre>";
        print_r($test_appointment);
        echo "</pre>";
        
    } else {
        echo "<h3>❌ ID mapping failed - need to debug further</h3>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
