<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $db = Database::getInstance();
    
    // Check if appointments table exists
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    
    echo "<h2>Existing Tables:</h2>";
    foreach ($tables as $table) {
        echo "- " . $table['name'] . "<br>";
    }
    
    // Check if appointments table exists specifically
    $appointmentsExists = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='appointments'");
    
    if ($appointmentsExists) {
        echo "<h2>Appointments Table Structure:</h2>";
        $structure = $db->fetchAll("PRAGMA table_info(appointments)");
        echo "<table border='1'>";
        echo "<tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th><th>Primary Key</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>" . $column['name'] . "</td>";
            echo "<td>" . $column['type'] . "</td>";
            echo "<td>" . $column['notnull'] . "</td>";
            echo "<td>" . $column['dflt_value'] . "</td>";
            echo "<td>" . $column['pk'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<h2>Appointments table does not exist!</h2>";
        echo "<p>Creating appointments table...</p>";
        
        // Create appointments table
        $createSQL = "
        CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            patient_first_name VARCHAR(100) NOT NULL,
            patient_last_name VARCHAR(100) NOT NULL,
            patient_phone VARCHAR(20) NOT NULL,
            patient_email VARCHAR(100) NOT NULL,
            schedule_day VARCHAR(20) NOT NULL,
            laboratory TEXT,
            reference_number VARCHAR(50) UNIQUE NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES users(id),
            FOREIGN KEY (doctor_id) REFERENCES users(id)
        )";
        
        $db->exec($createSQL);
        echo "<p>Appointments table created successfully!</p>";
    }
    
    // Check if activity_logs table exists
    $activityLogsExists = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='activity_logs'");
    
    if (!$activityLogsExists) {
        echo "<h2>Creating activity_logs table...</h2>";
        $createActivitySQL = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        
        $db->exec($createActivitySQL);
        echo "<p>Activity logs table created successfully!</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
