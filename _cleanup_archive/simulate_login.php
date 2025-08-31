<?php
session_start();
require_once 'includes/config.php';

// Get the first user to simulate login
$db = Database::getInstance();
$user = $db->fetch("SELECT * FROM users WHERE role = 'patient' LIMIT 1");

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['email'] = $user['email'];
    
    echo "<h2>Simulated Login</h2>";
    echo "<p>Logged in as: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>";
    echo "<p>User ID: " . $user['id'] . "</p>";
    echo "<p>Role: " . $user['role'] . "</p>";
    echo "<p><a href='patient/appointments.php'>View My Appointments</a></p>";
    echo "<p><a href='patient/book-appointment.php'>Book New Appointment</a></p>";
    echo "<p><a href='test_appointment_booking.php'>View Database Status</a></p>";
} else {
    echo "<h2>No Patient Users Found</h2>";
    echo "<p>No patient users found in database. Please register first.</p>";
}
?>
