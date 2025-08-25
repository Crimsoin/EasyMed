<?php
$page_title = "Reports & Analytics";
$additional_css = ['admin/sidebar.css', 'admin/dashboard.css', 'admin/report-and-analytics.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get date range for reports (default to last 30 days)
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$report_type = $_GET['report_type'] ?? 'overview';

// Validate dates
if (!isValidDate($start_date) || !isValidDate($end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
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
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'no_show'", [$start_date, $end_date])['count'],
    'rescheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'rescheduled'", [$start_date, $end_date])['count']
];

// Calculate rates
$total_appointments = $stats['appointments']['total'];
$stats['rates'] = [
    'no_show_rate' => $total_appointments > 0 ? round(($stats['appointments']['no_show'] / $total_appointments) * 100, 1) : 0,
    'cancellation_rate' => $total_appointments > 0 ? round(($stats['appointments']['cancelled'] / $total_appointments) * 100, 1) : 0,
    'completion_rate' => $total_appointments > 0 ? round(($stats['appointments']['completed'] / $total_appointments) * 100, 1) : 0,
    'reschedule_rate' => $total_appointments > 0 ? round(($stats['appointments']['rescheduled'] / $total_appointments) * 100, 1) : 0
];

$stats['users'] = [
    'total_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'active_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 1")['count'],
    'total_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'],
    'new_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['count']
];

// Daily appointment trends with key metrics
$daily_trends = $db->fetchAll("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
        ROUND((SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as no_show_rate,
        ROUND((SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as cancellation_rate
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);

// Weekly trends (last 12 weeks)
$weekly_trends = $db->fetchAll("
    SELECT 
        YEARWEEK(appointment_date, 1) as week_number,
        DATE_SUB(appointment_date, INTERVAL WEEKDAY(appointment_date) DAY) as week_start,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
        ROUND((SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as no_show_rate,
        ROUND((SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as cancellation_rate
    FROM appointments 
    WHERE appointment_date BETWEEN DATE_SUB(?, INTERVAL 12 WEEK) AND ?
    GROUP BY YEARWEEK(appointment_date, 1), DATE_SUB(appointment_date, INTERVAL WEEKDAY(appointment_date) DAY)
    ORDER BY week_number ASC
", [$end_date, $end_date]);

// Monthly trends (last 12 months)
$monthly_trends = $db->fetchAll("
    SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
        ROUND((SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as no_show_rate,
        ROUND((SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as cancellation_rate
    FROM appointments 
    WHERE appointment_date BETWEEN DATE_SUB(?, INTERVAL 11 MONTH) AND ?
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month ASC
", [$end_date, $end_date]);

// Doctor performance with key metrics
$doctor_performance = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        d.specialty,
        COUNT(a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
        SUM(CASE WHEN a.status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_appointments,
        ROUND((SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as no_show_rate,
        ROUND((SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as cancellation_rate,
        ROUND((SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as completion_rate
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    WHERE u.role = 'doctor' AND u.is_active = 1
    GROUP BY u.id, u.first_name, u.last_name, d.specialty
    HAVING total_appointments > 0
    ORDER BY completion_rate DESC, total_appointments DESC
", [$start_date, $end_date]);

// Time-based analysis for patterns
$hourly_stats = $db->fetchAll("
    SELECT 
        HOUR(appointment_time) as hour,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancellations
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY HOUR(appointment_time)
    ORDER BY hour ASC
", [$start_date, $end_date]);

// Day of week patterns
$day_of_week_stats = $db->fetchAll("
    SELECT 
        DAYOFWEEK(appointment_date) as day_of_week,
        DAYNAME(appointment_date) as day_name,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancellations,
        ROUND((SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as no_show_rate
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(appointment_date), DAYNAME(appointment_date)
    ORDER BY day_of_week ASC
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
            <a href="../Report and Analytics/reports.php" class="nav-item active">
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
            <p>Comprehensive analytics and financial reporting for your medical practice</p>
        </div>

        <!-- Report Controls -->
        <div class="reports-filters">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="start_date" class="filter-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="filter-input" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date" class="filter-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="filter-input" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i> Update Report
                        </button>
                        <a href="export-report.php?export=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="btn btn-secondary" target="_blank">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                        <a href="export-report.php?export=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="overview">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </button>
                <button class="tab-button" data-tab="daily">
                    <i class="fas fa-calendar-day"></i> Daily
                </button>
                <button class="tab-button" data-tab="weekly">
                    <i class="fas fa-calendar-week"></i> Weekly
                </button>
                <button class="tab-button" data-tab="monthly">
                    <i class="fas fa-calendar-alt"></i> Monthly
                </button>
                <button class="tab-button" data-tab="performance">
                    <i class="fas fa-chart-line"></i> Performance
                </button>
            </div>

                    <!-- Overview Tab -->
                    <div class="tab-content active" id="overview-tab">
                        <!-- Key Metrics Overview -->
                        <div class="summary-cards">
                            <div class="summary-card appointments">
                                <div class="card-header">
                                    <h3 class="card-title">Total Appointments</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                </div>
                                <div class="card-value"><?php echo number_format($stats['appointments']['total']); ?></div>
                                <div class="card-change">
                                    <span class="change-neutral">Period: <?php echo date('M j', strtotime($start_date)) . ' - ' . date('M j', strtotime($end_date)); ?></span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">No Show Rate</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                </div>
                                <div class="card-value"><?php echo $stats['rates']['no_show_rate']; ?>%</div>
                                <div class="card-change">
                                    <span class="<?php echo $stats['rates']['no_show_rate'] > 15 ? 'change-negative' : ($stats['rates']['no_show_rate'] < 10 ? 'change-positive' : 'change-neutral'); ?>">
                                        <?php echo $stats['appointments']['no_show']; ?> no-shows
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Cancellation Rate</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                                <div class="card-value"><?php echo $stats['rates']['cancellation_rate']; ?>%</div>
                                <div class="card-change">
                                    <span class="<?php echo $stats['rates']['cancellation_rate'] > 20 ? 'change-negative' : ($stats['rates']['cancellation_rate'] < 15 ? 'change-positive' : 'change-neutral'); ?>">
                                        <?php echo $stats['appointments']['cancelled']; ?> cancellations
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Rescheduled</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-sync-alt"></i>
                                    </div>
                                </div>
                                <div class="card-value"><?php echo number_format($stats['appointments']['rescheduled']); ?></div>
                                <div class="card-change">
                                    <span class="change-neutral"><?php echo $stats['rates']['reschedule_rate']; ?>% of total</span>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Status Breakdown -->
                        <div class="charts-section">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-chart-pie"></i> Appointment Status Distribution
                                    </h3>
                                    <p class="chart-subtitle">Breakdown of all appointment outcomes</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="appointmentStatusChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-chart-bar"></i> Key Performance Rates
                                    </h3>
                                    <p class="chart-subtitle">Success and problem rates</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="performanceRatesChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                                <div class="chart-legend">
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #4caf50;"></div>
                                        <span class="legend-label">Completed</span>
                                        <span class="legend-value"><?php echo $stats['appointments']['completed']; ?></span>
                                        <span class="legend-percentage">
                                            <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['completed'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #ff9800;"></div>
                                        <span class="legend-label">Cancelled</span>
                                        <span class="legend-value"><?php echo $stats['appointments']['cancelled']; ?></span>
                                        <span class="legend-percentage">
                                            <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['cancelled'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #f44336;"></div>
                                        <span class="legend-label">No Show</span>
                                        <span class="legend-value"><?php echo $stats['appointments']['no_show']; ?></span>
                                        <span class="legend-percentage">
                                            <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['no_show'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #9c27b0;"></div>
                                        <span class="legend-label">Rescheduled</span>
                                        <span class="legend-value"><?php echo $stats['appointments']['rescheduled']; ?></span>
                                        <span class="legend-percentage">
                                            <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['rescheduled'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Trends -->
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">Daily Appointment Trends</h3>
                                <p class="chart-subtitle">Appointment volume over time</p>
                            </div>
                            <div class="chart-content">
                                <canvas id="dailyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Tab -->
                    <div class="tab-content" id="daily-tab">
                        <!-- Daily Summary -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Daily Average</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    $days_in_period = max(1, (strtotime($end_date) - strtotime($start_date)) / (60*60*24) + 1);
                                    echo round($stats['appointments']['total'] / $days_in_period, 1);
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-neutral">appointments per day</span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Best Day</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    if (!empty($daily_trends)) {
                                        $best_day = array_reduce($daily_trends, function($carry, $day) {
                                            return ($carry === null || $day['total_appointments'] > $carry['total_appointments']) ? $day : $carry;
                                        });
                                        echo $best_day['total_appointments'];
                                    } else {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-positive">
                                        <?php echo !empty($best_day) ? date('M j', strtotime($best_day['date'])) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Daily No-Show Avg</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php echo round($stats['appointments']['no_show'] / $days_in_period, 1); ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-negative">per day</span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Daily Cancellation Avg</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php echo round($stats['appointments']['cancelled'] / $days_in_period, 1); ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-warning">per day</span>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Trends Chart -->
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-chart-line"></i> Daily Appointment Trends
                                </h3>
                                <p class="chart-subtitle">Day-by-day breakdown of appointments and issues</p>
                            </div>
                            <div class="chart-content">
                                <canvas id="dailyDetailChart" width="800" height="400"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly Tab -->
                    <div class="tab-content" id="weekly-tab">
                        <!-- Weekly Summary -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Weekly Average</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    $weeks_count = max(1, count($weekly_trends));
                                    echo $weeks_count > 0 ? round(array_sum(array_column($weekly_trends, 'total_appointments')) / $weeks_count, 1) : 0;
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-neutral">appointments per week</span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Best Week</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    if (!empty($weekly_trends)) {
                                        $best_week = array_reduce($weekly_trends, function($carry, $week) {
                                            return ($carry === null || $week['total_appointments'] > $carry['total_appointments']) ? $week : $carry;
                                        });
                                        echo $best_week['total_appointments'];
                                    } else {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-positive">
                                        <?php echo !empty($best_week) ? 'Week of ' . date('M j', strtotime($best_week['week_start'])) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Avg Weekly No-Shows</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php echo $weeks_count > 0 ? round(array_sum(array_column($weekly_trends, 'no_show')) / $weeks_count, 1) : 0; ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-negative">per week</span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Avg Weekly Cancellations</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php echo $weeks_count > 0 ? round(array_sum(array_column($weekly_trends, 'cancelled')) / $weeks_count, 1) : 0; ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-warning">per week</span>
                                </div>
                            </div>
                        </div>

                        <!-- Weekly Trends Chart -->
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-chart-bar"></i> Weekly Appointment Trends
                                </h3>
                                <p class="chart-subtitle">Week-by-week performance over the last 12 weeks</p>
                            </div>
                            <div class="chart-content">
                                <canvas id="weeklyTrendsChart" width="800" height="400"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Tab -->
                    <div class="tab-content" id="monthly-tab">
                        <!-- Monthly Summary -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Monthly Average</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    $months_count = max(1, count($monthly_trends));
                                    echo $months_count > 0 ? round(array_sum(array_column($monthly_trends, 'total_appointments')) / $months_count, 1) : 0;
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-neutral">appointments per month</span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Best Month</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-award"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    if (!empty($monthly_trends)) {
                                        $best_month = array_reduce($monthly_trends, function($carry, $month) {
                                            return ($carry === null || $month['total_appointments'] > $carry['total_appointments']) ? $month : $carry;
                                        });
                                        echo $best_month['total_appointments'];
                                    } else {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="change-positive">
                                        <?php echo !empty($best_month) ? date('M Y', strtotime($best_month['month'] . '-01')) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Monthly No-Show Trend</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-trend-down"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    if (count($monthly_trends) >= 2) {
                                        $recent_rate = end($monthly_trends)['no_show_rate'];
                                        $previous_rate = prev($monthly_trends)['no_show_rate'];
                                        $trend = $recent_rate - $previous_rate;
                                        echo ($trend > 0 ? '+' : '') . round($trend, 1) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="<?php echo isset($trend) && $trend > 0 ? 'change-negative' : 'change-positive'; ?>">
                                        vs last month
                                    </span>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-header">
                                    <h3 class="card-title">Monthly Cancel Trend</h3>
                                    <div class="card-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                </div>
                                <div class="card-value">
                                    <?php 
                                    if (count($monthly_trends) >= 2) {
                                        $recent_cancel_rate = end($monthly_trends)['cancellation_rate'];
                                        reset($monthly_trends);
                                        $previous_cancel_rate = prev($monthly_trends)['cancellation_rate'];
                                        $cancel_trend = $recent_cancel_rate - $previous_cancel_rate;
                                        echo ($cancel_trend > 0 ? '+' : '') . round($cancel_trend, 1) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </div>
                                <div class="card-change">
                                    <span class="<?php echo isset($cancel_trend) && $cancel_trend > 0 ? 'change-negative' : 'change-positive'; ?>">
                                        vs last month
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Trends Chart -->
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-chart-area"></i> Monthly Appointment Trends
                                </h3>
                                <p class="chart-subtitle">12-month overview showing appointment patterns and rates</p>
                            </div>
                            <div class="chart-content">
                                <canvas id="monthlyTrendsChart" width="800" height="400"></canvas>
                            </div>
                        </div>
                    </div>
                        

                    <!-- Performance Tab -->
                    <div class="tab-content" id="performance-tab">
                        <!-- Doctor Performance Table -->
                        <div class="reports-table-section">
                            <div class="table-header">
                                <h3 class="table-title">Doctor Performance Analysis</h3>
                                <p class="table-subtitle">Detailed performance metrics for each doctor</p>
                            </div>
                            <div class="table-container">
                                <table class="reports-table">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Specialty</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>No Shows</th>
                                            <th>Revenue</th>
                                            <th>Avg Fee</th>
                                            <th>Success Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($doctor_performance as $doctor): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                                <td><?php echo $doctor['total_appointments']; ?></td>
                                                <td><span class="report-status status-completed"><?php echo $doctor['completed_appointments']; ?></span></td>
                                                <td><span class="report-status status-cancelled"><?php echo $doctor['cancelled_appointments']; ?></span></td>
                                                <td><span class="report-status status-no-show"><?php echo $doctor['no_show_appointments']; ?></span></td>
                                                <td>
                                                    <strong class="revenue-cell">$<?php echo number_format($doctor['revenue'], 2); ?></strong>
                                                </td>
                                                <td>$<?php echo number_format($doctor['avg_fee'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    $success_rate = $doctor['total_appointments'] > 0 ? 
                                                        round(($doctor['completed_appointments'] / $doctor['total_appointments']) * 100, 1) : 0;
                                                    ?>
                                                    <span class="<?php echo $success_rate >= 80 ? 'change-positive' : ($success_rate >= 60 ? 'change-neutral' : 'change-negative'); ?>">
                                                        <?php echo $success_rate; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Performance Charts -->
                        <div class="charts-section">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">Doctor Revenue Performance</h3>
                                    <p class="chart-subtitle">Revenue comparison across doctors</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="doctorRevenueChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">Doctor Workload</h3>
                                    <p class="chart-subtitle">Appointment distribution by status</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="doctorWorkloadChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-content" id="analytics-tab">
                        <!-- Analytics Overview -->
                        <div class="analytics-grid">
                            <div class="metric-card">
                                <span class="metric-value"><?php echo count($hourly_stats); ?></span>
                                <div class="metric-label">Peak Hours Tracked</div>
                                <div class="metric-trend trend-neutral">
                                    <i class="fas fa-clock"></i> Active time slots
                                </div>
                            </div>
                            
                            <div class="metric-card">
                                <span class="metric-value"><?php echo count($age_distribution); ?></span>
                                <div class="metric-label">Age Groups Served</div>
                                <div class="metric-trend trend-positive">
                                    <i class="fas fa-users"></i> Demographics
                                </div>
                            </div>
                            
                            <div class="metric-card">
                                <span class="metric-value"><?php echo count($specialty_breakdown); ?></span>
                                <div class="metric-label">Active Specialties</div>
                                <div class="metric-trend trend-neutral">
                                    <i class="fas fa-stethoscope"></i> Medical areas
                                </div>
                            </div>
                        </div>

                        <!-- Analytics Charts -->
                        <div class="charts-section">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">Peak Hours Analysis</h3>
                                    <p class="chart-subtitle">Appointment distribution by hour</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="hourlyStatsChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title">Weekly Patterns</h3>
                                    <p class="chart-subtitle">Appointment distribution by day of week</p>
                                </div>
                                <div class="chart-content">
                                    <canvas id="weeklyStatsChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Demographics -->
                        <?php if (!empty($age_distribution)): ?>
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">Patient Age Distribution</h3>
                                <p class="chart-subtitle">Demographics of patients served</p>
                            </div>
                            <div class="chart-content">
                                <canvas id="ageDistributionChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                        <!-- Doctor Performance Table -->
                        <div class="table-container">
                            <h3>Doctor Performance Analysis</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Specialty</th>
                                        <th>Total Appointments</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                        <th>No Shows</th>
                                        <th>Revenue</th>
                                        <th>Avg Fee</th>
                                        <th>Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctor_performance as $doctor): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                            <td><?php echo $doctor['total_appointments']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $doctor['completed_appointments']; ?></span></td>
                                            <td><span class="badge badge-warning"><?php echo $doctor['cancelled_appointments']; ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo $doctor['no_show_appointments']; ?></span></td>
                                            <td>
                                                <strong class="revenue">$<?php echo number_format($doctor['revenue'], 2); ?></strong>
                                            </td>
                                            <td>$<?php echo number_format($doctor['avg_fee'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $success_rate = $doctor['total_appointments'] > 0 ? 
                                                    round(($doctor['completed_appointments'] / $doctor['total_appointments']) * 100, 1) : 0;
                                                ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
                                                    <span class="progress-text"><?php echo $success_rate; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Performance Charts -->
                        <div class="charts-row">
                            <div class="chart-container">
                                <h3>Doctor Workload Distribution</h3>
                                <canvas id="doctorWorkloadChart" width="400" height="300"></canvas>
                            </div>
                            <div class="chart-container">
                                <h3>Weekly Appointment Pattern</h3>
                                <canvas id="weeklyPatternChart" width="400" height="300"></canvas>
                            </div>
                        </div>

                        <!-- Appointment Success Rate Chart -->
                        <div class="chart-container-large">
                            <h3>Appointment Completion Trends</h3>
                            <canvas id="completionTrendsChart" width="800" height="400"></canvas>
                        </div>

                        <!-- Performance Analysis Charts -->
                        <div class="charts-row">
                            <div class="chart-container">
                                <h3>Appointment Status Distribution</h3>
                                <canvas id="performanceStatusPieChart" width="400" height="300"></canvas>
                            </div>
                            <div class="chart-container">
                                <h3>Doctor Performance Comparison</h3>
                                <canvas id="doctorPerformanceBarChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-content" id="analytics-tab">
                        <!-- Peak Hours Analysis -->
                        <div class="analytics-section">
                            <div class="chart-container-large">
                                <h3><i class="fas fa-clock"></i> Peak Hours Analysis</h3>
                                <canvas id="hourlyChart" width="800" height="300"></canvas>
                            </div>
                        </div>

                        <!-- Specialty Performance -->
                        <div class="analytics-section">
                            <div class="section-title">
                                <h3><i class="fas fa-stethoscope"></i> Specialty Performance</h3>
                                <p>Performance breakdown by medical specialty</p>
                            </div>
                            <div class="specialty-analysis">
                                <?php 
                                $rank = 1;
                                foreach ($specialty_breakdown as $specialty): 
                                    $percentage = $stats['appointments']['total'] > 0 ? 
                                        round(($specialty['appointment_count'] / $stats['appointments']['total']) * 100, 1) : 0;
                                ?>
                                    <div class="specialty-card">
                                        <div class="specialty-rank">
                                            <?php echo $rank++; ?>
                                        </div>
                                        <div class="specialty-info">
                                            <h4><?php echo htmlspecialchars($specialty['specialty']); ?></h4>
                                            <p><?php echo $specialty['appointment_count']; ?> appointments (<?php echo $percentage; ?>%)</p>
                                        </div>
                                        <div class="specialty-stats">
                                            <p class="specialty-count"><?php echo $specialty['completed_count']; ?></p>
                                            <p class="specialty-percentage">completed</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Patient Demographics & Patterns -->
                        <div class="analytics-section">
                            <div class="section-title">
                                <h3><i class="fas fa-users"></i> Patient Demographics & Patterns</h3>
                                <p>Age distribution and appointment patterns analysis</p>
                            </div>
                            <div class="charts-row">
                                <div class="chart-container">
                                    <h3>Patient Age Distribution</h3>
                                    <canvas id="ageDistributionChart" width="400" height="300"></canvas>
                                </div>
                                <div class="chart-container">
                                    <h3>Appointment Cancellation Analysis</h3>
                                    <canvas id="cancellationChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Trend Analysis -->
                        <div class="analytics-section">
                            <div class="section-title">
                                <h3><i class="fas fa-chart-line"></i> Advanced Trend Analysis</h3>
                                <p>Multi-metric correlation and performance trends</p>
                            </div>
                            <div class="chart-container-large">
                                <h3>Multi-Metric Trend Analysis</h3>
                                <canvas id="multiTrendChart" width="800" height="400"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Chart Data Variables -->
<script>
// Global data variables for charts
window.appointmentStats = {
    completed: <?php echo $stats['appointments']['completed']; ?>,
    cancelled: <?php echo $stats['appointments']['cancelled']; ?>,
    no_show: <?php echo $stats['appointments']['no_show']; ?>,
    rescheduled: <?php echo $stats['appointments']['rescheduled']; ?>
};

window.appointmentRates = {
    completion_rate: <?php echo $stats['rates']['completion_rate']; ?>,
    no_show_rate: <?php echo $stats['rates']['no_show_rate']; ?>,
    cancellation_rate: <?php echo $stats['rates']['cancellation_rate']; ?>,
    reschedule_rate: <?php echo $stats['rates']['reschedule_rate']; ?>
};

window.dailyTrends = {
    labels: [<?php echo '"' . implode('","', array_map(function($trend) { return date('M j', strtotime($trend['date'])); }, $daily_trends)) . '"'; ?>],
    total: [<?php echo implode(',', array_column($daily_trends, 'total_appointments')); ?>],
    completed: [<?php echo implode(',', array_column($daily_trends, 'completed')); ?>],
    cancelled: [<?php echo implode(',', array_column($daily_trends, 'cancelled')); ?>],
    no_shows: [<?php echo implode(',', array_column($daily_trends, 'no_show')); ?>],
    rescheduled: [<?php echo implode(',', array_column($daily_trends, 'rescheduled')); ?>]
};

window.weeklyTrends = {
    labels: [<?php echo '"' . implode('","', array_map(function($week) { return 'Week of ' . date('M j', strtotime($week['week_start'])); }, $weekly_trends)) . '"'; ?>],
    total: [<?php echo implode(',', array_column($weekly_trends, 'total_appointments')); ?>],
    completed: [<?php echo implode(',', array_column($weekly_trends, 'completed')); ?>],
    cancelled: [<?php echo implode(',', array_column($weekly_trends, 'cancelled')); ?>],
    no_shows: [<?php echo implode(',', array_column($weekly_trends, 'no_show')); ?>],
    rescheduled: [<?php echo implode(',', array_column($weekly_trends, 'rescheduled')); ?>],
    no_show_rates: [<?php echo implode(',', array_column($weekly_trends, 'no_show_rate')); ?>],
    cancellation_rates: [<?php echo implode(',', array_column($weekly_trends, 'cancellation_rate')); ?>]
};

window.monthlyTrends = {
    labels: [<?php echo '"' . implode('","', array_map(function($month) { return date('M Y', strtotime($month['month'] . '-01')); }, $monthly_trends)) . '"'; ?>],
    total: [<?php echo implode(',', array_column($monthly_trends, 'total_appointments')); ?>],
    completed: [<?php echo implode(',', array_column($monthly_trends, 'completed')); ?>],
    cancelled: [<?php echo implode(',', array_column($monthly_trends, 'cancelled')); ?>],
    no_shows: [<?php echo implode(',', array_column($monthly_trends, 'no_show')); ?>],
    rescheduled: [<?php echo implode(',', array_column($monthly_trends, 'rescheduled')); ?>],
    no_show_rates: [<?php echo implode(',', array_column($monthly_trends, 'no_show_rate')); ?>],
    cancellation_rates: [<?php echo implode(',', array_column($monthly_trends, 'cancellation_rate')); ?>]
};

window.doctorPerformance = {
    names: [<?php echo '"' . implode('","', array_column($doctor_performance, 'doctor_name')) . '"'; ?>],
    completed: [<?php echo implode(',', array_column($doctor_performance, 'completed_appointments')); ?>],
    cancelled: [<?php echo implode(',', array_column($doctor_performance, 'cancelled_appointments')); ?>],
    no_shows: [<?php echo implode(',', array_column($doctor_performance, 'no_show_appointments')); ?>],
    rescheduled: [<?php echo implode(',', array_column($doctor_performance, 'rescheduled_appointments')); ?>],
    completion_rates: [<?php echo implode(',', array_column($doctor_performance, 'completion_rate')); ?>],
    no_show_rates: [<?php echo implode(',', array_column($doctor_performance, 'no_show_rate')); ?>],
    cancellation_rates: [<?php echo implode(',', array_column($doctor_performance, 'cancellation_rate')); ?>]
};

window.hourlyStats = {
    labels: [<?php 
        $hours = array_column($hourly_stats, 'hour');
        $hour_labels = array_map(function($h) { return '"' . sprintf('%02d:00', $h) . '"'; }, $hours);
        echo implode(',', $hour_labels);
    ?>],
    total: [<?php echo implode(',', array_column($hourly_stats, 'total_appointments')); ?>],
    no_shows: [<?php echo implode(',', array_column($hourly_stats, 'no_shows')); ?>],
    cancellations: [<?php echo implode(',', array_column($hourly_stats, 'cancellations')); ?>]
};

window.dayOfWeekStats = {
    labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    data: (function() {
        var weeklyData = new Array(7).fill(0);
        var noShowData = new Array(7).fill(0);
        <?php foreach ($day_of_week_stats as $day): ?>
            weeklyData[<?php echo $day['day_of_week'] - 1; ?>] = <?php echo $day['total_appointments']; ?>;
            noShowData[<?php echo $day['day_of_week'] - 1; ?>] = <?php echo $day['no_shows']; ?>;
        <?php endforeach; ?>
        return {
            appointments: weeklyData,
            no_shows: noShowData
        };
    })()
};
</script>

<!-- Include modular Reports Analytics JavaScript -->
<script src="../assets/js/reports-analytics.js"></script>

<!-- Initialize Reports -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            const targetContent = document.getElementById(targetTab + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // Initialize the Reports Analytics module if available
    if (typeof ReportsAnalytics !== 'undefined') {
        const reportsAnalytics = new ReportsAnalytics();
        reportsAnalytics.init();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
