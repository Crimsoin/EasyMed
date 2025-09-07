<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payment-gateway.php');
    exit();
}

try {
    // Get form data
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $reference_number = trim($_POST['reference_number'] ?? '');
    $gcash_reference = trim($_POST['gcash_reference'] ?? '');
    $payment_notes = trim($_POST['payment_notes'] ?? '');
    $patient_id = $_SESSION['user_id'];

    // Validate required fields
    $errors = [];
    
    if ($appointment_id <= 0) {
        $errors[] = 'Invalid appointment ID.';
    }
    
    if (empty($reference_number)) {
        $errors[] = 'Appointment reference number is required.';
    }
    
    if (empty($gcash_reference)) {
        $errors[] = 'GCash reference number is required.';
    }
    
    if (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Payment receipt is required.';
    }

    if (!empty($errors)) {
        $_SESSION['payment_errors'] = $errors;
        header('Location: payment-gateway.php');
        exit();
    }

    // Get database instance
    $db = Database::getInstance();

    // Verify appointment belongs to current patient
    $appointment = $db->fetch("
        SELECT a.*, p.user_id as patient_user_id, 
               d.consultation_fee, u.first_name, u.last_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE a.id = ? AND p.user_id = ?
    ", [$appointment_id, $patient_id]);

    if (!$appointment) {
        $_SESSION['payment_errors'] = ['Appointment not found or access denied.'];
        header('Location: payment-gateway.php');
        exit();
    }

    // Handle file upload
    $upload_dir = '../assets/uploads/payment_receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['payment_receipt'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['payment_errors'] = ['Invalid file type. Only JPG, PNG, and PDF files are allowed.'];
        header('Location: payment-gateway.php');
        exit();
    }
    
    // Check file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['payment_errors'] = ['File size must be less than 5MB.'];
        header('Location: payment-gateway.php');
        exit();
    }
    
    // Generate unique filename
    $filename = 'payment_' . $appointment_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $_SESSION['payment_errors'] = ['Failed to upload payment receipt. Please try again.'];
        header('Location: payment-gateway.php');
        exit();
    }

    // Insert payment record
    $payment_data = [
        'appointment_id' => $appointment_id,
        'patient_id' => $appointment['patient_id'],
        'amount' => $appointment['consultation_fee'],
        'payment_method' => 'gcash',
        'gcash_reference' => $gcash_reference,
        'receipt_file' => $filename,
        'payment_notes' => $payment_notes,
        'status' => 'pending_verification',
        'submitted_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $payment_id = $db->insert('payments', $payment_data);

    if ($payment_id) {
        // Update appointment updated_at timestamp
        $db->update('appointments', [
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $appointment_id]);

        // Log activity
        logActivity($patient_id, 'payment_submitted', "Submitted payment for appointment #{$reference_number}");
        
        // Clear payment session data
        unset($_SESSION['payment_data']);
        unset($_SESSION['payment_errors']);
        
        // Set success message
        $_SESSION['payment_success'] = [
            'message' => 'Payment proof submitted successfully!',
            'reference' => $reference_number,
            'gcash_reference' => $gcash_reference,
            'doctor' => "Dr. {$appointment['first_name']} {$appointment['last_name']}",
            'amount' => $appointment['consultation_fee']
        ];
        
        header('Location: appointments.php');
        exit();
    } else {
        // Delete uploaded file on database error
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        throw new Exception('Failed to process payment. Please try again.');
    }

} catch (Exception $e) {
    error_log("Payment processing error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['payment_errors'] = ['An error occurred while processing your payment. Error: ' . $e->getMessage()];
    header('Location: payment-gateway.php');
    exit();
}
?>
