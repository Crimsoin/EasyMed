<?php
$page_title = "Doctor Management";
$additional_css = ['admin/sidebar.css', 'admin/doctor-management.css', 'admin/dashboard.css', 'shared-modal.css'];
$additional_js = ['admin/doctor-management.js'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

require_once '../../includes/email.php';
$emailService = new EmailService();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
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
                    'appointment_date' => date('l, F j, Y', strtotime($appointment_details['appointment_date'])),
                    'appointment_time' => date('h:i A', strtotime($appointment_details['appointment_time'])),
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
            
            $message = "Appointment status updated successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Error updating appointment status.";
            $message_type = 'error';
        }
    } elseif ($action === 'toggle_status' && $doctor_id) {
        try {
            $current_status = $db->fetch("SELECT is_active FROM users WHERE id = ? AND role = 'doctor'", [$doctor_id]);
            $new_status = $current_status['is_active'] ? 0 : 1;
            
            $db->query("UPDATE users SET is_active = ? WHERE id = ? AND role = 'doctor'", [$new_status, $doctor_id]);
            
            $status_text = $new_status ? 'activated' : 'deactivated';
            $message = "Doctor has been $status_text successfully.";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Error updating doctor status.";
            $message_type = 'error';
        }
    } elseif ($action === 'delete' && $doctor_id) {
        try {
            // Get doctor information before deletion
            $doctor = $db->fetch("
                SELECT u.*, d.id as doctor_record_id 
                FROM users u 
                LEFT JOIN doctors d ON u.id = d.user_id 
                WHERE u.id = ? AND u.role = 'doctor'
            ", [$doctor_id]);
            
            if ($doctor) {
                $doctorName = $doctor['first_name'] . ' ' . $doctor['last_name'];
                $doctorRecordId = $doctor['doctor_record_id'];
                
                // Begin transaction
                $db->beginTransaction();
                
                        // Delete related records in the correct order to handle foreign key constraints
                        
                        if ($doctorRecordId) {
                            // 1. Delete lab_offer_doctors
                            $db->query("DELETE FROM lab_offer_doctors WHERE doctor_id = ?", [$doctorRecordId]);
                            
                            // 2. Delete doctor schedules
                            $db->query("DELETE FROM doctor_schedules WHERE doctor_id = ?", [$doctorRecordId]);
                            
                            // 3. Delete doctor breaks
                            $db->query("DELETE FROM doctor_breaks WHERE doctor_id = ?", [$doctorRecordId]);
                            
                            // 4. Delete doctor unavailable periods
                            $db->query("DELETE FROM doctor_unavailable WHERE doctor_id = ?", [$doctorRecordId]);
                            
                            // 5. Delete reviews for this doctor
                            $db->query("DELETE FROM reviews WHERE doctor_id = ?", [$doctorRecordId]);
                            
                            // 6. Handle appointments - find another active doctor or delete appointments
                            $alternativeDoctor = $db->fetch("
                                SELECT d.id FROM doctors d 
                                JOIN users u ON d.user_id = u.id 
                                WHERE u.is_active = 1 AND u.role = 'doctor' AND d.id != ? 
                                LIMIT 1
                            ", [$doctorRecordId]);
                            
                            if ($alternativeDoctor) {
                                // Assign appointments to another active doctor with a note
                                $db->query("
                                    UPDATE appointments 
                                    SET doctor_id = ?, 
                                        notes = COALESCE(notes, '') || CASE 
                                            WHEN notes IS NULL OR notes = '' THEN '[Original doctor deleted: ' || ? || ']'
                                            ELSE ' [Original doctor deleted: ' || ? || ']'
                                        END
                                    WHERE doctor_id = ?
                                ", [$alternativeDoctor['id'], $doctorName, $doctorName, $doctorRecordId]);
                            } else {
                                // No alternative doctor available - we have to delete appointments
                                $db->query("DELETE FROM appointments WHERE doctor_id = ?", [$doctorRecordId]);
                            }
                            
                            // 7. Delete doctor record
                            $db->query("DELETE FROM doctors WHERE id = ?", [$doctorRecordId]);
                        }
                        
                        // 8. Check if user also has a patient record and delete it
                        $patientRecord = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$doctor_id]);
                        if ($patientRecord) {
                            $patientId = $patientRecord['id'];
                            
                            // Delete patient-related records (similar to patient deletion logic)
                            // Delete reviews written by this patient
                            $db->query("DELETE FROM reviews WHERE patient_id = ?", [$patientId]);
                            
                            // Delete payments made by this patient  
                            $db->query("DELETE FROM payments WHERE patient_id = ?", [$patientId]);
                            
                            // Handle appointments as patient - delete them since doctor is being deleted anyway
                            $db->query("DELETE FROM appointments WHERE patient_id = ?", [$patientId]);
                            
                            // Delete patient record
                            $db->query("DELETE FROM patients WHERE id = ?", [$patientId]);
                        }
                        
                        // 9. Update payments verified_by to NULL if this doctor verified them
                        $db->query("UPDATE payments SET verified_by = NULL WHERE verified_by = ?", [$doctor_id]);
                        
                        // 10. Delete activity logs for this user
                        $db->query("DELETE FROM activity_logs WHERE user_id = ?", [$doctor_id]);
                        
                        // 11. Delete notifications for this user
                        $db->query("DELETE FROM notifications WHERE user_id = ?", [$doctor_id]);
                        
                        // 11. Finally, delete user record
                        $db->query("DELETE FROM users WHERE id = ?", [$doctor_id]);                // Commit transaction
                $db->commit();
                
                $message = "Doctor account '$doctorName' deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Doctor not found.";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            $message = "Error deleting doctor account: " . $e->getMessage();
            $message_type = 'error';
            error_log("Doctor deletion error: " . $e->getMessage());
        }
    } elseif ($action === 'reset_password' && $doctor_id) {
        try {
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $result = $db->update('users', ['password' => $hashedPassword], 'id = ?', [$doctor_id]);
            
            if ($result > 0) {
                // Send email notification to the doctor
                try {
                    $doctor_info = $db->fetch("SELECT first_name, last_name, email FROM users WHERE id = ?", [$doctor_id]);
                    if ($doctor_info && !empty($doctor_info['email'])) {
                        $emailService->sendPasswordResetNotification(
                            $doctor_info['email'],
                            $doctor_info['first_name'] . ' ' . $doctor_info['last_name'],
                            $defaultPassword,
                            'doctor'
                        );
                    }
                    $message = "Doctor password reset successfully. New password: $defaultPassword. A notification has been sent to their email.";
                    $message_type = 'success';
                } catch (Exception $emailEx) {
                    error_log("Password reset email failed for doctor {$doctor_id}: " . $emailEx->getMessage());
                    $message = "Doctor password reset successfully. New password: $defaultPassword. (Note: Email notification failed)";
                    $message_type = 'success';
                }
            } else {
                $message = "Failed to reset password - doctor not found.";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = "Error resetting password: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$specialty_filter = $_GET['specialty'] ?? '';
$status_filter = $_GET['status'] ?? '';
$availability_filter = $_GET['availability'] ?? '';

// Get all unique specialties for filter dropdown
$specialties = $db->fetchAll("SELECT DISTINCT specialty FROM doctors WHERE specialty IS NOT NULL AND specialty != '' ORDER BY specialty");

// Build dynamic query
$where_conditions = ["u.role = 'doctor'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR d.specialty LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($specialty_filter)) {
    $where_conditions[] = "d.specialty = ?";
    $params[] = $specialty_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "u.is_active = ?";
    $params[] = $status_filter;
}

if ($availability_filter !== '') {
    $where_conditions[] = "d.is_available = ?";
    $params[] = $availability_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get doctors with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$doctors = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.is_active, u.created_at,
           d.id as doctor_id, d.specialty, d.license_number, d.experience_years, d.consultation_fee, 
           d.schedule_days, d.schedule_time_start, d.schedule_time_end, d.is_available,
           d.biography, d.phone,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) as total_appointments,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.status = 'completed') as completed_appointments
    FROM users u 
    JOIN doctors d ON u.id = d.user_id 
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
", $params);

// Get total count for pagination
$total_doctors = $db->fetch("
    SELECT COUNT(*) as count 
    FROM users u 
    JOIN doctors d ON u.id = d.user_id 
    WHERE $where_clause
", $params)['count'];

$total_pages = ceil($total_doctors / $per_page);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'active' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 1")['count'],
    'available' => $db->fetch("SELECT COUNT(*) as count FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1")['count'],
    'inactive' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_active = 0")['count']
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
            <a href="../Dashboard/dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="../Patient Management/patients.php" class="nav-item">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="doctors.php" class="nav-item active">
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
            <h1>Doctor Management</h1>
            <p>Manage doctor accounts, specialties, and availability</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>


        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="doctors.php" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-search"></i> Search Doctors
                        </label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Search by name, email, or specialty..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i> Specialty
                        </label>
                        <select name="specialty" class="form-select">
                            <option value="">All Specialties</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo htmlspecialchars($specialty['specialty']); ?>" 
                                        <?php echo $specialty_filter === $specialty['specialty'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-toggle-on"></i> Status
                        </label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock"></i> Availability
                        </label>
                        <select name="availability" class="form-select">
                            <option value="">All</option>
                            <option value="1" <?php echo $availability_filter === '1' ? 'selected' : ''; ?>>Available</option>
                            <option value="0" <?php echo $availability_filter === '0' ? 'selected' : ''; ?>>Not Available</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="doctors.php" class="btn-clear">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <div class="filter-info">
                        <?php if ($search || $specialty_filter || $status_filter !== '' || $availability_filter !== ''): ?>
                            <i class="fas fa-info-circle"></i> 
                            Showing <?php echo count($doctors); ?> of <?php echo $total_doctors; ?> doctors
                        <?php else: ?>
                            <i class="fas fa-list"></i> 
                            Showing all <?php echo count($doctors); ?> doctors
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Doctors Table -->
        <div class="content-section">
            <div class="section-header">
                <h2>All Doctors (<?php echo $total_doctors; ?>)</h2>
                <div class="section-actions">
                    <a href="add-doctor.php" class="btn-add">
                        <i class="fas fa-user-plus"></i> Add New Doctor
                    </a>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Specialty</th>
                                <th>Experience</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Available</th>
                                <th>Appointments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    <tbody>
                        <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo $doctor['id']; ?></td>
                                    <td>
                                        <div class="doctor-info">
                                            <div class="doctor-details">
                                                <h4><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h4>
                                                <p class="license-number">License: <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td>
                                        <span class="specialty-badge">
                                            <?php echo htmlspecialchars($doctor['specialty']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td>₱<?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $doctor['is_available'] ? 'available' : 'unavailable'; ?>">
                                            <i class="fas <?php echo $doctor['is_available'] ? 'fa-clock' : 'fa-clock-o'; ?>"></i>
                                            <?php echo $doctor['is_available'] ? 'Available' : 'Not Available'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="doctor-stats">
                                            <div class="stat-item total">
                                                <i class="fas fa-calendar"></i> <?php echo $doctor['total_appointments']; ?>
                                            </div>
                                            <div class="stat-item completed">
                                                <i class="fas fa-check-circle"></i> <?php echo $doctor['completed_appointments']; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-doctor.php?id=<?php echo $doctor['id']; ?>" 
                                               class="btn-action btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn-action btn-schedule"
                                                    title="View Schedule"
                                                    onclick="viewSchedule(<?php echo $doctor['doctor_id']; ?>, '<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>', '<?php echo htmlspecialchars($doctor['specialty']); ?>')"
                                            >
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>

                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <?php if ($doctor['is_active']): ?>
                                                    <button type="submit" class="btn-action btn-delete" 
                                                            title="Deactivate"
                                                            onclick="return confirm('Are you sure you want to deactivate this doctor?')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn-action btn-toggle" 
                                                            title="Activate"
                                                            onclick="return confirm('Are you sure you want to activate this doctor?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reset password for this doctor to default (\'password123\')?')">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <button type="submit" class="btn-action btn-reset" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($doctors)): ?>
                            <tr>
                                <td colspan="10" class="empty-state">
                                    <?php if ($search || $specialty_filter || $status_filter !== '' || $availability_filter !== ''): ?>
                                        <i class="fas fa-search"></i>
                                        <h3>No doctors found</h3>
                                        <p>
                                            No doctors match your current filters. Try adjusting your search criteria or 
                                            <a href="doctors.php">clear all filters</a>.
                                        </p>
                                    <?php else: ?>
                                        <i class="fas fa-user-md"></i>
                                        <h3>No doctors registered</h3>
                                        <p>
                                            No doctors have been added to the system yet. 
                                            <a href="add-doctor.php">Add your first doctor</a> to get started.
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $specialty_filter ? '&specialty=' . urlencode($specialty_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . $status_filter : ''; ?><?php echo $availability_filter !== '' ? '&availability=' . $availability_filter : ''; ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $specialty_filter ? '&specialty=' . urlencode($specialty_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . $status_filter : ''; ?><?php echo $availability_filter !== '' ? '&availability=' . $availability_filter : ''; ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $specialty_filter ? '&specialty=' . urlencode($specialty_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . $status_filter : ''; ?><?php echo $availability_filter !== '' ? '&availability=' . $availability_filter : ''; ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_doctors); ?> 
                            of <?php echo $total_doctors; ?> doctors
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Doctor Schedule Calendar Modal -->
<div id="scheduleModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.55); align-items:center; justify-content:center; padding:1rem;">
    <div style="background:#fff; max-width:780px; width:100%; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,0.3); overflow:hidden; height:85vh; display:flex; flex-direction:column;">
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg,#2563eb,#1e3a8a); padding:1.25rem 1.75rem; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <div>
                <h3 id="calDoctorName" style="color:#fff; margin:0; font-size:1.2rem; font-weight:700;"></h3>
                <p id="calDoctorSpecialty" style="color:rgba(255,255,255,0.85); margin:0.15rem 0 0; font-size:0.85rem;"></p>
            </div>
            <button onclick="closeScheduleModal()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        <!-- Month Nav -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.75rem;border-bottom:1px solid #f1f5f9;background:#fff;flex-shrink:0;">
            <button onclick="changeCalMonth(-1)" style="background:#f8fafc;border:1px solid #e2e8f0;color:#1e3a8a;width:38px;height:38px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;"><i class="fas fa-chevron-left"></i></button>
            <span id="calMonthLabel" style="font-weight:700;font-size:1.1rem;color:#1e293b;letter-spacing:-0.01em;"></span>
            <button onclick="changeCalMonth(1)"  style="background:#f8fafc;border:1px solid #e2e8f0;color:#1e3a8a;width:38px;height:38px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;"><i class="fas fa-chevron-right"></i></button>
        </div>
        <!-- Legend -->
        <div style="display:flex;gap:1rem;padding:0.6rem 1.75rem;background:#fafafa;border-bottom:1px solid #e0e0e0;flex-shrink:0;flex-wrap:wrap;">
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#ffc107;display:inline-block;"></span>Pending</span>
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#2196f3;display:inline-block;"></span>Scheduled</span>
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#4caf50;display:inline-block;"></span>Completed</span>
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#f44336;display:inline-block;"></span>Cancelled</span>
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#9c27b0;display:inline-block;"></span>Rescheduled</span>
            <span style="font-size:0.78rem;display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:10px;border-radius:50%;background:#607d8b;display:inline-block;"></span>No Show</span>
        </div>
        <!-- Calendar Grid -->
        <div style="padding:1rem 1.25rem;overflow-y:auto;flex:1;min-height:0;">
            <div id="calGrid" style="min-height:300px;"></div>
        </div>
        <!-- Day Detail Panel -->
        <div id="calDayDetail" style="display:none;padding:0.9rem 1.75rem;border-top:2px solid #e0f7fa;background:#f0fdfd;flex-shrink:0;max-height:200px;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <strong id="calDayDetailTitle" style="color:#1e3a8a;font-size:0.95rem;"></strong>
                <button onclick="document.getElementById('calDayDetail').style.display='none'" style="background:none;border:none;cursor:pointer;color:#999;font-size:1.1rem;">&times;</button>
            </div>
            <div id="calDayDetailBody"></div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<?php include_once '../../includes/shared_appointment_details.php'; ?>

<style>
.btn-schedule { background:linear-gradient(135deg,#2563eb,#1e3a8a); color:#fff; border:none; cursor:pointer; }
.btn-schedule:hover { background:linear-gradient(135deg,#1e3a8a,#1e3a8a); transform:translateY(-1px); }
.cal-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap: 1px; background: #e0e0e0; border: 1px solid #e0e0e0; }
.cal-day-name { text-align:center; font-size:0.72rem; font-weight:700; color:#2563eb; padding:0.6rem 0; text-transform:uppercase; letter-spacing:0.05em; background: #fafafa; }
.cal-cell { min-height:90px; padding:0.5rem; background:#fff; position:relative; transition:background 0.15s; cursor:default; }
.cal-cell.has-appts { cursor:pointer; }
.cal-cell.has-appts:hover { background:#f0fdfd; }
.cal-cell.today { background:#e0f7fa; }
.cal-cell.empty { background:#f5f5f5; }
.cal-date-num { font-size:0.85rem; font-weight:700; color:#444; margin-bottom:0.3rem; }
.cal-cell.today .cal-date-num { color:#fff; background:#2563eb; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; }
.cal-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin:1px; }
.cal-dots { display:flex; flex-wrap:wrap; gap:2px; margin-top:2px; }
.cal-count { font-size:0.65rem; color:#888; margin-top:2px; }
.cal-appt-row { display:flex; align-items:center; gap:0.5rem; padding:0.35rem 0.5rem; border-radius:6px; background:#fff; border:1px solid #e0e0e0; margin-bottom:0.3rem; font-size:0.83rem; }
</style>

<script>
let _calDoctorId=null, _calYear=null, _calMonth=null;
const _MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
const _STATUS_COL={pending:'#ffc107',scheduled:'#2196f3',completed:'#4caf50',cancelled:'#f44336',rescheduled:'#9c27b0',no_show:'#607d8b'};

function viewSchedule(doctorId, name, specialty) {
    _calDoctorId = doctorId;
    const now = new Date();
    _calYear = now.getFullYear();
    _calMonth = now.getMonth() + 1;
    document.getElementById('calDoctorName').textContent = 'Dr. ' + name;
    document.getElementById('calDoctorSpecialty').textContent = specialty;
    document.getElementById('calDayDetail').style.display = 'none';
    document.getElementById('scheduleModal').style.display = 'flex';
    loadCalendar();
}

function closeScheduleModal() { document.getElementById('scheduleModal').style.display = 'none'; }

function changeCalMonth(dir) {
    _calMonth += dir;
    if (_calMonth < 1)  { _calMonth = 12; _calYear--; }
    if (_calMonth > 12) { _calMonth = 1;  _calYear++; }
    document.getElementById('calDayDetail').style.display = 'none';
    loadCalendar();
}

function loadCalendar() {
    document.getElementById('calGrid').innerHTML = '<div style="text-align:center;padding:2rem;color:#999;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    document.getElementById('calMonthLabel').textContent = _MONTHS[_calMonth-1] + ' ' + _calYear;
    fetch(`get_doctor_schedule.php?doctor_id=${_calDoctorId}&year=${_calYear}&month=${_calMonth}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                document.getElementById('calGrid').innerHTML = `<div style="text-align:center;padding:3rem;color:#e57373;"><i class="fas fa-exclamation-circle" style="font-size:2rem;margin-bottom:1rem;"></i><br>${data.error}</div>`;
            } else {
                renderCalendar(data);
            }
        })
        .catch(() => { document.getElementById('calGrid').innerHTML = '<div style="text-align:center;padding:2rem;color:#e57373;"><i class="fas fa-exclamation-triangle"></i> Failed to load.</div>'; });
}

function renderCalendar(data) {
    const appts  = data.appointments || {};
    const days   = data.days_in_month;
    const offset = data.first_day_of_week;
    const today  = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
    const DAY_HDRS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    let html = '<div class="cal-grid">';
    DAY_HDRS.forEach(d => { html += `<div class="cal-day-name">${d}</div>`; });
    for (let i = 0; i < offset; i++) html += '<div class="cal-cell empty"></div>';

    for (let d = 1; d <= days; d++) {
        const dateStr  = `${_calYear}-${String(_calMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dayAppts = appts[dateStr] || [];
        const isToday  = dateStr === todayStr;
        let cls = 'cal-cell' + (isToday ? ' today' : '') + (dayAppts.length ? ' has-appts' : '');
        const oc = dayAppts.length ? `onclick="showDayDetail('${dateStr}',${d})"` : '';
        html += `<div class="${cls}" ${oc}><div class="cal-date-num">${d}</div>`;
        if (dayAppts.length) {
            html += '<div class="cal-dots">';
            dayAppts.slice(0,8).forEach(a => {
                html += `<span class="cal-dot" style="background:${_STATUS_COL[a.status]||'#999'};" title="${a.patient_name} ${fmtT(a.appointment_time)}"></span>`;
            });
            html += `</div><div class="cal-count">${dayAppts.length} appt${dayAppts.length>1?'s':''}</div>`;
        }
        html += '</div>';
    }
    html += '</div>';
    document.getElementById('calGrid').innerHTML = html;
    window._calApptData = appts;
}

function showAppointmentDetails(id) {
    const modal = document.getElementById('appointmentModal');
    // We already have modalContent and modalFooter defined in the shared include
    
    // Fetch appointment details via AJAX (Reusing dashboard endpoint)
    fetch(`../Dashboard/get_appointment_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }
            
            const appointment = data.appointment;
            const payment = data.payment;
            const patientInfo = data.patient_info;
            
            // Standardize for shared renderer
            const standardizedData = {
                id: appointment.id,
                name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
                status: appointment.status,
                date: formatDateJS(appointment.appointment_date),
                time: fmtT(appointment.appointment_time),
                purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
                doctor: 'Dr. ' + appointment.doctor_first_name + ' ' + appointment.doctor_last_name,
                specialty: appointment.specialty,
                license: appointment.license_number,
                fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
                relationship: appointment.relationship || 'Self',
                dob: formatDateJS(appointment.date_of_birth),
                gender: appointment.gender,
                email: appointment.email || appointment.patient_email,
                phone: appointment.phone_number || appointment.patient_phone,
                address: appointment.address || appointment.patient_address,
                reason: appointment.illness || appointment.reason_for_visit,
                notes: appointment.notes,
                payment: payment ? {
                    amount: parseFloat(payment.amount).toFixed(2),
                    status: payment.status,
                    ref: payment.gcash_reference,
                    receipt: payment.receipt_path
                } : null,
                laboratory_image: patientInfo ? patientInfo.laboratory_image : null
            };
            
            showAppointmentOverview(standardizedData, 'admin');
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
        });
}

function closeAptModal() {
    closeBaseModal();
}

function formatDateJS(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function calculateAgeJS(dob) {
    const diff = Date.now() - new Date(dob).getTime();
    return Math.abs(new Date(diff).getUTCFullYear() - 1970);
}

function showDayDetail(dateStr, d) {
    const appts = (window._calApptData||{})[dateStr]||[];
    document.getElementById('calDayDetailTitle').textContent = `${_MONTHS[_calMonth-1]} ${d}, ${_calYear} — ${appts.length} appointment${appts.length!==1?'s':''}`;
    let html='';
    appts.forEach(a => {
        const col = _STATUS_COL[a.status]||'#999';
        const aptData = JSON.stringify(a).replace(/'/g, "&apos;");
        html += `<div class="cal-appt-row" onclick='showAppointmentDetails(${a.id})' style="cursor:pointer; transition: transform 0.2s;">
            <span class="cal-dot" style="background:${col};width:10px;height:10px;"></span>
            <span style="font-weight:600;color:#333;min-width:65px;">${fmtT(a.appointment_time)}</span>
            <span style="color:#444;flex:1;">${a.patient_name||'Unknown'}</span>
            <span style="font-size:0.73rem;color:#fff;background:${col};padding:2px 8px;border-radius:10px;white-space:nowrap;">${a.status.replace('_',' ')}</span>
            <i class="fas fa-chevron-right" style="color:#ccc; font-size:0.8rem;"></i>
        </div>`;
    });
    document.getElementById('calDayDetailBody').innerHTML = html;
    document.getElementById('calDayDetail').style.display = 'block';
}

function fmtT(t) {
    if (!t) return ''; const p=t.split(':'); const h=parseInt(p[0]); return (h%12||12)+':'+(p[1]||'00')+' '+(h>=12?'PM':'AM');
}
document.getElementById('scheduleModal').addEventListener('click', function(e){ if(e.target===this) closeScheduleModal(); });
</script>

</body>
</html>
