<?php
// Safe clear: delete patients and their related data in correct order
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

    // Counts before
    $counts = [];
    $tables = ['payments','reviews','appointments','patients'];
    foreach ($tables as $t) {
        $st = $db->query("SELECT COUNT(*) as c FROM $t");
        $counts[$t] = $st ? (int)$st->fetch(PDO::FETCH_ASSOC)['c'] : 0;
    }

    echo "Counts before deletion:\n";
    foreach ($counts as $t => $c) echo "  $t: $c\n";

    // Begin transaction
    $db->beginTransaction();

    // Delete in safe order: payments -> reviews -> appointments -> patients
    // Limit deletions to rows referencing patients (but here we clear all patients)
    $db->exec("DELETE FROM payments");
    $db->exec("DELETE FROM reviews");
    $db->exec("DELETE FROM appointments");
    $db->exec("DELETE FROM patients");

    $db->commit();

    // Counts after
    $after = [];
    foreach ($tables as $t) {
        $st = $db->query("SELECT COUNT(*) as c FROM $t");
        $after[$t] = $st ? (int)$st->fetch(PDO::FETCH_ASSOC)['c'] : 0;
    }

    echo "\nCounts after deletion:\n";
    foreach ($after as $t => $c) echo "  $t: $c\n";

    echo "\nDeletion completed successfully.\n";
    exit(0);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    echo "Error during deletion: " . $e->getMessage() . "\n";
    exit(1);
}
