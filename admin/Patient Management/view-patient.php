<?php
$page_title = 'View User';
$additional_css = ['admin/sidebar.css', 'view-patient.css', 'shared-modal.css']; // Include patient management specific CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get user ID
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: patients.php');
    exit;
}

// Get user data with additional information
$user = $db->fetch("
    SELECT u.*, 
           d.specialty, d.is_available,
           p.phone, p.date_of_birth, p.gender
    FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ?", [$userId]);

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: patients.php');
    exit;
}

// Get user statistics
$stats = [];
if ($user['role'] === 'patient') {
    $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$userId])['count'];
    $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'", [$userId])['count'];
    $stats['cancelled_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'cancelled'", [$userId])['count'];
} elseif ($user['role'] === 'doctor') {
    $doctorId = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($doctorId) {
        $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctorId])['count'];
        $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctorId])['count'];
        $stats['pending_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'", [$doctorId])['count'];
    }
}

// Get recent activity (appointments)
$recentActivity = [];
if ($user['role'] === 'patient') {
    // Get patient_id from patients table using user_id
    $patientId = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($patientId) {
        $recentActivity = $db->fetchAll("
            SELECT a.*, 
                   du.first_name as doctor_first_name, du.last_name as doctor_last_name,
                   doc.specialty
            FROM appointments a
            JOIN doctors doc ON a.doctor_id = doc.id
            JOIN users du ON doc.user_id = du.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10", [$patientId]);
    }
} elseif ($user['role'] === 'doctor') {
    $doctorId = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($doctorId) {
        $recentActivity = $db->fetchAll("
            SELECT a.*, 
                   pu.first_name as patient_first_name, pu.last_name as patient_last_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users pu ON p.user_id = pu.id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10", [$doctorId]);
    }
}

require_once '../../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="../Dashboard/dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="patients.php" class="nav-item active">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Feedbacks/feedback_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Feedbacks
            </a>
            <a href="../Settings/settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <!-- Profile Header -->
        <div class="content-header">
            <div class="profile-avatar">
                <?php if (isset($user['avatar']) && $user['avatar']): ?>
                    <img src="../../uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Patient Avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p><?php echo ucfirst($user['role']); ?> Profile</p>
                
                <div class="profile-badges">
                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <i class="fas fa-<?php echo $user['role'] === 'patient' ? 'user' : ($user['role'] === 'doctor' ? 'user-md' : 'user-shield'); ?>"></i>
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <?php if ($user['role'] === 'doctor' && isset($user['is_available'])): ?>
                    <span class="status-badge <?php echo $user['is_available'] ? 'available' : 'unavailable'; ?>">
                        <i class="fas fa-<?php echo $user['is_available'] ? 'calendar-check' : 'calendar-times'; ?>"></i>
                        <?php echo $user['is_available'] ? 'Available' : 'Unavailable'; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="patients.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- User Information Section -->
            <div class="info-section">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> User Information</h2>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>User ID</label>
                        <span><?php echo $user['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Username</label>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>First Name</label>
                        <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Last Name</label>
                        <span><?php echo htmlspecialchars($user['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <span><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></span>
                    </div>
                    <?php if ($user['role'] === 'patient'): ?>
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <span><?php echo $user['date_of_birth'] ? date('M j, Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Gender</label>
                        <span><?php echo $user['gender'] ? ucfirst($user['gender']) : 'Not provided'; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <label>Role</label>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <?php if ($user['role'] === 'doctor' && $user['specialty']): ?>
                    <div class="info-item">
                        <label>Specialty</label>
                        <span><?php echo htmlspecialchars($user['specialty']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Availability</label>
                        <span class="status-badge <?php echo $user['is_available'] ? 'active' : 'inactive'; ?>">
                            <?php echo $user['is_available'] ? 'Available' : 'Unavailable'; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <label>Created</label>
                        <span><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    <?php if ($user['updated_at']): ?>
                    <div class="info-item">
                        <label>Last Updated</label>
                        <span><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Sidebar -->
            <div class="info-section">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                
                <div class="quick-actions">
                    <?php if ($user['role'] === 'patient'): ?>
                    <a href="../Appointment/book-appointment.php?patient_id=<?php echo $user['id']; ?>" class="action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        Book Appointment
                    </a>
                    <?php elseif ($user['role'] === 'doctor'): ?>
                    <a href="../Doctor Management/doctor-schedule.php?id=<?php echo $user['id']; ?>" class="action-btn">
                        <i class="fas fa-calendar"></i>
                        View Schedule
                    </a>
                    <?php endif; ?>
                    <a href="#" class="action-btn" onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User
                    </a>
                    <a href="patients.php" class="action-btn">
                        <i class="fas fa-list"></i>
                        All Patients
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <?php foreach ($stats as $key => $value): ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon-<?php echo str_replace('_', '-', $key); ?>">
                    <i class="fas fa-<?php echo $key === 'total_appointments' ? 'calendar' : ($key === 'completed_appointments' ? 'check' : ($key === 'pending_appointments' ? 'clock' : 'times')); ?>"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $value; ?></h3>
                    <p><?php echo ucwords(str_replace('_', ' ', $key)); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <?php if (!empty($recentActivity)): ?>
        <div class="info-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Consultation History</h2>
                <a href="../Appointment/appointments.php" class="view-all-link">View All</a>
            </div>
            
            <div class="appointments-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="appointment-item clickable" onclick="viewAppointment(<?php echo $activity['id']; ?>)">
                    <div class="appointment-info">
                        <h4>
                            <?php if ($user['role'] === 'patient'): ?>
                                Dr. <?php echo htmlspecialchars($activity['doctor_first_name'] . ' ' . $activity['doctor_last_name']); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($activity['patient_first_name'] . ' ' . $activity['patient_last_name']); ?>
                            <?php endif; ?>
                        </h4>
                        <p class="appointment-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('M j, Y', strtotime($activity['appointment_date'])); ?>
                            <i class="fas fa-clock"></i>
                            <?php echo date('g:i A', strtotime($activity['appointment_time'])); ?>
                        </p>
                        <?php if ($user['role'] === 'patient' && isset($activity['specialty'])): ?>
                        <p class="appointment-reason">
                            <strong>Specialty:</strong> <?php echo htmlspecialchars($activity['specialty']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($activity['reason_for_visit'])): ?>
                        <p class="appointment-reason">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($activity['reason_for_visit']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($activity['notes']) && $activity['status'] === 'completed'): ?>
                        <div class="appointment-reason" style="margin-top: 10px; color: #1e40af; background: #eff6ff; padding: 8px; border-radius: 4px; border-left: 3px solid #3b82f6;">
                            <strong><i class="fas fa-file-medical"></i> Doctor's Findings:</strong><br>
                            <span style="white-space: pre-wrap; display: block; margin-top: 4px;"><?php echo htmlspecialchars($activity['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="appointment-status status-<?php echo $activity['status']; ?>">
                        <?php echo ucfirst($activity['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="info-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Consultation History</h2>
            </div>
            <div class="no-appointments">
                <i class="fas fa-calendar-times"></i>
                <p>No recent appointments found.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3><i class="fas fa-file-medical"></i> Appointment Overview</h3>
            <span class="close" onclick="document.getElementById('appointmentModal').style.display='none'"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="appointmentDetails">
            <!-- Content will be loaded via JavaScript -->
        </div>
    </div>
</div>

<style>
.appointment-item.clickable {
    cursor: pointer;
    transition: all 0.2s ease;
}

.appointment-item.clickable:hover {
    background: #f0f7ff;
    border-left-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}
</style>

<script>
function viewAppointment(id) {
    const modal = document.getElementById('appointmentModal');
    const detailsDiv = document.getElementById('appointmentDetails');
    
    detailsDiv.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading appointment details...</div>';
    modal.style.display = 'block';
    
    fetch(`../Appointment/get_appointment_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                detailsDiv.innerHTML = `<div style="text-align: center; padding: 2rem; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error: ${data.error}</div>`;
                return;
            }
            
            const appointment = data.appointment;
            const payment = data.payment;
            const patientInfo = data.patient_info;
            
            const initials = (appointment.patient_first_name[0] + (appointment.patient_last_name ? appointment.patient_last_name[0] : '')).toUpperCase();
            
            detailsDiv.innerHTML = `
                <div class="appointment-details-premium" style="background: #fdfdfd; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif;">
                    
                    <!-- 1. Patient Hero Banner -->
                    <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 32px 40px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 24px;">
                            <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.75rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);">
                                ${initials}
                            </div>
                            <div>
                                <h1 style="color: #0f172a; font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.04em;">${appointment.patient_first_name} ${appointment.patient_last_name}</h1>
                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                                    <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">#APT-${appointment.id.toString().padStart(5, '0')}</span></span>
                                    <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                                    <span class="appointment-status status-${appointment.status}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">${appointment.status.toUpperCase()}</span>
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
                                    <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${formatDate(appointment.appointment_date)}</div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Time Slot</label>
                                    <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${formatTime(appointment.appointment_time)}</div>
                                </div>
                                <div style="grid-column: span 2;">
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Service Requested</label>
                                    <div style="font-size: 1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-stethoscope" style="color: #cbd5e1; font-size: 0.9rem;"></i>
                                        ${appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || 'General Consultation')}
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
                                    ${appointment.doctor_first_name[0]}${appointment.doctor_last_name[0]}
                                </div>
                                <div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}</div>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">${appointment.specialty}</div>
                                </div>
                            </div>
                            <div style="padding-top: 16px; border-top: 1px dashed #e2e8f0; display: flex; justify-content: space-between;">
                                <div>
                                    <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">License</label>
                                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">${appointment.license_number || 'N/A'}</span>
                                </div>
                                <div style="text-align: right;">
                                    <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Professional Fee</label>
                                    <span style="font-size: 1rem; font-weight: 800; color: #0f172a;">₱${parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2)}</span>
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
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${calculateAge(appointment.patient_dob)} Years</div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${appointment.patient_gender || 'N/A'}</div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Contact Number</label>
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${appointment.patient_phone || 'N/A'}</div>
                                </div>
                                <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                                    <div style="font-size: 0.9rem; color: #475569;">${appointment.patient_email || 'N/A'}</div>
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
                                    <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${payment ? parseFloat(payment.amount).toFixed(2) : '0.00'}</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                    <div>
                                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                        <span class="appointment-status status-${payment ? payment.status : 'pending'}" style="font-weight: 800; font-size: 0.75rem;">${payment ? payment.status.toUpperCase() : 'PENDING'}</span>
                                    </div>
                                    <div style="text-align: right;">
                                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">GCash Ref</label>
                                        <span style="font-size: 0.95rem; font-weight: 700; color: #2563eb; font-family: monospace;">${payment ? payment.gcash_reference : 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Proof of Payment (Half Width) -->
                        ${payment && payment.receipt_path ? `
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                                <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                            </h3>
                            <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                                <img src="../../${payment.receipt_path}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../../${payment.receipt_path}', '_blank')">
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
                                    <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${appointment.reason_for_visit || 'General Consultation'}</div>
                                </div>
                                <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 16px;">
                                    <label style="display: block; font-size: 0.75rem; color: #2563eb; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Doctor's Findings</label>
                                    <div style="font-size: 0.95rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic;">
                                        ${appointment.notes || '"No records available yet."'}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
            detailsDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error loading appointment details.</div>';
        });
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeString) {
    if (!timeString) return 'N/A';
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return 'N/A';
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('appointmentModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

</body>
</html>
