<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css', 'shared-modal.css'];

// Require login as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();
$patient_user_id = $_SESSION['user_id'];

// Get the patient ID for the current user
$patientData = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$patient_user_id]);
$patient_id = $patientData ? $patientData['id'] : 0;

// Fetch list of doctors the patient has completed appointments with
$doctors = $db->fetchAll("
    SELECT DISTINCT d.id, u.first_name, u.last_name, d.specialty 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN appointments a ON a.doctor_id = d.id
    WHERE a.patient_id = ? AND a.status = 'completed'
    ORDER BY u.last_name, u.first_name
", [$patient_id]);

// Fetch current patient's reviews (by patients.id)
$reviews = $db->fetchAll(
    "SELECT r.*, d.id as doctor_id, u.first_name as doctor_first_name, u.last_name as doctor_last_name, d.specialty, a.appointment_date
     FROM reviews r
     LEFT JOIN doctors d ON r.doctor_id = d.id
     LEFT JOIN users u ON d.user_id = u.id
     LEFT JOIN appointments a ON r.appointment_id = a.id
     WHERE r.patient_id = ?
     ORDER BY r.created_at DESC",
    [$patient_id]
);

// Fetch recent completed appointments that need reviews with full details
$unreviewed_appointments = $db->fetchAll("
    SELECT 
        a.id, a.appointment_date, a.appointment_time, a.patient_info, a.status, a.created_at, a.doctor_id,
        u.first_name as dr_first_name, u.last_name as dr_last_name, 
        d.specialty, d.consultation_fee,
        p.amount as payment_amount
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN reviews r ON r.appointment_id = a.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.patient_id = ? AND a.status = 'completed' AND r.id IS NULL
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$patient_id]);

// Calculate fees for unreviewed appointments
foreach ($unreviewed_appointments as &$apt) {
    $p_info = json_decode($apt['patient_info'], true) ?? [];
    $purpose = $p_info['purpose'] ?? 'consultation';
    
    $apt['display_fee'] = $apt['consultation_fee'];
    $apt['fee_label'] = 'Consultation Fee';
    
    if ($purpose === 'laboratory' && !empty($apt['payment_amount'])) {
        $apt['display_fee'] = $apt['payment_amount'];
        $apt['fee_label'] = 'Laboratory Fee';
    }
}
unset($apt);

// Pull flash messages
$success = $_SESSION['review_success'] ?? null;
$errors = $_SESSION['review_errors'] ?? null;
unset($_SESSION['review_success'], $_SESSION['review_errors']);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Feedbacks - EasyMed</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        /* Premium Card Layout for Feedbacks */
        .appointments-container { width: 100%; max-width: none; margin: 0; padding: 24px 0; }
        .appointments-grid { display: flex; flex-direction: column; gap: 16px; }
        .appointment-card {
            background: #fff; border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative; display: flex; flex-direction: row; align-items: center;
        }
        .appointment-card:hover { transform: translateX(5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-color: #3b82f6; }
        .status-indicator { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #10b981; }
        
        .card-header { padding: 24px; display: flex; flex-direction: column; width: 280px; flex-shrink: 0; justify-content: center; }
        .doctor-meta h3 { font-size: 1.15rem; font-weight: 800; color: #1e293b; margin: 0 0 6px 0; }
        .specialty-pill {
            display: inline-block; padding: 4px 12px; background: #eff6ff; color: #3b82f6;
            border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.5px; width: fit-content;
        }
        .appointment-id {
            margin-top: 10px; font-size: 0.7rem; font-weight: 700; color: #94a3b8;
            background: #f8fafc; padding: 4px 10px; border-radius: 6px; width: fit-content;
        }
        
        .card-body {
            padding: 20px 24px; flex-grow: 1; display: flex; align-items: center; gap: 24px;
            border-left: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9;
        }
        .info-grid { display: flex; gap: 24px; margin-bottom: 0; flex-grow: 1; }
        .info-item { flex: 1; display: flex; flex-direction: column; gap: 4px; min-width: 100px; }
        .info-label { font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 0.9rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .info-value i { color: #cbd5e1; font-size: 0.8rem; }
        
        .card-footer { padding: 24px; display: flex; flex-direction: column; width: 220px; flex-shrink: 0; gap: 12px; align-items: stretch; }
        .status-pill {
            padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            text-transform: uppercase; background: #ecfdf5; color: #047857;
        }
        .btn-review-main {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white; border: none; padding: 10px; border-radius: 10px;
            font-weight: 700; font-size: 0.85rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: all 0.3s ease;
        }
        .btn-review-main:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3); color: white; }

        .no-appointments { 
            text-align: center; padding: 60px 40px; color: #64748b; background: #fff;
            border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: flex; flex-direction: column; align-items: center; gap: 15px; border: 1px solid #f1f5f9;
        }
        .no-appointments i { font-size: 3rem; color: #cbd5e1; }

        @media (max-width: 1200px) {
            .appointment-card { flex-direction: column; align-items: stretch; }
            .card-header, .card-footer { width: 100%; }
            .card-body { flex-direction: column; align-items: stretch; border: none; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; }
        }

        /* Premium Modal Overhaul */
        .feedback-modal-content { 
            max-width: 550px !important; 
            border: none !important;
            padding: 0 !important;
            overflow: hidden;
            border-radius: 24px !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        }
        .modal-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            padding: 32px 40px !important;
            color: white !important;
            position: relative;
            border: none !important;
        }
        .modal-header h3 {
            font-size: 1.5rem !important;
            font-weight: 800 !important;
            margin: 0 !important;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white !important;
        }
        .modal-header h3 i { 
            background: rgba(255, 255, 255, 0.2);
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .close-modal {
            color: rgba(255, 255, 255, 0.8) !important;
            font-size: 28px !important;
            transition: all 0.2s ease;
        }
        .close-modal:hover { color: white !important; transform: rotate(90deg); }

        .modal-body { padding: 40px !important; }
        
        .modal-dr-info {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .modal-dr-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 1.2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .rating-widget {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 2px solid #f1f5f9;
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 10px 0 30px 0;
            transition: all 0.3s ease;
        }
        .rating-widget:hover { border-color: #3b82f6; background: white; }
        
        .star-box { 
            font-size: 32px; 
            color: #e2e8f0; 
            cursor: pointer; 
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .star-box.active { color: #fbbf24; transform: scale(1.1); }
        .star-box:hover { transform: scale(1.2); }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 18px;
            border-radius: 16px;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
            display: flex; align-items: center; justify-content: center; gap: 12px;
            transition: all 0.3s ease;
        }
        .btn-submit:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.3);
        }

        .modal-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 30px;
        }
        .modal-checkbox:hover { background: #f1f5f9; }
        .modal-checkbox input { 
            width: 20px; 
            height: 20px; 
            margin: 0;
            accent-color: #3b82f6; 
            cursor: pointer;
        }
        .modal-checkbox span { 
            font-size: 0.95rem; 
            color: #475569; 
            font-weight: 600; 
        }

        /* Existing styles below */
        .reviews-container { width: 100%; max-width: none; margin: 0; padding: 0; }
        .reviews-grid { 
            display: grid; 
            grid-template-columns: 1fr 380px; 
            gap: 40px; 
            align-items: start;
        }
        .reviews-main { display: flex; flex-direction: column; gap: 24px; }
        .reviews-group-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .reviews-group-title i { color: #3b82f6; }
        .review-card { background: white; padding: 28px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .review-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); border-color: #3b82f6; }
        .review-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #cbd5e1; transition: background 0.3s ease; }
        .review-card:hover::before { background: #3b82f6; }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .doctor-meta h4 { margin: 0; color: #1e293b; font-size: 1.15rem; font-weight: 800; }
        .doctor-meta p { margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .rating-display { display: flex; align-items: center; gap: 4px; background: #fef3c7; padding: 6px 12px; border-radius: 10px; color: #b45309; font-weight: 800; font-size: 0.9rem; }
        .rating-display i { color: #fbbf24; }
        .review-content { color: #334155; font-size: 1rem; line-height: 1.6; margin: 0; font-weight: 500; }
        .review-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 0.8rem; font-weight: 600; }
        .review-date { display: flex; align-items: center; gap: 6px; }
        .anon-badge { background: #f1f5f9; padding: 4px 10px; border-radius: 6px; color: #64748b; }
        .msg { padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; }
        .msg-success { background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; }
        .msg-error { background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; }
        .empty-state { text-align: center; padding: 80px 40px; background: white; border-radius: 20px; border: 2px dashed #e2e8f0; color: #94a3b8; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; color: #cbd5e1; }
        .empty-state h3 { color: #1e293b; margin: 0 0 10px 0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #64748b; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid #f1f5f9; border-radius: 12px; font-size: 0.95rem; background: #f8fafc; color: #1e293b; font-weight: 500; font-family: inherit; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #3b82f6; background: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        textarea.form-control { min-height: 120px; resize: none; }
        .checkbox-container { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 10px; cursor: pointer; }
        .checkbox-container input { width: 18px; height: 18px; accent-color: #3b82f6; }
        .checkbox-container label { margin: 0; cursor: pointer; font-size: 0.85rem; color: #475569; font-weight: 600; }

        @media (max-width: 1024px) {
            .reviews-grid { grid-template-columns: 1fr; }
            .sidebar-appointments { position: static; }
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
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="feedbacks.php" class="nav-item active">
                    <i class="fas fa-star"></i> Feedbacks
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </nav>
        </div>

        <div class="patient-content">
            <div class="content-header">
                <h1>My Feedbacks</h1>
                <p>Manage and submit reviews for doctors you've visited.</p>
            </div>

            <div class="reviews-container">
                <?php if ($success): ?>
                    <div class="msg msg-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="msg msg-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
                    </div>
                <?php endif; ?>

                <div class="appointments-container">
                    <?php if (empty($unreviewed_appointments)): ?>
                        <div class="no-appointments">
                            <i class="fas fa-check-circle"></i>
                            <h3>All Caught Up!</h3>
                            <p>You have no recent completed appointments waiting for a review.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-grid">
                            <?php foreach ($unreviewed_appointments as $apt): 
                                $p_info = json_decode($apt['patient_info'], true) ?? [];
                                $purpose = $p_info['purpose'] ?? 'consultation';
                                $reference_number = $p_info['reference_number'] ?? ('APT-' . $apt['id']);
                            ?>
                                <div class="appointment-card">
                                    <div class="status-indicator"></div>
                                    
                                    <div class="card-header">
                                        <div class="doctor-meta">
                                            <h3>Dr. <?= htmlspecialchars($apt['dr_first_name'] . ' ' . $apt['dr_last_name']) ?></h3>
                                            <span class="specialty-pill"><?= htmlspecialchars($apt['specialty']) ?></span>
                                        </div>
                                        <div class="appointment-id">
                                            REF: <?= htmlspecialchars($reference_number) ?>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <span class="info-label">Date</span>
                                                <span class="info-value"><i class="far fa-calendar-alt"></i> <?= date('M j, Y', strtotime($apt['appointment_date'])) ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Time</span>
                                                <span class="info-value"><i class="far fa-clock"></i> <?= date('g:i A', strtotime($apt['appointment_time'])) ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Service Type</span>
                                                <span class="info-value"><i class="fas fa-stethoscope"></i> <?= ucfirst($purpose) ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label"><?= htmlspecialchars($apt['fee_label']) ?></span>
                                                <span class="info-value"><i class="fas fa-tag"></i> ₱<?= number_format($apt['display_fee'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <div class="status-pill">
                                            <i class="fas fa-check-double"></i> COMPLETED
                                        </div>
                                        <button class="btn-review-main" onclick='openFeedbackModal(<?= htmlspecialchars(json_encode([
                                            "apt_id" => $apt['id'],
                                            "dr_id" => $apt['doctor_id'],
                                            "dr_name" => "Dr. " . $apt['dr_first_name'] . " " . $apt['dr_last_name'],
                                            "dr_spec" => $apt['specialty']
                                        ])) ?>)'>
                                            <i class="fas fa-star-half-alt"></i> Review Service
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Review History Section -->
                    <div style="margin-top: 60px;">
                        <div class="reviews-group-title" style="margin-bottom: 24px;">
                            <i class="fas fa-history"></i> Your Review History
                        </div>
                        
                        <?php if (empty($reviews)): ?>
                            <div class="no-appointments" style="padding: 40px; background: white; border: 1px dashed #e2e8f0; box-shadow: none;">
                                <i class="fas fa-comment-slash" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="font-size: 0.95rem; margin-top: 10px;">No review history found. Your submitted reviews will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="reviews-main">
                                <?php foreach ($reviews as $r): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div class="doctor-meta">
                                                <h4>Dr. <?= htmlspecialchars($r['doctor_first_name'] . ' ' . $r['doctor_last_name']) ?></h4>
                                                <p><?= htmlspecialchars($r['specialty'] ?? 'Medical Professional') ?></p>
                                            </div>
                                            <div class="rating-display">
                                                <i class="fas fa-star"></i>
                                                <?= intval($r['rating']) ?>.0
                                            </div>
                                        </div>
                                        <p class="review-content"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                                        <div class="review-footer">
                                            <div class="review-date">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php if ($r['appointment_date']): ?>
                                                    Appt: <?= date('M j, Y', strtotime($r['appointment_date'])) ?>
                                                <?php else: ?>
                                                    Reviewed: <?= date('M j, Y', strtotime($r['created_at'])) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($r['is_anonymous']): ?>
                                                <span class="anon-badge"><i class="fas fa-user-secret"></i> Anonymous Review</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Feedback Submission Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content feedback-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-feather-alt"></i> Share Experience</h3>
                <span class="close-modal" onclick="closeFeedbackModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-dr-info">
                    <div class="modal-dr-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <h4 id="modalDrName" style="margin: 0; color: #1e293b; font-size: 1.15rem; font-weight: 800;"></h4>
                        <p id="modalDrSpec" style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;"></p>
                    </div>
                </div>

                <form action="process_feedback.php" method="post" id="feedbackForm">
                    <input type="hidden" name="appointment_id" id="modalAptId">
                    <input type="hidden" name="doctor_id" id="modalDrId">
                    
                    <div class="form-group">
                        <label>How would you rate your visit?</label>
                        <div class="rating-widget" id="starRating">
                            <i class="fas fa-star star-box" data-rating="1"></i>
                            <i class="fas fa-star star-box" data-rating="2"></i>
                            <i class="fas fa-star star-box" data-rating="3"></i>
                            <i class="fas fa-star star-box" data-rating="4"></i>
                            <i class="fas fa-star star-box" data-rating="5"></i>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="5" required>
                    </div>

                    <div class="form-group">
                        <label>Describe your experience</label>
                        <textarea name="review_text" class="form-control" placeholder="What did you like about the service? Was there anything we could improve? Your feedback helps thousands of other patients." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="modal-checkbox">
                            <input type="checkbox" name="is_anonymous" value="1">
                            <span>Keep my identity private in this review</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-magic"></i> Submit Review
                    </button>
                </form>
            </div>
        </div>
    </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-box');
            const ratingInput = document.getElementById('ratingInput');
            
            function updateStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }

            stars.forEach(star => {
                star.addEventListener('mouseenter', function() {
                    updateStars(parseInt(this.dataset.rating));
                });

                star.addEventListener('click', function() {
                    const r = parseInt(this.dataset.rating);
                    ratingInput.value = r;
                    updateStars(r);
                });
            });
            
            document.getElementById('starRating').addEventListener('mouseleave', function() {
                updateStars(parseInt(ratingInput.value));
            });
        });

        function openFeedbackModal(data) {
            document.getElementById('modalDrName').textContent = data.dr_name;
            document.getElementById('modalDrSpec').textContent = data.dr_spec;
            document.getElementById('modalAptId').value = data.apt_id;
            document.getElementById('modalDrId').value = data.dr_id;
            
            const modal = document.getElementById('feedbackModal');
            modal.style.display = 'block';
            setTimeout(() => modal.classList.add('show'), 10);
            document.body.style.overflow = 'hidden';
        }

        function closeFeedbackModal() {
            const modal = document.getElementById('feedbackModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('feedbackModal');
            if (event.target == modal) {
                closeFeedbackModal();
            }
        }
    </script>
</body>
</html>
