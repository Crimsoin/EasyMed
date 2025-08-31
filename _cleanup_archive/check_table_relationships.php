<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>Checking Table Relationships</h2>";
    
    // List all tables
    echo "<h3>All Tables:</h3>";
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
    echo "<pre>";
    foreach($tables as $table) {
        echo $table['name'] . "\n";
    }
    echo "</pre>";
    
    // Check patients table structure
    echo "<h3>Patients Table Structure:</h3>";
    try {
        $patients_structure = $db->fetchAll("PRAGMA table_info(patients)");
        echo "<pre>";
        foreach($patients_structure as $col) {
            echo $col['name'] . " - " . $col['type'] . "\n";
        }
        echo "</pre>";
        
        // Show some patients data
        echo "<h4>Patients Data:</h4>";
        $patients_data = $db->fetchAll("SELECT * FROM patients LIMIT 5");
        echo "<pre>";
        print_r($patients_data);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "Patients table error: " . $e->getMessage() . "<br>";
    }
    
    // Check doctors table structure
    echo "<h3>Doctors Table Structure:</h3>";
    try {
        $doctors_structure = $db->fetchAll("PRAGMA table_info(doctors)");
        echo "<pre>";
        foreach($doctors_structure as $col) {
            echo $col['name'] . " - " . $col['type'] . "\n";
        }
        echo "</pre>";
        
        // Show some doctors data
        echo "<h4>Doctors Data:</h4>";
        $doctors_data = $db->fetchAll("SELECT * FROM doctors LIMIT 5");
        echo "<pre>";
        print_r($doctors_data);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "Doctors table error: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
