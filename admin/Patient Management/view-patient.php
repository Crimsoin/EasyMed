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
           COALESCE(p.phone, u.phone) as phone, 
           COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth, 
           COALESCE(p.gender, u.gender) as gender
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
                   doc.specialty, doc.consultation_fee,
                   pay.amount as paid_amount
            FROM appointments a
            JOIN doctors doc ON a.doctor_id = doc.id
            JOIN users du ON doc.user_id = du.id
            LEFT JOIN payments pay ON a.id = pay.appointment_id
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
        <div class="info-section history-section">
            <div class="section-header">
                <h2>Patient History</h2>
                <a href="../Dashboard/dashboard.php" class="view-all-btn">
                    <i class="fas fa-list-ul"></i> View All
                </a>
            </div>
            
            <div class="appointments-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="appointment-item clickable" onclick="viewAppointment(<?php echo $activity['id']; ?>)">
                    <div class="appointment-main">
                        <div class="doctor-brief">
                            <h4>Dr. <?php echo htmlspecialchars($activity['doctor_first_name'] . ' ' . $activity['doctor_last_name']); ?></h4>
                            <span class="specialty"><?php echo htmlspecialchars($activity['specialty'] ?? 'Medical Practitioner'); ?></span>
                        </div>
                        
                        <div class="appointment-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo date('F j, Y', strtotime($activity['appointment_date'])) . ' at ' . date('g:i A', strtotime($activity['appointment_time'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clipboard-list"></i>
                                <span><?php echo htmlspecialchars(!empty($activity['illness']) ? $activity['illness'] : ($activity['reason_for_visit'] ?: 'Consultation Only')); ?></span>
                            </div>
                            <div class="meta-item amount">
                                <i class="fas fa-coins"></i>
                                <span>₱<?php echo number_format($activity['paid_amount'] ?: $activity['consultation_fee'] ?: 0, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="appointment-side">
                        <span class="status-badge status-<?php echo strtolower($activity['status']); ?>">
                            <?php echo strtoupper($activity['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="info-section history-section">
            <div class="section-header">
                <h2>Patient History</h2>
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
<?php include_once '../../includes/shared_appointment_details.php'; ?>

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
    fetch(`../Appointment/get_appointment_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }
            
            const appointment = data.appointment;
            const payment = data.payment;
            const patientInfo = data.patient_info;
            
            // Standardize for shared renderer
            const standardizedData = {
                id: appointment.id,
                name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
                status: appointment.status,
                date: formatDateModal(appointment.appointment_date),
                time: formatTimeModal(appointment.appointment_time),
                purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
                doctor: 'Dr. ' + appointment.doctor_first_name + ' ' + appointment.doctor_last_name,
                specialty: appointment.specialty,
                license: appointment.license_number,
                fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
                relationship: appointment.relationship || 'Self',
                dob: formatDateModal(appointment.patient_dob),
                gender: appointment.patient_gender,
                email: appointment.patient_email,
                phone: appointment.patient_phone,
                address: appointment.patient_address,
                reason: appointment.illness || appointment.reason_for_visit,
                notes: appointment.notes,
                payment: payment ? {
                    amount: parseFloat(payment.amount).toFixed(2),
                    status: payment.status,
                    ref: payment.gcash_reference,
                    receipt: payment.receipt_path
                } : null,
                laboratory_image: patientInfo ? patientInfo.laboratory_image : null
            };
            
            showAppointmentOverview(standardizedData, 'admin');
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
        });
}

function closeAptModal() {
    closeBaseModal();
}
</script>

</body>
</html>
