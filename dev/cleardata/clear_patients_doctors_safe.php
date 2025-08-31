<?php
// Safe clear: delete patients, doctors and their related data in correct order
$dbPath = __DIR__ . '/../../database/easymed.sqlite';
if (!file_exists($dbPath)) {
    echo "Database not found at $dbPath\n";
    exit(1);
}
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure foreign keys are enforced
    $db->exec('PRAGMA foreign_keys = ON');

    // Tables to clear (children first)
    $sequence = [
        'payments',
        'reviews',
        'appointments',
        'patients',
        'doctor_unavailable',
        'doctor_breaks',
        'doctor_schedules',
        'doctors'
    ];

    echo "Counting records before deletion...\n";
    $before = [];
    foreach ($sequence as $t) {
        try { $st = $db->query("SELECT COUNT(*) as c FROM $t"); $before[$t] = (int)$st->fetch(PDO::FETCH_ASSOC)['c']; }
        catch (Exception $e) { $before[$t] = 'ERR'; }
    }
    // Users counts
    try { $uTotal = (int)$db->query("SELECT COUNT(*) as c FROM users")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uTotal='ERR'; }
    try { $uDoctors = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role = 'doctor'")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uDoctors='ERR'; }
    try { $uPatients = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role = 'patient'")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uPatients='ERR'; }

    echo "Before:\n";
    foreach ($before as $t => $c) echo sprintf("  %-20s %s\n", $t.':', $c);
    echo sprintf("  %-20s %s\n", 'users_total:', $uTotal);
    echo sprintf("  %-20s %s\n", 'users_doctor:', $uDoctors);
    echo sprintf("  %-20s %s\n", 'users_patient:', $uPatients);

    // Begin transaction
    $db->beginTransaction();

    // Delete sequence
    foreach ($sequence as $t) {
        try {
            $db->exec("DELETE FROM $t");
            echo "Deleted all rows from $t\n";
        } catch (Exception $e) {
            echo "Warning: could not clear $t: " . $e->getMessage() . "\n";
        }
    }

    // Delete user records for patients and doctors
    try {
        $db->exec("DELETE FROM users WHERE role IN ('patient','doctor')");
        echo "Deleted users with role patient/doctor\n";
    } catch (Exception $e) {
        echo "Warning: could not delete users: " . $e->getMessage() . "\n";
    }

    $db->commit();

    // Counts after
    echo "\nCounting records after deletion...\n";
    $after = [];
    foreach ($sequence as $t) {
        try { $st = $db->query("SELECT COUNT(*) as c FROM $t"); $after[$t] = (int)$st->fetch(PDO::FETCH_ASSOC)['c']; }
        catch (Exception $e) { $after[$t] = 'ERR'; }
    }
    try { $uTotal2 = (int)$db->query("SELECT COUNT(*) as c FROM users")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uTotal2='ERR'; }
    try { $uDoctors2 = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role = 'doctor'")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uDoctors2='ERR'; }
    try { $uPatients2 = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role = 'patient'")->fetch(PDO::FETCH_ASSOC)['c']; } catch(Exception $e){ $uPatients2='ERR'; }

    echo "After:\n";
    foreach ($after as $t => $c) echo sprintf("  %-20s %s\n", $t.':', $c);
    echo sprintf("  %-20s %s\n", 'users_total:', $uTotal2);
    echo sprintf("  %-20s %s\n", 'users_doctor:', $uDoctors2);
    echo sprintf("  %-20s %s\n", 'users_patient:', $uPatients2);

    echo "\nOperation completed.\n";
    exit(0);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "Error during operation: " . $e->getMessage() . "\n";
    exit(1);
}
