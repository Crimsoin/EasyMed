<?php
require_once 'includes/config.php';

echo "<h2>Checking Doctor Profile Database Schema</h2>";

$db = Database::getInstance();

echo "<h3>Users Table Structure:</h3>";
$userColumns = $db->fetchAll("PRAGMA table_info(users)");
foreach ($userColumns as $col) {
    echo "<p>{$col['name']} ({$col['type']})</p>";
}

echo "<h3>Doctors Table Structure:</h3>";
$doctorColumns = $db->fetchAll("PRAGMA table_info(doctors)");
foreach ($doctorColumns as $col) {
    echo "<p>{$col['name']} ({$col['type']})</p>";
}

echo "<h3>Sample Doctor Data:</h3>";
$sample = $db->fetch("
    SELECT u.*, d.* 
    FROM users u 
    JOIN doctors d ON d.user_id = u.id 
    WHERE u.role = 'doctor' 
    LIMIT 1
");

if ($sample) {
    echo "<table border='1'>";
    foreach ($sample as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No doctor records found</p>";
}
?>
