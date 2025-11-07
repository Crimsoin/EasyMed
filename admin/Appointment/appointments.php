<?php
$page_title = "Appointment Management";
$additional_css = ['admin/sidebar.css', 'admin/appointment.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';
require_once '../../includes/email.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
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
        
        header('Location: appointments.php');
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
        
        header('Location: appointments.php');
        exit();
    }

    // Admin confirms uploaded payment / verifies payment
    if ($action === 'confirm_payment' && $appointment_id) {
        try {
            // Mark payment record as verified if a payments table exists for this appointment
            $db->query("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = " . date('Y-m-d H:i:s') . " WHERE appointment_id = ? AND status != 'verified'", [$_SESSION['user_id'], $appointment_id]);

            // Log activity
            logActivity($_SESSION['user_id'], 'confirm_payment', "Confirmed payment for appointment #$appointment_id");

            $_SESSION['success_message'] = "Payment confirmed successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error confirming payment.";
        }

        header('Location: appointments.php');
        exit();
    }

    // Admin can unverify a previously verified payment
    if ($action === 'reschedule' && $appointment_id) {
        $new_date = $_POST['appointment_date'] ?? '';
        $new_time = $_POST['appointment_time'] ?? '';
        
        if (empty($new_date) || empty($new_time)) {
            $_SESSION['error_message'] = "Please provide both date and time for rescheduling.";
            header('Location: appointments.php');
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
                header('Location: appointments.php');
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
        
        header('Location: appointments.php');
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
            <a href="../Dashboard/dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="../Patient Management/patients.php" class="nav-item">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Appointment/appointments.php" class="nav-item active">
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
            <h1>Appointment Management</h1>
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

        <!-- Appointment Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-active">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-inactive">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rescheduled']; ?></h3>
                    <p>Rescheduled</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-filtered">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-filtered">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['no_show']; ?></h3>
                    <p>No Show</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="appointments.php" class="filter-form">
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
                    <a href="../Appointment/appointments.php" class="btn-clear">
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
                                            <a href="../Appointment/appointments.php">clear all filters</a>.
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
                        <option value="rescheduled">Rescheduled</option>
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

<?php require_once '../../includes/footer.php'; ?>
