<?php
session_start();
require_once 'includes/config.php';

echo "<h2>Debug Appointments Query</h2>";

// Simulate login if no session
if (!isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE role = 'patient' LIMIT 1");
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        echo "<p>Auto-logged in as: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . " (ID: {$user['id']})</p>";
    }
}

if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    
    echo "<h3>Current User Session:</h3>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Role: " . $_SESSION['role'] . "</p>";
    
    echo "<h3>Testing Appointments Query:</h3>";
    
    // Test the exact query from appointments.php
    try {
        $appointments = $db->fetchAll("
            SELECT a.*, 
                   d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                   doc.specialty, doc.consultation_fee
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users d ON a.doctor_id = d.id
            JOIN doctors doc ON d.id = doc.user_id
            WHERE p.user_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ", [$_SESSION['user_id']]);
        
        echo "<p>Query executed successfully. Found " . count($appointments) . " appointments.</p>";
        
        if (!empty($appointments)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr>
                    <th>ID</th>
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
                echo "<td>Dr. " . htmlspecialchars($apt['doctor_first_name'] . ' ' . $apt['doctor_last_name']) . "</td>";
                echo "<td>" . $apt['appointment_date'] . "</td>";
                echo "<td>" . $apt['appointment_time'] . "</td>";
                echo "<td>" . $apt['status'] . "</td>";
                echo "<td>" . htmlspecialchars($apt['reason']) . "</td>";
                echo "<td>" . $apt['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p><strong>No appointments found for this user.</strong></p>";
            
            // Let's check if there are appointments for other patients
            $all_appointments = $db->fetchAll("SELECT a.*, p.user_id, u.first_name, u.last_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id");
            echo "<h4>All appointments in database:</h4>";
            if (!empty($all_appointments)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Appointment ID</th><th>Patient User ID</th><th>Patient Name</th><th>Date</th><th>Time</th></tr>";
                foreach ($all_appointments as $apt) {
                    echo "<tr>";
                    echo "<td>" . $apt['id'] . "</td>";
                    echo "<td>" . $apt['user_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) . "</td>";
                    echo "<td>" . $apt['appointment_date'] . "</td>";
                    echo "<td>" . $apt['appointment_time'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No appointments found in entire database.</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p><strong>Error executing query:</strong> " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p>No user session found.</p>";
}
?>
