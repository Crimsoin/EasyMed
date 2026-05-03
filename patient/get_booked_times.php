<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctor_id || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Fetch all booked times for this doctor on this date that are not cancelled
    $booked = $db->fetchAll("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status != 'cancelled'
    ", [$doctor_id, $date]);

    // Format times to H:i for easy comparison in JS
    $times = array_map(function($row) {
        return date('H:i', strtotime($row['appointment_time']));
    }, $booked);

    echo json_encode([
        'success' => true, 
        'booked_times' => $times
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error'
    ]);
}
