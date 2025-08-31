<?php
session_start();
require_once 'includes/config.php';

// Get the first doctor user to simulate login
$db = Database::getInstance();
$doctor = $db->fetch("SELECT * FROM users WHERE role = 'doctor' LIMIT 1");

if ($doctor) {
    $_SESSION['user_id'] = $doctor['id'];
    $_SESSION['role'] = $doctor['role'];
    $_SESSION['first_name'] = $doctor['first_name'];
    $_SESSION['last_name'] = $doctor['last_name'];
    $_SESSION['email'] = $doctor['email'];
    
    echo "<h2>Simulated Doctor Login</h2>";
    echo "<p>Logged in as: Dr. " . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . "</p>";
    echo "<p>User ID: " . $doctor['id'] . "</p>";
    echo "<p>Role: " . $doctor['role'] . "</p>";
    echo "<p><a href='doctor/dashboard_doctor.php'>Access Doctor Dashboard</a></p>";
    echo "<p><a href='doctor/appointments.php'>View Doctor Appointments</a></p>";
    echo "<p><a href='doctor/patients.php'>View Patients</a></p>";
    
    // Check if this doctor has any appointments
    $appointments = $db->fetchAll("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctor['id']]);
    echo "<p>This doctor has " . $appointments[0]['count'] . " appointments in the system.</p>";
    
} else {
    echo "<h2>No Doctor Users Found</h2>";
    echo "<p>No doctor users found in database. Let me create one:</p>";
    
    try {
        // Create a doctor user
        $doctor_user_id = $db->insertData('users', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'dr.jane.smith@hospital.com',
            'password' => password_hash('doctor123', PASSWORD_DEFAULT),
            'role' => 'doctor',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create doctor profile
        $doctor_id = $db->insertData('doctors', [
            'user_id' => $doctor_user_id,
            'specialty' => 'General Medicine',
            'license_number' => 'MD-' . rand(100000, 999999),
            'consultation_fee' => 75.00,
            'experience_years' => 8,
            'biography' => 'Experienced general practitioner with focus on preventive care.',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p>✅ Created doctor user with ID: $doctor_user_id</p>";
        echo "<p>✅ Created doctor profile with ID: $doctor_id</p>";
        
        // Set session
        $_SESSION['user_id'] = $doctor_user_id;
        $_SESSION['role'] = 'doctor';
        $_SESSION['first_name'] = 'Jane';
        $_SESSION['last_name'] = 'Smith';
        $_SESSION['email'] = 'dr.jane.smith@hospital.com';
        
        echo "<p><a href='doctor/dashboard_doctor.php'>Access Doctor Dashboard</a></p>";
        
    } catch (Exception $e) {
        echo "<p>Error creating doctor: " . $e->getMessage() . "</p>";
    }
}
?>
