<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    // Check if we're using SQLite or MySQL
    if (DB_TYPE === 'sqlite') {
        echo "=== SQLite Database Schema Check ===\n";
        
        // Get table schema for doctors table
        $schema = $db->fetchAll("PRAGMA table_info(doctors)");
        
        echo "Doctors table columns:\n";
        foreach ($schema as $column) {
            echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
        }
        
        // Check if table exists and has data
        $count = $db->fetch("SELECT COUNT(*) as count FROM doctors");
        echo "\nDoctors table has " . $count['count'] . " records\n";
        
        // Show sample data if exists
        if ($count['count'] > 0) {
            echo "\nSample doctor records:\n";
            $doctors = $db->fetchAll("SELECT * FROM doctors LIMIT 3");
            foreach ($doctors as $doctor) {
                echo "ID: " . $doctor['id'] . "\n";
                foreach ($doctor as $key => $value) {
                    echo "  $key: $value\n";
                }
                echo "\n";
            }
        }
        
    } else {
        echo "=== MySQL Database Schema Check ===\n";
        $schema = $db->fetchAll("DESCRIBE doctors");
        
        echo "Doctors table columns:\n";
        foreach ($schema as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
