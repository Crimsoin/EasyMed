<?php
$page_title = "View Doctor Profile";
$additional_css = ['admin/sidebar.css', 'admin/view-doctor-profile.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$doctor_id) {
    header('Location: doctors.php');
    exit();
}

// Get doctor information
$doctor = $db->fetch("
    SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image, u.is_active, u.created_at,
           d.id as doctor_id, d.specialty, d.license_number, d.experience_years, 
           d.consultation_fee, d.schedule_days, d.schedule_time_start, d.schedule_time_end, 
           d.is_available, d.biography, d.phone,
           p.date_of_birth, p.gender
    FROM users u 
    JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ? AND u.role = 'doctor'
", [$doctor_id]);

if (!$doctor) {
    header('Location: doctors.php');
    exit();
}

// Get doctor statistics
$stats = [
    'total_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)", [$doctor_id])['count'],
    'completed_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status = 'completed'", [$doctor_id])['count'],
    'pending_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status IN ('scheduled', 'confirmed')", [$doctor_id])['count'],
    'cancelled_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status = 'cancelled'", [$doctor_id])['count']
];

// Get recent appointments
$recent_appointments = $db->fetchAll("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason_for_visit,
           (u.first_name || ' ' || u.last_name) as patient_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
", [$doctor_id]);

// Get average rating
$rating_data = $db->fetch("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
", [$doctor_id]);

$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_reviews = $rating_data['total_reviews'];

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
            <a href="../Patient Management/patients.php" class="nav-item">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="doctors.php" class="nav-item active">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Appointment/appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
            <a href="../Report and Analytics/reports.php" class="nav-item">
                <i class="fas fa-chart-bar"></i> Reports & Analytics
            </a>
            <a href="../Settings/settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <div class="header-left">
                <h1>Doctor Profile</h1>
                <p>View and manage doctor information</p>
            </div>
            <div class="header-actions">
                <a href="doctors.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>
                <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
        </div>

        <!-- Doctor Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if ($doctor['profile_image']): ?>
                    <img src="../../assets/images/profiles/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                         alt="<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h2>
                <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                <div class="profile-badges">
                    <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <span class="availability-badge <?php echo $doctor['is_available'] ? 'available' : 'unavailable'; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <?php echo $doctor['is_available'] ? 'Available' : 'Not Available'; ?>
                    </span>
                    <?php if ($avg_rating > 0): ?>
                        <span class="rating-badge">
                            <i class="fas fa-star"></i>
                            <?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['total_appointments']; ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['completed_appointments']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['pending_appointments']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-cancelled">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['cancelled_appointments']; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="profile-content">
            <div class="content-left">
                <!-- Personal Information -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <span>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span><?php echo htmlspecialchars($doctor['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <span><?php echo htmlspecialchars($doctor['phone'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Gender</label>
                            <span><?php echo $doctor['gender'] ? ucfirst(htmlspecialchars($doctor['gender'])) : 'Not specified'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth</label>
                            <span><?php echo $doctor['date_of_birth'] ? date('F j, Y', strtotime($doctor['date_of_birth'])) : 'Not provided'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Member Since</label>
                            <span><?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-stethoscope"></i> Professional Information</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Specialty</label>
                            <span><?php echo htmlspecialchars($doctor['specialty']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>License Number</label>
                            <span><?php echo htmlspecialchars($doctor['license_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Experience</label>
                            <span><?php echo $doctor['experience_years']; ?> years</span>
                        </div>
                        <div class="info-item">
                            <label>Consultation Fee</label>
                            <span>$<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                        </div>
                        <div class="info-item full-width">
                            <label>Schedule</label>
                            <span>
                                <?php if ($doctor['schedule_days']): ?>
                                    <?php echo htmlspecialchars($doctor['schedule_days']); ?><br>
                                    <?php if ($doctor['schedule_time_start'] && $doctor['schedule_time_end']): ?>
                                        <small class="schedule-time">
                                            <?php echo date('g:i A', strtotime($doctor['schedule_time_start'])); ?> - 
                                            <?php echo date('g:i A', strtotime($doctor['schedule_time_end'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($doctor['biography']): ?>
                            <div class="info-item full-width">
                                <label>Biography</label>
                                <span class="biography"><?php echo nl2br(htmlspecialchars($doctor['biography'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-right">
                <!-- Recent Appointments -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-check"></i> Recent Appointments</h3>
                        <a href="../Appointment/appointments.php?doctor_id=<?php echo $doctor['id']; ?>" class="view-all-link">
                            View All
                        </a>
                    </div>
                    <div class="appointments-list">
                        <?php if (!empty($recent_appointments)): ?>
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-info">
                                        <h4><?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                        <p class="appointment-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                            at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                        <?php if ($appointment['reason_for_visit']): ?>
                                            <p class="appointment-reason">
                                                <?php echo htmlspecialchars($appointment['reason_for_visit']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-appointments">
                                <i class="fas fa-calendar-times"></i>
                                <p>No appointments found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-cogs"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" class="action-btn">
                            <i class="fas fa-edit"></i>
                            Edit Profile
                        </a>
                        <button type="button" class="action-btn" onclick="toggleDoctorStatus(<?php echo $doctor['id']; ?>)">
                            <i class="fas fa-<?php echo $doctor['is_active'] ? 'ban' : 'check'; ?>"></i>
                            <?php echo $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </button>
                        <a href="../Appointment/appointments.php?doctor_id=<?php echo $doctor['id']; ?>" class="action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            View Appointments
                        </a>
                        <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>" class="action-btn">
                            <i class="fas fa-envelope"></i>
                            Send Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Toggle Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Confirm Action</h3>
        <p id="statusModalText"></p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeStatusModal()">Cancel</button>
            <form id="statusForm" method="POST" action="doctors.php" style="display: inline;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="doctor_id" id="doctorIdInput">
                <button type="submit" class="btn-confirm">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDoctorStatus(doctorId) {
    const isActive = <?php echo $doctor['is_active'] ? 'true' : 'false'; ?>;
    const actionText = isActive ? 'deactivate' : 'activate';
    const doctorName = "<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>";
    
    document.getElementById('statusModalText').textContent = 
        `Are you sure you want to ${actionText} Dr. ${doctorName}?`;
    document.getElementById('doctorIdInput').value = doctorId;
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking X
document.querySelector('.close').onclick = function() {
    closeStatusModal();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
