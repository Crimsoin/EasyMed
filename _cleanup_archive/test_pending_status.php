<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "=== Testing 'pending' status insertion ===\n";
    
    // Test inserting an appointment with 'pending' status
    $test_data = [
        'patient_id' => 1,
        'doctor_id' => 1,
        'appointment_date' => '2025-08-25',
        'appointment_time' => '14:00:00',
        'reason_for_visit' => 'Test pending appointment',
        'status' => 'pending',
        'patient_info' => '{"test": "pending status"}',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    echo "Inserting test appointment with 'pending' status...\n";
    $appointment_id = $db->insert('appointments', $test_data);
    
    if ($appointment_id) {
        echo "✅ SUCCESS: Test appointment created with ID: " . $appointment_id . "\n";
        
        // Verify the appointment was inserted correctly
        $appointment = $db->fetch("SELECT * FROM appointments WHERE id = ?", [$appointment_id]);
        echo "Status in database: " . $appointment['status'] . "\n";
        
        // Clean up test appointment
        $db->delete('appointments', 'id = ?', [$appointment_id]);
        echo "✅ Test appointment cleaned up\n";
        
    } else {
        echo "❌ FAILED: Could not create test appointment\n";
    }
    
    echo "\n=== Testing all valid statuses ===\n";
    $valid_statuses = ['pending', 'scheduled', 'confirmed', 'completed', 'cancelled'];
    
    foreach ($valid_statuses as $status) {
        $test_data['status'] = $status;
        try {
            $id = $db->insert('appointments', $test_data);
            echo "✅ Status '$status': OK\n";
            $db->delete('appointments', 'id = ?', [$id]);
        } catch (Exception $e) {
            echo "❌ Status '$status': FAILED - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ All tests completed!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>
