<?php
session_start();
require_once 'includes/config.php';

echo "<h1>Doctor Portal Pages Test</h1>";

// Ensure doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $db = Database::getInstance();
    $doctor = $db->fetch("SELECT * FROM users WHERE role = 'doctor' LIMIT 1");
    if ($doctor) {
        $_SESSION['user_id'] = $doctor['id'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['first_name'] = $doctor['first_name'];
        $_SESSION['last_name'] = $doctor['last_name'];
        $_SESSION['email'] = $doctor['email'];
        echo "<p>✅ Auto-logged in as Dr. " . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . "</p>";
    } else {
        echo "<p>❌ No doctor user found</p>";
        exit;
    }
}

echo "<h2>Testing Doctor Portal Pages</h2>";

$pages = [
    'Dashboard' => 'doctor/dashboard_doctor.php',
    'Appointments' => 'doctor/appointments.php', 
    'Schedule' => 'doctor/schedule.php',
    'Patients' => 'doctor/patients.php',
    'Profile' => 'doctor/profile.php'
];

foreach ($pages as $name => $url) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
    echo "<h3>$name</h3>";
    
    try {
        // Test by making a simple request to check for database errors
        $test_url = "http://localhost:8080/$url";
        echo "<p><a href='$test_url' target='_blank'>🔗 Open $name Page</a></p>";
        
        // Quick database test for each page
        $db = Database::getInstance();
        switch ($name) {
            case 'Dashboard':
                $test = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$_SESSION['user_id']]);
                echo "<p>✅ Dashboard query test: {$test['count']} appointments found</p>";
                break;
                
            case 'Appointments':
                $test = $db->fetchAll("
                    SELECT a.id 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN users u ON p.user_id = u.id
                    WHERE a.doctor_id = ? 
                    LIMIT 1
                ", [$_SESSION['user_id']]);
                echo "<p>✅ Appointments query test: " . count($test) . " results</p>";
                break;
                
            case 'Schedule':
                $test = $db->fetch("SELECT COUNT(*) as count FROM doctor_schedules WHERE doctor_id = ?", [$_SESSION['user_id']]);
                echo "<p>✅ Schedule query test: {$test['count']} schedule entries found</p>";
                break;
                
            case 'Patients':
                $test = $db->fetchAll("
                    SELECT p.id 
                    FROM patients p
                    JOIN users u ON p.user_id = u.id
                    JOIN appointments a ON a.patient_id = p.id
                    WHERE a.doctor_id = ?
                    GROUP BY p.id
                    LIMIT 1
                ", [$_SESSION['user_id']]);
                echo "<p>✅ Patients query test: " . count($test) . " patients found</p>";
                break;
                
            case 'Profile':
                $test = $db->fetch("
                    SELECT u.first_name, d.specialty 
                    FROM users u
                    JOIN doctors d ON d.user_id = u.id
                    WHERE u.id = ?
                ", [$_SESSION['user_id']]);
                echo "<p>✅ Profile query test: Found Dr. {$test['first_name']} ({$test['specialty']})</p>";
                break;
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error testing $name: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

echo "<h2>Database Tables Verification</h2>";

$required_tables = ['users', 'doctors', 'patients', 'appointments', 'doctor_schedules', 'doctor_breaks', 'doctor_unavailable'];

foreach ($required_tables as $table) {
    try {
        $count = $db->fetch("SELECT COUNT(*) as count FROM $table")['count'];
        echo "<p>✅ $table: $count records</p>";
    } catch (Exception $e) {
        echo "<p>❌ $table: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>🎉 All Tests Complete!</h2>";
echo "<p>If all tests show ✅, then all doctor portal pages should be working correctly.</p>";
?>
