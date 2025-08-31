<?php
// Report counts for patient/doctor related tables
$dbPath = __DIR__ . '/../../database/easymed.sqlite';
if (!file_exists($dbPath)) { echo "ERROR: DB not found at $dbPath\n"; exit(1); }
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queries = [
    'users_total' => "SELECT COUNT(*) as c FROM users",
    'users_admin' => "SELECT COUNT(*) as c FROM users WHERE role = 'admin'",
    'users_doctor' => "SELECT COUNT(*) as c FROM users WHERE role = 'doctor'",
    'users_patient' => "SELECT COUNT(*) as c FROM users WHERE role = 'patient'",
    'patients' => "SELECT COUNT(*) as c FROM patients",
    'doctors' => "SELECT COUNT(*) as c FROM doctors",
    'appointments' => "SELECT COUNT(*) as c FROM appointments",
    'payments' => "SELECT COUNT(*) as c FROM payments",
    'reviews' => "SELECT COUNT(*) as c FROM reviews",
    'doctor_schedules' => "SELECT COUNT(*) as c FROM doctor_schedules",
    'doctor_breaks' => "SELECT COUNT(*) as c FROM doctor_breaks",
    'doctor_unavailable' => "SELECT COUNT(*) as c FROM doctor_unavailable",
];
$results = [];
foreach ($queries as $k => $q) {
    try { $st = $db->query($q); $results[$k] = (int)$st->fetch(PDO::FETCH_ASSOC)['c']; }
    catch (Exception $e) { $results[$k] = 'ERROR: '.$e->getMessage(); }
}
echo "Current database counts:\n";
foreach ($results as $k => $v) { echo sprintf("  %-20s %s\n", $k.':', $v); }
exit(0);
?>
