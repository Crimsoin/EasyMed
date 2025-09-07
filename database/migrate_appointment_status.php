<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = Database::getInstance();

    echo "Starting appointment status migration...\n";

    // Check current status values in the database
    $currentStatuses = $db->fetchAll("SELECT DISTINCT status FROM appointments");
    echo "Current appointment statuses in database:\n";
    foreach ($currentStatuses as $status) {
        echo "- " . $status['status'] . "\n";
    }

    // Check if any appointments have 'confirmed' status
    $confirmedCount = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed'")['count'];
    echo "\nAppointments with 'confirmed' status: " . $confirmedCount . "\n";

    if ($confirmedCount > 0) {
        echo "Migrating 'confirmed' appointments to 'scheduled'...\n";
        $db->query("UPDATE appointments SET status = 'scheduled' WHERE status = 'confirmed'");
        echo "Migration completed!\n";
    }

    // For SQLite, we need to recreate the table to change the ENUM
    echo "\nRecreating appointments table with updated status enum...\n";

    // Start transaction
    $db->query("BEGIN TRANSACTION");

    // Create new table with updated schema
    $db->query("
        CREATE TABLE appointments_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            duration INTEGER,
            reason_for_visit TEXT,
            status TEXT CHECK(status IN ('pending', 'rescheduled', 'scheduled', 'completed', 'cancelled', 'no_show')) DEFAULT 'pending',
            notes TEXT,
            patient_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Copy data from old table to new table
    $db->query("
        INSERT INTO appointments_new (
            id, patient_id, doctor_id, appointment_date, appointment_time,
            duration, reason_for_visit, status, notes,
            patient_info, created_at, updated_at
        )
        SELECT
            id, patient_id, doctor_id, appointment_date, appointment_time,
            duration, reason_for_visit, status, notes,
            patient_info, created_at, updated_at
        FROM appointments
    ");

    // Drop old table
    $db->query("DROP TABLE appointments");

    // Rename new table
    $db->query("ALTER TABLE appointments_new RENAME TO appointments");

    // Create indexes
    $db->query("CREATE INDEX idx_patient_date ON appointments(patient_id, appointment_date)");
    $db->query("CREATE INDEX idx_doctor_date ON appointments(doctor_id, appointment_date)");
    $db->query("CREATE INDEX idx_status ON appointments(status)");
    $db->query("CREATE INDEX idx_appointment_datetime ON appointments(appointment_date, appointment_time)");

    // Commit transaction
    $db->query("COMMIT");

    echo "Migration completed successfully!\n";

    // Verify the new schema
    echo "\nVerifying new table schema...\n";
    $schema = $db->fetchAll("PRAGMA table_info(appointments)");
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }

    // Check final status distribution
    echo "\nFinal appointment status distribution:\n";
    $statusCounts = $db->fetchAll("
        SELECT status, COUNT(*) as count
        FROM appointments
        GROUP BY status
        ORDER BY status
    ");
    foreach ($statusCounts as $statusCount) {
        echo "- " . $statusCount['status'] . ": " . $statusCount['count'] . "\n";
    }

} catch (Exception $e) {
    // Rollback on error
    if (isset($db)) {
        $db->query("ROLLBACK");
    }
    echo "Error during migration: " . $e->getMessage() . "\n";
    echo "Migration rolled back.\n";
}
?>
