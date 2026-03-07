<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
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
    
    // Check file upload with detailed error messages
    if (!isset($_FILES['payment_receipt'])) {
        $errors[] = 'Payment receipt is required.';
    } elseif ($_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = $_FILES['payment_receipt']['error'];
        switch ($upload_error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'The uploaded file exceeds the maximum allowed size (2MB). Please use a smaller file.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'The file was only partially uploaded. Please try again.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded. Please select a payment receipt.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = 'Server configuration error: Missing temporary folder. Please contact support.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = 'Server error: Failed to write file to disk. Please contact support.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors[] = 'File upload was stopped by a PHP extension. Please contact support.';
                break;
            default:
                $errors[] = 'An unknown error occurred during file upload. Please try again.';
                break;
        }
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
        SELECT a.*, p.id as patient_id, p.user_id as patient_user_id, 
               d.id as doctor_id, d.consultation_fee, u.first_name, u.last_name,
               JSON_EXTRACT(a.patient_info, '$.purpose') as purpose,
               JSON_EXTRACT(a.patient_info, '$.laboratory') as laboratory
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

    // Determine the correct fee based on appointment purpose
    $fee = $appointment['consultation_fee'];
    $purpose = trim($appointment['purpose'] ?? '', '"');
    $laboratory_name = trim($appointment['laboratory'] ?? '', '"');
    
    // If purpose is laboratory, get the lab offer price
    if ($purpose === 'laboratory' && !empty($laboratory_name)) {
        $lab_offer = $db->fetch("
            SELECT lo.price 
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lo.title = ? AND lod.doctor_id = ?
        ", [$laboratory_name, $appointment['doctor_id']]);
        
        if ($lab_offer && !empty($lab_offer['price'])) {
            $fee = $lab_offer['price'];
        }
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
    
    // Check file size (2MB limit - matches PHP upload_max_filesize)
    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['payment_errors'] = ['File size must be less than 2MB.'];
        header('Location: payment-gateway.php');
        exit();
    }
    
    // Additional validation: check if file was actually uploaded via HTTP POST
    if (!is_uploaded_file($file['tmp_name'])) {
        $_SESSION['payment_errors'] = ['Invalid file upload. Please try again.'];
        header('Location: payment-gateway.php');
        exit();
    }
    
    // Generate unique filename
    $filename = 'payment_' . $appointment_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    // Attempt to move uploaded file with better error handling
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $error_msg = 'Failed to upload payment receipt.';
        
        // Check specific issues
        if (!is_writable($upload_dir)) {
            $error_msg .= ' Upload directory is not writable.';
            error_log("Payment upload error: Directory $upload_dir is not writable");
        } elseif (!file_exists($file['tmp_name'])) {
            $error_msg .= ' Temporary file not found.';
            error_log("Payment upload error: Temporary file {$file['tmp_name']} not found");
        } else {
            error_log("Payment upload error: move_uploaded_file failed for unknown reason. Tmp: {$file['tmp_name']}, Dest: $file_path");
        }
        
        $_SESSION['payment_errors'] = [$error_msg . ' Please try again or contact support.'];
        header('Location: payment-gateway.php');
        exit();
    }

    // Insert payment record
    $payment_data = [
        'appointment_id' => (int)$appointment_id,
        'patient_id' => (int)$appointment['patient_id'],
        'amount' => (float)$fee,
        'payment_method' => 'gcash',
        'gcash_reference' => $gcash_reference,
        'receipt_file' => $filename,
        'payment_notes' => !empty($payment_notes) ? $payment_notes : null,
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
        ], 'id = ?', [$appointment_id]);

        // Log activity
        logActivity($patient_id, 'payment_submitted', "Submitted payment for appointment #{$reference_number}");
        
        // Clear payment session data
        unset($_SESSION['payment_data']);
        unset($_SESSION['payment_errors']);
        
        // Set success message
        $_SESSION['payment_success'] = 'Payment proof submitted successfully! Your payment is now pending verification.';
        
        // Redirect to appointments page
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
