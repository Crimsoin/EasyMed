<?php
$page_title = 'User Management';
$additional_css = ['admin/settings.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_GET['action'] ?? '' === 'delete' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId !== $_SESSION['user_id']) { // Can't delete yourself
        $db->update('users', ['is_active' => 0], 'id = ?', [$userId]);
        $_SESSION['success_message'] = 'User deactivated successfully';
    }
    header('Location: users.php');
    exit;
}

if ($_GET['action'] ?? '' === 'activate' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $db->update('users', ['is_active' => 1], 'id = ?', [$userId]);
    $_SESSION['success_message'] = 'User activated successfully';
    header('Location: users.php');
    exit;
}

// Get all users with filtering
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = ['1=1'];
$params = [];

    if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "u.is_active = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$users = $db->fetchAll("
    SELECT u.*, d.specialty 
    FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    WHERE $where_clause
    ORDER BY u.created_at DESC
", $params);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'active' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'],
    'admins' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count']
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
            <a href="users.php" class="nav-item active">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Appointment/appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
            <a href="../Report and Analytics/reports.php" class="nav-item">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="../Settings/settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <p>Manage all users in the system</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="content-section">
            <div class="section-header">
                <h2>Statistics Overview</h2>
                <a href="add-user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
            </div>
            
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['doctors']; ?></div>
                    <div class="stat-label">Doctors</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['patients']; ?></div>
                    <div class="stat-label">Patients</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-section">
            <div class="section-header">
                <h2>Filter Users</h2>
            </div>
            
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">Search</label>
               <input type="text" id="search" name="search" class="form-control" 
                   placeholder="Search by name or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="patient" <?php echo $role_filter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-section">
            <div class="section-header">
                <h2>All Users (<?php echo count($users); ?>)</h2>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <h3>No users found</h3>
                    <p>No users match your current filters.</p>
                    <a href="add-user.php" class="btn btn-primary">Add First User</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Specialty</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $user['specialty'] ? htmlspecialchars($user['specialty']) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="user-actions">
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <?php if ($user['is_active']): ?>
                                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-delete" title="Deactivate"
                                                       onclick="return confirm('Are you sure you want to deactivate this user?')">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-view" title="Activate"
                                                       onclick="return confirm('Are you sure you want to activate this user?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

