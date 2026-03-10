<?php
$page_title = "View Doctor Profile";
$additional_css = ['admin/sidebar.css', 'admin/view-doctor-profile.css', 'shared-modal.css'];
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

// Get laboratory offers
$lab_offers = $db->fetchAll("
    SELECT lo.* 
    FROM lab_offers lo
    JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
    JOIN doctors d ON lod.doctor_id = d.id
    WHERE d.user_id = ?
    ORDER BY lo.title ASC
", [$doctor_id]);

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
            <a href="../Feedbacks/feedback_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Feedbacks
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
                <div class="specialty-pill"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                <div class="profile-badges">
                    <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $doctor['is_active'] ? 'Active Member' : 'Inactive'; ?>
                    </span>
                    <span class="availability-badge <?php echo $doctor['is_available'] ? 'available' : 'unavailable'; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <?php echo $doctor['is_available'] ? 'Online & Available' : 'Currently Offline'; ?>
                    </span>
                    <?php if ($avg_rating > 0): ?>
                        <span class="rating-badge">
                            <i class="fas fa-star"></i>
                            <?php echo $avg_rating; ?> <small>(<?php echo $total_reviews; ?> reviews)</small>
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
                            <span>₱<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
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
                    </div>
                </div>

                <!-- Consultation History -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-check"></i> Consultation History</h3>
                        <a href="../Appointment/appointments.php?doctor_id=<?php echo $doctor["id"]; ?>" class="view-all-link">
                            View All
                        </a>
                    </div>
                    <div class="appointments-list">
                        <?php if (!empty($recent_appointments)): ?>
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="appointment-item clickable" onclick="viewAppointment(<?php echo $appointment["id"]; ?>)">
                                    <div class="appointment-info">
                                        <h4><?php echo htmlspecialchars($appointment["patient_name"]); ?></h4>
                                        <p class="appointment-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date("M j, Y", strtotime($appointment["appointment_date"])); ?>
                                            at <?php echo date("g:i A", strtotime($appointment["appointment_time"])); ?>
                                        </p>
                                        <?php if ($appointment["reason_for_visit"]): ?>
                                            <p class="appointment-reason">
                                                <?php echo htmlspecialchars($appointment["reason_for_visit"]); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="appointment-status status-<?php echo $appointment["status"]; ?>">
                                        <?php echo ucfirst($appointment["status"]); ?>
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
            </div>

            <div class="content-right">

                <!-- Laboratory Offers -->
                <div class="info-section">
                    <div class="section-header">
                        <h3><i class="fas fa-flask"></i> Laboratory Offers</h3>
                    </div>
                    <div class="lab-offers-list">
                        <?php if (!empty($lab_offers)): ?>
                            <?php foreach ($lab_offers as $offer): ?>
                                <div class="lab-offer-item">
                                    <div class="lab-offer-info">
                                        <h4><?php echo htmlspecialchars($offer['title']); ?></h4>
                                        <?php if (!empty($offer['description'])): ?>
                                            <p class="lab-offer-description">
                                                <?php echo htmlspecialchars($offer['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($offer['price'])): ?>
                                            <p class="lab-offer-price">
                                                <i class="fas fa-coins"></i> ₱<?php echo number_format($offer['price'], 2); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="lab-offer-status status-<?php echo $offer['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $offer['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-appointments">
                                <i class="fas fa-flask"></i>
                                <p>No laboratory offers added yet</p>
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

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%; padding: 0;">
        <div class="modal-header">
            <h3><i class="fas fa-file-medical"></i> Appointment Overview</h3>
            <span class="close-modal" onclick="document.getElementById('appointmentModal').style.display='none'"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="appointmentDetails" style="max-height: calc(90vh - 80px); overflow-y: auto;">
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

.close-modal {
    font-size: 1.5rem;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #1e293b;
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
                    <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 24px 32px; display: flex; align-items: center; justify-content: space-between;">
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

                    <div style="padding: 32px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px;">
                        
                        <!-- 2. Appointment Schedule Card -->
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 20px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 20px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
                        <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 20px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 20px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 16px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                                <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                            </h3>
                            <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                                <img src="../../${payment.receipt_path}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../../${payment.receipt_path}', '_blank')">
                            </div>
                        </div>
                        ` : ''}

                        <!-- 7. Observations Card (Full Width) -->
                        <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -24px -24px 20px -24px; padding: 14px 24px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
    const statusModal = document.getElementById('statusModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
    if (event.target == statusModal) {
        statusModal.style.display = "none";
    }
}
</script>

</body>
</html>
