<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

echo "Creating doctor schedule tables if missing...\n";
$db = Database::getInstance();

try {
    $db->query("CREATE TABLE IF NOT EXISTS doctor_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        slot_duration INTEGER NOT NULL DEFAULT 30,
        is_available INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE(doctor_id, day_of_week)
    )");
    echo "- doctor_schedules ensured\n";

    $db->query("CREATE TABLE IF NOT EXISTS doctor_breaks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        break_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        reason TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )");
    echo "- doctor_breaks ensured\n";

    $db->query("CREATE TABLE IF NOT EXISTS doctor_unavailable (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        unavailable_date DATE NOT NULL,
        reason TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE(doctor_id, unavailable_date)
    )");
    echo "- doctor_unavailable ensured\n";

    echo "All doctor schedule tables created or already existed.\n";
} catch (Exception $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

?>
