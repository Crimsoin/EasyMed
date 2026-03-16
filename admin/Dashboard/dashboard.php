<?php
$page_title = 'Admin Dashboard';
$page_description = 'EasyMed Admin Dashboard - Manage your clinic system';
$additional_css = ['admin/sidebar.css', 'admin/dashboard.css', 'admin/appointment.css', 'shared-modal.css']; // Include sidebar, dashboard and appointment CSS
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

// Core Appointment Statistics (Lifetime Total for Dashboard Cards)
$stats['appointments'] = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM appointments")['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")['count'],
    'scheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'")['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")['count'],
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'no_show'")['count'],
    'rescheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'rescheduled'")['count']
];

// Calculate rates based on RESOLVED appointments (those that have reached a terminal state)
$resolved_appointments = $stats['appointments']['completed'] + $stats['appointments']['cancelled'] + $stats['appointments']['no_show'];
$stats['rates'] = [
    'cancellation_rate' => $resolved_appointments > 0 ? round(($stats['appointments']['cancelled'] / $resolved_appointments) * 100, 1) : 0,
    'completion_rate' => $resolved_appointments > 0 ? round(($stats['appointments']['completed'] / $resolved_appointments) * 100, 1) : 0,
    'no_show_rate' => $resolved_appointments > 0 ? round(($stats['appointments']['no_show'] / $resolved_appointments) * 100, 1) : 0
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
            
            // Automatically verify payment if confirmed during scheduling
            if ($new_status === 'scheduled' && isset($_POST['verify_payment'])) {
                $db->query("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = datetime('now') WHERE appointment_id = ? AND status != 'verified'", [$_SESSION['user_id'], $appointment_id]);
                logActivity($_SESSION['user_id'], 'confirm_payment', "Payment verified during status update for appointment #$appointment_id");
            }

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

// Get appointment management statistics
$appt_stats = [
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
    <button class="sidebar-toggle" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

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
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's your clinic overview.</p>
            
            <!-- Premium Real-time Clock Dashboard -->
            <div class="datetime-display" style="
                margin: 1.5rem 0; 
                padding: 1.5rem 2rem; 
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.03) 100%); 
                border-radius: 16px; 
                border: 1px solid rgba(37, 99, 235, 0.15);
                box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.05);
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: relative;
                overflow: hidden;">
                
                <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                    <div style="
                        width: 60px; 
                        height: 60px; 
                        background: white; 
                        border-radius: 14px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.1);
                        color: var(--primary-cyan);
                        font-size: 1.75rem;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.02em;" id="current-date"></div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                padding: 4px 10px;
                                background: #dcfce7;
                                color: #166534;
                                border-radius: 20px;
                                font-size: 0.7rem;
                                font-weight: 800;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;">
                                <span style="width: 6px; height: 6px; background: #166534; border-radius: 50%; display: inline-block; animation: pulse 2s infinite;"></span>
                                Live System Time
                            </span>
                            <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary-cyan); font-family: 'JetBrains Mono', 'Courier New', monospace;" id="current-time"></div>
                        </div>
                    </div>
                </div>

                <!-- Abstract Decorative Element -->
                <div style="position: absolute; right: -20px; top: -20px; opacity: 0.03; font-size: 8rem; pointer-events: none;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>

            <style>
            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.5); opacity: 0.5; }
                100% { transform: scale(1); opacity: 1; }
            }
            #current-time { transition: all 0.2s ease; }
            </style>
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
                    <small><?php echo $stats['rates']['completion_rate']; ?>% success rate</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['appointments']['scheduled'] + $stats['appointments']['pending']; ?></h3>
                    <p>Total Upcoming</p>
                    <small><?php echo $stats['appointments']['scheduled']; ?> confirmed, <?php echo $stats['appointments']['pending']; ?> pending</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['appointments']['rescheduled']; ?></h3>
                    <p>Rescheduled</p>
                    <small><?php echo $stats['appointments']['total'] > 0 ? round(($stats['appointments']['rescheduled'] / $stats['appointments']['total']) * 100, 1) : 0; ?>% reschedule rate</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rates']['no_show_rate']; ?>%</h3>
                    <p>No Show Rate</p>
                    <small><?php echo $stats['appointments']['no_show']; ?> total no-shows</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rates']['cancellation_rate']; ?>%</h3>
                    <p>Cancellation Rate</p>
                    <small><?php echo $stats['appointments']['cancelled']; ?> total cancelled</small>
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
                <h2><i class="fas fa-chart-line"></i> Appointments Overview</h2>
                <div class="date-range-info">
                    <span id="current-period-label">Today</span>
                    <small id="current-date-range"><?php echo date('M j, Y'); ?></small>
                </div>
            </div>
            <div class="appointments-overview-card">
                <div class="filter-controls">
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-period="daily" onclick="filterAppointments('daily')">
                            Daily View
                        </button>
                        <button class="filter-btn" data-period="weekly" onclick="filterAppointments('weekly')">
                            Weekly
                        </button>
                        <button class="filter-btn" data-period="monthly" onclick="filterAppointments('monthly')">
                            Monthly
                        </button>
                    </div>
                </div>
                
                <div class="appointments-stats-grid">
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="total-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now')")['count']; ?>
                        </div>
                        <div class="stat-label">Appointment</div>
                        <div class="stat-change" id="appointments-change">
                            <i class="fas fa-minus"></i> <span>0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="completed-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status = 'completed'")['count']; ?>
                        </div>
                        <div class="stat-label">Success</div>
                        <div class="stat-change" id="completed-change">
                            <i class="fas fa-minus"></i> <span>0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="pending-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status IN ('pending', 'scheduled', 'rescheduled', 'ongoing')")['count']; ?>
                        </div>
                        <div class="stat-label">Active</div>
                        <div class="stat-change" id="pending-change">
                            <i class="fas fa-minus"></i> <span>0%</span>
                        </div>
                    </div>
                    
                    <div class="appointment-stat-item">
                        <div class="stat-value" id="cancelled-appointments">
                            <?php echo $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = date('now') AND status IN ('cancelled', 'no_show')")['count']; ?>
                        </div>
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-change" id="cancelled-change">
                            <i class="fas fa-minus"></i> <span>0%</span>
                        </div>
                    </div>
                </div>
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
                <h2><i class="fas fa-list-ul"></i> Managed Appointments (<?php echo $total_appointments; ?>)</h2>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Patient Identity</th>
                            <th>Clinical Expert</th>
                            <th>Schedule</th>
                            <th>Status Code</th>
                            <th>Valuation</th>
                            <th>Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-family: 'JetBrains Mono', monospace;">#<?php echo str_pad($appointment['id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                                    <td>
                                        <div class="user-info">
                                            <div class="doctor-avatar-initials" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb;">
                                                <?php echo strtoupper(substr($appointment['patient_first_name'], 0, 1) . substr($appointment['patient_last_name'] ?? '', 0, 1)); ?>
                                            </div>
                                            <div class="user-details" style="padding-left: 10px;">
                                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                                <p style="margin: 0; font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-details" style="padding-left: 0;">
                                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h4>
                                                <span class="specialty-badge" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; margin-top: 4px;"><?php echo htmlspecialchars($appointment['specialty']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="log-timestamp">
                                            <strong style="color: #334155;"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                            <br>
                                            <small style="color: #64748b;"><i class="far fa-clock"></i> <?php echo formatTime($appointment['appointment_time']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($appointment['status'] ?: 'pending'); ?>">
                                            <?php echo strtoupper(htmlspecialchars($appointment['status'] ?: 'pending')); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong style="color: #0f172a; font-size: 0.95rem;">₱<?php echo number_format($appointment['display_fee'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="appointment-actions">
                                            <button type="button" class="btn btn-view" 
                                                    onclick="viewAppointment(<?php echo $appointment['id']; ?>)" 
                                                    title="View Full Profile">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-edit" 
                                                    onclick="editAppointment(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>')" 
                                                    title="Modify Status">
                                                <i class="fas fa-sliders-h"></i>
                                            </button>

                                            <?php if (in_array($appointment['status'], ['scheduled', 'rescheduled'])): ?>
                                                <button type="button" class="btn btn-reschedule" 
                                                        onclick="openRescheduleModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['appointment_date']; ?>', '<?php echo $appointment['appointment_time']; ?>')" 
                                                        title="Re-schedule">
                                                    <i class="fas fa-calendar-day"></i>
                                                </button>
                                            <?php endif; ?>


                                        </div>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                            
                            <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <div style="padding: 4rem; text-align: center;">
                                        <i class="fas fa-search" style="font-size: 3.5rem; color: #cbd5e1; margin-bottom: 1.5rem; display: block;"></i>
                                        <h3 style="color: #475569; font-weight: 700; font-size: 1.4rem; margin-bottom: 0.5rem;">No Matching Records Found</h3>
                                        <p style="color: #94a3b8; font-size: 0.95rem; max-width: 400px; margin: 0 auto 1.5rem auto;">
                                            We couldn't find any clinic records matching your current filter criteria.
                                            Try adjusting your parameters or clear the workspace.
                                        </p>
                                        <a href="dashboard.php" class="btn" style="background: #eff6ff; color: #2563eb; border-radius: 12px; font-weight: 700; padding: 0.8rem 1.5rem;">
                                            <i class="fas fa-redo"></i> Reset Workspace
                                        </a>
                                    </div>
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

        <!-- Doctor Performance -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-medal"></i> Professional Performance Index</h2>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Professional Profile</th>
                            <th>Clinical Domain</th>
                            <th>Total Vol.</th>
                            <th>Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctor_performance as $doctor): ?>
                        <?php 
                            $name_parts = explode(' ', $doctor['doctor_name']);
                            $initials = (isset($name_parts[0][0]) ? $name_parts[0][0] : '') . (isset($name_parts[1][0]) ? $name_parts[1][0] : '');
                            $completion_rate = $doctor['completion_rate'] ?: 0;
                            $rate_class = $completion_rate >= 80 ? 'good' : ($completion_rate >= 60 ? 'average' : 'poor');
                        ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="doctor-avatar-initials"><?php echo strtoupper($initials); ?></div>
                                    <div class="user-details">
                                        <h4>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></h4>
                                        <p>Medical Practitioner</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="specialty-badge">
                                    <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialty']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $doctor['total_appointments']; ?></strong></td>
                            <td style="width: 200px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <span style="font-weight: 700; font-size: 0.85rem; color: #1e293b;"><?php echo $completion_rate; ?>%</span>
                                    <span style="font-size: 0.75rem; color: #64748b;"><?php echo $doctor['completed_appointments']; ?> Completed</span>
                                </div>
                                <div class="performance-meter">
                                    <div class="performance-fill fill-<?php echo $rate_class; ?>" style="width: <?php echo $completion_rate; ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($doctor_performance)): ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-chart-bar"></i> No metrics recorded for the selected window.
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
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Operator</th>
                                <th>Identity</th>
                                <th>Operation</th>
                                <th>Details</th>
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
                                            <span style="color: #94a3b8; font-weight: 500;">Core System</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $log['role'] ?: 'system'; ?>">
                                        <?php echo strtoupper($log['role'] ?: 'system'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="log-action">
                                        <i class="fas fa-<?php 
                                            echo match(strtolower($log['action'])) {
                                                'login' => 'shield-alt',
                                                'logout' => 'power-off',
                                                'register' => 'user-plus',
                                                'update_profile' => 'user-edit',
                                                'book_appointment' => 'calendar-plus',
                                                'cancel_appointment' => 'calendar-times',
                                                'view_profile' => 'id-card',
                                                default => 'microchip'
                                            };
                                        ?>"></i>
                                        <?php echo str_replace('_', ' ', strtoupper($log['action'])); ?>
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
                                    <i class="fas fa-terminal"></i> No system operations recorded for this window.
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
    color: #2563eb;
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
    background: #2563eb;
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
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
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
    background: rgba(37, 99, 235, 0.02);
    border: 1px solid rgba(37, 99, 235, 0.1);
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
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
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
            // Logic to get the Monday of the current week
            const monday = new Date(now);
            const day = now.getDay();
            const diff = now.getDate() - day + (day === 0 ? -6 : 1);
            monday.setDate(diff);
            
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            
            label.textContent = 'This Week';
            dateRange.textContent = `${monday.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${sunday.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
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
    // Combine cancellations and no-shows for the loss count
    document.getElementById('cancelled-appointments').textContent = (stats.cancelled || 0) + (stats.no_show || 0);
    
    // Update change indicators
    updateChangeIndicator('appointments-change', changes.total || 0);
    updateChangeIndicator('completed-change', changes.completed || 0);
    updateChangeIndicator('pending-change', changes.pending || 0);
    // Combine changes for cancellations/no-shows if possible, otherwise use cancelled change
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

// Update Live Clock
function updateLiveClock() {
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (!dateElement || !timeElement) return;
    
    const now = new Date();
    
    // Format Date: Weekday, Month Day, Year
    dateElement.textContent = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Format Time: HH:MM:SS AM/PM
    timeElement.textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodLabel('daily');
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
});
</script>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3><i class="fas fa-file-medical"></i> Appointment Overview</h3>
            <span class="close" onclick="document.getElementById('appointmentModal').style.display='none'"><i class="fas fa-times"></i></span>
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
            <h3><i class="fas fa-sliders-h"></i> Update Appointment Status</h3>
            <span class="close-modal" onclick="closeModal('statusModal')"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body">
            <form id="statusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="statusAppointmentId">
                
                <div class="modal-section">
                    <div class="form-group">
                        <label for="statusSelect" class="form-label" style="font-weight: 600; color: #475569;">Status</label>
                        <select name="status" id="statusSelect" class="form-control" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem; font-size: 0.95rem; color: #1e293b; background-color: #f8fafc;" required>
                            <option value="pending">Pending</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 1.5rem 2rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 1rem; border-radius: 0 0 12px 12px;">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt"></i> Reschedule Appointment</h3>
            <span class="close-modal" onclick="closeModal('rescheduleModal')"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body">
            <form id="rescheduleForm" method="POST">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" id="rescheduleAppointmentId">
                
                <div class="modal-section" style="display: grid; gap: 1.5rem; border-bottom: none;">
                    <div class="form-group">
                        <label for="rescheduleDate" class="form-label" style="font-weight: 600; color: #475569;">New Date</label>
                        <input type="date" name="appointment_date" id="rescheduleDate" class="form-control" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem; font-size: 0.95rem; color: #1e293b; background-color: #f8fafc;" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="rescheduleTime" class="form-label" style="font-weight: 600; color: #475569;">New Time</label>
                        <select name="appointment_time" id="rescheduleTime" class="form-control" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem; font-size: 0.95rem; color: #1e293b; background-color: #f8fafc;" required>
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
                </div>
                
                <div class="modal-footer" style="padding: 1.5rem 2rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 1rem; border-radius: 0 0 12px 12px;">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Reschedule Appointment</button>
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
            
            const initials = (appointment.patient_first_name[0] + (appointment.patient_last_name ? appointment.patient_last_name[0] : '')).toUpperCase();
            
            detailsDiv.innerHTML = `
                <div class="appointment-details-premium" style="background: #fdfdfd; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif;">
                    
                    <!-- 1. Patient Hero Banner -->
                    <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 32px 40px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 24px;">
                            <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.75rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);">
                                ${initials}
                            </div>
                            <div>
                                <h1 style="color: #0f172a; font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.04em;">${appointment.first_name || (appointment.patient_first_name + ' ' + appointment.patient_last_name)} ${appointment.last_name || ''}</h1>
                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                                    <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">#APT-${appointment.id.toString().padStart(5, '0')}</span></span>
                                    <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                                    <span class="status-badge status-${appointment.status}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">${appointment.status.toUpperCase()}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 40px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px;">
                        
                        <!-- 2. Appointment Schedule Card -->
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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
                                        ${appointment.purpose === 'consultation' ? 'Medical Consultation' : appointment.reason_for_visit}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Expert Consultation Card -->
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
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

                        <!-- 4. Patient Profile Card -->
                        <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-info-circle" style="color: white; font-size: 0.9rem;"></i> Information Details
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px;">
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Relationship</label>
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${appointment.relationship || 'Self'}</div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Age Group</label>
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${calculateAge(appointment.date_of_birth)} Years</div>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                                    <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${appointment.gender || 'N/A'}</div>
                                </div>
                                <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 32px;">
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                                        <div style="font-size: 0.9rem; color: #475569;">${appointment.email || appointment.patient_email || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Phone Contact</label>
                                        <div style="font-size: 0.9rem; color: #475569;">${appointment.phone_number || appointment.patient_phone || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Home Address</label>
                                        <div style="font-size: 0.9rem; color: #475569; line-height: 1.5;">${appointment.address || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Transaction Summary Card -->
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-receipt" style="color: white; font-size: 0.9rem;"></i> Payment Summary
                            </h3>
                            <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                    <span style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Amount Recieved</span>
                                    <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${payment ? parseFloat(payment.amount).toFixed(2) : '0.00'}</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                    <div>
                                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                        <span class="status-badge status-${payment ? payment.status : 'pending'}" style="font-weight: 800; font-size: 0.75rem;">${payment ? payment.status.toUpperCase() : 'PENDING'}</span>
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
                        <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                                <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                            </h3>
                            <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                                <img src="../../${payment.receipt_path}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../../${payment.receipt_path}', '_blank')">
                            </div>
                        </div>
                        ` : ''}

                        <!-- 7. Observations Card (Full Width) -->
                        <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                            <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-file-medical-alt" style="color: white; font-size: 0.9rem;"></i> Clinical Records
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 20px;">
                                <div>
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Reason for Visit</label>
                                    <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${appointment.illness || appointment.reason_for_visit || 'General Consultation'}</div>
                                </div>
                                <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 16px;">
                                    <label style="display: block; font-size: 0.75rem; color: #2563eb; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Doctor's Findings</label>
                                    <div style="font-size: 0.95rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic;">
                                        ${appointment.notes || '"No records available yet."'}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> <!-- End of Card Grid -->

                    <!-- Modal Actions Footer -->
                    ${appointment.status === 'pending' ? `
                    <div style="margin-top: 32px; padding: 24px 40px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; background: #f8fafc; border-radius: 0 0 20px 20px;">
                        <form method="POST" style="display: flex; align-items: center; gap: 24px;" onsubmit="return confirm('Confirm marking this appointment as Scheduled?')">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="appointment_id" value="${appointment.id}">
                            <input type="hidden" name="status" value="scheduled">
                            
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px 18px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer;">
                                <input type="checkbox" id="verify_payment" name="verify_payment" value="1" style="width: 18px; height: 18px; cursor: pointer;" 
                                    onchange="this.form.querySelector('button').disabled = !this.checked; this.form.querySelector('button').style.opacity = this.checked ? '1' : '0.5'; this.form.querySelector('button').style.cursor = this.checked ? 'pointer' : 'not-allowed';">
                                <label for="verify_payment" style="font-size: 0.9rem; font-weight: 700; color: #475569; cursor: pointer; user-select: none;">Payment Verified</label>
                            </div>

                            <button type="submit" class="modal-btn" 
                                disabled style="opacity: 0.5; cursor: not-allowed; padding: 12px 28px; font-size: 0.95rem; font-weight: 800; border-radius: 12px; display: flex; align-items: center; gap: 10px; border: none; background: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15); transition: all 0.2s;">
                                <i class="fas fa-calendar-check"></i> Mark Scheduled
                            </button>
                        </form>
                    </div>
                    ` : ''}
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
