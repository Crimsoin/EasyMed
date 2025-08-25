<?php
$page_title = 'Patient Management';
$additional_css = ['admin/sidebar.css', 'admin/patient-management.css']; // Include sidebar and patient management CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $patient_id = $_POST['patient_id'] ?? '';
    
    if ($action === 'toggle_status' && $patient_id) {
        $patientId = (int)$patient_id;
        if ($patientId !== $_SESSION['user_id']) { // Can't toggle your own account
            try {
                $current_status = $db->fetch("SELECT is_active FROM users WHERE id = ? AND role = 'patient'", [$patientId]);
                if ($current_status) {
                    $new_status = $current_status['is_active'] ? 0 : 1;
                    
                    $result = $db->query("UPDATE users SET is_active = ? WHERE id = ? AND role = 'patient'", [$new_status, $patientId]);
                    
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $_SESSION['success'] = "Patient has been $status_text successfully.";
                } else {
                    $_SESSION['error'] = 'Patient not found.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error updating patient status: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Cannot modify your own account status';
        }
        header('Location: patients.php');
        exit;
    }
}

if ($_GET['action'] ?? '' === 'reset_password' && isset($_GET['id'])) {
    $patientId = (int)$_GET['id'];
    if ($patientId !== $_SESSION['user_id']) { // Can't reset your own password this way
        try {
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $result = $db->update('users', ['password' => $hashedPassword], 'id = ?', [$patientId]);
            if ($result > 0) {
                $_SESSION['success'] = 'Patient password reset successfully. New password: password123';
            } else {
                $_SESSION['error'] = 'Failed to reset password - no rows affected';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error resetting password: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Cannot reset your own password using this method';
    }
    header('Location: patients.php');
    exit;
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_filter = $_GET['age'] ?? '';

// Check if any specific filters are applied (not just the apply_filters button)
$specific_filters_applied = !empty($search) || !empty($status_filter) || !empty($gender_filter) || !empty($age_filter);

// Always load patients - either all patients or filtered patients
// Build WHERE conditions
$where_conditions = ["u.role = 'patient'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "u.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "u.is_active = 0";
    }
}

if (!empty($gender_filter)) {
    $where_conditions[] = "p.gender = ?";
    $params[] = $gender_filter;
}

if (!empty($age_filter)) {
    $current_date = date('Y-m-d');
    switch ($age_filter) {
        case 'under_18':
            $where_conditions[] = "p.date_of_birth > DATE_SUB('$current_date', INTERVAL 18 YEAR)";
            break;
        case '18_30':
            $where_conditions[] = "p.date_of_birth BETWEEN DATE_SUB('$current_date', INTERVAL 30 YEAR) AND DATE_SUB('$current_date', INTERVAL 18 YEAR)";
            break;
        case '31_50':
            $where_conditions[] = "p.date_of_birth BETWEEN DATE_SUB('$current_date', INTERVAL 50 YEAR) AND DATE_SUB('$current_date', INTERVAL 31 YEAR)";
            break;
        case 'over_50':
            $where_conditions[] = "p.date_of_birth < DATE_SUB('$current_date', INTERVAL 50 YEAR)";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get patients with filters
$patients = $db->fetchAll("
    SELECT u.*, p.date_of_birth, p.gender, p.phone, p.address, p.emergency_contact, p.emergency_phone, p.blood_type, p.allergies, p.medical_history
    FROM users u 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE $where_clause
    ORDER BY u.created_at DESC
", $params);

// Get statistics for display
$total_patients = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'];
$active_patients = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND is_active = 1")['count'];
$inactive_patients = $total_patients - $active_patients;

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
            <a href="patients.php" class="nav-item active">
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
            <h1>Patient Management</h1>
            <p>Manage all patients in the system</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Patient Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Patients</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $active_patients; ?></h3>
                    <p>Active Patients</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-inactive">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $inactive_patients; ?></h3>
                    <p>Inactive Patients</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-filtered">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo count($patients); ?></h3>
                    <p>Filtered Results</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="patients.php" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-search"></i> Search Patients
                        </label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Search by name, email, username, phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-toggle-on"></i> Status
                        </label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-venus-mars"></i> Gender
                        </label>
                        <select name="gender" class="form-select">
                            <option value="">All Genders</option>
                            <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $gender_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-birthday-cake"></i> Age Group
                        </label>
                        <select name="age" class="form-select">
                            <option value="">All Ages</option>
                            <option value="under_18" <?php echo $age_filter === 'under_18' ? 'selected' : ''; ?>>Under 18</option>
                            <option value="18_30" <?php echo $age_filter === '18_30' ? 'selected' : ''; ?>>18-30 years</option>
                            <option value="31_50" <?php echo $age_filter === '31_50' ? 'selected' : ''; ?>>31-50 years</option>
                            <option value="over_50" <?php echo $age_filter === 'over_50' ? 'selected' : ''; ?>>Over 50</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="patients.php" class="btn-clear">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <?php if (!empty($patients)): ?>
                    <?php endif; ?>
                    <div class="filter-info">
                        <?php if ($specific_filters_applied): ?>
                            <i class="fas fa-info-circle"></i> 
                            Showing <?php echo count($patients); ?> of <?php echo $total_patients; ?> patients
                        <?php else: ?>
                            <i class="fas fa-list"></i> 
                            Showing all <?php echo count($patients); ?> patients
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="content-section">
            <div class="section-header">
                <h2>All Patients (<?php echo count($patients); ?>)</h2>
                <div class="section-actions">
                    <a href="add-patient.php" class="btn-add">
                        <i class="fas fa-user-plus"></i> Add New Patient
                    </a>

                </div>
            </div>
            
            <div class="section-content">
            <div class="table-container">
                <div class="table-responsive">
                    <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo $patient['id']; ?></td>
                            <td>
                                <div class="patient-info">
                                    <div class="patient-details">
                                        <h4><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($patient['username']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                            <td><?php echo $patient['phone'] ? htmlspecialchars($patient['phone']) : '-'; ?></td>
                            <td>
                                <?php if ($patient['date_of_birth']): ?>
                                    <div class="date-info">
                                        <?php 
                                        $dob = new DateTime($patient['date_of_birth']);
                                        $today = new DateTime();
                                        $age = $today->diff($dob)->y;
                                        ?>
                                        <strong><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></strong>
                                        <small class="age-display">(<?php echo $age; ?> years old)</small>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($patient['gender']): ?>
                                    <span class="gender-badge gender-<?php echo $patient['gender']; ?>">
                                        <i class="fas fa-<?php echo $patient['gender'] === 'male' ? 'mars' : ($patient['gender'] === 'female' ? 'venus' : 'genderless'); ?>"></i>
                                        <?php echo ucfirst($patient['gender']); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $patient['is_active'] ? 'active' : 'inactive'; ?>">
                                    <i class="fas <?php echo $patient['is_active'] ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                                    <?php echo $patient['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                       class="btn-action btn-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if ($patient['id'] !== $_SESSION['user_id']): ?>
                                        <a href="patients.php?action=reset_password&id=<?php echo $patient['id']; ?>" 
                                           class="btn-action btn-toggle" title="Reset Password"
                                           onclick="return confirm('Are you sure you want to reset this patient\'s password to \'password123\'?')">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                            <?php if ($patient['is_active']): ?>
                                                <button type="submit" class="btn-action btn-delete" 
                                                        title="Deactivate"
                                                        onclick="return confirm('Are you sure you want to deactivate this patient?')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn-action btn-toggle" 
                                                        title="Activate"
                                                        onclick="return confirm('Are you sure you want to activate this patient?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <?php if ($specific_filters_applied): ?>
                                    <i class="fas fa-search"></i>
                                    <h3>No patients found</h3>
                                    <p>
                                        No patients match your current filters. Try adjusting your search criteria or 
                                        <a href="patients.php">clear all filters</a>.
                                    </p>
                                <?php else: ?>
                                    <i class="fas fa-user-plus"></i>
                                    <h3>No patients registered</h3>
                                    <p>
                                        No patients have been added to the system yet. 
                                        <a href="add-patient.php">Add your first patient</a> to get started.
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Patient Management JavaScript -->
<script src="../assets/js/patient-management.js"></script>

<?php require_once '../../includes/footer.php'; ?>
