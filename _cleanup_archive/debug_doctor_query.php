<?php
require_once 'includes/config.php';

echo "<h2>Checking Database Schema for Doctor Dashboard Fix</h2>";

$db = Database::getInstance();

// Check users table structure
echo "<h3>Users Table Structure:</h3>";
$userColumns = $db->fetchAll("PRAGMA table_info(users)");
echo "<table border='1'>";
echo "<tr><th>Column</th><th>Type</th><th>Not Null</th></tr>";
foreach ($userColumns as $col) {
    echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td><td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td></tr>";
}
echo "</table>";

// Check patients table structure
echo "<h3>Patients Table Structure:</h3>";
$patientColumns = $db->fetchAll("PRAGMA table_info(patients)");
echo "<table border='1'>";
echo "<tr><th>Column</th><th>Type</th><th>Not Null</th></tr>";
foreach ($patientColumns as $col) {
    echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td><td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td></tr>";
}
echo "</table>";

// Check appointments table structure
echo "<h3>Appointments Table Structure:</h3>";
$appointmentColumns = $db->fetchAll("PRAGMA table_info(appointments)");
echo "<table border='1'>";
echo "<tr><th>Column</th><th>Type</th><th>Not Null</th></tr>";
foreach ($appointmentColumns as $col) {
    echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td><td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td></tr>";
}
echo "</table>";

// Test the corrected query
echo "<h3>Testing Corrected Query:</h3>";
try {
    $testQuery = "
        SELECT a.*, 
               u.first_name as patient_first_name, 
               u.last_name as patient_last_name,
               p.phone as patient_phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = 1
        LIMIT 3
    ";
    
    $result = $db->fetchAll($testQuery);
    echo "<p>✅ Query executed successfully! Found " . count($result) . " records.</p>";
    
    if (!empty($result)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Patient Name</th><th>Phone</th><th>Date</th><th>Time</th></tr>";
        foreach ($result as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . htmlspecialchars($row['patient_first_name'] . ' ' . $row['patient_last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['patient_phone'] ?? 'N/A') . "</td>";
            echo "<td>{$row['appointment_date']}</td>";
            echo "<td>{$row['appointment_time']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
