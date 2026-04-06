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
               p_pay.id as payment_id, p_pay.status as payment_verification_status, 
               p_pay.gcash_reference, p_pay.submitted_at, p_pay.amount as payment_amount,
               p_pay.receipt_file,
               COALESCE(p.phone, pu.phone) as patient_phone,
               COALESCE(p.date_of_birth, pu.date_of_birth) as patient_dob,
               COALESCE(p.gender, pu.gender) as patient_gender,
               COALESCE(p.address, pu.address) as patient_address
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users du ON doc.user_id = du.id
        LEFT JOIN payments p_pay ON a.id = p_pay.appointment_id
        JOIN patients p ON a.patient_id = p.id
        JOIN users pu ON p.user_id = pu.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$patientId]);

    // Calculate correct fee and normalize demographics for each appointment
    foreach ($appointments as &$appointment) {
        $p_info = json_decode($appointment['patient_info'], true) ?? [];
        $purpose = $p_info['purpose'] ?? 'consultation';
        $laboratory_name = $p_info['laboratory'] ?? '';
        
        // Final normalization logic (similar to doctor's dashboard)
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
                                    "email" => $appointment['patient_email'] ?? $p_info['email'] ?? ($_SESSION['email'] ?? 'N/A'),
                                    "phone" => $appointment['patient_phone'] ?? $p_info['phone'] ?? ($_SESSION['phone'] ?? 'N/A'),
                                    "address" => $appointment['patient_address'] ?? $p_info['address'] ?? 'N/A',
                                    "gender" => !empty($appointment['patient_gender']) ? ucfirst($appointment['patient_gender']) : ($p_info['gender'] ?? 'N/A'),
                                    "dob" => !empty($appointment['patient_dob']) ? formatDate($appointment['patient_dob']) : ($p_info['date_of_birth'] ?? 'N/A'),
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
                                        <?php 
                                        $display_reason = !empty($appointment['illness']) ? $appointment['illness'] : ($appointment['reason_for_visit'] ?: 'General Consultation');
                                        if (!empty($display_reason)): 
                                        ?>
                                        <div class="reason">
                                            <i class="fas fa-clipboard"></i>
                                            <?php echo htmlspecialchars($display_reason); ?>
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
<?php include_once '../includes/shared_appointment_details.php'; ?>

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
    showAppointmentOverview(data, 'patient');
}

function closeModal() {
    closeBaseModal();
}

// Update immediately and then every second
updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>

</body>
</html>
