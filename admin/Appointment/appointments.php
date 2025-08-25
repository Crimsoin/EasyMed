<?php
$page_title = "Appointment Management";
$additional_css = ['admin/sidebar.css', 'admin/appointment.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    if ($action === 'update_status' && $appointment_id) {
        $new_status = $_POST['status'] ?? '';
        
        try {
            $db->query("UPDATE appointments SET status = ?, updated_at = " . db_current_datetime() . " WHERE id = ?", 
                      [$new_status, $appointment_id]);
            
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
            $db->query("UPDATE appointments SET status = 'cancelled', updated_at = " . db_current_datetime() . " WHERE id = ?", [$appointment_id]);
            
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
            // and set the appointment payment_status to 'verified'
            $db->query("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = " . db_current_datetime() . " WHERE appointment_id = ? AND status != 'verified'", [$_SESSION['user_id'], $appointment_id]);

            $db->query("UPDATE appointments SET payment_status = 'verified', updated_at = " . db_current_datetime() . " WHERE id = ?", [$appointment_id]);

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
    if ($action === 'unverify_payment' && $appointment_id) {
        try {
            // Reset payments verification fields for this appointment
            $db->query("UPDATE payments SET status = 'submitted', verified_by = NULL, verified_at = NULL, updated_at = " . db_current_datetime() . " WHERE appointment_id = ? AND status = 'verified'", [$appointment_id]);

            // Mark appointment payment_status back to submitted
            $db->query("UPDATE appointments SET payment_status = 'submitted', updated_at = " . db_current_datetime() . " WHERE id = ?", [$appointment_id]);

            logActivity($_SESSION['user_id'], 'unverify_payment', "Unverified payment for appointment #$appointment_id");
            $_SESSION['success_message'] = "Payment unverified successfully.";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error unverifying payment.";
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
           d.specialty, d.consultation_fee
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users pu ON p.user_id = pu.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN users du ON d.user_id = du.id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT $per_page OFFSET $offset
", $params);

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
    'confirmed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed'")['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")['count'],
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
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['confirmed']; ?></h3>
                    <p>Confirmed</p>
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
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
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
                            <th>Payment</th>
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
                                        <span class="payment-badge payment-<?php echo htmlspecialchars($appointment['payment_status'] ?? 'pending'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($appointment['payment_status'] ?? 'pending')); ?>
                                        </span>
                                    </td>

                                    <td>
                                        $<?php echo number_format($appointment['consultation_fee'], 2); ?>
                                    </td>
                                    <td>
                                        <div class="appointment-actions">
                                            <button type="button" class="btn btn-sm btn-view" 
                                                    onclick="viewAppointment(<?php echo $appointment['id']; ?>)" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled'): ?>
                                                <button type="button" class="btn btn-sm btn-edit" 
                                                        onclick="editAppointment(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>')" 
                                                        title="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php 
                                                // Toggle-confirm button: confirms when pending/submitted, unverifies when verified
                                                $paymentStatus = $appointment['payment_status'] ?? 'pending';
                                                $enabledStatuses = ['pending', 'submitted', 'verified'];
                                                $isEnabled = in_array($paymentStatus, $enabledStatuses, true);

                                                if ($paymentStatus === 'verified') {
                                                    $formAction = 'unverify_payment';
                                                    $btnClass = 'btn-secondary';
                                                    $btnIcon = 'fa-undo';
                                                    $btnTitle = 'Set payment to pending';
                                                    $confirmMsg = 'Set this payment back to pending?';
                                                } else {
                                                    $formAction = 'confirm_payment';
                                                    $btnClass = 'btn-warning';
                                                    $btnIcon = 'fa-credit-card';
                                                    $btnTitle = 'Confirm Payment';
                                                    $confirmMsg = 'Mark payment as verified?';
                                                }
                                            ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo htmlspecialchars($confirmMsg); ?>')">
                                                <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $btnClass; ?>" 
                                                        title="<?php echo htmlspecialchars($btnTitle); ?>" 
                                                        <?php echo $isEnabled ? '' : 'disabled'; ?>
                                                        <?php echo $isEnabled ? '' : 'style="opacity:0.55;cursor:not-allowed;"'; ?>
                                                >
                                                    <i class="fas <?php echo $btnIcon; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <?php if ($appointment['status'] !== 'cancelled'): ?>
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
                                <td colspan="9" class="empty-state">
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
    <div class="modal-content" style="max-width: 1000px; width: 90%;">
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
                        <option value="confirmed">Confirmed</option>
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
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <div style="margin-bottom: 0.5rem;"><strong>Name:</strong> ${appointment.patient_first_name} ${appointment.patient_last_name}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Email:</strong> ${appointment.patient_email || 'N/A'}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Phone:</strong> ${appointment.patient_phone || 'N/A'}</div>
                            ${appointment.date_of_birth ? `<div style="margin-bottom: 0.5rem;"><strong>Date of Birth:</strong> ${formatDate(appointment.date_of_birth)}</div>` : ''}
                            ${appointment.gender ? `<div style="margin-bottom: 0.5rem;"><strong>Gender:</strong> ${appointment.gender}</div>` : ''}
                            ${appointment.address ? `<div style="margin-bottom: 0.5rem;"><strong>Address:</strong> ${appointment.address}</div>` : ''}
                        </div>
                        
                        ${patientInfo ? `
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.5rem;">Booking Information</h5>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px;">
                            ${patientInfo.first_name ? `<div style="margin-bottom: 0.5rem;"><strong>Booking Name:</strong> ${patientInfo.first_name} ${patientInfo.last_name}</div>` : ''}
                            ${patientInfo.phone ? `<div style="margin-bottom: 0.5rem;"><strong>Booking Phone:</strong> ${patientInfo.phone}</div>` : ''}
                            ${patientInfo.laboratory ? `<div style="margin-bottom: 0.5rem;"><strong>Laboratory Service:</strong> ${patientInfo.laboratory}</div>` : ''}
                            ${patientInfo.reference_number ? `<div><strong>Reference:</strong> ${patientInfo.reference_number}</div>` : ''}
                        </div>
                        ` : ''}
                    </div>
                    
                    <div>
                        <h4 style="color: var(--primary-cyan); margin-bottom: 1rem; border-bottom: 2px solid var(--light-cyan); padding-bottom: 0.5rem;">
                            <i class="fas fa-user-md"></i> Doctor Information
                        </h4>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <div style="margin-bottom: 0.5rem;"><strong>Name:</strong> Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Specialty:</strong> ${appointment.specialty}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>License:</strong> ${appointment.license_number || 'N/A'}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Email:</strong> ${appointment.doctor_email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${appointment.doctor_phone || 'N/A'}</div>
                        </div>
                        
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.5rem;">Appointment Details</h5>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <div style="margin-bottom: 0.5rem;"><strong>Date:</strong> ${formatDate(appointment.appointment_date)}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Time:</strong> ${formatTime(appointment.appointment_time)}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Status:</strong> <span class="status-badge status-${appointment.status}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span></div>
                            <div style="margin-bottom: 0.5rem;"><strong>Consultation Fee:</strong> ₱${parseFloat(appointment.consultation_fee || 0).toFixed(2)}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Payment Status:</strong> <span class="payment-badge payment-${appointment.payment_status || 'pending'}">${(appointment.payment_status || 'pending').charAt(0).toUpperCase() + (appointment.payment_status || 'pending').slice(1)}</span></div>
                            ${appointment.reason_for_visit ? `<div><strong>Reason:</strong> ${appointment.reason_for_visit}</div>` : ''}
                        </div>
                        
                        ${payment ? `
                        <h5 style="color: var(--primary-cyan); margin-bottom: 0.5rem;">Payment Information</h5>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px;">
                            <div style="margin-bottom: 0.5rem;"><strong>Amount:</strong> ₱${parseFloat(payment.amount || 0).toFixed(2)}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Payment Method:</strong> ${payment.payment_method || 'N/A'}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>GCash Reference:</strong> ${payment.gcash_reference || 'N/A'}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Status:</strong> ${payment.status || 'N/A'}</div>
                            <div style="margin-bottom: 0.5rem;"><strong>Submitted:</strong> ${formatDateTime(payment.created_at)}</div>
                            ${payment.verified_at ? `<div style="margin-bottom: 0.5rem;"><strong>Verified:</strong> ${formatDateTime(payment.verified_at)}</div>` : ''}
                            ${payment.verified_by_name ? `<div><strong>Verified By:</strong> ${payment.verified_by_name} ${payment.verified_by_lastname}</div>` : ''}
                            ${payment.receipt_path ? `<div style="margin-top: 0.5rem;"><a href="../../${payment.receipt_path}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-file-image"></i> View Receipt</a></div>` : ''}
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div style="margin-top: 2rem; text-align: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('appointmentModal')">Close</button>
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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const appointmentModal = document.getElementById('appointmentModal');
    const statusModal = document.getElementById('statusModal');
    
    if (event.target === appointmentModal) {
        appointmentModal.style.display = 'none';
    }
    if (event.target === statusModal) {
        statusModal.style.display = 'none';
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
