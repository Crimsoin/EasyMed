<?php
require_once 'includes/config.php';

echo "<h2>Creating Doctor Schedule Tables</h2>";

$db = Database::getInstance();

try {
    // Create doctor_schedules table for SQLite
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
    echo "<p>✅ doctor_schedules table created successfully</p>";

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
    echo "<p>✅ doctor_breaks table created successfully</p>";

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
    echo "<p>✅ doctor_unavailable table created successfully</p>";

    // Insert sample schedules for existing doctors
    $doctors = $db->fetchAll("SELECT id FROM users WHERE role = 'doctor'");
    
    foreach ($doctors as $doctor) {
        $doctor_id = $doctor['id'];
        
        // Check if schedule already exists
        $existing = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules WHERE doctor_id = ?", [$doctor_id]);
        
        if ($existing['count'] == 0) {
            // Create default schedule (Monday to Friday, 9 AM to 5 PM)
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
            echo "<p>✅ Created default schedule for doctor ID: $doctor_id</p>";
        } else {
            echo "<p>ℹ️ Schedule already exists for doctor ID: $doctor_id</p>";
        }
    }
    
    echo "<h3>Schedule Tables Summary:</h3>";
    $schedule_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules")['count'];
    $breaks_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_breaks")['count'];
    $unavailable_count = $db->fetch("SELECT COUNT(*) as count FROM doctor_unavailable")['count'];
    
    echo "<p>Doctor Schedules: $schedule_count records</p>";
    echo "<p>Doctor Breaks: $breaks_count records</p>";
    echo "<p>Doctor Unavailable: $unavailable_count records</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
