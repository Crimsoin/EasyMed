<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "=== Patients Table Schema ===\n";
    
    // Get table schema for patients table
    $schema = $db->fetchAll("PRAGMA table_info(patients)");
    
    echo "Patients table columns:\n";
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Check if table has data
    $count = $db->fetch("SELECT COUNT(*) as count FROM patients");
    echo "\nPatients table has " . $count['count'] . " records\n";
    
    // Show sample data if exists
    if ($count['count'] > 0) {
        echo "\nSample patient records:\n";
        $patients = $db->fetchAll("SELECT * FROM patients LIMIT 2");
        foreach ($patients as $patient) {
            echo "ID: " . $patient['id'] . "\n";
            foreach ($patient as $key => $value) {
                echo "  $key: $value\n";
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
