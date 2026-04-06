<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css', 'shared-modal.css'];

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$db = Database::getInstance();

// Get patient record ID
$patient_record = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$patient_id]);
if (!$patient_record) {
    header('Location: ' . SITE_URL . '/patient/dashboard_patients.php');
    exit();
}

$patient_record_id = $patient_record['id'];

// Get patient's appointments with payment status
$appointments = $db->fetchAll("
    SELECT 
        a.id, a.appointment_date, a.appointment_time, a.reason_for_visit, a.illness, 
        a.status, a.patient_info, a.notes, a.reschedule_reason, a.created_at, a.updated_at,
        u.first_name as doctor_first_name, u.last_name as doctor_last_name,
        d.id as doctor_internal_id, d.specialty, d.license_number, d.consultation_fee,
        p_pay.id as payment_id, p_pay.status as payment_verification_status, 
        p_pay.gcash_reference, p_pay.submitted_at, p_pay.amount as payment_amount,
        p_pay.receipt_file,
        COALESCE(p.phone, pu.phone) as patient_phone,
        COALESCE(p.date_of_birth, pu.date_of_birth) as patient_dob,
        COALESCE(p.gender, pu.gender) as patient_gender,
        COALESCE(p.address, pu.address) as patient_address,
        pu.email as patient_email
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN payments p_pay ON a.id = p_pay.appointment_id
    JOIN patients p ON a.patient_id = p.id
    JOIN users pu ON p.user_id = pu.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$patient_record_id]);

// Calculate correct fee and normalize demographics for each appointment
foreach ($appointments as &$appointment) {
    $p_info = json_decode($appointment['patient_info'], true) ?? [];
    $purpose = $p_info['purpose'] ?? 'consultation';
    $laboratory_name = $p_info['laboratory'] ?? '';
    
    // Final normalization logic
    $appointment['patient_dob'] = (isset($p_info['date_of_birth']) && $p_info['date_of_birth'] !== '') ? $p_info['date_of_birth'] : $appointment['patient_dob'];
    $appointment['patient_gender'] = (isset($p_info['gender']) && $p_info['gender'] !== '') ? $p_info['gender'] : $appointment['patient_gender'];
    $appointment['patient_phone'] = (isset($p_info['phone']) && $p_info['phone'] !== '') ? $p_info['phone'] : $appointment['patient_phone'];
    $appointment['patient_address'] = (isset($p_info['address']) && $p_info['address'] !== '') ? $p_info['address'] : $appointment['patient_address'];

    // Default to consultation fee
    $appointment['display_fee'] = $appointment['consultation_fee'];
    $appointment['fee_label'] = 'Consultation Fee';
    
    // If laboratory and payment amount exists, use that
    if ($purpose === 'laboratory' && !empty($appointment['payment_amount'])) {
        $appointment['display_fee'] = $appointment['payment_amount'];
        $appointment['fee_label'] = 'Laboratory Fee';
    }
    // Otherwise, if laboratory, try to fetch from lab_offers table
    elseif ($purpose === 'laboratory' && !empty($laboratory_name)) {
        $lab_offer = $db->fetch("
            SELECT lo.price 
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lo.title = ? AND lod.doctor_id = ? AND lo.is_active = 1
        ", [$laboratory_name, $appointment['doctor_internal_id']]);
        
        if ($lab_offer && !empty($lab_offer['price'])) {
            $appointment['display_fee'] = $lab_offer['price'];
            $appointment['fee_label'] = 'Laboratory Fee';
        }
    }
}
unset($appointment); // Break reference

// Get success message if redirected from booking or payment
$success_message = $_SESSION['appointment_success'] ?? $_SESSION['payment_success'] ?? null;
unset($_SESSION['appointment_success']);
unset($_SESSION['payment_success']);

// Get error messages if any
$error_messages = $_SESSION['appointment_errors'] ?? null;
unset($_SESSION['appointment_errors']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - EasyMed</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        /* Container: match the page content/header width and alignment */
        .appointments-container {
            /* occupy full width of the content column so cards align with .content-header */
            width: 100%;
            max-width: none;
            margin: 0; /* don't center separately from the page layout */
            padding: 24px 0; /* keep vertical spacing, horizontal spacing follows page layout */
        }

        /* Messages */
        .success-message {
            background: #ecfdf5;
            color: #065f46;
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            border: 1px solid #a7f3d0;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.05);
        }
        .success-message i {
            font-size: 1.5rem;
            color: #10b981;
        }
        .error-message {
            background: #fef2f2;
            color: #991b1b;
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            border: 1px solid #fecaca;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.05);
        }
        .error-message h4 { margin-top: 0; }

        /* Grid changed to List */
        .appointments-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Card changed to row */
        .appointment-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            display: flex;
            flex-direction: row;
            align-items: center;
        }

        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #3b82f6;
        }

        /* Status-based Left Border Indicator */
        .status-indicator {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }
        .indicator-pending { background: #f59e0b; }
        .indicator-scheduled, .indicator-ongoing { background: #3b82f6; }
        .indicator-completed { background: #10b981; }
        .indicator-cancelled, .indicator-no_show { background: #ef4444; }
        .indicator-rescheduled { background: #8b5cf6; }

        .card-header {
            padding: 24px;
            display: flex;
            flex-direction: column;
            width: 280px;
            flex-shrink: 0;
            border-bottom: none;
            justify-content: center;
        }

        .doctor-meta h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 6px 0;
        }

        .specialty-pill {
            display: inline-block;
            padding: 4px 12px;
            background: #eff6ff;
            color: #3b82f6;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
        }

        .appointment-id {
            margin-top: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 6px;
            width: fit-content;
        }

        .card-body {
            padding: 20px 24px;
            flex-grow: 1;
            display: flex;
            align-items: center;
            gap: 24px;
            border-left: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
        }

        .info-grid {
            display: flex;
            gap: 24px;
            margin-bottom: 0;
            flex-grow: 1;
        }

        .info-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 100px;
        }

        .info-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .info-value i {
            color: #cbd5e1;
            font-size: 0.8rem;
        }

        /* Payment Strip */
        .payment-strip {
            margin: 0;
            padding: 0;
            background: transparent;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
            border: none;
            min-width: 180px;
        }

        .appointment-details-group {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .laboratory-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: #475569;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }

        .laboratory-info i {
            color: #3b82f6;
        }

        .payment-status-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pay-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
        }

        .pay-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
        }

        .card-footer {
            padding: 24px;
            display: flex;
            flex-direction: column;
            width: 200px;
            flex-shrink: 0;
            gap: 12px;
            background: none;
            align-items: stretch;
        }

        .status-pill {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-transform: uppercase;
        }

        .status-pill-pending { background: #fffbeb; color: #b45309; }
        .status-pill-scheduled { background: #eff6ff; color: #1d4ed8; }
        .status-pill-completed { background: #ecfdf5; color: #047857; }
        .status-pill-cancelled { background: #fef2f2; color: #b91c1c; }

        .details-btn {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 700;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .details-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        /* No appointments */
        .no-appointments { 
            text-align: center; 
            padding: 80px 40px; 
            color: #64748b;
            background: #fff;
            border-radius: 24px;
            margin: 40px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .no-appointments-icon {
            width: 100px;
            height: 100px;
            background: #f1f5f9;
            color: #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .no-appointments h3 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }
        .no-appointments p {
            margin: 0;
            font-size: 1.1rem;
            max-width: 400px;
        }

        /* Button */
        .btn-book-new { 
            display: inline-flex; 
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
            color: #fff; 
            padding: 16px 32px; 
            text-decoration: none; 
            border-radius: 16px; 
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
        }
        .btn-book-new:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.3);
            color: #fff;
        }
        
        /* Payment action buttons */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-cyan), var(--dark-cyan));
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-cyan), var(--primary-cyan));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        /* Payment status colors */
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --error: #dc3545;
            --info: #17a2b8;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .appointment-card {
                flex-direction: column;
                align-items: stretch;
            }
            .card-header, .card-footer {
                width: 100%;
            }
            .card-body {
                flex-direction: column;
                align-items: stretch;
                border-left: none;
                border-right: none;
                border-top: 1px solid #f1f5f9;
                border-bottom: 1px solid #f1f5f9;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .card-footer {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            .status-pill, .details-btn {
                width: auto;
            }
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .card-footer {
                flex-direction: column;
            }
            .status-pill, .details-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/header.php'; ?>

    <div class="patient-container">
        <div class="patient-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-user"></i> Patient Portal</h3>
                <p style="margin: 0.5rem 0 0 0; color: #ffffffff; font-size: 0.9rem; font-weight: 500;">
                    <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
                </p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_patients.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="book-appointment.php" class="nav-item">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="appointments.php" class="nav-item active">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="feedbacks.php" class="nav-item">
                    <i class="fas fa-star"></i> Feedbacks
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </nav>
        </div>

        <div class="patient-content">
            <div class="content-header">
                <h1>My Appointments</h1>
                <p>Manage your upcoming and past appointments</p>
            </div>
            <div class="appointments-container">
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php if (is_array($success_message)): ?>
                    <h4><?= htmlspecialchars($success_message['message']) ?></h4>
                    <p><strong>Reference:</strong> <?= htmlspecialchars($success_message['reference']) ?></p>
                    <p><strong>Doctor:</strong> <?= htmlspecialchars($success_message['doctor']) ?></p>
                    <p><strong>Date:</strong> <?= htmlspecialchars($success_message['date']) ?></p>
                    <p><strong>Time:</strong> <?= htmlspecialchars($success_message['time']) ?></p>
                    <p><strong>Day:</strong> <?= htmlspecialchars($success_message['day']) ?></p>
                <?php else: ?>
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_messages): ?>
            <div class="error-message">
                <h4>Error:</h4>
                <?php foreach ($error_messages as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <div class="no-appointments-icon">
                    <i class="far fa-calendar-times"></i>
                </div>
                <h3>No Appointments Found</h3>
                <p>It looks like you haven't booked any medical appointments yet. Ready to take care of your health?</p>
                <a href="book-appointment.php" class="btn-book-new">
                    <i class="fas fa-plus"></i> Book Your First Appointment
                </a>
            </div>
        <?php else: ?>
            <div class="appointments-grid">
                <?php foreach ($appointments as $appointment): 
                    $p_info = json_decode($appointment['patient_info'], true) ?? [];
                    $purpose = $p_info['purpose'] ?? 'consultation';
                    $laboratory = $p_info['laboratory'] ?? '';
                    $reference_number = $p_info['reference_number'] ?? ('APT-' . $appointment['id']);
                    $status = strtolower($appointment['status']);
                ?>
                    <div class="appointment-card">
                        <div class="status-indicator indicator-<?= $status ?>"></div>
                        
                        <div class="card-header">
                            <div class="doctor-meta">
                                <h3>Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></h3>
                                <span class="specialty-pill"><?= htmlspecialchars($appointment['specialty']) ?></span>
                            </div>
                            <div class="appointment-id">
                                REF: <?= htmlspecialchars($reference_number) ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="appointment-details-group">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Date</span>
                                        <span class="info-value"><i class="far fa-calendar-alt"></i> <?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Time</span>
                                        <span class="info-value"><i class="far fa-clock"></i> <?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Service Type</span>
                                        <span class="info-value"><i class="fas fa-stethoscope"></i> <?= ucfirst($purpose) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label"><?= htmlspecialchars($appointment['fee_label']) ?></span>
                                        <span class="info-value"><i class="fas fa-tag"></i> ₱<?= number_format($appointment['display_fee'], 2) ?></span>
                                    </div>
                                </div>

                                <?php if ($laboratory): ?>
                                <div class="laboratory-info">
                                    <i class="fas fa-flask"></i>
                                    <span><strong>Laboratory:</strong> <?= htmlspecialchars($laboratory) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="payment-strip">
                                <div class="payment-status-wrapper">
                                    <?php 
                                    $verification_status = $appointment['payment_verification_status'] ?? null;
                                    if (!$verification_status): ?>
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="color: #b45309; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-exclamation-circle"></i> Payment Required
                                            </span>
                                        </div>
                                        <a href="payment-gateway.php?appointment_id=<?= $appointment['id'] ?>" class="pay-btn">
                                            <i class="fas fa-wallet"></i> Pay Now
                                        </a>
                                    <?php elseif ($verification_status === 'pending_verification'): ?>
                                        <span style="color: #0369a1; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-hourglass-half"></i> Pending Verification
                                        </span>
                                    <?php elseif ($verification_status === 'verified'): ?>
                                        <span style="color: #047857; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-check-circle"></i> Paid & Verified
                                        </span>
                                    <?php elseif ($verification_status === 'rejected'): ?>
                                        <span style="color: #b91c1c; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-times-circle"></i> Payment Rejected
                                        </span>
                                        <a href="payment-gateway.php?appointment_id=<?= $appointment['id'] ?>" class="pay-btn">
                                            <i class="fas fa-redo"></i> Retry
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">
                                    Booked: <?= date('M j, Y', strtotime($appointment['created_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="status-pill status-pill-<?= $status ?>">
                                <?php
                                $status_icon = 'clock';
                                if ($status === 'completed') $status_icon = 'check-double';
                                if ($status === 'cancelled') $status_icon = 'times';
                                if ($status === 'scheduled') $status_icon = 'calendar-check';
                                ?>
                                <i class="fas fa-<?= $status_icon ?>"></i>
                                <?= ucfirst($status) ?>
                            </div>
                            
                            <button type="button" class="details-btn" onclick='showAppointmentDetails(<?php 
                                echo json_encode([
                                    "id" => $appointment['id'],
                                    "name" => ($p_info['first_name'] ?? $_SESSION['first_name']) . " " . ($p_info['last_name'] ?? $_SESSION['last_name']),
                                    "id_num" => $p_info['reference_number'] ?? ("APT-" . str_pad($appointment['id'], 5, "0", STR_PAD_LEFT)),
                                    "date" => date('F j, Y', strtotime($appointment['appointment_date'])),
                                    "time" => date('g:i A', strtotime($appointment['appointment_time'])),
                                    "status" => ucfirst($appointment['status']),
                                    "fee" => number_format($appointment['display_fee'], 2),
                                    "purpose" => ucfirst($purpose),
                                    "laboratory" => $laboratory,
                                    "doctor" => "Dr. " . $appointment['doctor_first_name'] . " " . $appointment['doctor_last_name'],
                                    "doctor_initials" => $appointment['doctor_first_name'][0] . $appointment['doctor_last_name'][0],
                                    "specialty" => $appointment['specialty'],
                                    "license" => $appointment['license_number'] ?? 'N/A',
                                    "email" => $appointment['patient_email'] ?? $p_info['email'] ?? ($_SESSION['email'] ?? 'N/A'),
                                    "phone" => $appointment['patient_phone'] ?? $p_info['phone'] ?? ($_SESSION['phone'] ?? 'N/A'),
                                    "address" => $appointment['patient_address'] ?? $p_info['address'] ?? 'N/A',
                                    "gender" => !empty($appointment['patient_gender']) ? ucfirst($appointment['patient_gender']) : ($p_info['gender'] ?? 'N/A'),
                                    "dob" => !empty($appointment['patient_dob']) ? formatDate($appointment['patient_dob']) : ($p_info['dob'] ?? 'N/A'),
                                    "reason" => !empty($appointment['illness']) ? $appointment['illness'] : ($appointment['reason_for_visit'] ?: 'General Consultation'),
                                    "notes" => $appointment['notes'] ?? '',
                                    "laboratory_image" => $p_info['laboratory_image'] ?? null,
                                    "payment" => [
                                        "amount" => number_format($appointment['payment_amount'] ?? $appointment['display_fee'], 2),
                                        "ref" => $appointment['gcash_reference'] ?? 'N/A',
                                        "status" => $appointment['payment_verification_status'] ?? 'pending',
                                        "receipt" => $appointment['receipt_file'] ? ("assets/uploads/payment_receipts/" . $appointment['receipt_file']) : null
                                    ],
                                                                         "reschedule_reason" => $appointment['reschedule_reason'],
                                     "updated_at" => $appointment['updated_at']
                                ]);
                            ?>)'>
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="book-appointment.php" class="btn-book-new">Book Another Appointment</a>
            </div>
        <?php endif; ?>
    </div>

<!-- Appointment Details Modal -->
<?php include_once '../includes/shared_appointment_details.php'; ?>

<script>
function showAppointmentDetails(data) {
    showAppointmentOverview(data, 'patient');
}

function closeModal() {
    closeBaseModal();
}
</script>
</body>
</html>
