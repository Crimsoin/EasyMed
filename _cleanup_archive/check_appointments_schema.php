<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "=== Appointments Table Schema ===\n";
    
    // Get table schema for appointments table
    $schema = $db->fetchAll("PRAGMA table_info(appointments)");
    
    echo "Appointments table columns:\n";
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Check if table exists and has data
    $count = $db->fetch("SELECT COUNT(*) as count FROM appointments");
    echo "\nAppointments table has " . $count['count'] . " records\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
