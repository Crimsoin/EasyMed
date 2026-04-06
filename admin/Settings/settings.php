<?php
$page_title = "System Settings";
$additional_css = ['admin/sidebar.css', 'admin/dashboard.css', 'admin/settings.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';




// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();

// Handle form submissions
$success_message = '';
$error_message = '';

// Get current settings from database or set defaults
$current_settings = [
    'site_name' => 'EasyMed',
    'site_description' => 'Modern Medical Practice Management System',
    'admin_email' => 'admin@easymed.com',
    'site_phone' => '+1 (555) 123-4567',
    'site_address' => '123 Medical Center Dr, Healthcare City, HC 12345',
    'appointment_duration' => 30,
    'max_appointments_per_day' => 20,
    'advance_booking_days' => 60,
    'cancellation_hours' => 24,
    'default_consultation_fee' => 100.00,
    'currency_symbol' => '$',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'timezone' => 'America/New_York',
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'auto_confirm_appointments' => 0,
    'maintenance_mode' => 0,
    'max_login_attempts' => 3,
    'session_timeout' => 3600,
    'backup_frequency' => 'weekly',
    'theme_color' => '#2563eb',
    'logo_url' => '',
    'favicon_url' => ''
];

// Try to get settings from database
try {
    $settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
    foreach ($settings_result as $setting) {
        $current_settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    // If table doesn't exist, we'll create it
    try {
        if (DB_TYPE === 'sqlite') {
            $db->query("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $db->query("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Insert default settings
        foreach ($current_settings as $key => $value) {
            if (DB_TYPE === 'sqlite') {
                $db->query("INSERT OR IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            } else {
                $db->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }
        }
    } catch (Exception $e) {
        $error_message = "Error initializing settings: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_settings') {
            $settings_keys = [
                'site_name', 'site_description', 'admin_email', 'site_phone', 
                'site_address', 'timezone'
            ];

            foreach ($settings_keys as $key) {
                if (isset($_POST[$key])) {
                    $value = trim($_POST[$key]);

                    // Validate email if present
                    if ($key === 'admin_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email address");
                    }

                    if (DB_TYPE === 'sqlite') {
                        $db->query("INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
                    } else {
                        $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $value]);
                    }
                    $current_settings[$key] = $value;
                }
            }


            $success_message = "Settings updated successfully!";

        } elseif ($action === 'update_profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($first_name) || empty($last_name) || empty($email)) {
                throw new Exception("All profile fields are required");
            }

            $db->update('users', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$_SESSION['user_id']]);
            
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $success_message = "Admin profile updated successfully!";

        } elseif ($action === 'change_password') {
            $current_pw = $_POST['current_password'] ?? '';
            $new_pw = $_POST['new_password'] ?? '';
            $confirm_pw = $_POST['confirm_password'] ?? '';
            
            $user = $db->fetch("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
            
            if (!password_verify($current_pw, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            if ($new_pw !== $confirm_pw) {
                throw new Exception("New passwords do not match");
            }
            if (strlen($new_pw) < 6) {
                throw new Exception("Password must be at least 6 characters");
            }

            $db->update('users', [
                'password' => password_hash($new_pw, PASSWORD_DEFAULT)
            ], 'id = ?', [$_SESSION['user_id']]);
            
            $success_message = "Password changed successfully!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get admin data for Security tab
$admin_profile = $db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'database_version' => getDatabaseVersion($db),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'disk_free_space' => function_exists('disk_free_space') ? formatBytes(disk_free_space('.')) : 'Unknown',
    'disk_total_space' => function_exists('disk_total_space') ? formatBytes(disk_total_space('.')) : 'Unknown'
];

// Function to get database version compatible with both MySQL and SQLite
function getDatabaseVersion($db) {
    try {
        if (DB_TYPE === 'sqlite') {
            $version = $db->fetch("SELECT sqlite_version() as version");
            return 'SQLite ' . ($version['version'] ?? 'Unknown');
        } else {
            $version = $db->fetch("SELECT VERSION() as version");
            return 'MySQL ' . ($version['version'] ?? 'Unknown');
        }
    } catch (Exception $e) {
        return 'Unknown';
    }
}

// Get database statistics
$db_stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_doctors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'")['count'],
    'total_patients' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'patient'")['count'],
    'total_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments")['count'],
    'database_size' => 'Calculating...'
];

// Helper function to format bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

// Available timezones
$timezones = [
    'America/New_York' => 'Eastern Time',
    'America/Chicago' => 'Central Time',
    'America/Denver' => 'Mountain Time',
    'America/Los_Angeles' => 'Pacific Time',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Paris',
    'Asia/Tokyo' => 'Tokyo',
    'Australia/Sydney' => 'Sydney',
    'UTC' => 'UTC'
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
            <a href="../Doctor Management/doctors.php" class="nav-item">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Feedbacks/feedback_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Feedbacks
            </a>
            <a href="settings.php" class="nav-item active">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header" style="margin-bottom: 2rem;">
            <div>
                <h1>System Settings</h1>
                <p>Configure and manage your EasyMed system settings</p>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Settings Grid -->
        <div class="settings-grid" style="display: flex; flex-direction: column; gap: 2rem; width: 100%;">




                <form method="POST" class="settings-form">
                    <div class="content-section">
                        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h2><i class="fas fa-user"></i> My Admin Profile</h2>
                            <button type="button" class="btn btn-sm btn-outline edit-card-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem; border: 1px solid var(--primary-cyan); color: var(--primary-cyan); background: transparent; border-radius: 6px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($admin_profile['first_name']) ?>" disabled required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($admin_profile['last_name']) ?>" disabled required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($admin_profile['email']) ?>" disabled required>
                        </div>
                        
                        <div class="settings-actions" style="display: none; justify-content: flex-start; padding: 1rem 0; border-top: 1px solid #f0f0f0; margin-top: 1rem; background: transparent; gap: 1rem;">
                            <button type="submit" name="action" value="update_profile" class="btn btn-primary">
                                <i class="fas fa-user-check"></i> Save Profile
                            </button>
                            <button type="button" class="btn btn-secondary cancel-card-btn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>

                <form method="POST" class="settings-form">
                    <div class="content-section">
                        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h2><i class="fas fa-key"></i> Change Password</h2>
                            <button type="button" class="btn btn-sm btn-outline edit-card-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem; border: 1px solid var(--primary-cyan); color: var(--primary-cyan); background: transparent; border-radius: 6px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" disabled required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" disabled required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" disabled required>
                        </div>

                        <div class="settings-actions" style="display: none; justify-content: flex-start; padding: 1rem 0; border-top: 1px solid #f0f0f0; margin-top: 1rem; background: transparent; gap: 1rem;">
                            <button type="submit" name="action" value="change_password" class="btn btn-primary">
                                <i class="fas fa-shield-alt"></i> Update Password
                            </button>
                            <button type="button" class="btn btn-secondary cancel-card-btn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>
        </div>
    </div>
</div>

<script>
// Edit Toggle Functionality
document.querySelectorAll('.edit-card-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const card = this.closest('.content-section');
        const cardInputs = card.querySelectorAll('input, select, textarea');
        const cardActions = card.querySelectorAll('.settings-actions');
        
        cardInputs.forEach(input => {
            input.disabled = false;
        });
        
        this.style.display = 'none';
        cardActions.forEach(action => {
            action.style.display = 'flex';
        });
    });
});

// Cancel functionality for individual cards
document.querySelectorAll('.cancel-card-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        location.reload();
    });
});

// Tab functionality
document.addEventListener('DOMContentLoaded', function() {

    // Form validation and loading state for all forms
    document.querySelectorAll('.settings-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Show loading state for the specific submit button clicked
            const submitBtn = e.submitter;
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                // Use setTimeout to allow the browser to include the button's value in the POST request
                setTimeout(() => {
                    submitBtn.disabled = true;
                }, 1);
                
                // Reset button after 3 seconds if form doesn't submit/reload
                setTimeout(() => {
                    if (submitBtn) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }, 3000);
            }
        });
    });

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
});
</script>

</body>
</html>
