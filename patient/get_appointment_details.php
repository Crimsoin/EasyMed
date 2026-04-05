<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit();
}

$appointment_id = intval($_GET['id']);
$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

try {
    // First verify that this appointment belongs to the logged-in patient
    $patient = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$user_id]);
    if (!$patient) {
        http_response_code(404);
        echo json_encode(['error' => 'Patient record not found']);
        exit();
    }
    $patient_internal_id = $patient['id'];

    // Get appointment details
    $appointment = $db->fetch("
        SELECT a.*, 
               COALESCE(pu.first_name, a.first_name) as patient_first_name, 
               COALESCE(pu.last_name, a.last_name) as patient_last_name, 
               COALESCE(pu.email, a.email) as patient_email, 
               COALESCE(p.phone, pu.phone, a.phone_number) as patient_phone, 
               pu.profile_image as patient_image,
               COALESCE(p.date_of_birth, pu.date_of_birth) as patient_dob, 
               COALESCE(p.gender, pu.gender) as patient_gender, 
               COALESCE(p.address, pu.address, a.address) as patient_address,
               du.first_name as doctor_first_name, du.last_name as doctor_last_name,
               du.email as doctor_email, du.profile_image as doctor_image,
               d.id as doctor_internal_id, d.specialty, d.license_number, d.consultation_fee, d.phone as doctor_phone
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users du ON d.user_id = du.id
        WHERE a.id = ? AND a.patient_id = ?
    ", [$appointment_id, $patient_internal_id]);

    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found or unauthorized']);
        exit();
    }

    // Parse patient info JSON
    $patient_info = null;
    if ($appointment['patient_info']) {
        $patient_info = json_decode($appointment['patient_info'], true);
    }
    
    // Calculate display fee
    $appointment['display_fee'] = $appointment['consultation_fee'];
    $purpose = $patient_info['purpose'] ?? 'consultation';
    $laboratory_name = $patient_info['laboratory'] ?? '';
    
    if ($purpose === 'laboratory' && !empty($laboratory_name) && !empty($appointment['doctor_internal_id'])) {
        $lab_offer = $db->fetch("
            SELECT lo.price 
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lo.title = ? AND lod.doctor_id = ? AND lo.is_active = 1
        ", [$laboratory_name, $appointment['doctor_internal_id']]);
        
        if ($lab_offer && !empty($lab_offer['price'])) {
            $appointment['display_fee'] = $lab_offer['price'];
        }
    }

    // Get payment info
    $payment = $db->fetch("
        SELECT p.*
        FROM payments p
        WHERE p.appointment_id = ?
        ORDER BY p.created_at DESC
        LIMIT 1
    ", [$appointment_id]);

    if ($payment && !empty($payment['receipt_file'])) {
        $payment['receipt_path'] = 'assets/uploads/payment_receipts/' . $payment['receipt_file'];
    }

    echo json_encode([
        'appointment' => $appointment,
        'payment' => $payment,
        'patient_info' => $patient_info
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
