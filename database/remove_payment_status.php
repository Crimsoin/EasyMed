<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = Database::getInstance();

    echo "Starting payment_status column removal...\n";

    // Check current appointments table schema
    echo "Current appointments table schema:\n";
    $schema = $db->fetchAll('PRAGMA table_info(appointments)');
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }

    // Check if payment_status column exists
    $hasPaymentStatus = false;
    foreach ($schema as $column) {
        if ($column['name'] === 'payment_status') {
            $hasPaymentStatus = true;
            break;
        }
    }

    if (!$hasPaymentStatus) {
        echo "\npayment_status column not found. Nothing to remove.\n";
        exit(0);
    }

    echo "\nRemoving payment_status column from appointments table...\n";

    // Start transaction
    $db->query("BEGIN TRANSACTION");

    // Create new table without payment_status column
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

    // Copy data from old table to new table (excluding payment_status)
    $db->query("
        INSERT INTO appointments_new (
            id, patient_id, doctor_id, appointment_date, appointment_time,
            duration, reason_for_visit, status, notes, patient_info,
            created_at, updated_at
        )
        SELECT
            id, patient_id, doctor_id, appointment_date, appointment_time,
            duration, reason_for_visit, status, notes, patient_info,
            created_at, updated_at
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

    echo "Column removal completed successfully!\n";

    // Verify the new schema
    echo "\nUpdated appointments table schema:\n";
    $schema = $db->fetchAll("PRAGMA table_info(appointments)");
    foreach ($schema as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }

    // Check final record count
    $count = $db->fetch("SELECT COUNT(*) as count FROM appointments")['count'];
    echo "\nTotal appointments: {$count}\n";

} catch (Exception $e) {
    // Rollback on error
    if (isset($db)) {
        $db->query("ROLLBACK");
    }
    echo "Error during column removal: " . $e->getMessage() . "\n";
    echo "Operation rolled back.\n";
}
?>
