<?php
$page_title = "Reports & Analytics";
$additional_css = ['admin/dashboard.css', 'admin/report-and-analytics.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

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

// Generate comprehensive statistics
$stats = [];

// General Statistics
$stats['appointments'] = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?", [$start_date, $end_date])['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'pending'", [$start_date, $end_date])['count'],
    'confirmed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'confirmed'", [$start_date, $end_date])['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'", [$start_date, $end_date])['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'cancelled'", [$start_date, $end_date])['count'],
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'no_show'", [$start_date, $end_date])['count']
];

$stats['users'] = [
    'total_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'active_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 1")['count'],
    'total_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'],
    'new_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['count']
];

// Revenue calculations
$revenue_data = $db->fetch("
    SELECT 
        COUNT(*) as completed_appointments,
        SUM(d.consultation_fee) as total_revenue,
        AVG(d.consultation_fee) as avg_consultation_fee
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.user_id 
    WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'
", [$start_date, $end_date]);

$stats['revenue'] = [
    'total' => $revenue_data['total_revenue'] ?? 0,
    'completed_appointments' => $revenue_data['completed_appointments'] ?? 0,
    'average_per_appointment' => $revenue_data['avg_consultation_fee'] ?? 0
];

// Daily appointment trends
$daily_trends = $db->fetchAll("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);

// Doctor performance with financial data
$doctor_performance = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        d.specialty,
        COUNT(a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) as revenue,
        AVG(CASE WHEN a.status = 'completed' THEN d.consultation_fee END) as avg_fee
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    WHERE u.role = 'doctor' AND u.is_active = 1
    GROUP BY u.id, u.first_name, u.last_name, d.specialty
    HAVING total_appointments > 0
    ORDER BY completed_appointments DESC, total_appointments DESC
", [$start_date, $end_date]);

// Revenue by specialty
$specialty_revenue = $db->fetchAll("
    SELECT 
        d.specialty,
        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) as total_revenue,
        AVG(CASE WHEN a.status = 'completed' THEN d.consultation_fee END) as avg_fee
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    WHERE u.is_active = 1
    GROUP BY d.specialty
    HAVING completed_appointments > 0
    ORDER BY total_revenue DESC
", [$start_date, $end_date]);

// Daily revenue for financial trends
$daily_revenue = $db->fetchAll("
    SELECT 
        DATE(a.appointment_date) as date,
        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) as revenue
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.user_id
    WHERE a.appointment_date BETWEEN ? AND ?
    GROUP BY DATE(a.appointment_date)
    ORDER BY date ASC
", [$start_date, $end_date]);

// Monthly revenue trend (last 12 months)
$monthly_revenue = $db->fetchAll("
    SELECT 
        DATE_FORMAT(a.appointment_date, '%Y-%m') as month,
        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) as revenue
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.user_id
    WHERE a.appointment_date BETWEEN DATE_SUB(?, INTERVAL 11 MONTH) AND ?
    GROUP BY DATE_FORMAT(a.appointment_date, '%Y-%m')
    ORDER BY month ASC
", [$end_date, $end_date]);

// Specialty breakdown for analytics
$specialty_breakdown = $db->fetchAll("
    SELECT 
        d.specialty,
        COUNT(a.id) as appointment_count,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) as revenue
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    WHERE u.is_active = 1
    GROUP BY d.specialty
    HAVING appointment_count > 0
    ORDER BY appointment_count DESC
", [$start_date, $end_date]);

// Peak hours analysis
$hourly_stats = $db->fetchAll("
    SELECT 
        HOUR(appointment_time) as hour,
        COUNT(*) as appointment_count
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY HOUR(appointment_time)
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
            <a href="users.php" class="nav-item">
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
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>Comprehensive analytics and financial reporting for your medical practice</p>
        </div>

        <!-- Report Controls -->
        <div class="content-section">
            <div class="section-header">
                <h2>Report Parameters</h2>
            </div>
            <div class="section-content">
                <form method="GET" class="filter-section">
                    <div class="filter-grid">
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
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-line"></i> Update Report
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <div class="export-buttons">
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
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="content-section">
            <div class="section-header">
                <h2>Analytics Dashboard</h2>
                <small class="date-range">Period: <?php echo formatDate($start_date); ?> - <?php echo formatDate($end_date); ?></small>
            </div>
            <div class="section-content">
                <div class="report-tabs">
                    <div class="tab-navigation">
                        <button class="tab-button active" data-tab="overview">
                            <i class="fas fa-tachometer-alt"></i> Overview
                        </button>
                        <button class="tab-button" data-tab="financial">
                            <i class="fas fa-dollar-sign"></i> Financial
                        </button>
                        <button class="tab-button" data-tab="performance">
                            <i class="fas fa-chart-line"></i> Performance
                        </button>
                        <button class="tab-button" data-tab="analytics">
                            <i class="fas fa-analytics"></i> Analytics
                        </button>
                    </div>

                    <!-- Overview Tab -->
                    <div class="tab-content active" id="overview-tab">
                        <!-- Key Metrics Overview -->
                        <div class="kpi-grid">
                            <div class="kpi-card">
                                <div class="kpi-icon appointments">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3><?php echo number_format($stats['appointments']['total']); ?></h3>
                                    <p>Total Appointments</p>
                                    <small><?php echo $stats['appointments']['completed']; ?> completed</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon revenue">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3>$<?php echo number_format($stats['revenue']['total'], 2); ?></h3>
                                    <p>Total Revenue</p>
                                    <small>$<?php echo number_format($stats['revenue']['average_per_appointment'], 2); ?> avg per appointment</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon patients">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3><?php echo number_format($stats['users']['total_patients']); ?></h3>
                                    <p>Total Patients</p>
                                    <small><?php echo $stats['users']['new_patients']; ?> new patients</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon doctors">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3><?php echo number_format($stats['users']['active_doctors']); ?></h3>
                                    <p>Active Doctors</p>
                                    <small>of <?php echo $stats['users']['total_doctors']; ?> total</small>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Status Breakdown -->
                        <div class="charts-row">
                            <div class="chart-container">
                                <h3>Appointment Status</h3>
                                <canvas id="statusPieChart" width="400" height="300"></canvas>
                            </div>
                            <div class="status-breakdown">
                                <h3>Status Breakdown</h3>
                                <div class="status-item">
                                    <span class="status-indicator completed"></span>
                                    <span class="status-label">Completed</span>
                                    <span class="status-count"><?php echo $stats['appointments']['completed']; ?></span>
                                    <span class="status-percentage">
                                        <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['completed'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator confirmed"></span>
                                    <span class="status-label">Confirmed</span>
                                    <span class="status-count"><?php echo $stats['appointments']['confirmed']; ?></span>
                                    <span class="status-percentage">
                                        <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['confirmed'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator pending"></span>
                                    <span class="status-label">Pending</span>
                                    <span class="status-count"><?php echo $stats['appointments']['pending']; ?></span>
                                    <span class="status-percentage">
                                        <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['pending'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator cancelled"></span>
                                    <span class="status-label">Cancelled</span>
                                    <span class="status-count"><?php echo $stats['appointments']['cancelled']; ?></span>
                                    <span class="status-percentage">
                                        <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['cancelled'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator no-show"></span>
                                    <span class="status-label">No Show</span>
                                    <span class="status-count"><?php echo $stats['appointments']['no_show']; ?></span>
                                    <span class="status-percentage">
                                        <?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['no_show'] / $stats['appointments']['total']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Trends -->
                        <div class="chart-container-large">
                            <h3>Daily Appointment Trends</h3>
                            <canvas id="dailyTrendsChart" width="800" height="400"></canvas>
                        </div>
                    </div>

                    <!-- Financial Tab -->
                    <div class="tab-content" id="financial-tab">
                        <!-- Financial KPIs -->
                        <div class="kpi-grid">
                            <div class="kpi-card">
                                <div class="kpi-icon revenue">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3>$<?php echo number_format($stats['revenue']['total'], 2); ?></h3>
                                    <p>Total Revenue</p>
                                    <small><?php echo $stats['revenue']['completed_appointments']; ?> completed appointments</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon appointments">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3>$<?php 
                                        $avg_daily_revenue = count($daily_revenue) > 0 ? $stats['revenue']['total'] / count($daily_revenue) : 0;
                                        echo number_format($avg_daily_revenue, 2); 
                                    ?></h3>
                                    <p>Average Daily Revenue</p>
                                    <small>Based on <?php echo count($daily_revenue); ?> active days</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon patients">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3>$<?php echo number_format($stats['revenue']['average_per_appointment'], 2); ?></h3>
                                    <p>Average per Appointment</p>
                                    <small>Revenue per completed appointment</small>
                                </div>
                            </div>
                            
                            <div class="kpi-card">
                                <div class="kpi-icon doctors">
                                    <i class="fas fa-trending-up"></i>
                                </div>
                                <div class="kpi-content">
                                    <h3><?php echo count($doctor_performance); ?></h3>
                                    <p>Revenue-Generating Doctors</p>
                                    <small>Active in this period</small>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Charts -->
                        <div class="charts-row">
                            <div class="chart-container">
                                <h3>Revenue by Specialty</h3>
                                <canvas id="specialtyRevenueChart" width="400" height="300"></canvas>
                            </div>
                            <div class="specialty-breakdown">
                                <h3>Specialty Breakdown</h3>
                                <?php foreach ($specialty_revenue as $specialty): ?>
                                    <div class="specialty-revenue-item">
                                        <div class="specialty-info">
                                            <h4><?php echo htmlspecialchars($specialty['specialty']); ?></h4>
                                            <p><?php echo $specialty['completed_appointments']; ?> appointments</p>
                                        </div>
                                        <div class="specialty-revenue">
                                            <strong>$<?php echo number_format($specialty['total_revenue'], 2); ?></strong>
                                            <small>Avg: $<?php echo number_format($specialty['avg_fee'], 2); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Daily Revenue Chart -->
                        <div class="chart-container-large">
                            <h3>Daily Revenue Trend</h3>
                            <canvas id="dailyRevenueChart" width="800" height="400"></canvas>
                        </div>

                        <!-- Monthly Revenue Chart -->
                        <div class="chart-container-large">
                            <h3>12-Month Revenue Trend</h3>
                            <canvas id="monthlyRevenueChart" width="800" height="400"></canvas>
                        </div>
                    </div>

                    <!-- Performance Tab -->
                    <div class="tab-content" id="performance-tab">
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
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-content" id="analytics-tab">
                        <!-- Peak Hours Analysis -->
                        <div class="chart-container-large">
                            <h3>Peak Hours Analysis</h3>
                            <canvas id="hourlyChart" width="800" height="300"></canvas>
                        </div>

                        <!-- Specialty Analysis -->
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });
});

// Appointment Status Pie Chart
const statusCtx = document.getElementById('statusPieChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled', 'No Show'],
        datasets: [{
            data: [
                <?php echo $stats['appointments']['completed']; ?>,
                <?php echo $stats['appointments']['confirmed']; ?>,
                <?php echo $stats['appointments']['pending']; ?>,
                <?php echo $stats['appointments']['cancelled']; ?>,
                <?php echo $stats['appointments']['no_show']; ?>
            ],
            backgroundColor: [
                '#4caf50',
                '#2196f3',
                '#ff9800',
                '#f44336',
                '#9e9e9e'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed;
                    }
                }
            }
        }
    }
});

// Daily Trends Chart
const trendsCtx = document.getElementById('dailyTrendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: [<?php echo '"' . implode('","', array_map(function($trend) { return formatDate($trend['date'], 'M j'); }, $daily_trends)) . '"'; ?>],
        datasets: [{
            label: 'Total Appointments',
            data: [<?php echo implode(',', array_column($daily_trends, 'total_appointments')); ?>],
            borderColor: '#00bcd4',
            backgroundColor: 'rgba(0, 188, 212, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Completed',
            data: [<?php echo implode(',', array_column($daily_trends, 'completed')); ?>],
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y;
                    }
                }
            }
        }
    }
});

// Specialty Revenue Pie Chart
const specialtyCtx = document.getElementById('specialtyRevenueChart').getContext('2d');
new Chart(specialtyCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo '"' . implode('","', array_column($specialty_revenue, 'specialty')) . '"'; ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($specialty_revenue, 'total_revenue')); ?>],
            backgroundColor: [
                '#00bcd4', '#4caf50', '#ff9800', '#2196f3', '#9c27b0', 
                '#f44336', '#795548', '#607d8b', '#ffeb3b', '#e91e63'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});

// Daily Revenue Chart
const dailyRevenueCtx = document.getElementById('dailyRevenueChart').getContext('2d');
new Chart(dailyRevenueCtx, {
    type: 'line',
    data: {
        labels: [<?php echo '"' . implode('","', array_map(function($day) { return formatDate($day['date'], 'M j'); }, $daily_revenue)) . '"'; ?>],
        datasets: [{
            label: 'Daily Revenue',
            data: [<?php echo implode(',', array_column($daily_revenue, 'revenue')); ?>],
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});

// Monthly Revenue Chart
const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo '"' . implode('","', array_column($monthly_revenue, 'month')) . '"'; ?>],
        datasets: [{
            label: 'Monthly Revenue',
            data: [<?php echo implode(',', array_column($monthly_revenue, 'revenue')); ?>],
            backgroundColor: '#00bcd4',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});

// Hourly Distribution Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            $hours = array_column($hourly_stats, 'hour');
            $hour_labels = array_map(function($h) { return '"' . sprintf('%02d:00', $h) . '"'; }, $hours);
            echo implode(',', $hour_labels);
        ?>],
        datasets: [{
            label: 'Appointments',
            data: [<?php echo implode(',', array_column($hourly_stats, 'appointment_count')); ?>],
            backgroundColor: '#2196f3',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Appointments: ' + context.parsed.y;
                    }
                }
            }
        }
    }
});
</script>

<style>
/* Tab Navigation */
.report-tabs {
    margin-top: 1.5rem;
}

.tab-navigation {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #f0f0f0;
}

.tab-button {
    padding: 1rem 1.5rem;
    border: none;
    background: none;
    color: var(--text-light);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.tab-button:hover {
    color: var(--primary-cyan);
    background: rgba(0, 188, 212, 0.05);
}

.tab-button.active {
    color: var(--primary-cyan);
    border-bottom-color: var(--primary-cyan);
    background: rgba(0, 188, 212, 0.1);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-content h3 {
    margin-bottom: 1rem;
    color: var(--text-dark);
    font-size: 1.2rem;
}

/* Responsive tabs */
@media (max-width: 768px) {
    .tab-navigation {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .tab-button {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
