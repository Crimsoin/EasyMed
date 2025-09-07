<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/login.php');
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
        a.id, a.appointment_date, a.appointment_time, a.reason_for_visit, 
        a.status, a.patient_info, a.notes, a.created_at,
        u.first_name as doctor_first_name, u.last_name as doctor_last_name,
        d.specialty, d.consultation_fee,
        p.id as payment_id, p.status as payment_verification_status, 
        p.gcash_reference, p.submitted_at
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$patient_record_id]);

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
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #c3e6cb;
            box-shadow: 0 4px 12px rgba(21, 87, 36, 0.1);
        }
        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #f5c6cb;
            box-shadow: 0 4px 12px rgba(114, 28, 36, 0.1);
        }

        /* Grid */
        .appointments-grid {
            display: grid;
            gap: 20px;
        }

        /* Card */
        .appointment-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.06);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        /* Body split: left = doctor header, right = details */
        .appointment-body {
            display: flex;
            gap: 28px;
            align-items: flex-start;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .appointment-left {
            flex: 0 0 320px;
            min-width: 240px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
            padding-right: 12px;
            border-right: 1px solid rgba(0,0,0,0.04);
        }
        .appointment-right {
            flex: 1 1 480px;
            min-width: 260px;
            padding-left: 20px;
        }

        .doctor-info {
            font-size: 20px;
            font-weight: 800;
            color: #111;
            margin-bottom: 2px;
            line-height: 1.15;
        }
        .doctor-sub { margin-bottom: 8px; }
        .doctor-sub .doc-specialty {
            font-size: 13px;
            color: #5b6a73;
            background: #f6f8f9;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 600;
            border: 1px solid rgba(0,0,0,0.04);
        }

        /* Status pill */
        .appointment-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-pending { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        .status-confirmed, .status-scheduled { 
            background: linear-gradient(135deg, #d1ecf1 0%, #00cec9 100%); 
            color: #0c5460; 
            border: 1px solid #00cec9;
        }
        .status-completed { 
            background: linear-gradient(135deg, #d4edda 0%, #00b894 100%); 
            color: #155724; 
            border: 1px solid #00b894;
        }
        .status-cancelled { 
            background: linear-gradient(135deg, #f8d7da 0%, #e17055 100%); 
            color: #721c24; 
            border: 1px solid #e17055;
        }

        /* Details: label/value rows (left-aligned for better reading) */
        .detail-list {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 8px 24px;
            align-items: center;
        }
        .detail-row { padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #6b7a81; font-size: 13px; }
        .detail-value { color: #2c3336; font-weight: 600; font-size: 14px; }

        /* No appointments */
        .no-appointments { 
            text-align: center; 
            padding: 80px 20px; 
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 16px;
            margin: 40px 0;
        }
        .no-appointments h3 {
            color: #495057;
            margin-bottom: 12px;
        }

        /* Button */
        .btn-book-new { 
            display: inline-block; 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
            color: #fff; 
            padding: 14px 28px; 
            text-decoration: none; 
            border-radius: 12px; 
            margin-top: 24px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(0, 123, 255, 0.3);
            transition: all 0.3s ease;
        }
        .btn-book-new:hover { 
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
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
            box-shadow: 0 4px 12px rgba(0, 188, 212, 0.3);
        }
        
        /* Payment status colors */
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --error: #dc3545;
            --info: #17a2b8;
        }

        /* Responsive */
        @media (max-width: 800px) {
            .appointments-container {
                padding: 16px;
            }
            .appointment-card {
                padding: 20px;
            }
            .appointment-body {
                gap: 20px;
            }
            .appointment-left, .appointment-right { 
                flex: 1 1 100%; 
            }
            .appointment-right { 
                margin-top: 16px; 
            }
            .detail-row {
                padding: 8px 0;
            }
            .doctor-info {
                font-size: 20px;
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
                <a href="reviews.php" class="nav-item">
                    <i class="fas fa-star"></i> Reviews
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
                <h4><?= htmlspecialchars($success_message['message']) ?></h4>
                <p><strong>Reference:</strong> <?= htmlspecialchars($success_message['reference']) ?></p>
                <p><strong>Doctor:</strong> <?= htmlspecialchars($success_message['doctor']) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($success_message['date']) ?></p>
                <p><strong>Time:</strong> <?= htmlspecialchars($success_message['time']) ?></p>
                <p><strong>Day:</strong> <?= htmlspecialchars($success_message['day']) ?></p>
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
                <h3>No Appointments Found</h3>
                <p>You haven't booked any appointments yet.</p>
                <a href="book-appointment.php" class="btn-book-new">Book Your First Appointment</a>
            </div>
        <?php else: ?>
            <div class="appointments-grid">
                <?php foreach ($appointments as $appointment): ?>
                    <?php 
                    $patient_info = json_decode($appointment['patient_info'], true) ?? [];
                    $reference_number = $patient_info['reference_number'] ?? 'N/A';
                    $laboratory = $patient_info['laboratory'] ?? $appointment['reason_for_visit'];
                    ?>
                    <div class="appointment-card <?= $appointment['status'] ?>">
                        <div class="appointment-body">
                            <div class="appointment-left">
                                <div class="doctor-info">
                                    Dr. <?= htmlspecialchars($appointment['doctor_first_name']) ?> <?= htmlspecialchars($appointment['doctor_last_name']) ?>
                                </div>
                                <div class="doctor-sub">
                                    <span class="doc-specialty"><?= htmlspecialchars($appointment['specialty']) ?></span>
                                </div>
                                <span class="appointment-status status-<?= $appointment['status'] ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span>
                            </div>

                            <div class="appointment-right">
                                    <div class="detail-list">
                                        <div class="detail-row">
                                            <div class="detail-label">Reference:</div>
                                            <div class="detail-value"><?= htmlspecialchars($reference_number) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Date:</div>
                                            <div class="detail-value"><?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Time:</div>
                                            <div class="detail-value"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></div>
                                        </div>
                                        <?php if ($laboratory): ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Laboratory:</div>
                                            <div class="detail-value"><?= htmlspecialchars($laboratory) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Fee:</div>
                                            <div class="detail-value">₱<?= number_format($appointment['consultation_fee'], 2) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Payment:</div>
                                            <div class="detail-value">
                                                <?php 
                                                $verification_status = $appointment['payment_verification_status'] ?? null;
                                                
                                                if (!$verification_status): ?>
                                                    <span style="color: var(--warning); font-weight: bold;">
                                                        <i class="fas fa-clock"></i> Payment Required
                                                    </span>
                                                    <div style="margin-top: 0.5rem;">
                                                        <a href="payment-gateway.php?appointment_id=<?= $appointment['id'] ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-credit-card"></i> Pay Now
                                                        </a>
                                                    </div>
                                                <?php elseif ($verification_status === 'pending_verification'): ?>
                                                    <span style="color: var(--info); font-weight: bold;">
                                                        <i class="fas fa-hourglass-half"></i> Pending Verification
                                                    </span>
                                                    <?php if ($appointment['gcash_reference']): ?>
                                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                                            Ref: <?= htmlspecialchars($appointment['gcash_reference']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($verification_status === 'verified'): ?>
                                                    <span style="color: var(--success); font-weight: bold;">
                                                        <i class="fas fa-check-circle"></i> Paid & Verified
                                                    </span>
                                                    <?php if ($appointment['submitted_at']): ?>
                                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                                            Paid: <?= date('M j, Y', strtotime($appointment['submitted_at'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($verification_status === 'rejected'): ?>
                                                    <span style="color: var(--error); font-weight: bold;">
                                                        <i class="fas fa-times-circle"></i> Payment Rejected
                                                    </span>
                                                    <div style="margin-top: 0.5rem;">
                                                        <a href="payment-gateway.php?appointment_id=<?= $appointment['id'] ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-redo"></i> Retry Payment
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light);">
                                                        <i class="fas fa-question-circle"></i> Status Unknown
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Booked:</div>
                                            <div class="detail-value"><?= date('M j, Y g:i A', strtotime($appointment['created_at'])) ?></div>
                                        </div>
                                        <?php if ($appointment['notes']): ?>
                                        <div class="detail-row">
                                            <div class="detail-label">Notes:</div>
                                            <div class="detail-value"><?= htmlspecialchars($appointment['notes']) ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="book-appointment.php" class="btn-book-new">Book Another Appointment</a>
            </div>
        <?php endif; ?>
    </div>
    
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
