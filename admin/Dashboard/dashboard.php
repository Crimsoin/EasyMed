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

require_once '../../includes/email.php';
$emailService = new EmailService();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if ($action === 'update_status' && $appointment_id) {
        $new_status = $_POST['status'] ?? '';
        
        try {
            // Get appointment and patient details before updating
            $appointment_details = $db->fetch("
                SELECT a.*, 
                       pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.email as patient_email,
                       du.first_name as doctor_first_name, du.last_name as doctor_last_name,
                       d.specialty, d.consultation_fee
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN users du ON d.user_id = du.id
                WHERE a.id = ?
            ", [$appointment_id]);
            
            $db->query("UPDATE appointments SET status = ?, updated_at = datetime('now') WHERE id = ?", 
                      [$new_status, $appointment_id]);
            
            // Send email notification based on status change
            if ($appointment_details && $appointment_details['patient_email']) {
                $patient_email = $appointment_details['patient_email'];
                $patient_name = $appointment_details['patient_first_name'] . ' ' . $appointment_details['patient_last_name'];
                $doctor_name = 'Dr. ' . $appointment_details['doctor_first_name'] . ' ' . $appointment_details['doctor_last_name'];
                
                $appointment_data = [
                    'appointment_id' => $appointment_id,
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'specialty' => $appointment_details['specialty'],
                    'appointment_date' => formatDate($appointment_details['appointment_date']),
                    'appointment_time' => formatTime($appointment_details['appointment_time']),
                    'reason' => $appointment_details['reason_for_visit'] ?? 'General consultation',
                    'fee' => number_format($appointment_details['consultation_fee'], 2)
                ];
                
                switch ($new_status) {
                    case 'scheduled':
                        $emailService->sendAppointmentScheduled($patient_email, $patient_name, $appointment_data);
                        break;
                    case 'cancelled':
                        $emailService->sendAppointmentCancelled($patient_email, $patient_name, $appointment_data);
                        break;
                }
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'update_appointment', "Updated appointment #$appointment_id status to $new_status");
            
            $_SESSION['success_message'] = "Appointment status updated successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating appointment status.";
        }
        
        header('Location: dashboard.php');
        exit();
    }
    
    if ($action === 'delete' && $appointment_id) {
        try {
            // Get appointment and patient details before cancelling
            $appointment_details = $db->fetch("
                SELECT a.*, 
                       pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.email as patient_email,
                       du.first_name as doctor_first_name, du.last_name as doctor_last_name,
                       d.specialty, d.consultation_fee
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN users du ON d.user_id = du.id
                WHERE a.id = ?
            ", [$appointment_id]);
            
            $db->query("UPDATE appointments SET status = 'cancelled', updated_at = datetime('now') WHERE id = ?", [$appointment_id]);
            
            // Send cancellation email notification
            if ($appointment_details && $appointment_details['patient_email']) {
                $patient_email = $appointment_details['patient_email'];
                $patient_name = $appointment_details['patient_first_name'] . ' ' . $appointment_details['patient_last_name'];
                $doctor_name = 'Dr. ' . $appointment_details['doctor_first_name'] . ' ' . $appointment_details['doctor_last_name'];
                
                $appointment_data = [
                    'appointment_id' => $appointment_id,
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'specialty' => $appointment_details['specialty'],
                    'appointment_date' => formatDate($appointment_details['appointment_date']),
                    'appointment_time' => formatTime($appointment_details['appointment_time']),
                    'reason' => $appointment_details['reason_for_visit'] ?? 'General consultation',
                    'fee' => number_format($appointment_details['consultation_fee'], 2)
                ];
                
                $emailService->sendAppointmentCancelled($patient_email, $patient_name, $appointment_data);
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'cancel_appointment', "Cancelled appointment #$appointment_id");
            
            $_SESSION['success_message'] = "Appointment cancelled successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error cancelling appointment.";
        }
        
        header('Location: dashboard.php');
        exit();
    }

    // Admin confirms uploaded payment / verifies payment
    if ($action === 'confirm_payment' && $appointment_id) {
        try {
            // Mark payment record as verified if a payments table exists for this appointment
            $db->query("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = datetime('now') WHERE appointment_id = ? AND status != 'verified'", [$_SESSION['user_id'], $appointment_id]);

            // Log activity
            logActivity($_SESSION['user_id'], 'confirm_payment', "Confirmed payment for appointment #$appointment_id");

            $_SESSION['success_message'] = "Payment confirmed successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error confirming payment.";
        }

        header('Location: dashboard.php');
        exit();
    }

    // Admin can unverify a previously verified payment
    if ($action === 'reschedule' && $appointment_id) {
        $new_date = $_POST['appointment_date'] ?? '';
        $new_time = $_POST['appointment_time'] ?? '';
        
        if (empty($new_date) || empty($new_time)) {
            $_SESSION['error_message'] = "Please provide both date and time for rescheduling.";
            header('Location: dashboard.php');
            exit();
        }
        
        try {
            // Get appointment and patient details before rescheduling
            $appointment_details = $db->fetch("
                SELECT a.*, 
                       pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.email as patient_email,
                       du.first_name as doctor_first_name, du.last_name as doctor_last_name,
                       d.specialty, d.consultation_fee
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN users du ON d.user_id = du.id
                WHERE a.id = ?
            ", [$appointment_id]);
            
            // Check if the new slot is available
            $existing_appointment = $db->fetch("
                SELECT id FROM appointments 
                WHERE doctor_id = (SELECT doctor_id FROM appointments WHERE id = ?) 
                AND appointment_date = ? 
                AND appointment_time = ? 
                AND status NOT IN ('cancelled', 'no_show')
                AND id != ?
            ", [$appointment_id, $new_date, $new_time, $appointment_id]);
            
            if ($existing_appointment) {
                $_SESSION['error_message'] = "The selected date and time slot is not available. Please choose a different time.";
                header('Location: dashboard.php');
                exit();
            }
            
            // Get current appointment status to preserve it appropriately
            $current_appointment = $db->fetch("SELECT status FROM appointments WHERE id = ?", [$appointment_id]);
            $current_status = $current_appointment['status'];

            // Determine the new status based on current status
            $new_status = $current_status;
            if ($current_status === 'pending' || $current_status === 'scheduled') {
                // When rescheduling from pending or scheduled, set to rescheduled
                $new_status = 'rescheduled';
            } elseif ($current_status === 'rescheduled') {
                // Keep as rescheduled to maintain the rescheduled state
                $new_status = 'rescheduled';
            }
            // For completed, cancelled, no_show - reschedule shouldn't be available
            
            // Update appointment with new date/time and appropriate status
            $db->query("
                UPDATE appointments 
                SET appointment_date = ?, appointment_time = ?, status = ?, updated_at = datetime('now') 
                WHERE id = ?
            ", [$new_date, $new_time, $new_status, $appointment_id]);
            
            // Send reschedule email notification
            if ($appointment_details && $appointment_details['patient_email']) {
                $patient_email = $appointment_details['patient_email'];
                $patient_name = $appointment_details['patient_first_name'] . ' ' . $appointment_details['patient_last_name'];
                $doctor_name = 'Dr. ' . $appointment_details['doctor_first_name'] . ' ' . $appointment_details['doctor_last_name'];
                
                $appointment_data = [
                    'appointment_id' => $appointment_id,
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'specialty' => $appointment_details['specialty'],
                    'appointment_date' => formatDate($new_date), // Use new date
                    'appointment_time' => formatTime($new_time), // Use new time
                    'old_date' => formatDate($appointment_details['appointment_date']), // Include old date for reference
                    'old_time' => formatTime($appointment_details['appointment_time']), // Include old time for reference
                    'reason' => $appointment_details['reason_for_visit'] ?? 'General consultation',
                    'fee' => number_format($appointment_details['consultation_fee'], 2)
                ];
                
                $emailService->sendAppointmentRescheduled($patient_email, $patient_name, $appointment_data);
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'reschedule_appointment', "Rescheduled appointment #$appointment_id to $new_date $new_time (status: $new_status)");
            
            $_SESSION['success_message'] = "Appointment rescheduled successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error rescheduling appointment.";
        }
        
        header('Location: dashboard.php');
        exit();
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Get all doctors for filter dropdown
$doctors = $db->fetchAll("
    SELECT d.id, u.first_name, u.last_name, d.specialty 
    FROM doctors d
    JOIN users u ON d.user_id = u.id 
    WHERE u.role = 'doctor' AND u.is_active = 1 
    ORDER BY u.first_name, u.last_name
");

// Build dynamic query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(pu.first_name LIKE ? OR pu.last_name LIKE ? OR du.first_name LIKE ? OR du.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if (!empty($doctor_filter)) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "date(a.appointment_date) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get appointments with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$appointments = $db->fetchAll("
    SELECT a.*, 
           pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.email as patient_email, 
           p.phone as patient_phone,
           du.first_name as doctor_first_name, du.last_name as doctor_last_name,
           d.id as doctor_internal_id, d.specialty, d.consultation_fee
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users pu ON p.user_id = pu.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN users du ON d.user_id = du.id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT $per_page OFFSET $offset
", $params);

// Calculate correct fee for each appointment
foreach ($appointments as &$appointment) {
    $patient_info = json_decode($appointment['patient_info'], true);
    $purpose = $patient_info['purpose'] ?? 'consultation';
    $laboratory_name = $patient_info['laboratory'] ?? '';
    
    // Default to consultation fee
    $appointment['display_fee'] = $appointment['consultation_fee'];
    
    // If laboratory, try to fetch from lab_offers table
    if ($purpose === 'laboratory' && !empty($laboratory_name) && !empty($appointment['doctor_internal_id'])) {
        $lab_offer = $db->fetch("
            SELECT lo.price 
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lo.title = ? AND lod.doctor_id = ? AND lo.is_active = 1
        ", [$laboratory_name, $appointment['doctor_internal_id']]);
        
        if ($lab_offer && !empty($lab_offer['price'])) {
            $appointment['display_fee'] = $lab_offer['price'];
        }
    }
}
unset($appointment); // Break reference

// Get total count for pagination
$total_appointments = $db->fetch("
    SELECT COUNT(*) as count 
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users pu ON p.user_id = pu.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN users du ON d.user_id = du.id
    WHERE $where_clause
", $params)['count'];

$total_pages = ceil($total_appointments / $per_page);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM appointments")['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status IN ('pending', 'scheduled')")['count'],
    'rescheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'rescheduled'")['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")['count'],
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'no_show'")['count'],
    'today' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE " . db_date_equals('appointment_date'))['count']
];

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
                </div>
                <?php 
                // Set pagination variables
                $logs_per_page = 10;
                $current_log_page = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;
                $log_offset = ($current_log_page - 1) * $logs_per_page;

                // Get total count for pagination
                $total_logs_count = $db->fetch("
                    SELECT COUNT(*) as count 
                    FROM activity_logs al
                    WHERE DATE(al.created_at) BETWEEN ? AND ?
                ", [$start_date, $end_date])['count'];
                
                $total_log_pages = ceil($total_logs_count / $logs_per_page);

                // Get recent activity logs with pagination
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
                    LIMIT ? OFFSET ?
                ", [$start_date, $end_date, $logs_per_page, $log_offset]);
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
                
                <!-- Logs Pagination -->
                <?php if ($total_log_pages > 1): ?>
                    <div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                        <div class="pagination" style="display: flex; gap: 0.5rem;">
                            <?php if ($current_log_page > 1): ?>
                                <a href="?log_page=<?php echo ($current_log_page - 1); ?>" 
                                   class="pagination-btn" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $current_log_page - 2); $i <= min($total_log_pages, $current_log_page + 2); $i++): ?>
                                <a href="?log_page=<?php echo $i; ?>" 
                                   class="pagination-btn" style="padding: 0.5rem 1rem; background: <?php echo $i === $current_log_page ? 'var(--primary-cyan)' : '#fff'; ?>; color: <?php echo $i === $current_log_page ? '#fff' : '#333'; ?>; border: 1px solid <?php echo $i === $current_log_page ? 'var(--primary-cyan)' : '#ccc'; ?>; border-radius: 4px; text-decoration: none;">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($current_log_page < $total_log_pages): ?>
                                <a href="?log_page=<?php echo ($current_log_page + 1); ?>" 
                                   class="pagination-btn" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info" style="color: #666; font-size: 0.9rem;">
                            Showing <?php echo ($log_offset + 1); ?> to <?php echo min($log_offset + $logs_per_page, $total_logs_count); ?> 
                            of <?php echo $total_logs_count; ?> logs
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- Appointment Management -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Appointment Management</h2>
                <p>Manage all appointments in the system</p>
            </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

<!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="dashboard.php" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-search"></i> Search Appointments
                        </label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by patient or doctor name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-toggle-on"></i> Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="rescheduled" <?php echo $status_filter === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-md"></i> Doctor
                        </label>
                        <select name="doctor" class="form-control">
                            <option value="">All Doctors</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> (<?php echo htmlspecialchars($doctor['specialty']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i> Date
                        </label>
                        <input type="date" name="date" class="form-control" 
                               value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="dashboard.php" class="btn-clear">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <div class="filter-info">
                        <?php if ($search || $status_filter || $doctor_filter || $date_filter): ?>
                            <i class="fas fa-info-circle"></i> 
                            Showing <?php echo count($appointments); ?> of <?php echo $total_appointments; ?> appointments
                        <?php else: ?>
                            <i class="fas fa-list"></i> 
                            Showing all <?php echo count($appointments); ?> appointments
                        <?php endif; ?>
                    </div>
                </div>



            </form>
        </div>

        <!-- Appointments Table -->
        <div class="content-section">
            <div class="section-header">
                <h2>All Appointments (<?php echo $total_appointments; ?>)</h2>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo $appointment['id']; ?></td>
                                    <td>
                                        <div class="patient-info">
                                            <strong><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></strong>
                                            <div class="contact-info">
                                                <small><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                                <?php if ($appointment['patient_phone']): ?>
                                                    <br><small><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info">
                                            <strong>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></strong>
                                            <div class="specialty-info">
                                                <small><?php echo htmlspecialchars($appointment['specialty']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="datetime-info">
                                            <strong><?php echo formatDate($appointment['appointment_date']); ?></strong>
                                            <div><?php echo formatTime($appointment['appointment_time']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="reason-text" title="<?php echo htmlspecialchars($appointment['reason_for_visit'] ?? ''); ?>">
                                            <?php 
                                            $reason = $appointment['reason_for_visit'] ?? '';
                                            echo htmlspecialchars(substr($reason, 0, 50) . (strlen($reason) > 50 ? '...' : ''));
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        ₱<?php echo number_format($appointment['display_fee'], 2); ?>
                                    </td>
                                    <td>
                                        <div class="appointment-actions">
                                            <button type="button" class="btn btn-sm btn-view" 
                                                    onclick="viewAppointment(<?php echo $appointment['id']; ?>)" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                                <?php // Update Status - always available ?>
                                                <button type="button" class="btn btn-sm btn-edit" 
                                                        onclick="editAppointment(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>')" 
                                                        title="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <?php // Schedule Appointment - only for pending ?>
                                                <?php if ($appointment['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Are you sure you want to schedule this appointment?')">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <input type="hidden" name="status" value="scheduled">
                                                        <button type="submit" class="btn btn-primary btn-sm" title="Schedule Appointment">
                                                            <i class="fas fa-calendar-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php // Reschedule Appointment - only for scheduled or rescheduled ?>
                                                <?php if (in_array($appointment['status'], ['scheduled', 'rescheduled'])): ?>
                                                    <button type="button" class="btn btn-sm btn-reschedule" 
                                                            onclick="openRescheduleModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['appointment_date']; ?>', '<?php echo $appointment['appointment_time']; ?>')" 
                                                            title="Reschedule Appointment">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php // Cancel - only for pending, scheduled, rescheduled ?>
                                                <?php if (in_array($appointment['status'], ['pending', 'scheduled', 'rescheduled'])): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-delete" title="Cancel">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <?php if ($search || $status_filter || $doctor_filter || $date_filter): ?>
                                        <i class="fas fa-search"></i>
                                        <h3>No appointments found</h3>
                                        <p>
                                            No appointments match your current filters. Try adjusting your search criteria or 
                                            <a href="dashboard.php">clear all filters</a>.
                                        </p>
                                    <?php else: ?>
                                        <i class="fas fa-calendar-plus"></i>
                                        <h3>No appointments scheduled</h3>
                                        <p>
                                            No appointments have been scheduled yet. Appointments will appear here once they are created.
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $doctor_filter ? '&doctor=' . $doctor_filter : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>" 
                                   class="btn btn-pagination">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $doctor_filter ? '&doctor=' . $doctor_filter : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>" 
                                   class="btn btn-pagination <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $doctor_filter ? '&doctor=' . $doctor_filter : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>" 
                                   class="btn btn-pagination">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_appointments); ?> 
                            of <?php echo $total_appointments; ?> appointments
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>Appointment Details</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="appointmentDetails">
            <!-- Content will be loaded via JavaScript -->
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Appointment Status</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="statusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="statusAppointmentId">
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reschedule Appointment</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="rescheduleForm" method="POST">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" id="rescheduleAppointmentId">
                
                <div class="form-group">
                    <label for="appointment_date">New Date</label>
                    <input type="date" name="appointment_date" id="rescheduleDate" class="form-control" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="appointment_time">New Time</label>
                    <select name="appointment_time" id="rescheduleTime" class="form-control" required>
                        <option value="">Select Time</option>
                        <option value="09:00:00">9:00 AM</option>
                        <option value="09:30:00">9:30 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="10:30:00">10:30 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="11:30:00">11:30 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="12:30:00">12:30 PM</option>
                        <option value="13:00:00">1:00 PM</option>
                        <option value="13:30:00">1:30 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="14:30:00">2:30 PM</option>
                        <option value="15:00:00">3:00 PM</option>
                        <option value="15:30:00">3:30 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                        <option value="16:30:00">4:30 PM</option>
                        <option value="17:00:00">5:00 PM</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Reschedule Appointment</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functionality
function viewAppointment(id) {
    // Show loading state
    const modal = document.getElementById('appointmentModal');
    const detailsDiv = document.getElementById('appointmentDetails');
    
    detailsDiv.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading appointment details...</div>';
    modal.style.display = 'block';
    
    // Fetch appointment details via AJAX
    fetch(`get_appointment_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                detailsDiv.innerHTML = `<div style="text-align: center; padding: 2rem; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error: ${data.error}</div>`;
                return;
            }
            
            const appointment = data.appointment;
            const payment = data.payment;
            const patientInfo = data.patient_info;
            
            detailsDiv.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="color: var(--primary-cyan); margin-bottom: 1rem; border-bottom: 2px solid var(--light-cyan); padding-bottom: 0.5rem;">
                            <i class="fas fa-user"></i> Patient Information
                        </h4>
                        <div style="background: #f5f5f5; padding: 1.2rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div style="margin-bottom: 0.7rem;"><strong>Name:</strong> ${appointment.patient_first_name} ${appointment.patient_last_name}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Email:</strong> ${appointment.patient_email || 'N/A'}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Phone:</strong> ${appointment.patient_phone || 'N/A'}</div>
                        </div>
                        
                        ${patientInfo ? `
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.8rem; font-size: 1.1rem;">Booking Information</h5>
                        <div style="background: #f5f5f5; padding: 1.2rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            ${patientInfo.first_name ? `<div style="margin-bottom: 0.7rem;"><strong>Booking Name:</strong> ${patientInfo.first_name} ${patientInfo.last_name}</div>` : ''}
                            ${patientInfo.phone ? `<div style="margin-bottom: 0.7rem;"><strong>Booking Phone:</strong> ${patientInfo.phone}</div>` : ''}
                            ${patientInfo.laboratory ? `<div style="margin-bottom: 0.7rem;"><strong>Laboratory Service:</strong> ${patientInfo.laboratory}</div>` : ''}
                            ${patientInfo.reference_number ? `<div><strong>Reference:</strong> ${patientInfo.reference_number}</div>` : ''}
                        </div>
                        ` : ''}
                        
                        ${payment && payment.receipt_path ? `
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.8rem; font-size: 1.1rem;"><i class="fas fa-image"></i> Payment Screenshot</h5>
                        <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px; text-align: center;">
                            <img src="../../${payment.receipt_path}" alt="Payment Receipt" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;" onclick="window.open('../../${payment.receipt_path}', '_blank')">
                            <div style="margin-top: 0.8rem; font-size: 0.9rem;">
                                <a href="../../${payment.receipt_path}" target="_blank" style="color: var(--primary-cyan); text-decoration: none;">
                                    <i class="fas fa-search-plus"></i> View Full Size
                                </a>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div>
                        <h4 style="color: var(--primary-cyan); margin-bottom: 1rem; border-bottom: 2px solid var(--light-cyan); padding-bottom: 0.5rem;">
                            <i class="fas fa-user-md"></i> Doctor Information
                        </h4>
                        <div style="background: #f5f5f5; padding: 1.2rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div style="margin-bottom: 0.7rem;"><strong>Name:</strong> Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Specialty:</strong> ${appointment.specialty}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>License:</strong> ${appointment.license_number || 'N/A'}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Email:</strong> ${appointment.doctor_email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${appointment.doctor_phone || 'N/A'}</div>
                        </div>
                        
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.8rem; font-size: 1.1rem;">Appointment Details</h5>
                        <div style="background: #f5f5f5; padding: 1.2rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div style="margin-bottom: 0.7rem;"><strong>Date:</strong> ${formatDate(appointment.appointment_date)}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Time:</strong> ${formatTime(appointment.appointment_time)}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Status:</strong> <span class="status-badge status-${appointment.status}">${appointment.status.toUpperCase()}</span></div>
                            <div style="margin-bottom: 0.7rem;"><strong>Fee:</strong> ₱${parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2)}</div>
                            ${appointment.reason_for_visit ? `<div><strong>Reason:</strong> ${appointment.reason_for_visit}</div>` : ''}
                        </div>
                        
                        ${payment ? `
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.8rem; font-size: 1.1rem;">Payment Information</h5>
                        <div style="background: #f5f5f5; padding: 1.2rem; border-radius: 8px;">
                            <div style="margin-bottom: 0.7rem;"><strong>Amount:</strong> ₱${parseFloat(payment.amount || 0).toFixed(2)}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Payment Method:</strong> ${payment.payment_method || 'N/A'}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>GCash Reference:</strong> ${payment.gcash_reference || 'N/A'}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Status:</strong> ${payment.status || 'N/A'}</div>
                            <div style="margin-bottom: 0.7rem;"><strong>Submitted:</strong> ${formatDateTime(payment.created_at)}</div>
                            ${payment.verified_at ? `<div style="margin-bottom: 0.7rem;"><strong>Verified:</strong> ${formatDateTime(payment.verified_at)}</div>` : ''}
                            ${payment.verified_by_name ? `<div><strong>Verified By:</strong> ${payment.verified_by_name} ${payment.verified_by_lastname}</div>` : ''}
                        </div>
                        ` : ''}

                        ${payment ? (payment.status && payment.status !== 'verified' ? `
                        <div style="margin-top: 0.8rem;">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to verify this payment?')">
                                <input type="hidden" name="action" value="confirm_payment">
                                <input type="hidden" name="appointment_id" value="${appointment.id}">
                                <button type="submit" class="btn" style="background: #27ae60; color:#fff; border-radius:4px; padding:0.5rem 0.8rem;">
                                    <i class="fas fa-check"></i> Approve Payment
                                </button>
                            </form>
                        </div>
                        ` : '') : ''}
                    </div>
                </div>
                
            `;
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
            detailsDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error loading appointment details. Please try again.</div>';
        });
}

// Helper functions for formatting
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

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function editAppointment(id, currentStatus) {
    document.getElementById('statusAppointmentId').value = id;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('statusModal').style.display = 'block';
}

function openRescheduleModal(id, currentDate, currentTime) {
    document.getElementById('rescheduleAppointmentId').value = id;
    document.getElementById('rescheduleDate').value = currentDate;
    document.getElementById('rescheduleTime').value = currentTime;
    document.getElementById('rescheduleModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const appointmentModal = document.getElementById('appointmentModal');
    const statusModal = document.getElementById('statusModal');
    const rescheduleModal = document.getElementById('rescheduleModal');
    
    if (event.target === appointmentModal) {
        appointmentModal.style.display = 'none';
    }
    if (event.target === statusModal) {
        statusModal.style.display = 'none';
    }
    if (event.target === rescheduleModal) {
        rescheduleModal.style.display = 'none';
    }
}

// Close modal when clicking X
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.onclick = function() {
        this.closest('.modal').style.display = 'none';
    }
});
</script>

</body>
</html>

<style>
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
</script>

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
