<?php
// Final safe clear: ensure child references to users are removed, then delete patient/doctor users
$dbPath = __DIR__ . '/../../database/easymed.sqlite';
if (!file_exists($dbPath)) { echo "Database not found at $dbPath\n"; exit(1); }
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    echo "Counts before:\n";
    $checks = ['users_total' => "SELECT COUNT(*) as c FROM users",
               'users_doctor' => "SELECT COUNT(*) as c FROM users WHERE role='doctor'",
               'users_patient' => "SELECT COUNT(*) as c FROM users WHERE role='patient'",
               'activity_logs' => "SELECT COUNT(*) as c FROM activity_logs",
               'notifications' => "SELECT COUNT(*) as c FROM notifications",
               'payments_verified_by_notnull' => "SELECT COUNT(*) as c FROM payments WHERE verified_by IS NOT NULL"
    ];
    foreach ($checks as $k=>$q) { $st = $db->query($q); $v = $st? (int)$st->fetch(PDO::FETCH_ASSOC)['c'] : 0; echo "  $k: $v\n"; }

    $db->beginTransaction();

    // Null out payments.verified_by to avoid FK issues
    try {
        $db->exec("UPDATE payments SET verified_by = NULL WHERE verified_by IS NOT NULL");
        echo "Set payments.verified_by = NULL where applicable\n";
    } catch (Exception $e) { echo "Warning updating payments: " . $e->getMessage() . "\n"; }

    // Delete activity logs and notifications referencing users
    try { $db->exec("DELETE FROM activity_logs"); echo "Deleted activity_logs\n"; } catch (Exception $e) { echo "Warn activity_logs: " . $e->getMessage() . "\n"; }
    try { $db->exec("DELETE FROM notifications"); echo "Deleted notifications\n"; } catch (Exception $e) { echo "Warn notifications: " . $e->getMessage() . "\n"; }

    // Finally delete user accounts with roles patient/doctor
    try {
        $db->exec("DELETE FROM users WHERE role IN ('patient','doctor')");
        echo "Deleted users with role patient/doctor\n";
    } catch (Exception $e) {
        echo "Error deleting users: " . $e->getMessage() . "\n";
        $db->rollBack();
        exit(1);
    }

    $db->commit();

    echo "Counts after:\n";
    foreach ($checks as $k=>$q) { $st = $db->query($q); $v = $st? (int)$st->fetch(PDO::FETCH_ASSOC)['c'] : 0; echo "  $k: $v\n"; }

    echo "Done.\n";
    exit(0);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "Fatal: " . $e->getMessage() . "\n";
    exit(1);
}
