<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctor_id = intval($_GET['doctor_id'] ?? 0);
if (!$doctor_id) {
    echo json_encode(['error' => 'Invalid doctor ID']);
    exit;
}

$db = Database::getInstance();

// Get doctor basic info
$doctor = $db->fetch("
    SELECT d.id, u.first_name, u.last_name, d.specialty
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
", [$doctor_id]);

if (!$doctor) {
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

// Get schedule for all days
$schedule = $db->fetchAll("
    SELECT day_of_week, start_time, end_time, slot_duration, is_available
    FROM doctor_schedules
    WHERE doctor_id = ?
    ORDER BY day_of_week
", [$doctor_id]);

echo json_encode([
    'doctor'   => $doctor,
    'schedule' => $schedule
]);
?>
