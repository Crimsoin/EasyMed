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
                    'notes' => $notes
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
                $db->update('appointments', ['notes' => $notes], 'id = ?', [$appointment_id]);
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
$doctor_record_id = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_id])['id'] ?? 0;

$verify_sql = "
    SELECT COUNT(*) as count 
    FROM appointments a
    WHERE a.patient_id = ? AND a.doctor_id = ?
";
$verify_result = $db->fetch($verify_sql, [$patientId, $doctor_record_id]);

// Get doctor specialty for modals
$doctor_specialty = $db->fetch("SELECT specialty FROM doctors WHERE id = ?", [$doctor_record_id])['specialty'] ?? 'Medical Practitioner';

if ($verify_result['count'] == 0) {
    $_SESSION['error'] = 'Patient not found or you do not have permission to view them.';
    header('Location: patients.php');
    exit;
}

// Get user data
$user = $db->fetch("
    SELECT u.*, 
           p.phone, p.date_of_birth, p.gender, p.id as patient_record_id,
           p.address, p.blood_type, p.allergies, p.medical_history, p.emergency_contact, p.emergency_phone
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
if ($user['role'] === 'patient' && $doctor_record_id) {
    $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ?", [$user['patient_record_id'], $doctor_record_id])['count'];
    $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status = 'completed'", [$user['patient_record_id'], $doctor_record_id])['count'];
    $stats['cancelled_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status = 'cancelled'", [$user['patient_record_id'], $doctor_record_id])['count'];
}

// Recent Activity
$recentActivity = [];
if ($user['role'] === 'patient' && $doctor_record_id) {
    $recentActivity = $db->fetchAll("
        SELECT a.*
        FROM appointments a
        WHERE a.patient_id = ? AND a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10", [$user['patient_record_id'], $doctor_record_id]);
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

    <div class="doctor-content" style="flex: 1; padding: 2rem; background-color: #f8fafc; min-height: 100vh;">
        <!-- Profile Header -->
        <div class="content-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div style="display: flex; gap: 2rem;">
                <div class="profile-avatar" style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                
                <div class="profile-info" style="display: flex; flex-direction: column; justify-content: center;">
                    <h1 style="margin: 0 0 0.5rem 0; font-size: 2rem; color: #1e293b;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p style="margin: 0 0 1rem 0; color: #64748b;"><?php echo ucfirst($user['role']); ?> Profile</p>
                    
                    <div class="profile-badges" style="display: flex; gap: 1rem;">
                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>" style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; background: <?php echo $user['is_active'] ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $user['is_active'] ? '#166534' : '#991b1b'; ?>;">
                            <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="patients.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; text-decoration: none; color: #475569; font-weight: 500; background: white; transition: all 0.2s;">
                    <i class="fas fa-arrow-left"></i> Back to Patients
                </a>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Left Column -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- User Information Section -->
                <div class="info-section" style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div class="section-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-user" style="color: #3b82f6; margin-right: 0.5rem;"></i> Patient Information</h2>
                    </div>
                    
                    <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="info-item">
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Email</label>
                            <span style="color: #0f172a;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Phone</label>
                            <span style="color: #0f172a;"><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Date of Birth</label>
                            <span style="color: #0f172a;"><?php echo $user['date_of_birth'] ? date('M j, Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Gender</label>
                            <span style="color: #0f172a;"><?php echo $user['gender'] ? ucfirst($user['gender']) : 'Not provided'; ?></span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Address</label>
                            <span style="color: #0f172a;"><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></span>
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
                                'age' => !empty($user['date_of_birth']) ? calculateAge($user['date_of_birth']) : 'N/A',
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
                                'laboratory_image_path' => $activity_patient_info['laboratory_image'] ?? null
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

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Statistics -->
                <div class="stats-grid" style="display: grid; gap: 1rem;">
                    <?php foreach ($stats as $key => $value): ?>
                    <div class="stat-card" style="background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 1rem;">
                        <div class="stat-icon" style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; 
                            <?php echo $key === 'total_appointments' ? 'background: #eff6ff; color: #3b82f6;' : 
                                     ($key === 'completed_appointments' ? 'background: #f0fdf4; color: #16a34a;' : 'background: #fef2f2; color: #dc2626;'); ?>">
                            <i class="fas fa-<?php echo $key === 'total_appointments' ? 'calendar' : ($key === 'completed_appointments' ? 'check' : 'times'); ?>"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.5rem; color: #0f172a;"><?php echo $value; ?></h3>
                            <p style="margin: 0; font-size: 0.875rem; color: #64748b;"><?php echo ucwords(str_replace('_', ' ', $key)); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Medical Info Summary -->
                <div class="info-section" style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div class="section-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-notes-medical" style="color: #ef4444; margin-right: 0.5rem;"></i> Medical Summary</h2>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Blood Type</label>
                            <span style="color: #0f172a; font-weight: 500;"><?php echo $user['blood_type'] ?: 'Unknown'; ?></span>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Allergies</label>
                            <span style="color: <?php echo $user['allergies'] ? '#ef4444' : '#0f172a'; ?>; font-weight: 500;"><?php echo htmlspecialchars($user['allergies'] ?: 'No known allergies'); ?></span>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Medical History Summary</label>
                            <p style="margin: 0; color: #475569; font-size: 0.875rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($user['medical_history'] ?: 'No history provided')); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="info-section" style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div class="section-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-phone-alt" style="color: #f59e0b; margin-right: 0.5rem;"></i> Emergency Contact</h2>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Name</label>
                            <span style="color: #0f172a; font-weight: 500;"><?php echo htmlspecialchars($user['emergency_contact'] ?: 'Not provided'); ?></span>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Phone</label>
                            <span style="color: #0f172a; font-weight: 500;"><?php echo htmlspecialchars($user['emergency_phone'] ?: 'Not provided'); ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%; max-height: 90vh; overflow-y: auto; border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; padding: 24px 40px; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-file-medical"></i> Appointment Overview
            </h3>
            <span class="close-modal" onclick="closeModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.5rem; cursor: pointer; transition: all 0.2s;"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="modalContent" style="padding: 0; background: #fdfdfd;">
            <!-- Content injected by JavaScript -->
        </div>
        <div class="modal-footer" id="modalFooter" style="background: #f8fafc; border-top: 1px solid #edf2f7; padding: 24px 40px; border-radius: 0 0 20px 20px; display: flex; gap: 12px; align-items: center; justify-content: flex-end;">
            <!-- Buttons injected by JavaScript -->
        </div>
    </div>
</div>

<div id="findingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Final Findings</h3>
            <span class="close-modal" onclick="closeFindingsModal()"><i class="fas fa-times"></i></span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_findings">
                <input type="hidden" name="appointment_id" id="findingsAptId">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-pen"></i> Doctor's Notes & Findings</div>
                    <textarea name="notes" id="findingsNotesArea" class="findings-textarea" placeholder="Enter patient diagnosis, prescriptions, or summary here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeFindingsModal()">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary">Save Findings</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('#status, #date');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Add search functionality with debounce
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
});

function showAppointmentDetails(data) {
    const modal = document.getElementById('appointmentModal');
    const content = document.getElementById('modalContent');
    const footer = document.getElementById('modalFooter');
    
    // Helper for initials
    const initials = data.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    
    content.innerHTML = `
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
                            <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">#APT-${data.id.toString().padStart(5, '0')}</span></span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span class="status-badge status-${data.status.toLowerCase()}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">${data.status.toUpperCase()}</span>
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
                                ${data.purpose}
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
                            ${data.doctor_first_name[0]}${data.doctor_last_name[0]}
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">Dr. ${data.doctor_first_name} ${data.doctor_last_name}</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">${data.specialty}</div>
                        </div>
                    </div>
                    <div style="padding-top: 16px; border-top: 1px dashed #e2e8f0; display: flex; justify-content: flex-end;">
                        <div style="text-align: right;">
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Portal Status</label>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Verified Doctor</span>
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
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${data.age} Years</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.gender}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Relationship</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.relationship}</div>
                        </div>
                        <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 32px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.email || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Phone Contact</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.phone || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Home Address</label>
                                <div style="font-size: 0.9rem; color: #475569; line-height: 1.5;">${data.address || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                ${data.laboratory_image_path ? `
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-flask" style="color: white; margin-right: 10px;"></i> Laboratory Request
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../${data.laboratory_image_path}" alt="Laboratory Request" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../${data.laboratory_image_path}', '_blank')">
                    </div>
                </div>
                ` : ''}

                <!-- 5. Transaction Summary Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt" style="color: white; font-size: 0.9rem;"></i> Payment Summary
                    </h3>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Amount Recieved</span>
                            <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${parseFloat(data.payment_amount).toFixed(2)}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                <span class="status-badge status-${data.payment_status.toLowerCase()}" style="font-weight: 800; font-size: 0.75rem;">${data.payment_status.toUpperCase()}</span>
                            </div>
                            <div style="text-align: right;">
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">GCash Ref</label>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #2563eb; font-family: monospace;">${data.gcash_reference}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Proof of Payment (Half Width) -->
                ${data.receipt_path ? `
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../${data.receipt_path}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../${data.receipt_path}', '_blank')">
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
                            <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${data.reason}</div>
                        </div>
                        <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 16px;">
                            <label style="display: block; font-size: 0.75rem; color: #2563eb; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Doctor's Findings</label>
                            <div style="font-size: 0.95rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic;">
                                ${data.notes || '"No findings recorded yet."'}
                            </div>
                        </div>
                    </div>
                </div>

                </div>
        </div>
    `;

    // Footer actions
    let footerHtml = `
        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;">Close</button>
        <button type="button" class="modal-btn modal-btn-secondary" onclick="window.print()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;"><i class="fas fa-print"></i> Print</button>
    `;

    if (data.can_complete) {
        footerHtml = `
            <div style="flex: 1; display: flex; gap: 12px;">
                <button type="button" class="modal-btn" onclick='markAsNoShow(${data.id})' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #ef4444; background: #fef2f2; color: #dc2626; transition: all 0.2s;">
                    <i class="fas fa-user-slash"></i> No Show
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick='openFindingsModal(${data.id}, ${JSON.stringify(data.notes || "")}, "complete")' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                    <i class="fas fa-check-circle"></i> Mark as Completed
                </button>
            </div>
            ${footerHtml}
        `;
    } else if (data.can_add_findings) {
        footerHtml = `
            <div style="flex: 1;"></div>
            <button type="button" class="modal-btn modal-btn-primary" onclick='openFindingsModal(${data.id}, ${JSON.stringify(data.notes || "")}, "update_findings")' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: all 0.2s;">
                <i class="fas fa-pen"></i> Add/Edit Findings
            </button>
            ${footerHtml}
        `;
    }

    footer.innerHTML = footerHtml;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function openFindingsModal(id, currentNotes, action = 'complete') {
    document.getElementById('findingsAptId').value = id;
    document.getElementById('findingsNotesArea').value = currentNotes;
    
    // Update action and button text
    const form = document.querySelector('#findingsModal form');
    const actionInput = form.querySelector('input[name="action"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const headerTitle = document.querySelector('#findingsModal .modal-header h3');
    
    actionInput.value = action;
    if (action === 'complete') {
        submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-clipboard-check"></i> Save Findings';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-pen"></i> Update Findings';
    }

    document.getElementById('findingsModal').style.display = 'block';
    document.getElementById('appointmentModal').style.zIndex = '999';
}

function markAsNoShow(id) {
    if (confirm('Are you sure you want to mark this appointment as No Show?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'no_show';
        
        const idInput = document.createElement('input');
        idInput.name = 'appointment_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeFindingsModal() {
    document.getElementById('findingsModal').style.display = 'none';
    document.getElementById('appointmentModal').style.zIndex = '1000';
}

function closeModal() {
    const modal = document.getElementById('appointmentModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const aptModal = document.getElementById('appointmentModal');
    const findModal = document.getElementById('findingsModal');
    if (event.target == aptModal) {
        closeModal();
    }
    if (event.target == findModal) {
        closeFindingsModal();
    }
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
