<?php
$page_title = "Doctor Dashboard";
$additional_css = ['doctor/sidebar-doctor.css', 'doctor/dashboard-doctor.css'];

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$db = Database::getInstance();

// Resolve doctor record id (doctors.id) from logged in user
$doctor_user_id = $_SESSION['user_id'];
$doctor_record = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_user_id]);
if (!$doctor_record) {
    die("Doctor profile not found.");
}
$doctor_id = $doctor_record['id'];

// Get doctor's appointments for today
$today_appointments = $db->fetchAll("
    SELECT a.*,
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           p.phone as patient_phone, p.date_of_birth as patient_dob,
           a.patient_info
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND date(a.appointment_date) = date('now')
    ORDER BY a.appointment_time ASC
", [$doctor_id]);

// Get upcoming appointments (next 7 days)
$upcoming_appointments = $db->fetchAll("
    SELECT a.*,
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           p.date_of_birth as patient_dob,
           a.patient_info
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
    AND date(a.appointment_date) > date('now')
    AND date(a.appointment_date) <= date('now', '+7 days')
    AND a.status IN ('scheduled', 'confirmed', 'pending', 'rescheduled')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 10
", [$doctor_id]);

// Normalize appointment arrays to include a safe 'reason' key (avoid undefined index warnings)
foreach ($today_appointments as &$appt) {
    $patient_info = [];
    if (!empty($appt['patient_info'])) {
        $decoded = json_decode($appt['patient_info'], true);
        if (is_array($decoded)) $patient_info = $decoded;
    }
    $appt['reason'] = $appt['reason_for_visit'] ?? ($patient_info['laboratory'] ?? '');
    // ensure patient_dob key exists
    if (!isset($appt['patient_dob'])) $appt['patient_dob'] = $patient_info['date_of_birth'] ?? null;
}
unset($appt);

foreach ($upcoming_appointments as &$appt) {
    $patient_info = [];
    if (!empty($appt['patient_info'])) {
        $decoded = json_decode($appt['patient_info'], true);
        if (is_array($decoded)) $patient_info = $decoded;
    }
    $appt['reason'] = $appt['reason_for_visit'] ?? ($patient_info['laboratory'] ?? '');
    if (!isset($appt['patient_dob'])) $appt['patient_dob'] = $patient_info['date_of_birth'] ?? null;
}
unset($appt);

// Get statistics
$stats = [
    'today' => count($today_appointments),
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'", [$doctor_id])['count'],
    'this_week' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND date(appointment_date) > date('now') AND date(appointment_date) <= date('now', '+7 days')", [$doctor_id])['count'],
    'total_patients' => $db->fetch("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?", [$doctor_id])['count']
];

require_once '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
            <p>Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_doctor.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="schedule.php" class="nav-item">
                <i class="fas fa-clock"></i> Schedule
            </a>
            <a href="patients.php" class="nav-item">
                <i class="fas fa-users"></i> My Patients
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        </nav>
    </div>

    <div class="doctor-content">
        <div class="content-header">
            <h1><i class="fas fa-home"></i> Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <p>Here's your practice overview for today</p>
            
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
                <h2>Today's Overview</h2>
            </div>
            <div class="section-content stats-content">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['today']; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['this_week']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="content-section">
            <div class="section-header">
                <h2>Today's Schedule - <?php echo formatDate(date('Y-m-d')); ?></h2>
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-calendar"></i> View All Appointments
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($today_appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-check"></i>
                        <h3>No appointments today</h3>
                        <p>You have a free day! Enjoy your time off.</p>
                    </div>
                <?php else: ?>
                    <div class="schedule-timeline">
                        <?php foreach ($today_appointments as $appointment): ?>
                            <div class="appointment-timeline-item">
                                <div class="timeline-time">
                                    <?php echo formatTime($appointment['appointment_time']); ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="patient-info">
                                        <h4><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                        <?php if ($appointment['patient_phone']): ?>
                                            <p class="contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-reason">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                                    </div>
                                    <div class="appointment-status">
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="content-section">
            <div class="section-header">
                <h2>Upcoming Appointments</h2>
                <small style="color: #666; font-weight: normal;">Next 7 days (excluding today)</small>
            </div>
            <div class="section-content">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No upcoming appointments</h3>
                        <p>Your schedule is clear for the next week.</p>
                        <?php
                        // Debug information (uncomment to troubleshoot)
                        /*
                        <p style="font-size: 12px; color: #999; margin-top: 10px;">
                            Debug: Doctor ID = <?php echo $doctor_id; ?><br>
                            Total appointments for this doctor: <?php echo $stats['total_patients']; ?><br>
                            Today's date: <?php echo date('Y-m-d'); ?><br>
                            Next week: <?php echo date('Y-m-d', strtotime('+7 days')); ?>
                        </p>
                        */
                        ?>
                    </div>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-info">
                                    <div class="patient-info">
                                        <h4><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                    </div>
                                    <div class="appointment-details">
                                        <div class="date-time">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?>
                                        </div>
                                        <div class="reason">
                                            <i class="fas fa-clipboard"></i>
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
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
                    <a href="appointments.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="action-content">
                            <h3>Manage Appointments</h3>
                            <p>View and manage your patient appointments</p>
                        </div>
                    </a>
                    
                    <a href="schedule.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="action-content">
                            <h3>Update Schedule</h3>
                            <p>Modify your availability and working hours</p>
                        </div>
                    </a>
                    
                    <a href="patients.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-content">
                            <h3>Patient Records</h3>
                            <p>Access and manage your patient information</p>
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
