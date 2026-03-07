<?php
$page_title = 'Admin Dashboard';
$page_description = 'EasyMed Admin Dashboard - Manage your clinic system';
$additional_css = ['admin/sidebar.css', 'admin/dashboard.css']; // Include sidebar and dashboard CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

// Check if user is logged in and is admin
$auth = new Auth();
$auth->requireRole('admin');

// Get dashboard statistics
$db = Database::getInstance();

// Count users by role
$adminCount = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1")['count'];
$doctorCount = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 1")['count'];
$patientCount = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND is_active = 1")['count'];
$totalUsers = $adminCount + $doctorCount + $patientCount;

// Get recent users
$recentUsers = $db->fetchAll("SELECT id, username, email, role, first_name, last_name, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Get appointment statistics (simplified for dashboard)
$totalAppointments = $db->fetch("SELECT COUNT(*) as count FROM appointments")['count'];
$pendingAppointments = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")['count'];
$todayAppointments = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE " . db_date_equals('appointment_date'))['count'];

// Dashboard-specific aliases for backward compatibility
$totalPatients = $patientCount;
$totalDoctors = $doctorCount;
$pendingPayments = 0; // Placeholder - implement when payment system is added
$totalRevenue = 0.00; // Placeholder - implement when payment system is added

// Get recent appointments (if table exists)
try {
    $recentAppointments = $db->fetchAll("
        SELECT a.*, 
               pu.first_name as patient_first_name, pu.last_name as patient_last_name,
               du.first_name as doctor_first_name, du.last_name as doctor_last_name,
               d.specialty
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users du ON d.user_id = du.id
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
} catch (Exception $e) {
    $recentAppointments = []; // Table may not exist yet
}

// System logs count (if a system_logs table exists)
$systemLogsCount = 0;
try {
    $systemLogsCount = $db->fetch("SELECT COUNT(*) as count FROM system_logs")['count'];
} catch (Exception $e) {
    // table may not exist or permission denied; default to 0
    $systemLogsCount = 0;
}

// Get date range for reports (default to last 30 days and next 30 days to capture all appointments)
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$report_type = $_GET['report_type'] ?? 'overview';

// Validate dates
if (!isValidDate($start_date) || !isValidDate($end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d', strtotime('+30 days'));
}

// Ensure start date is not after end date
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Generate key performance metrics
$stats = [];

// Core Appointment Statistics
$stats['appointments'] = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?", [$start_date, $end_date])['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'", [$start_date, $end_date])['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'cancelled'", [$start_date, $end_date])['count'],
    'scheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'scheduled'", [$start_date, $end_date])['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'pending'", [$start_date, $end_date])['count'],
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'no_show'", [$start_date, $end_date])['count'],
    'rescheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'rescheduled'", [$start_date, $end_date])['count']
];

// Calculate rates
$total_appointments = $stats['appointments']['total'];
$stats['rates'] = [
    'cancellation_rate' => $total_appointments > 0 ? round(($stats['appointments']['cancelled'] / $total_appointments) * 100, 1) : 0,
    'completion_rate' => $total_appointments > 0 ? round(($stats['appointments']['completed'] / $total_appointments) * 100, 1) : 0,
    'no_show_rate' => $total_appointments > 0 ? round(($stats['appointments']['no_show'] / $total_appointments) * 100, 1) : 0
];

$stats['users'] = [
    'total_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'active_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 1")['count'],
    'total_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'],
    'new_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['count']
];

// Daily appointment trends
$daily_trends = $db->fetchAll("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);

// Doctor performance
$doctor_performance = $db->fetchAll("
    SELECT 
        du.first_name || ' ' || du.last_name as doctor_name,
        d.specialty,
        COUNT(a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
        SUM(CASE WHEN a.status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_appointments,
        ROUND((CAST(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS FLOAT) / COUNT(a.id)) * 100, 1) as completion_rate
    FROM doctors d
    JOIN users du ON d.user_id = du.id
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    WHERE du.role = 'doctor' AND du.is_active = 1
    GROUP BY du.first_name, du.last_name, d.specialty
    ORDER BY total_appointments DESC
", [$start_date, $end_date]);

// Hourly statistics
$hourly_stats = $db->fetchAll("
    SELECT 
        CAST(strftime('%H', appointment_time) AS INTEGER) as hour,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancellations
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY CAST(strftime('%H', appointment_time) AS INTEGER)
    ORDER BY hour ASC
", [$start_date, $end_date]);

require_once '../../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="../Dashboard/dashboard.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="../Patient Management/patients.php" class="nav-item">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Appointment/appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
            <a href="../Reviews/review_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Reviews
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
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's your clinic overview.</p>
            
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

        <!-- Key Performance Indicators -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['appointments']['total']; ?></h3>
                    <p>Total Appointments</p>
                    <small><?php echo $stats['rates']['completion_rate']; ?>% completion rate</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['appointments']['completed']; ?></h3>
                    <p>Completed</p>
                    <small><?php echo $stats['appointments']['scheduled']; ?> scheduled</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['appointments']['rescheduled']; ?></h3>
                    <p>Rescheduled</p>
                    <small><?php echo $total_appointments > 0 ? round(($stats['appointments']['rescheduled'] / $total_appointments) * 100, 1) : 0; ?>% reschedule rate</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rates']['no_show_rate']; ?>%</h3>
                    <p>No Show Rate</p>
                    <small><?php echo $stats['appointments']['no_show']; ?> no shows</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rates']['cancellation_rate']; ?>%</h3>
                    <p>Cancellation Rate</p>
                    <small><?php echo $stats['appointments']['cancelled']; ?> cancelled</small>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="content-section">
            <div class="section-header">
                <h2>Recent Users</h2>
                <a href="../Patient Management/patients.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage All Users
                </a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

</body>
</html>
