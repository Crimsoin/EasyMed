<?php
$page_title = "Patient Dashboard";
$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css', 'shared-modal.css'];

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();

// Get patient_id from patients table
$patientId = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$_SESSION['user_id']])['id'] ?? 0;

// Get patient's appointments
$appointments = [];
if ($patientId) {
    $appointments = $db->fetchAll("
        SELECT a.*, 
               du.first_name as doctor_first_name, du.last_name as doctor_last_name,
               doc.id as doctor_internal_id, doc.specialty, doc.license_number, doc.consultation_fee,
               p.id as payment_id, p.status as payment_verification_status, 
               p.gcash_reference, p.submitted_at, p.amount as payment_amount,
               p.receipt_file
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users du ON doc.user_id = du.id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$patientId]);

    // Calculate correct fee for each appointment
    foreach ($appointments as &$appointment) {
        $patient_info = json_decode($appointment['patient_info'], true);
        $purpose = $patient_info['purpose'] ?? 'consultation';
        $laboratory_name = $patient_info['laboratory'] ?? '';
        
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
}

// Get appointment statistics for patient
$stats = [
    'total' => $patientId ? $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patientId])['count'] : 0,
    'upcoming' => $patientId ? $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= date('now') AND status IN ('scheduled', 'confirmed')", [$patientId])['count'] : 0,
    'completed' => $patientId ? $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'", [$patientId])['count'] : 0,
    'pending' => $patientId ? $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'scheduled'", [$patientId])['count'] : 0
];

require_once '../includes/header.php';
?>

<div class="patient-container">
    <div class="patient-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user"></i> Patient Portal</h3>
            <p style="margin: 0.5rem 0 0 0; color: #ffffffff; font-size: 0.9rem; font-weight: 500;">
                <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
            </p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_patients.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="book-appointment.php" class="nav-item">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </a>
            <a href="appointments.php" class="nav-item">
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
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <p>Manage your healthcare appointments and profile</p>
            
            <!-- Premium Real-time Clock Dashboard -->
            <div class="datetime-display" style="
                margin: 1.5rem 0; 
                padding: 1.5rem 2rem; 
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.03) 100%); 
                border-radius: 16px; 
                border: 1px solid rgba(37, 99, 235, 0.15);
                box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.05);
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: relative;
                overflow: hidden;">
                
                <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                    <div style="
                        width: 60px; 
                        height: 60px; 
                        background: white; 
                        border-radius: 14px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.1);
                        color: var(--primary-cyan);
                        font-size: 1.75rem;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.02em;" id="current-date"></div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                padding: 4px 10px;
                                background: #dcfce7;
                                color: #166534;
                                border-radius: 20px;
                                font-size: 0.7rem;
                                font-weight: 800;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;">
                                <span style="width: 6px; height: 6px; background: #166534; border-radius: 50%; display: inline-block; animation: pulse 2s infinite;"></span>
                                Live System Time
                            </span>
                            <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary-cyan); font-family: 'JetBrains Mono', 'Courier New', monospace;" id="current-time"></div>
                        </div>
                    </div>
                </div>

                <!-- Abstract Decorative Element -->
                <div style="position: absolute; right: -20px; top: -20px; opacity: 0.03; font-size: 8rem; pointer-events: none;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>

            <style>
            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.5); opacity: 0.5; }
                100% { transform: scale(1); opacity: 1; }
            }
            #current-time { transition: all 0.2s ease; }
            </style>
        </div>

        <!-- Quick Stats -->
        <div class="content-section">
            <div class="section-header">
                <h2>Your Appointment Overview</h2>
                <a href="book-appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book New Appointment
                </a>
            </div>
            <div class="section-content stats-content">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['upcoming']; ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient History -->
        <div class="content-section">
            <div class="section-header">
                <h2>Patient History</h2>
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> View All
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No appointments yet</h3>
                        <p>Book your first appointment to get started!</p>
                        <a href="book-appointment.php" class="btn btn-primary">Book Appointment</a>
                    </div>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($appointments as $appointment): 
                            $p_info = json_decode($appointment['patient_info'], true) ?? [];
                            $purpose = $p_info['purpose'] ?? 'consultation';
                            $laboratory = $p_info['laboratory'] ?? '';
                        ?>
                            <div class="appointment-card clickable" onclick='showAppointmentDetails(<?php 
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
                                    "email" => $p_info['email'] ?? ($_SESSION['email'] ?? 'N/A'),
                                    "phone" => $p_info['phone'] ?? ($_SESSION['phone'] ?? 'N/A'),
                                    "address" => $p_info['address'] ?? 'N/A',
                                    "gender" => $p_info['gender'] ?? ($_SESSION['gender'] ?? 'N/A'),
                                    "age" => $p_info['age'] ?? 'N/A',
                                    "reason" => $appointment['reason_for_visit'],
                                    "notes" => $appointment['notes'],
                                    "payment" => [
                                        "amount" => number_format($appointment['payment_amount'] ?? $appointment['display_fee'], 2),
                                        "ref" => $appointment['gcash_reference'] ?? 'N/A',
                                        "status" => $appointment['payment_verification_status'] ?? 'pending',
                                        "receipt" => $appointment['receipt_file'] ? ("assets/uploads/payment_receipts/" . $appointment['receipt_file']) : null
                                    ]
                                ]);
                            ?>)'>
                                <div class="appointment-info">
                                    <div class="doctor-info">
                                        <h4>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($appointment['specialty']); ?></p>
                                    </div>
                                    <div class="appointment-details">
                                        <div class="date-time">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?>
                                        </div>
                                        <?php if (!empty($appointment['reason_for_visit'])): ?>
                                        <div class="reason">
                                            <i class="fas fa-clipboard"></i>
                                            <?php echo htmlspecialchars($appointment['reason_for_visit']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($appointment['notes']) && $appointment['status'] === 'completed'): ?>
                                        <div class="reason" style="margin-top: 5px; color: #1e40af; background: #eff6ff; padding: 8px; border-radius: 4px; border-left: 3px solid #3b82f6;">
                                            <i class="fas fa-file-medical"></i> <strong>Findings:</strong><br>
                                            <span style="white-space: pre-wrap; display: block; margin-top: 4px;"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="fee">
                                            <i class="fas fa-coins"></i>
                                            ₱<?php echo number_format($appointment['display_fee'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions removed -->
    </div>
</div>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-medical"></i> Appointment Overview</h3>
            <span class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="modalContent">
            <!-- Content injected by JavaScript -->
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal()">Close</button>
            <button type="button" class="modal-btn modal-btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Details</button>
        </div>
    </div>
</div>

<style>
.appointment-card.clickable {
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
}

.appointment-card.clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: #3b82f6;
}
</style>

<script>
// Update Live Clock
function updateLiveClock() {
    const now = new Date();
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (!dateElement || !timeElement) return;

    // Format date
    dateElement.textContent = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Format time
    timeElement.textContent = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
}

function showAppointmentDetails(data) {
    const modal = document.getElementById('appointmentModal');
    const modalContent = document.getElementById('modalContent');
    
    // Match the admin modal size
    const modalContentContainer = document.querySelector('#appointmentModal .modal-content');
    if (modalContentContainer) {
        modalContentContainer.style.maxWidth = '1000px';
        modalContentContainer.style.width = '95%';
        modalContentContainer.style.maxHeight = '90vh';
        modalContentContainer.style.overflowY = 'auto';
        modalContentContainer.style.borderRadius = '24px';
        modalContentContainer.style.padding = '0';
    }

    // Format name for avatar
    const initials = data.name.split(' ').map(n => n.charAt(0)).join('').toUpperCase();
    
    modalContent.innerHTML = `
        <div class="appointment-details-premium" style="background: #fdfdfd; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif;">
            
            <!-- 1. Patient Hero Banner -->
            <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 32px 40px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 24px;">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.75rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);">
                        ${initials}
                    </div>
                    <div>
                        <h1 style="color: #0f172a; font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.04em;">${data.name}</h1>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                            <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">${data.id_num}</span></span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span class="status-badge status-${data.status.toLowerCase()}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em; background: ${getStatusBadgeBg(data.status)}; color: ${getStatusBadgeColor(data.status)};">${data.status.toUpperCase()}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding: 40px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px;">
                
                <!-- 2. Appointment Schedule Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar-alt" style="color: white; font-size: 0.9rem;"></i> Core Schedule
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Date</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.date}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Time Slot</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.time}</div>
                        </div>
                        <div style="grid-column: span 2;">
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Service Requested</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-stethoscope" style="color: #cbd5e1; font-size: 0.9rem;"></i>
                                ${data.purpose} ${data.laboratory ? `- ${data.laboratory}` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Expert Consultation Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-md" style="color: white; font-size: 0.9rem;"></i> Medical Expert
                    </h3>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <div style="width: 52px; height: 52px; background: #f8fafc; border: 1px solid #e2e8f0; color: #2563eb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            ${data.doctor_initials}
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">${data.doctor}</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">${data.specialty}</div>
                        </div>
                    </div>
                    <div style="padding-top: 16px; border-top: 1px dashed #e2e8f0; display: flex; justify-content: space-between;">
                        <div>
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">License</label>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">${data.license}</span>
                        </div>
                        <div style="text-align: right;">
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Professional Fee</label>
                            <span style="font-size: 1rem; font-weight: 800; color: #0f172a;">₱${data.fee}</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Patient Information Card -->
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-info-circle" style="color: white; font-size: 0.9rem;"></i> Information Details
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Age Group</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${data.age} ${data.age !== 'N/A' ? 'Years' : ''}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.gender}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Contact Number</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${data.phone}</div>
                        </div>
                        <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                            <div style="font-size: 0.9rem; color: #475569;">${data.email}</div>
                        </div>
                    </div>
                </div>

                <!-- 5. Transaction Summary Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt" style="color: white; font-size: 0.9rem;"></i> Payment Summary
                    </h3>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Amount Received</span>
                            <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${data.payment.amount}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                <span class="appointment-status status-${data.payment.status}" style="font-weight: 800; font-size: 0.75rem; color: ${getPaymentStatusColor(data.payment.status)}">${data.payment.status.toUpperCase()}</span>
                            </div>
                            <div style="text-align: right;">
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">GCash Ref</label>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #2563eb; font-family: monospace;">${data.payment.ref}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Proof of Payment (Half Width) -->
                ${data.payment.receipt ? `
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../${data.payment.receipt}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../${data.payment.receipt}', '_blank')">
                    </div>
                </div>
                ` : ''}

                <!-- 7. Observations Card (Full Width) -->
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-medical-alt" style="color: white; font-size: 0.9rem;"></i> Clinical Records
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Reason for Visit</label>
                            <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${data.reason || 'General Consultation'}</div>
                        </div>
                        <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 16px;">
                            <label style="display: block; font-size: 0.75rem; color: #2563eb; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Doctor's Findings</label>
                            <div style="font-size: 0.95rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic;">
                                ${data.notes || '"No records available yet."'}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    `;

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function getStatusBadgeBg(status) {
    status = status.toLowerCase();
    if (status === 'completed') return '#ecfdf5';
    if (status === 'cancelled') return '#fef2f2';
    if (status === 'scheduled' || status === 'ongoing') return '#eff6ff';
    if (status === 'pending') return '#fffbeb';
    return '#f8fafc';
}

function getStatusBadgeColor(status) {
    status = status.toLowerCase();
    if (status === 'completed') return '#047857';
    if (status === 'cancelled') return '#b91c1c';
    if (status === 'scheduled' || status === 'ongoing') return '#1d4ed8';
    if (status === 'pending') return '#b45309';
    return '#64748b';
}

function getPaymentStatusColor(status) {
    status = status.toLowerCase();
    if (status === 'verified') return '#059669';
    if (status === 'pending_verification') return '#0369a1';
    if (status === 'rejected') return '#b91c1c';
    return '#64748b';
}

function closeModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset modal styles
    const modalContentContainer = document.querySelector('#appointmentModal .modal-content');
    if (modalContentContainer) {
        modalContentContainer.style.maxWidth = '';
        modalContentContainer.style.width = '';
        modalContentContainer.style.maxHeight = '';
        modalContentContainer.style.overflowY = '';
        modalContentContainer.style.borderRadius = '';
        modalContentContainer.style.padding = '';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('appointmentModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Update immediately and then every second
updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>

</body>
</html>
