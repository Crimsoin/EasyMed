<?php
session_start();
require_once 'includes/config.php';

echo "<h2>Test Appointment Booking</h2>";

$db = Database::getInstance();

// Get a patient user and a doctor
$patient_user = $db->fetch("SELECT * FROM users WHERE role = 'patient' LIMIT 1");
$doctor_user = $db->fetch("SELECT * FROM users WHERE role = 'doctor' LIMIT 1");

if (!$patient_user) {
    echo "<p>No patient user found. Let's create one:</p>";
    
    try {
        $user_id = $db->insertData('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@email.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'patient',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $patient_id = $db->insertData('patients', [
            'user_id' => $user_id,
            'phone' => '123-456-7890',
            'address' => '123 Main St',
            'gender' => 'Male',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p>Created patient user with ID: $user_id and patient record with ID: $patient_id</p>";
        $patient_user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        
    } catch (Exception $e) {
        echo "<p>Error creating patient: " . $e->getMessage() . "</p>";
        exit;
    }
}

if (!$doctor_user) {
    echo "<p>No doctor user found.</p>";
    exit;
}

// Get the patient record
$patient = $db->fetch("SELECT * FROM patients WHERE user_id = ?", [$patient_user['id']]);

if (!$patient) {
    echo "<p>Patient record not found for user. Creating one:</p>";
    try {
        $patient_id = $db->insertData('patients', [
            'user_id' => $patient_user['id'],
            'phone' => '123-456-7890',
            'address' => '123 Main St',
            'gender' => 'Male',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p>Created patient record with ID: $patient_id</p>";
        $patient = $db->fetch("SELECT * FROM patients WHERE id = ?", [$patient_id]);
    } catch (Exception $e) {
        echo "<p>Error creating patient record: " . $e->getMessage() . "</p>";
        exit;
    }
}

echo "<p>Patient User: " . htmlspecialchars($patient_user['first_name'] . ' ' . $patient_user['last_name']) . " (ID: {$patient_user['id']})</p>";
echo "<p>Patient Record ID: {$patient['id']}</p>";
echo "<p>Doctor: " . htmlspecialchars($doctor_user['first_name'] . ' ' . $doctor_user['last_name']) . " (ID: {$doctor_user['id']})</p>";

// Create a test appointment
echo "<h3>Creating Test Appointment</h3>";

try {
    $appointment_id = $db->insertData('appointments', [
        'patient_id' => $patient['id'],
        'doctor_id' => $doctor_user['id'],
        'appointment_date' => '2025-08-25',
        'appointment_time' => '10:00:00',
        'reason' => 'Test appointment from debug script',
        'status' => 'scheduled',
        'notes' => 'This is a test appointment',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p>✅ Successfully created appointment with ID: $appointment_id</p>";
    
    // Set session for this user
    $_SESSION['user_id'] = $patient_user['id'];
    $_SESSION['role'] = 'patient';
    $_SESSION['first_name'] = $patient_user['first_name'];
    $_SESSION['last_name'] = $patient_user['last_name'];
    $_SESSION['email'] = $patient_user['email'];
    
    echo "<p>Session set for patient. <a href='patient/appointments.php'>View Appointments</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error creating appointment: " . $e->getMessage() . "</p>";
}

// Verify appointment was created
$appointment = $db->fetch("SELECT * FROM appointments WHERE id = ?", [$appointment_id ?? 0]);
if ($appointment) {
    echo "<h3>Appointment Created Successfully:</h3>";
    echo "<pre>";
    print_r($appointment);
    echo "</pre>";
} else {
    echo "<p>Appointment not found in database.</p>";
}
?>
