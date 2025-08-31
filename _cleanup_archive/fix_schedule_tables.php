<?php
require_once 'includes/config.php';

echo "<h1>Creating Doctor Schedule Tables - Final Fix</h1>";

$db = Database::getInstance();

try {
    echo "<h3>Current Database Info:</h3>";
    echo "<p>Database Type: " . DB_TYPE . "</p>";
    echo "<p>Database Path: " . SQLITE_PATH . "</p>";
    
    // Check current tables
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo "<h3>Current Tables:</h3>";
    foreach ($tables as $table) {
        echo "<p>- {$table['name']}</p>";
    }
    
    echo "<h3>Creating Doctor Schedule Tables...</h3>";
    
    // Drop existing tables if they exist (to ensure clean creation)
    $db->query("DROP TABLE IF EXISTS doctor_schedules");
    $db->query("DROP TABLE IF EXISTS doctor_breaks");  
    $db->query("DROP TABLE IF EXISTS doctor_unavailable");
    echo "<p>✅ Dropped existing tables if any</p>";
    
    // Create doctor_schedules table
    $sql_schedules = "
        CREATE TABLE doctor_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            slot_duration INTEGER NOT NULL DEFAULT 30,
            is_available INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(doctor_id, day_of_week)
        )
    ";
    $db->query($sql_schedules);
    echo "<p>✅ Created doctor_schedules table</p>";
    
    // Create doctor_breaks table
    $sql_breaks = "
        CREATE TABLE doctor_breaks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            break_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    $db->query($sql_breaks);
    echo "<p>✅ Created doctor_breaks table</p>";
    
    // Create doctor_unavailable table
    $sql_unavailable = "
        CREATE TABLE doctor_unavailable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            unavailable_date DATE NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(doctor_id, unavailable_date)
        )
    ";
    $db->query($sql_unavailable);
    echo "<p>✅ Created doctor_unavailable table</p>";
    
    // Verify tables were created
    $tables_after = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'doctor_%' ORDER BY name");
    echo "<h3>Doctor Tables Created:</h3>";
    foreach ($tables_after as $table) {
        echo "<p>✅ {$table['name']}</p>";
    }
    
    // Get all doctors and create default schedules
    $doctors = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'doctor'");
    echo "<h3>Creating Default Schedules:</h3>";
    
    if (empty($doctors)) {
        echo "<p>⚠️ No doctors found. Creating a sample doctor...</p>";
        
        // Create a sample doctor
        $doctor_user_id = $db->insertData('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'dr.john.doe@hospital.com',
            'password' => password_hash('doctor123', PASSWORD_DEFAULT),
            'role' => 'doctor',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $doctor_profile_id = $db->insertData('doctors', [
            'user_id' => $doctor_user_id,
            'specialty' => 'General Medicine',
            'license_number' => 'MD-' . rand(100000, 999999),
            'consultation_fee' => 100.00,
            'experience_years' => 10,
            'biography' => 'Experienced family doctor.',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p>✅ Created sample doctor: Dr. John Doe (User ID: $doctor_user_id)</p>";
        
        // Refresh doctors list
        $doctors = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'doctor'");
    }
    
    foreach ($doctors as $doctor) {
        $doctor_id = $doctor['id'];
        $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
        
        // Create default schedule (Monday=1 to Friday=5, 9 AM to 5 PM)
        for ($day = 1; $day <= 5; $day++) {
            $db->insertData('doctor_schedules', [
                'doctor_id' => $doctor_id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'slot_duration' => 30,
                'is_available' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        echo "<p>✅ Created schedule for Dr. $doctor_name (ID: $doctor_id)</p>";
    }
    
    // Final verification
    echo "<h3>Final Verification:</h3>";
    $schedule_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules")['count'];
    $breaks_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_breaks")['count'];
    $unavailable_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_unavailable")['count'];
    
    echo "<p>✅ Doctor Schedules: $schedule_count records</p>";
    echo "<p>✅ Doctor Breaks: $breaks_count records</p>";
    echo "<p>✅ Doctor Unavailable: $unavailable_count records</p>";
    
    // Test a sample query from the schedule page
    echo "<h3>Testing Schedule Page Query:</h3>";
    $test_schedules = $db->fetchAll("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week", [$doctors[0]['id']]);
    echo "<p>✅ Found " . count($test_schedules) . " schedule entries for first doctor</p>";
    
    echo "<h2>🎉 SUCCESS!</h2>";
    echo "<p><strong>All doctor schedule tables have been created successfully!</strong></p>";
    echo "<p><a href='doctor/schedule.php' target='_blank' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Doctor Schedule Page</a></p>";
    echo "<p><a href='simulate_doctor_login.php' target='_blank'>Ensure Doctor Login First</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>
