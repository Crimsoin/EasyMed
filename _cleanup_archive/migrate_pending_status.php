<?php
require_once 'includes/database.php';
require_once 'includes/config.php';

try {
    $db = Database::getInstance();
    
    echo "=== EasyMed Database Migration: Add 'pending' status ===\n";
    
    // Check current table structure
    echo "Checking current appointments table structure...\n";
    $tableInfo = $db->fetchAll("PRAGMA table_info(appointments)");
    
    // Find the status column
    $statusColumn = null;
    foreach ($tableInfo as $column) {
        if ($column['name'] === 'status') {
            $statusColumn = $column;
            break;
        }
    }
    
    if ($statusColumn) {
        echo "Found status column: " . $statusColumn['type'] . "\n";
        
        // Since SQLite doesn't support ALTER COLUMN with CHECK constraints,
        // we need to recreate the table
        
        echo "Creating backup of appointments table...\n";
        $db->query("CREATE TABLE appointments_backup AS SELECT * FROM appointments");
        
        echo "Dropping original appointments table...\n";
        $db->query("DROP TABLE appointments");
        
        echo "Creating new appointments table with 'pending' status...\n";
        $db->query("
            CREATE TABLE appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                doctor_id INTEGER NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                duration INTEGER DEFAULT 30,
                reason_for_visit TEXT,
                status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'scheduled', 'confirmed', 'completed', 'cancelled')),
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                patient_info TEXT,
                FOREIGN KEY (patient_id) REFERENCES patients(id),
                FOREIGN KEY (doctor_id) REFERENCES doctors(id)
            )
        ");
        
        echo "Restoring data from backup...\n";
        $db->query("
            INSERT INTO appointments (
                id, patient_id, doctor_id, appointment_date, appointment_time, 
                duration, reason_for_visit, status, notes, created_at, updated_at, patient_info
            )
            SELECT 
                id, patient_id, doctor_id, appointment_date, appointment_time,
                duration, reason_for_visit, 
                CASE 
                    WHEN status = 'scheduled' THEN 'pending'
                    ELSE status 
                END as status,
                notes, created_at, updated_at, patient_info
            FROM appointments_backup
        ");
        
        echo "Dropping backup table...\n";
        $db->query("DROP TABLE appointments_backup");
        
        echo "✅ Migration completed successfully!\n";
        echo "- All existing 'scheduled' appointments converted to 'pending'\n";
        echo "- New appointments will default to 'pending' status\n";
        echo "- Valid statuses: pending, scheduled, confirmed, completed, cancelled\n";
        
    } else {
        echo "❌ Status column not found in appointments table!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    
    // Try to restore from backup if it exists
    try {
        $db->query("DROP TABLE IF EXISTS appointments");
        $db->query("ALTER TABLE appointments_backup RENAME TO appointments");
        echo "✅ Restored from backup\n";
    } catch (Exception $restoreError) {
        echo "❌ Could not restore from backup: " . $restoreError->getMessage() . "\n";
    }
}
?>
