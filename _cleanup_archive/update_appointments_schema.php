<?php
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    // Check current appointments table schema
    echo "Current appointments table schema:\n";
    $schema = $db->query("PRAGMA table_info(appointments)");
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Check if patient_info column exists
    $hasPatientInfo = false;
    foreach ($schema as $column) {
        if ($column['name'] === 'patient_info') {
            $hasPatientInfo = true;
            break;
        }
    }
    
    if (!$hasPatientInfo) {
        echo "\nAdding patient_info column to appointments table...\n";
        $db->query("ALTER TABLE appointments ADD COLUMN patient_info TEXT");
        echo "Column added successfully!\n";
    } else {
        echo "\npatient_info column already exists.\n";
    }
    
    echo "\nUpdated appointments table schema:\n";
    $schema = $db->query("PRAGMA table_info(appointments)");
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
