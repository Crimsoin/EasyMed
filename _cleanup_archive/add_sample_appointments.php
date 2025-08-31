<?php
require_once 'includes/config.php';

$db = Database::getInstance();

echo "Adding a sample appointment for today to make reports more interesting...\n\n";

// Add an appointment for today
$appointment_date = date('Y-m-d');
$appointment_time = '14:30:00';
$doctor_id = 1; // Dr. John Smith
$patient_id = 1; // Our sample patient
$status = 'completed';
$notes = 'Regular checkup completed successfully';

try {
    $db->query("
        INSERT INTO appointments (doctor_id, patient_id, appointment_date, appointment_time, status, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [$doctor_id, $patient_id, $appointment_date, $appointment_time, $status, $notes, date('Y-m-d H:i:s')]);
    
    echo "✓ Added appointment for today:\n";
    echo "  - Doctor: Dr. John Smith\n";
    echo "  - Date: $appointment_date\n";
    echo "  - Time: $appointment_time\n";
    echo "  - Status: $status\n\n";
    
    // Add another appointment for yesterday
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $db->query("
        INSERT INTO appointments (doctor_id, patient_id, appointment_date, appointment_time, status, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [2, $patient_id, $yesterday, '10:00:00', 'scheduled', 'Follow-up appointment', date('Y-m-d H:i:s')]);
    
    echo "✓ Added appointment for yesterday:\n";
    echo "  - Doctor: Dr. Maria Garcia\n";
    echo "  - Date: $yesterday\n";
    echo "  - Time: 10:00:00\n";
    echo "  - Status: scheduled\n\n";
    
    echo "Sample appointments added successfully!\n";
    echo "The reports page should now show meaningful data.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
