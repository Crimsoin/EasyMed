<?php
require_once 'includes/config.php';

echo "<h1>Creating Doctor Schedule Tables</h1>";

$db = Database::getInstance();

try {
    // Check if tables exist first
    $tables_check = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'doctor_%'");
    echo "<h3>Existing doctor-related tables:</h3>";
    if (empty($tables_check)) {
        echo "<p>No doctor tables found.</p>";
    } else {
        foreach ($tables_check as $table) {
            echo "<p>- {$table['name']}</p>";
        }
    }

    echo "<h3>Creating missing tables...</h3>";

    // Create doctor_schedules table
    $db->query("
        CREATE TABLE IF NOT EXISTS doctor_schedules (
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
    ");
    echo "<p>✅ doctor_schedules table created</p>";

    // Create doctor_breaks table
    $db->query("
        CREATE TABLE IF NOT EXISTS doctor_breaks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            break_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ doctor_breaks table created</p>";

    // Create doctor_unavailable table
    $db->query("
        CREATE TABLE IF NOT EXISTS doctor_unavailable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL,
            unavailable_date DATE NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(doctor_id, unavailable_date)
        )
    ");
    echo "<p>✅ doctor_unavailable table created</p>";

    // Verify tables were created
    $tables_after = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'doctor_%'");
    echo "<h3>Tables after creation:</h3>";
    foreach ($tables_after as $table) {
        echo "<p>✅ {$table['name']}</p>";
    }

    // Get all doctors and create default schedules
    $doctors = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'doctor'");
    echo "<h3>Creating default schedules for doctors:</h3>";
    
    foreach ($doctors as $doctor) {
        $doctor_id = $doctor['id'];
        $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
        
        // Check if schedule already exists
        $existing = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules WHERE doctor_id = ?", [$doctor_id]);
        
        if ($existing['count'] == 0) {
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
        } else {
            echo "<p>ℹ️ Schedule already exists for Dr. $doctor_name (ID: $doctor_id)</p>";
        }
    }

    // Final verification
    echo "<h3>Final Verification:</h3>";
    $schedule_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules")['count'];
    $breaks_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_breaks")['count'];
    $unavailable_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_unavailable")['count'];
    
    echo "<p>Doctor Schedules: $schedule_count records</p>";
    echo "<p>Doctor Breaks: $breaks_count records</p>";
    echo "<p>Doctor Unavailable: $unavailable_count records</p>";
    
    if ($schedule_count > 0) {
        echo "<p><strong>🎉 Success! Now you can access the doctor schedule page.</strong></p>";
        echo "<p><a href='doctor/schedule.php' target='_blank'>Test Doctor Schedule Page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>
