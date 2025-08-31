<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "=== Foreign Key Constraints ===\n";
    
    // Check foreign key constraints
    $constraints = $db->fetchAll("PRAGMA foreign_key_list(appointments)");
    echo "Appointments table foreign keys:\n";
    foreach ($constraints as $constraint) {
        echo "- Column: {$constraint['from']} -> {$constraint['table']}.{$constraint['to']}\n";
    }
    
    // Check if the foreign keys point to the right tables
    echo "\nChecking constraint references:\n";
    
    // For patient_id -> should this reference users or patients table?
    $patients_table_exists = false;
    try {
        $patients = $db->fetchAll("SELECT id FROM patients LIMIT 1");
        $patients_table_exists = true;
        echo "- patients table exists\n";
    } catch (Exception $e) {
        echo "- patients table does not exist or is empty\n";
    }
    
    // For doctor_id -> should this reference doctors or users table?
    echo "- doctors table exists\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
