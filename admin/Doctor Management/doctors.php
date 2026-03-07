<?php
$page_title = "Doctor Management";
$additional_css = ['admin/sidebar.css', 'admin/doctor-management.css'];
$additional_js = ['admin/doctor-management.js'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
    
    if ($action === 'toggle_status' && $doctor_id) {
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
            <h1>Doctor Management</h1>
            <p>Manage doctor accounts, specialties, and availability</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Doctor Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Doctors</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['active']; ?></h3>
                    <p>Active Doctors</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-inactive">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['available']; ?></h3>
                    <p>Available Now</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-filtered">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['inactive']; ?></h3>
                    <p>Inactive</p>
                </div>
            </div>
        </div>

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
                                            <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" 
                                               class="btn-action btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
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
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <button type="submit" class="btn-action btn-danger" 
                                                        title="Delete Account"
                                                        onclick="return confirm('Are you sure you want to permanently delete this doctor account? This action cannot be undone and will remove all associated data including appointments, schedules, and reviews.')">
                                                    <i class="fas fa-trash"></i>
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

</body>
</html>
