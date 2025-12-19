<?php
$page_title = "Reports & Analytics";
$additional_css = ['admin/sidebar.css', 'admin/dashboard.css', 'admin/report-and-analytics.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

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
            <a href="../Dashboard/dashboard.php" class="nav-item">
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
            <a href="reports.php" class="nav-item active">
                <i class="fas fa-chart-bar"></i> Reports & Analytics
            </a>
            <a href="../Settings/settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h1>Reports & Analytics</h1>
            <p>Clinic performance insights and data analysis for <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?></p>
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

        <!-- Appointments Overview Card -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Appointments Overview</h2>
                <p>Total appointments with time-based filtering</p>
            </div>
            <div class="appointments-overview-card">
                <div class="filter-controls">
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-period="daily" onclick="filterAppointments('daily')">
                            <i class="fas fa-calendar-day"></i> Daily
                        </button>
                        <button class="filter-btn" data-period="weekly" onclick="filterAppointments('weekly')">
                            <i class="fas fa-calendar-week"></i> Weekly
                        </button>
                        <button class="filter-btn" data-period="monthly" onclick="filterAppointments('monthly')">
                            <i class="fas fa-calendar"></i> Monthly
                        </button>
                    </div>
                    <div class="date-range-info">
                        <span id="current-period-label">Today</span>
                        <small id="current-date-range"><?php echo date('M j, Y'); ?></small>
                    </div>
                </div>
                
                <div class="appointments-stats-grid">
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="total-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now')")['count']; ?>
                        </div>
                        <div class="stat-label">Total Appointments</div>
                        <div class="stat-change" id="appointments-change">
                            <i class="fas fa-arrow-up"></i> <span>+0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="completed-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status = 'completed'")['count']; ?>
                        </div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-change" id="completed-change">
                            <i class="fas fa-arrow-up"></i> <span>+0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="pending-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status IN ('pending', 'scheduled')")['count']; ?>
                        </div>
                        <div class="stat-label">Pending/Scheduled</div>
                        <div class="stat-change" id="pending-change">
                            <i class="fas fa-arrow-up"></i> <span>+0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="cancelled-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status = 'cancelled'")['count']; ?>
                        </div>
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-change" id="cancelled-change">
                            <i class="fas fa-arrow-down"></i> <span>+0%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctor Performance -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-user-md"></i> Doctor Performance</h2>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Total Appointments</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>No Show</th>
                            <th>Rescheduled</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctor_performance as $doctor): ?>
                        <tr>
                            <td>
                                <strong>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                            </td>
                            <td>
                                <span class="specialty-badge">
                                    <?php echo htmlspecialchars($doctor['specialty']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $doctor['total_appointments']; ?></strong></td>
                            <td><span class="status-completed"><?php echo $doctor['completed_appointments']; ?></span></td>
                            <td><span class="status-cancelled"><?php echo $doctor['cancelled_appointments']; ?></span></td>
                            <td><span class="status-no-show"><?php echo $doctor['no_show_appointments']; ?></span></td>
                            <td><span class="status-rescheduled"><?php echo $doctor['rescheduled_appointments']; ?></span></td>
                            <td>
                                <span class="completion-rate completion-rate-<?php echo $doctor['completion_rate'] >= 80 ? 'good' : ($doctor['completion_rate'] >= 60 ? 'average' : 'poor'); ?>">
                                    <?php echo $doctor['completion_rate'] ?: '0'; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($doctor_performance)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-info-circle"></i> No appointment data available for the selected date range.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Logs Section -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> System Logs - Account Activity</h2>
                <p>Track user activities and system events</p>
            </div>
            <div class="table-container">
                <div class="logs-header-controls">
                    <button class="btn btn-danger btn-sm" onclick="clearSystemLogs()" id="clearLogsBtn">
                        <i class="fas fa-trash-alt"></i> Clear System Logs
                    </button>
                </div>
                <?php 
                // Get recent activity logs
                $activity_logs = $db->fetchAll("
                    SELECT 
                        al.*,
                        u.username,
                        u.first_name,
                        u.last_name,
                        u.role
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE DATE(al.created_at) BETWEEN ? AND ?
                    ORDER BY al.created_at DESC
                    LIMIT 50
                ", [$start_date, $end_date]);
                ?>

                <!-- Detailed Activity Log -->
                <div class="activity-log-table">
                    <h3>Recent Activity Log</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="log-timestamp">
                                        <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                                        <br>
                                        <small><?php echo date('g:i A', strtotime($log['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="log-user">
                                        <?php if ($log['username']): ?>
                                            <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                            <br>
                                            <small>@<?php echo htmlspecialchars($log['username']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $log['role']; ?>">
                                        <?php echo ucfirst($log['role'] ?? 'System'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="log-action">
                                        <i class="fas fa-<?php 
                                            echo match(strtolower($log['action'])) {
                                                'login' => 'sign-in-alt',
                                                'logout' => 'sign-out-alt',
                                                'register' => 'user-plus',
                                                'update_profile' => 'edit',
                                                'book_appointment' => 'calendar-plus',
                                                'cancel_appointment' => 'calendar-times',
                                                'view_profile' => 'eye',
                                                default => 'cog'
                                            };
                                        ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="log-description">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($activity_logs)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-info-circle"></i> No activity logs found for the selected date range.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Insights -->
        <div class="content-section insights-section">
            <div class="section-header">
                <h2><i class="fas fa-lightbulb"></i> Key Insights</h2>
            </div>
            <div class="insights-grid">
                <div class="insight-card">
                    <h4>Appointment Performance</h4>
                    <ul class="insight-list">
                        <li>
                            <i class="fas fa-check-circle"></i> 
                            <?php echo $stats['rates']['completion_rate']; ?>% completion rate
                        </li>
                        <li>
                            <i class="fas fa-times-circle"></i> 
                            <?php echo $stats['rates']['cancellation_rate']; ?>% cancellation rate
                        </li>
                        <li>
                            <i class="fas fa-user-times"></i> 
                            <?php echo $stats['rates']['no_show_rate']; ?>% no-show rate
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo $stats['appointments']['total']; ?> total appointments in period
                        </li>
                    </ul>
                </div>
                
                <div class="insight-card">
                    <h4>Practice Growth</h4>
                    <ul class="insight-list">
                        <li>
                            <i class="fas fa-user-plus"></i> 
                            <?php echo $stats['users']['new_patients']; ?> new patients
                        </li>
                        <li>
                            <i class="fas fa-user-md"></i> 
                            <?php echo $stats['users']['active_doctors']; ?> active doctors
                        </li>
                        <li>
                            <i class="fas fa-users"></i> 
                            <?php echo $stats['users']['total_patients']; ?> total patients
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.form-input, .form-select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary-cyan);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: var(--text-dark);
}

.data-table tbody tr:hover {
    background-color: #f8f9fa;
}

.stat-card {
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    font-size: 2rem;
    margin: 0 0 0.5rem 0;
    font-weight: bold;
}

.stat-card p {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    opacity: 0.9;
}

.stat-card small {
    font-size: 0.85rem;
    opacity: 0.8;
}

/* Status indicators */
.status-completed {
    color: #28a745;
    font-weight: 500;
}

.status-scheduled {
    color: #007bff;
    font-weight: 500;
}

.status-cancelled {
    color: #dc3545;
    font-weight: 500;
}

/* Specialty badge */
.specialty-badge {
    background: var(--light-cyan);
    color: var(--primary-cyan);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Completion rate styling */
.completion-rate {
    font-weight: bold;
}

.completion-rate-good {
    color: #28a745;
}

.completion-rate-average {
    color: #ffc107;
}

.completion-rate-poor {
    color: #dc3545;
}

/* Hourly distribution */
.hourly-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
}

.hourly-stat-card {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid var(--primary-cyan);
}

.hourly-count {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-cyan);
}

.hourly-time {
    font-size: 0.9rem;
    color: #6c757d;
}

.hourly-cancellations {
    font-size: 0.8rem;
    color: #dc3545;
}

/* Insights section */
.insights-section {
    background: linear-gradient(135deg, var(--primary-cyan), var(--dark-cyan));
    color: white;
}

.insights-section .section-header h2 {
    color: white;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.insight-card {
    background: rgba(255,255,255,0.1);
    padding: 1.5rem;
    border-radius: 8px;
}

.insight-card h4 {
    color: white;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.insight-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.insight-list li {
    margin-bottom: 0.5rem;
    color: rgba(255,255,255,0.9);
}

.insight-list li:last-child {
    margin-bottom: 0;
}

.insight-list i {
    margin-right: 0.5rem;
    width: 1rem;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}

/* System Logs Styles */
.activity-log-table {
    margin-top: 2rem;
}

.activity-log-table h3 {
    margin-bottom: 1rem;
    color: var(--text-dark);
}

.log-timestamp {
    font-size: 0.9rem;
    line-height: 1.3;
}

.log-user strong {
    color: var(--text-dark);
}

.log-user small {
    color: #6c757d;
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.role-admin {
    background: #dc3545;
    color: white;
}

.role-doctor {
    background: #28a745;
    color: white;
}

.role-patient {
    background: #007bff;
    color: white;
}

.role-system {
    background: #6c757d;
    color: white;
}

.log-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.log-action i {
    color: var(--primary-cyan);
}

.log-description {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.logs-header-controls {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 1.5rem;
    padding-top: 0.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
    transform: translateY(-1px);
}

.btn-danger:active {
    transform: translateY(0);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn:disabled:hover {
    transform: none;
}

/* Appointments Overview Card Styles */
.appointments-overview-card {
    background: var(--white);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 188, 212, 0.1);
    border: 1px solid #eee;
}

.filter-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: 2px solid #eee;
    border-radius: 8px;
    background: var(--white);
    color: var(--text-dark);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    border-color: var(--primary-cyan);
    color: var(--primary-cyan);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--primary-cyan);
    border-color: var(--primary-cyan);
    color: var(--white);
}

.filter-btn i {
    font-size: 0.8rem;
}

.date-range-info {
    text-align: right;
}

.date-range-info span {
    display: block;
    font-weight: 600;
    color: var(--text-dark);
    font-size: 1.1rem;
}

.date-range-info small {
    color: var(--text-light);
    font-size: 0.85rem;
}

.appointments-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.appointment-stat-item {
    background: rgba(0, 188, 212, 0.02);
    border: 1px solid rgba(0, 188, 212, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.appointment-stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--primary-cyan), var(--light-cyan));
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.appointment-stat-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 188, 212, 0.15);
}

.appointment-stat-item:hover::before {
    transform: scaleX(1);
}

.stat-value {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--primary-cyan);
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-change {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.stat-change.positive {
    color: #28a745;
}

.stat-change.negative {
    color: #dc3545;
}

.stat-change.neutral {
    color: #6c757d;
}

.stat-change i {
    font-size: 0.7rem;
}

/* Responsive styles for appointments overview */
@media (max-width: 768px) {
    .filter-controls {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .filter-buttons {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        flex: 1;
        min-width: 100px;
    }
    
    .date-range-info {
        text-align: center;
    }
    
    .appointments-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .appointment-stat-item {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
}

@media (max-width: 480px) {
    .appointments-overview-card {
        padding: 1rem;
    }
    
    .filter-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .appointments-stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }
}
</style>

<script>
function clearSystemLogs() {
    if (confirm('Are you sure you want to clear all system logs? This action cannot be undone.')) {
        const btn = document.getElementById('clearLogsBtn');
        const originalText = btn.innerHTML;
        
        // Disable button and show loading
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
        
        // Send AJAX request to clear logs
        fetch('clear_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'clear_logs' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('System logs cleared successfully!');
                // Reload the page to show updated data
                window.location.reload();
            } else {
                alert('Error clearing logs: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while clearing logs.');
        })
        .finally(() => {
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
}

// Appointments Overview Functions
let currentPeriod = 'daily';

function filterAppointments(period) {
    currentPeriod = period;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-period="${period}"]`).classList.add('active');
    
    // Update period label and date range
    updatePeriodLabel(period);
    
    // Fetch and update data
    fetchAppointmentData(period);
}

function updatePeriodLabel(period) {
    const label = document.getElementById('current-period-label');
    const dateRange = document.getElementById('current-date-range');
    
    const now = new Date();
    
    switch(period) {
        case 'daily':
            label.textContent = 'Today';
            dateRange.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            break;
        case 'weekly':
            const startOfWeek = new Date(now.setDate(now.getDate() - now.getDay()));
            const endOfWeek = new Date(now.setDate(now.getDate() - now.getDay() + 6));
            label.textContent = 'This Week';
            dateRange.textContent = `${startOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
            break;
        case 'monthly':
            label.textContent = 'This Month';
            dateRange.textContent = now.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long' 
            });
            break;
    }
}

function fetchAppointmentData(period) {
    // Show loading state
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(val => {
        val.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    });
    
    // Send AJAX request
    fetch('get_appointment_stats.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ period: period })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateAppointmentStats(data.stats, data.changes);
        } else {
            console.error('Error fetching appointment data:', data.message);
            // Reset to default values on error
            resetAppointmentStats();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resetAppointmentStats();
    });
}

function updateAppointmentStats(stats, changes) {
    // Update stat values
    document.getElementById('total-appointments').textContent = stats.total || 0;
    document.getElementById('completed-appointments').textContent = stats.completed || 0;
    document.getElementById('pending-appointments').textContent = stats.pending || 0;
    document.getElementById('cancelled-appointments').textContent = stats.cancelled || 0;
    
    // Update change indicators
    updateChangeIndicator('appointments-change', changes.total || 0);
    updateChangeIndicator('completed-change', changes.completed || 0);
    updateChangeIndicator('pending-change', changes.pending || 0);
    updateChangeIndicator('cancelled-change', changes.cancelled || 0);
}

function updateChangeIndicator(elementId, change) {
    const element = document.getElementById(elementId);
    const icon = element.querySelector('i');
    const span = element.querySelector('span');
    
    const changeValue = Math.abs(change);
    const changeText = changeValue === 0 ? '0%' : `${change > 0 ? '+' : ''}${change}%`;
    
    span.textContent = changeText;
    
    // Update classes and icons
    element.className = 'stat-change';
    if (change > 0) {
        element.classList.add('positive');
        icon.className = 'fas fa-arrow-up';
    } else if (change < 0) {
        element.classList.add('negative');
        icon.className = 'fas fa-arrow-down';
    } else {
        element.classList.add('neutral');
        icon.className = 'fas fa-minus';
    }
}

function resetAppointmentStats() {
    document.getElementById('total-appointments').textContent = '0';
    document.getElementById('completed-appointments').textContent = '0';
    document.getElementById('pending-appointments').textContent = '0';
    document.getElementById('cancelled-appointments').textContent = '0';
    
    // Reset change indicators
    document.querySelectorAll('.stat-change span').forEach(span => {
        span.textContent = '+0%';
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodLabel('daily');
});
</script>

</body>
</html>
