<?php
$page_title = 'View Patient';
$additional_css = ['doctor/sidebar-doctor.css', 'shared-modal.css'];
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();
$doctor_user_id = $_SESSION['user_id'];
$doctor_record = $db->fetch("SELECT id, specialty FROM doctors WHERE user_id = ?", [$doctor_user_id]);
if (!$doctor_record) {
    die("Doctor profile not found.");
}
$doctor_id = $doctor_record['id'];

// Handle appointment status updates
if ($_POST && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    $patient_id_param = (int)($_GET['id'] ?? 0);
    
    // Get appointment details
    $appointment_details = $db->fetch("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ?", [$appointment_id, $doctor_id]);
    
    if ($appointment_details) {
        switch ($action) {
            case 'complete':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', [
                    'status' => 'completed',
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as completed successfully.";
                header("Location: view-patient.php?id=" . $patient_id_param . "&success=" . urlencode($success_message));
                exit();
            case 'no_show':
                $db->update('appointments', ['status' => 'no_show'], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as No Show.";
                header("Location: view-patient.php?id=" . $patient_id_param . "&success=" . urlencode($success_message));
                exit();
            case 'update_findings':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', [
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$appointment_id]);
                $success_message = "Appointment findings updated successfully.";
                header("Location: view-patient.php?id=" . $patient_id_param . "&success=" . urlencode($success_message));
                exit();
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Get patient ID
$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) {
    header('Location: patients.php');
    exit;
}

// Ensure doctor's patient connection
$verify_sql = "
    SELECT COUNT(*) as count 
    FROM appointments a
    WHERE a.patient_id = ? AND a.doctor_id = ?
";
$verify_result = $db->fetch($verify_sql, [$patientId, $doctor_id]);

// Get doctor specialty for modals
$doctor_specialty = $doctor_record['specialty'] ?? 'Medical Practitioner';

if ($verify_result['count'] == 0) {
    $_SESSION['error'] = 'Patient not found or you do not have permission to view them.';
    header('Location: patients.php');
    exit;
}

// Get user data
$user = $db->fetch("
    SELECT u.*, 
           COALESCE(p.phone, u.phone) as phone, 
           COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth, 
           COALESCE(p.gender, u.gender) as gender,
           COALESCE(p.address, u.address) as address,
           p.id as patient_record_id,
           p.blood_type, p.allergies, p.medical_history, p.emergency_contact, p.emergency_phone
    FROM users u 
    JOIN patients p ON u.id = p.user_id
    WHERE p.id = ?", [$patientId]);

if (!$user) {
    $_SESSION['error'] = 'Patient not found';
    header('Location: patients.php');
    exit;
}

// Stats
$stats = [];
if ($user['role'] === 'patient' && $doctor_id) {
    $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ?", [$user['patient_record_id'], $doctor_id])['count'];
    $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status = 'completed'", [$user['patient_record_id'], $doctor_id])['count'];
    $stats['cancelled_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status = 'cancelled'", [$user['patient_record_id'], $doctor_id])['count'];
}

// Recent Activity
$recentActivity = [];
if ($user['role'] === 'patient' && $doctor_id) {
    $recentActivity = $db->fetchAll("
        SELECT a.*
        FROM appointments a
        WHERE a.patient_id = ? AND a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10", [$user['patient_record_id'], $doctor_id]);
}

require_once '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
            <p>Dr. <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? 'Doctor')); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_doctor.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="schedule.php" class="nav-item">
                <i class="fas fa-clock"></i> Schedule
            </a>
            <a href="patients.php" class="nav-item active">
                <i class="fas fa-users"></i> My Patients
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        </nav>
    </div>

    <div class="doctor-content">
        <!-- Profile Header -->
        <div class="content-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
            <div style="display: flex; gap: 2rem; align-items: center;">
                <div class="profile-avatar" style="width: 110px; height: 110px; border-radius: 20px; background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2);">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                
                <div class="profile-info">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; color: #0f172a; letter-spacing: -0.025em;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>" style="padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 0.5rem; background: <?php echo $user['is_active'] ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $user['is_active'] ? '#15803d' : '#991b1b'; ?>;">
                            <i class="fas fa-<?php echo $user['is_active'] ? 'circle' : 'times-circle'; ?>" style="font-size: 0.6rem;"></i>
                            <?php echo $user['is_active'] ? 'Active Patient' : 'Inactive'; ?>
                        </span>
                    </div>
                    <div style="display: flex; gap: 1.5rem; color: #64748b; font-weight: 500;">
                        <span><i class="fas fa-id-badge" style="margin-right: 0.5rem; color: #3b82f6;"></i> ID: #<?php echo str_pad($user['patient_id'] ?? $patientId, 4, '0', STR_PAD_LEFT); ?></span>
                        <span><i class="fas fa-calendar-alt" style="margin-right: 0.5rem; color: #3b82f6;"></i> Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="patients.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; border: 1.5px solid #e2e8f0; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; background: white; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 0.875rem;" onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateX(-4px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#475569'; this.style.transform='translateX(0)';">
                    <i class="fas fa-chevron-left"></i> Back to Patients
                </a>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Main Column -->
            <div style="display: flex; flex-direction: column; gap: 2rem; grid-column: 1 / -1;">
                
                <!-- Statistics -->
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 0.5rem;">
                    <?php 
                    $stat_labels = [
                        'total_appointments' => ['label' => 'Total Consultations', 'icon' => 'calendar-check', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
                        'completed_appointments' => ['label' => 'Completed Sessions', 'icon' => 'check-double', 'color' => '#10b981', 'bg' => '#ecfdf5'],
                        'cancelled_appointments' => ['label' => 'Cancelled/No-show', 'icon' => 'times-circle', 'color' => '#ef4444', 'bg' => '#fef2f2']
                    ];
                    foreach ($stats as $key => $value): 
                        $cfg = $stat_labels[$key] ?? ['label' => ucwords(str_replace('_', ' ', $key)), 'icon' => 'info-circle', 'color' => '#64748b', 'bg' => '#f8fafc'];
                    ?>
                    <div class="stat-card" style="background: white; border-radius: 1.25rem; padding: 1.75rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem; border: 1px solid #f1f5f9; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';" >
                        <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: <?php echo $cfg['bg']; ?>; color: <?php echo $cfg['color']; ?>;">
                            <i class="fas fa-<?php echo $cfg['icon']; ?>"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1;"><?php echo $value; ?></h3>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; font-weight: 600; color: #64748b;"><?php echo $cfg['label']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- User Information Section -->
                <div class="info-section" style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #f1f5f9; margin-bottom: 2rem;">
                    <div class="section-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-user-circle" style="color: #3b82f6; margin-right: 0.5rem;"></i> Personal Information</h2>
                    </div>
                    
                    <div class="info-grid" style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 0.5rem; gap: 1rem;">
                        <div class="info-item" style="flex: 1;">
                            <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Email Address</label>
                            <span style="color: #1e293b; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="far fa-envelope" style="color: #6366f1;"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </span>
                        </div>
                        <div class="info-item" style="flex: 1;">
                            <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Phone Number</label>
                            <span style="color: #1e293b; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="fas fa-phone" style="color: #10b981;"></i>
                                <?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?>
                            </span>
                        </div>
                        <div class="info-item" style="flex: 1;">
                            <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Date of Birth</label>
                            <span style="color: #1e293b; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="far fa-calendar-alt" style="color: #f59e0b;"></i>
                                <?php echo $user['date_of_birth'] ? date('M j, Y', strtotime($user['date_of_birth'])) : 'N/A'; ?>
                            </span>
                        </div>
                        <div class="info-item" style="flex: 1;">
                            <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Gender</label>
                            <span style="color: #1e293b; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="fas fa-venus-mars" style="color: #ec4899;"></i>
                                <?php echo $user['gender'] ? ucfirst($user['gender']) : 'N/A'; ?>
                            </span>
                        </div>
                        <div class="info-item" style="flex: 1.5;">
                            <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Home Address</label>
                            <span style="color: #1e293b; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="fas fa-map-marker-alt" style="color: #64748b;"></i>
                                <?php echo htmlspecialchars($user['address'] ?: 'No address registered'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="info-section" style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div class="section-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-history" style="color: #3b82f6; margin-right: 0.5rem;"></i> Appointment History</h2>
                        <a href="appointments.php?patient_id=<?php echo $user['id']; ?>" class="view-all-link" style="color: #3b82f6; text-decoration: none; font-weight: 500; font-size: 0.875rem;">View All</a>
                    </div>
                    
                    <?php if (!empty($recentActivity)): ?>
                    <div class="appointments-list" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="appointment-item clickable" style="border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; display: flex; justify-content: space-between; align-items: flex-start; transition: all 0.2s; cursor: pointer;" 
                                onclick="showAppointmentDetails(<?php $activity_patient_info = !empty($activity['patient_info']) ? (json_decode($activity['patient_info'], true) ?? []) : []; echo htmlspecialchars(json_encode([
                                'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                                'account_name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                                'date' => formatDate($activity['appointment_date']),
                                'time' => formatTime($activity['appointment_time']),
                                'email' => $user['email'] ?? 'N/A',
                                'phone' => $user['phone'] ?? 'N/A',
                                'address' => $user['address'] ?? 'N/A',
                                'gender' => ucfirst($user['gender'] ?? 'N/A'),
                                'dob' => !empty($user['date_of_birth']) ? formatDate($user['date_of_birth']) : 'N/A',
                                'reason' => $activity['illness'] ?? $activity['reason_for_visit'] ?? 'Consultation',
                                'purpose' => ucfirst($activity['purpose'] ?? 'Consultation'),
                                'relationship' => ucfirst($activity['relationship'] ?? 'Self'),
                                 'status' => ucfirst($activity['status']),
                                 'id' => $activity['id'],
                                 'notes' => $activity['notes'] ?? '',
                                 'can_complete' => in_array(strtolower($activity['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                 'can_no_show' => in_array(strtolower($activity['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                 'can_add_findings' => strtolower($activity['status']) === 'completed',
                                 'doctor_first_name' => $_SESSION['first_name'],
                                 'doctor_last_name' => $_SESSION['last_name'],
                                'specialty' => $doctor_specialty,
                                'payment_status' => $activity['payment_status'] ?? 'PENDING',
                                'payment_amount' => $activity['payment_amount'] ?? 0,
                                'gcash_reference' => $activity['gcash_reference'] ?? 'N/A',
                                'receipt_path' => $activity['payment_receipt_path'] ?? '',
                                                                 'laboratory_image_path' => $activity_patient_info['laboratory_image'] ?? null, 'reschedule_reason' => $activity['reschedule_reason'], 'updated_at' => $activity['updated_at']
                            ]), ENT_QUOTES, 'UTF-8'); ?>)" 
                             onmouseover="this.style.borderColor='#3b82f6'; this.style.backgroundColor='#f8fafc';" 
                             onmouseout="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='transparent';">
                            <div class="appointment-info">
                                <p class="appointment-date" style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.875rem; display: flex; gap: 1rem;">
                                    <span><i class="fas fa-calendar" style="color: #2563eb;"></i> <?php echo date('M j, Y', strtotime($activity['appointment_date'])); ?></span>
                                    <span><i class="fas fa-clock" style="color: #2563eb;"></i> <?php echo date('g:i A', strtotime($activity['appointment_time'])); ?></span>
                                </p>
                                <?php if (!empty($activity['reason_for_visit'])): ?>
                                <p class="appointment-reason" style="margin: 0; color: #0f172a; font-weight: 500;">
                                    <?php echo htmlspecialchars($activity['reason_for_visit']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <span class="appointment-status status-<?php echo $activity['status']; ?>" style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: <?php echo $activity['status'] === 'completed' ? '#dcfce7' : ($activity['status'] === 'pending' ? '#fef08a' : '#f1f5f9'); ?>; color: <?php echo $activity['status'] === 'completed' ? '#166534' : ($activity['status'] === 'pending' ? '#854d0e' : '#475569'); ?>;">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="no-appointments" style="text-align: center; padding: 2rem; color: #94a3b8;">
                        <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p style="margin: 0;">No past appointments found with you.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<?php include_once '../includes/shared_appointment_details.php'; ?>

<div id="findingsModal" class="modal" style="display: none; z-index: 10001;">
    <div class="modal-content" style="max-width: 600px; width: 90%; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-clipboard-check"></i> Final Findings</h3>
            <span class="close-modal" onclick="closeFindingsModal()" style="cursor: pointer; opacity: 0.8; transition: opacity 0.2s;"><i class="fas fa-times"></i></span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding: 30px; background: white;">
                <input type="hidden" name="action" value="update_findings">
                <input type="hidden" name="appointment_id" id="findingsAptId">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.05em;">Doctor's Notes & Findings</label>
                    <textarea name="notes" id="findingsNotesArea" style="width: 100%; height: 200px; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; resize: none; focus: border-color #2563eb; outline: none; transition: border-color 0.2s;" placeholder="Enter patient diagnosis, prescriptions, or summary here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 30px; background: #f8fafc; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeFindingsModal()" style="padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary" style="padding: 10px 25px; border-radius: 10px; border: none; background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; font-weight: 700; cursor: pointer; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);">Save Findings</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAppointmentDetails(data) {
    showAppointmentOverview(data, 'doctor');
}

function openFindingsModal(id, currentNotes, action = 'complete') {
    document.getElementById('findingsAptId').value = id;
    document.getElementById('findingsNotesArea').value = currentNotes;
    
    // Update action and button text
    const form = document.querySelector('#findingsModal form');
    const actionInput = form.querySelector('input[name="action"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const headerTitle = document.querySelector('#findingsModal .modal-header h3');
    
    if (actionInput) actionInput.value = action;
    if (action === 'complete') {
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save Findings';
        if (headerTitle) headerTitle.innerHTML = '<i class="fas fa-clipboard-check"></i> Save Findings';
    } else {
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Findings';
        if (headerTitle) headerTitle.innerHTML = '<i class="fas fa-pen"></i> Update Findings';
    }

    document.getElementById('findingsModal').style.display = 'block';
    document.getElementById('appointmentModal').style.zIndex = '999';
}

function closeFindingsModal() {
    document.getElementById('findingsModal').style.display = 'none';
    document.getElementById('appointmentModal').style.zIndex = '1000';
}

function closeAptModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}
</script>



<style>
/* Add missing modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}
.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 600px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.modal-header {
    background: linear-gradient(135deg, #2563eb, #1e3a8a);
    color: white;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 { margin: 0; }
.close-modal {
    color: rgba(255,255,255,0.8);
    font-size: 1.5rem;
    cursor: pointer;
}
</style>

</body>
</html>
