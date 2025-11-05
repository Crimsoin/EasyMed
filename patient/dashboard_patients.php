<?php
$page_title = "Patient Dashboard";
$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

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
               doc.specialty, doc.consultation_fee
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users du ON doc.user_id = du.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$patientId]);
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
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <p>Manage your healthcare appointments and profile</p>
            
            <!-- Current Date and Time Display -->
            <div class="datetime-display" style="margin-top: 1rem; padding: 1rem; background: rgba(0, 188, 212, 0.1); border-radius: 8px; border-left: 4px solid var(--primary-cyan);">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="font-size: 2rem;">
                        <i class="fas fa-clock" style="color: var(--primary-cyan);"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-cyan);" id="current-date"></div>
                        <div style="font-size: 1rem; color: var(--text-light);" id="current-time"></div>
                    </div>
                </div>
            </div>
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
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card">
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
                                        <div class="fee">
                                            <i class="fas fa-coins"></i>
                                            ₱<?php echo number_format($appointment['consultation_fee'], 2); ?>
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

        <!-- Quick Actions -->
        <div class="content-section">
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="section-content">
                <div class="quick-actions">
                    <a href="book-appointment.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Book Appointment</h3>
                            <p>Schedule a new appointment with one of our doctors</p>
                        </div>
                    </a>
                    
                    <a href="doctors.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="action-content">
                            <h3>Find Doctors</h3>
                            <p>Browse our list of available doctors and specialists</p>
                        </div>
                    </a>
                    
                    <a href="profile.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div class="action-content">
                            <h3>Update Profile</h3>
                            <p>Keep your personal information and preferences up to date</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update current date and time
function updateDateTime() {
    const now = new Date();
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    // Format date
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    dateElement.textContent = now.toLocaleDateString('en-US', options);
    
    // Format time
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    };
    timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
}

// Update immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

<?php require_once '../includes/footer.php'; ?>
