<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit();
}

$appointment_id = intval($_GET['id']);
$db = Database::getInstance();

try {
    // Get appointment details with all related information
    $appointment = $db->fetch("
        SELECT a.*, 
               pu.first_name as patient_first_name, pu.last_name as patient_last_name, 
               pu.email as patient_email, pu.phone as patient_phone, pu.profile_image as patient_image,
               p.date_of_birth, p.gender, p.address,
               du.first_name as doctor_first_name, du.last_name as doctor_last_name,
               du.email as doctor_email, du.profile_image as doctor_image,
               d.specialty, d.license_number, d.consultation_fee, d.phone as doctor_phone
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users du ON d.user_id = du.id
        WHERE a.id = ?
    ", [$appointment_id]);

    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        exit();
    }

    // Get payment information if exists
    $payment = $db->fetch("
        SELECT p.*, u.first_name as verified_by_name, u.last_name as verified_by_lastname
        FROM payments p
        LEFT JOIN users u ON p.verified_by = u.id
        WHERE p.appointment_id = ?
        ORDER BY p.created_at DESC
        LIMIT 1
    ", [$appointment_id]);

    // Parse patient info JSON if it exists
    $patient_info = null;
    if ($appointment['patient_info']) {
        $patient_info = json_decode($appointment['patient_info'], true);
    }

    // Format the response
    $response = [
        'appointment' => $appointment,
        'payment' => $payment,
        'patient_info' => $patient_info
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
