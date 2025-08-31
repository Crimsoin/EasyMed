<?php
require_once 'includes/config.php';

echo "<h1>Complete Database Diagnostic</h1>";

try {
    echo "<h3>Configuration Check:</h3>";
    echo "<p><strong>DB_TYPE:</strong> " . DB_TYPE . "</p>";
    echo "<p><strong>SQLITE_PATH:</strong> " . SQLITE_PATH . "</p>";
    echo "<p><strong>Absolute Path:</strong> " . realpath(SQLITE_PATH) . "</p>";
    echo "<p><strong>Directory:</strong> " . dirname(SQLITE_PATH) . "</p>";
    echo "<p><strong>File exists:</strong> " . (file_exists(SQLITE_PATH) ? 'YES' : 'NO') . "</p>";
    
    if (file_exists(SQLITE_PATH)) {
        echo "<p><strong>File size:</strong> " . filesize(SQLITE_PATH) . " bytes</p>";
        echo "<p><strong>File permissions:</strong> " . substr(sprintf('%o', fileperms(SQLITE_PATH)), -4) . "</p>";
        echo "<p><strong>Last modified:</strong> " . date('Y-m-d H:i:s', filemtime(SQLITE_PATH)) . "</p>";
    }
    
    echo "<h3>Database Connection Test:</h3>";
    $db = Database::getInstance();
    echo "<p>✅ Database connection successful</p>";
    
    // Test a simple query
    $version = $db->fetch("SELECT sqlite_version() as version");
    echo "<p><strong>SQLite Version:</strong> " . $version['version'] . "</p>";
    
    echo "<h3>Current Tables:</h3>";
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    if (empty($tables)) {
        echo "<p>❌ No tables found in database</p>";
    } else {
        foreach ($tables as $table) {
            $count = $db->fetch("SELECT COUNT(*) as count FROM {$table['name']}")['count'];
            echo "<p>✅ {$table['name']} ($count records)</p>";
        }
    }
    
    // Force create the table with direct SQL
    echo "<h3>Force Creating doctor_schedules Table:</h3>";
    
    // Drop and recreate to ensure clean state
    try {
        $db->query("DROP TABLE IF EXISTS doctor_schedules");
        echo "<p>✅ Dropped existing doctor_schedules table if it existed</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ No existing table to drop</p>";
    }
    
    // Create with minimal structure first
    $sql = "
        CREATE TABLE doctor_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            slot_duration INTEGER DEFAULT 30,
            is_available INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $db->query($sql);
    echo "<p>✅ Created doctor_schedules table with simplified structure</p>";
    
    // Verify the table was created
    $verify = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='doctor_schedules'");
    if (!empty($verify)) {
        echo "<p>✅ Verified: doctor_schedules table exists in database</p>";
        
        // Test insert
        $test_insert = $db->insertData('doctor_schedules', [
            'doctor_id' => 1,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration' => 30,
            'is_available' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p>✅ Test insert successful, new record ID: $test_insert</p>";
        
        // Test select
        $test_select = $db->fetchAll("SELECT * FROM doctor_schedules LIMIT 1");
        echo "<p>✅ Test select successful, found " . count($test_select) . " record(s)</p>";
        
    } else {
        echo "<p>❌ Table creation failed - not found in sqlite_master</p>";
    }
    
    // Show final table list
    echo "<h3>Final Table List:</h3>";
    $final_tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    foreach ($final_tables as $table) {
        echo "<p>✅ {$table['name']}</p>";
    }
    
    echo "<h2>🎉 Database Setup Complete!</h2>";
    echo "<p><a href='doctor/schedule.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Test Schedule Page</a></p>";
    echo "<p><a href='simulate_doctor_login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Login as Doctor First</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error Details:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
