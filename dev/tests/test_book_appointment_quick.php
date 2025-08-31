<?php
// Quick test for book appointment page
session_start();

// Mock session data for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'patient';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Patient';
$_SESSION['email'] = 'test@example.com';

// Check if the page loads without errors
try {
    ob_start();
    include 'patient/book-appointment.php';
    $output = ob_get_clean();
    
    echo "Page loaded successfully!<br>";
    echo "Output length: " . strlen($output) . " characters<br>";
    
    // Check for JavaScript errors in the output
    if (strpos($output, 'openAppointmentModal') !== false) {
        echo "JavaScript function openAppointmentModal found ✓<br>";
    } else {
        echo "JavaScript function openAppointmentModal NOT found ✗<br>";
    }
    
    if (strpos($output, 'appointmentModal') !== false) {
        echo "Modal element found ✓<br>";
    } else {
        echo "Modal element NOT found ✗<br>";
    }
    
    if (strpos($output, 'btn btn-primary') !== false) {
        echo "Button found ✓<br>";
    } else {
        echo "Button NOT found ✗<br>";
    }
    
} catch (Exception $e) {
    echo "Error loading page: " . $e->getMessage() . "<br>";
}
?>
