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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $doctorCount; ?></h3>
                    <p>Doctors</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $patientCount; ?></h3>
                    <p>Patients</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalAppointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $systemLogsCount; ?></h3>
                    <p>System Logs</p>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="content-section">
            <div class="section-header">
                <h2>Recent Users</h2>
                <a href="../Patient Management/patients.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage All Users
                </a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
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
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="user-actions">
                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>
            
            <div class="quick-actions">
                <a href="add-patient-admin.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-content">
                        <h4>Add New User</h4>
                        <p>Create a new user account</p>
                    </div>
                </a>
                
                <a href="../Patient Management/patients.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-content">
                        <h4>Manage Users</h4>
                        <p>View and edit all users</p>
                    </div>
                </a>
                
                <a href="../Doctor Management/doctors.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="action-content">
                        <h4>Manage Doctors</h4>
                        <p>Add and manage doctors</p>
                    </div>
                </a>
                
                <a href="../Appointment/appointments.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="action-content">
                        <h4>View Appointments</h4>
                        <p>Check all appointments</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- System Overview -->
        <div class="content-section">
            <div class="section-header">
                <h2>System Overview</h2>
            </div>
            
            <div style="padding: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div style="text-align: center; padding: 1rem; background: rgba(0, 188, 212, 0.1); border-radius: 8px;">
                        <h4 style="color: var(--primary-cyan); margin-bottom: 0.5rem;"><?php echo $pendingAppointments; ?></h4>
                        <p style="margin: 0; color: var(--text-light);">Pending Appointments</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(76, 175, 80, 0.1); border-radius: 8px;">
                        <h4 style="color: #4caf50; margin-bottom: 0.5rem;"><?php echo $todayAppointments; ?></h4>
                        <p style="margin: 0; color: var(--text-light);">Today's Appointments</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: rgba(255, 152, 0, 0.1); border-radius: 8px;">
                        <h4 style="color: #ff9800; margin-bottom: 0.5rem;"><?php echo $adminCount; ?></h4>
                        <p style="margin: 0; color: var(--text-light);">System Administrators</p>
                    </div>
                </div>
            </div>
        </div>
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

<?php require_once '../../includes/footer.php'; ?>
