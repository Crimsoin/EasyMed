<?php
require_once 'includes/config.php';

echo "<h1>Direct Database Table Creation</h1>";

$db = Database::getInstance();

try {
    // Show current database path and info
    echo "<h3>Database Information:</h3>";
    echo "<p><strong>Database Type:</strong> " . DB_TYPE . "</p>";
    echo "<p><strong>SQLite Path:</strong> " . SQLITE_PATH . "</p>";
    echo "<p><strong>File Exists:</strong> " . (file_exists(SQLITE_PATH) ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>File Size:</strong> " . (file_exists(SQLITE_PATH) ? filesize(SQLITE_PATH) . ' bytes' : 'N/A') . "</p>";
    
    // Check current tables
    echo "<h3>Current Tables in Database:</h3>";
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    if (empty($tables)) {
        echo "<p>❌ No tables found!</p>";
    } else {
        foreach ($tables as $table) {
            echo "<p>✅ {$table['name']}</p>";
        }
    }
    
    // Try to create the tables step by step
    echo "<h3>Creating Tables Step by Step:</h3>";
    
    // Step 1: Create doctor_schedules table
    echo "<h4>Step 1: Creating doctor_schedules table</h4>";
    $create_schedules = "
        CREATE TABLE IF NOT EXISTS doctor_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            slot_duration INTEGER DEFAULT 30,
            is_available INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $result = $db->query($create_schedules);
    echo "<p>✅ doctor_schedules table creation query executed</p>";
    
    // Step 2: Create doctor_breaks table
    echo "<h4>Step 2: Creating doctor_breaks table</h4>";
    $create_breaks = "
        CREATE TABLE IF NOT EXISTS doctor_breaks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            break_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $result = $db->query($create_breaks);
    echo "<p>✅ doctor_breaks table creation query executed</p>";
    
    // Step 3: Create doctor_unavailable table
    echo "<h4>Step 3: Creating doctor_unavailable table</h4>";
    $create_unavailable = "
        CREATE TABLE IF NOT EXISTS doctor_unavailable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            unavailable_date DATE NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $result = $db->query($create_unavailable);
    echo "<p>✅ doctor_unavailable table creation query executed</p>";
    
    // Verify tables were created
    echo "<h3>Verification - Tables After Creation:</h3>";
    $tables_after = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    foreach ($tables_after as $table) {
        echo "<p>✅ {$table['name']}</p>";
    }
    
    // Test if doctor_schedules table specifically exists
    echo "<h3>Testing doctor_schedules Table:</h3>";
    try {
        $test_query = $db->fetchAll("SELECT * FROM doctor_schedules LIMIT 1");
        echo "<p>✅ doctor_schedules table exists and is accessible</p>";
        echo "<p>Current records: " . count($test_query) . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error accessing doctor_schedules: " . $e->getMessage() . "</p>";
    }
    
    // Add some sample data if table is empty
    $count = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules")['count'];
    if ($count == 0) {
        echo "<h3>Adding Sample Schedule Data:</h3>";
        
        // Get first doctor
        $doctor = $db->fetch("SELECT id FROM users WHERE role = 'doctor' LIMIT 1");
        if ($doctor) {
            // Add Monday to Friday schedule
            for ($day = 1; $day <= 5; $day++) {
                $db->insertData('doctor_schedules', [
                    'doctor_id' => $doctor['id'],
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'slot_duration' => 30,
                    'is_available' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            echo "<p>✅ Added sample schedule for doctor ID: {$doctor['id']}</p>";
        } else {
            echo "<p>⚠️ No doctors found to create schedule for</p>";
        }
    }
    
    echo "<h2>🎉 FINAL RESULT</h2>";
    $final_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules")['count'];
    echo "<p><strong>Doctor schedules in database: $final_count</strong></p>";
    
    if ($final_count > 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Tables created successfully!</p>";
        echo "<p><a href='doctor/schedule.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>TEST SCHEDULE PAGE NOW</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Tables created but no data found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Stack Trace:</strong></p><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
