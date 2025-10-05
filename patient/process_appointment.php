<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/email.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: book-appointment.php');
    exit();
}

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Get form data
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $schedule_day = trim($_POST['schedule_day'] ?? '');
    $schedule_time = trim($_POST['schedule_time'] ?? '');
    $laboratory = trim($_POST['laboratory'] ?? '');
    $patient_id = $_SESSION['user_id'];

    // Validate required fields
    $errors = [];
    
    if ($doctor_id <= 0) {
        $errors[] = 'Please select a valid doctor.';
    }
    
    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required.';
    }
    
    if (empty($schedule_day)) {
        $errors[] = 'Please select a schedule day.';
    }
    
    if (empty($schedule_time)) {
        $errors[] = 'Please select a schedule time.';
    }

    if (!empty($errors)) {
        $_SESSION['appointment_errors'] = $errors;
        $_SESSION['appointment_data'] = $_POST;
        header('Location: book-appointment.php');
        exit();
    }

    // Get database instance
    $db = Database::getInstance();

    // Get the patient record ID (not user ID)
    $patient_record = $db->fetch("SELECT id FROM patients WHERE user_id = ? AND status = 'active'", [$patient_id]);
    if (!$patient_record) {
        error_log("Appointment Error - No patient record found for user ID $patient_id");
        $_SESSION['appointment_errors'] = ['Patient profile not found. Please complete your profile first.'];
        $_SESSION['appointment_data'] = $_POST;
        header('Location: book-appointment.php');
        exit();
    }
    $patient_record_id = $patient_record['id'];

    // Get the doctor record ID (not user ID) and verify doctor exists and is available
    $doctor = $db->fetch("
        SELECT d.id as doctor_record_id, u.id as user_id, u.first_name, u.last_name, 
               d.specialty, d.schedule_days, d.schedule_time_start, d.schedule_time_end, d.consultation_fee
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE u.id = ? AND u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1 AND d.status = 'active'
    ", [$doctor_id]);

    if (!$doctor) {
        error_log("Appointment Error - Doctor ID $doctor_id not found or not available");
        $_SESSION['appointment_errors'] = ['Selected doctor is not available.'];
        $_SESSION['appointment_data'] = $_POST;
        header('Location: book-appointment.php');
        exit();
    }
    
    $doctor_record_id = $doctor['doctor_record_id'];

    // Verify the selected day or date is in doctor's schedule
    $doctor_days = array_map('trim', explode(',', $doctor['schedule_days']));
    // Normalize doctor's days for case-insensitive compare
    $doctor_days_upper = array_map('strtoupper', $doctor_days);

    // If the client sent a full date (YYYY-MM-DD), use it and validate its weekday
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_day)) {
        $selectedDate = DateTime::createFromFormat('Y-m-d', $schedule_day);
        if (!$selectedDate) {
            $_SESSION['appointment_errors'] = ['Selected date is invalid. Please choose a valid date.'];
            $_SESSION['appointment_data'] = $_POST;
            header('Location: book-appointment.php');
            exit();
        }
        $selectedDayName = strtoupper($selectedDate->format('l'));
        if (!in_array($selectedDayName, $doctor_days_upper)) {
            $_SESSION['appointment_errors'] = ['Selected day is not available for this doctor.'];
            $_SESSION['appointment_data'] = $_POST;
            header('Location: book-appointment.php');
            exit();
        }
        // Use the selected date directly
        $appointment_date = $selectedDate->format('Y-m-d');
    } else {
        // Otherwise assume a weekday name was submitted (backwards-compatibility)
        $found = false;
        foreach ($doctor_days as $d) {
            if (strcasecmp($d, $schedule_day) === 0) { $found = true; break; }
        }
        if (!$found) {
            $_SESSION['appointment_errors'] = ['Selected day is not available for this doctor.'];
            $_SESSION['appointment_data'] = $_POST;
            header('Location: book-appointment.php');
            exit();
        }
        // Create appointment date (next occurrence of the selected day)
        $appointment_date = getNextDateForDay($schedule_day);
    }

    // Check for existing appointment conflicts using doctor record ID
    $existing = $db->fetch("
        SELECT id FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'
    ", [$doctor_record_id, $appointment_date, $schedule_time]);

    if ($existing) {
        $_SESSION['appointment_errors'] = ['This time slot is already booked. Please select a different time.'];
        $_SESSION['appointment_data'] = $_POST;
        header('Location: book-appointment.php');
        exit();
    }

    // Generate appointment reference number
    $reference_number = 'APT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Prepare patient info JSON
    $patient_info = json_encode([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $email,
        'schedule_day' => $schedule_day,
        'laboratory' => $laboratory,
        'reference_number' => $reference_number
    ]);

    // Insert appointment using the correct patient and doctor record IDs
    $appointment_data = [
        'patient_id' => $patient_record_id,
        'doctor_id' => $doctor_record_id,
        'appointment_date' => $appointment_date,
        'appointment_time' => $schedule_time,
        'reason_for_visit' => $laboratory,
        // New appointments start as pending until approved by doctor/admin
        'status' => 'pending',
        'patient_info' => $patient_info,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $appointment_id = $db->insert('appointments', $appointment_data);

    if ($appointment_id) {
        // Send email notification for new appointment
        try {
            $emailService = new EmailService();
            $doctor_name = "Dr. {$doctor['first_name']} {$doctor['last_name']}";
            $patient_name = "$first_name $last_name";
            
            $appointment_data = [
                'appointment_id' => $appointment_id,
                'patient_name' => $patient_name,
                'doctor_name' => $doctor_name,
                'specialty' => $doctor['specialty'],
                'appointment_date' => formatDate($appointment_date),
                'appointment_time' => formatTime($schedule_time),
                'reason' => $laboratory ?: 'General consultation',
                'fee' => number_format($doctor['consultation_fee'], 2),
                'reference_number' => $reference_number
            ];
            
            // Send appointment confirmation email
            $emailService->sendAppointmentScheduled($email, $patient_name, $appointment_data);
            
        } catch (Exception $e) {
            // Log email error but don't stop the appointment process
            error_log("Email notification error for appointment #$appointment_id: " . $e->getMessage());
        }
        
        // Log activity
        logActivity($patient_id, 'appointment_created', "Created appointment with Dr. {$doctor['first_name']} {$doctor['last_name']}");
        
        // Store payment data in session for payment gateway
        $_SESSION['payment_data'] = [
            'appointment_id' => $appointment_id,
            'doctor_name' => "Dr. {$doctor['first_name']} {$doctor['last_name']}",
            'consultation_fee' => $doctor['consultation_fee'],
            'appointment_date' => $appointment_date,
            'appointment_time' => $schedule_time,
            'reference_number' => $reference_number
        ];
        
        // Redirect to payment gateway
        header('Location: payment-gateway.php');
        exit();
    } else {
        throw new Exception('Failed to create appointment. Please try again.');
    }

} catch (Exception $e) {
    error_log("Appointment creation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['appointment_errors'] = ['An error occurred while booking your appointment. Error: ' . $e->getMessage()];
    $_SESSION['appointment_data'] = $_POST;
    header('Location: book-appointment.php');
    exit();
}

/**
 * Get the next date for a given day of the week
 */
function getNextDateForDay($dayName) {
    $days = [
        'MONDAY' => 1, 'TUESDAY' => 2, 'WEDNESDAY' => 3, 'THURSDAY' => 4, 
        'FRIDAY' => 5, 'SATURDAY' => 6, 'SUNDAY' => 0
    ];
    
    $targetDay = $days[strtoupper($dayName)] ?? 1;
    $today = new DateTime();
    $currentDay = (int) $today->format('w'); // 0 = Sunday, 1 = Monday, etc.
    
    // Calculate days to add
    $daysToAdd = ($targetDay - $currentDay + 7) % 7;
    if ($daysToAdd === 0) {
        $daysToAdd = 7; // If it's the same day, book for next week
    }
    
    $appointmentDate = clone $today;
    $appointmentDate->add(new DateInterval("P{$daysToAdd}D"));
    
    return $appointmentDate->format('Y-m-d');
}
?>
