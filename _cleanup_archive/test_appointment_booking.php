<?php
require_once 'includes/config.php';

echo "<h2>Testing Appointment Booking Process</h2>";

$db = Database::getInstance();

// Check if we have any appointments in the database
echo "<h3>Current Appointments in Database:</h3>";
$appointments = $db->fetchAll("
    SELECT a.*, 
           u.first_name, u.last_name, u.email,
           p.phone, p.address, p.gender,
           d.first_name as doctor_first_name, d.last_name as doctor_last_name
    FROM appointments a
    LEFT JOIN patients pt ON a.patient_id = pt.id
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN users d ON a.doctor_id = d.id
    ORDER BY a.created_at DESC
");

if (empty($appointments)) {
    echo "<p>No appointments found in database.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th>ID</th>
            <th>Patient Name</th>
            <th>Patient Email</th>
            <th>Doctor</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Reason</th>
            <th>Created</th>
          </tr>";
    
    foreach ($appointments as $apt) {
        echo "<tr>";
        echo "<td>" . $apt['id'] . "</td>";
        echo "<td>" . htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($apt['email']) . "</td>";
        echo "<td>Dr. " . htmlspecialchars($apt['doctor_first_name'] . ' ' . $apt['doctor_last_name']) . "</td>";
        echo "<td>" . $apt['appointment_date'] . "</td>";
        echo "<td>" . $apt['appointment_time'] . "</td>";
        echo "<td>" . $apt['status'] . "</td>";
        echo "<td>" . htmlspecialchars($apt['reason']) . "</td>";
        echo "<td>" . $apt['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check users table
echo "<h3>Users in Database:</h3>";
$users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
if (empty($users)) {
    echo "<p>No users found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check patients table
echo "<h3>Patients in Database:</h3>";
$patients = $db->fetchAll("
    SELECT p.*, u.first_name, u.last_name, u.email 
    FROM patients p 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
");
if (empty($patients)) {
    echo "<p>No patients found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Gender</th><th>Created</th></tr>";
    foreach ($patients as $patient) {
        echo "<tr>";
        echo "<td>" . $patient['id'] . "</td>";
        echo "<td>" . $patient['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['email']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($patient['address']) . "</td>";
        echo "<td>" . $patient['gender'] . "</td>";
        echo "<td>" . $patient['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test session data
echo "<h3>Session Information:</h3>";
if (isset($_SESSION)) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<p>No session data available.</p>";
}
?>
