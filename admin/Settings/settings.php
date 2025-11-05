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
    'working_hours_start' => '09:00',
    'working_hours_end' => '17:00',
    'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'auto_confirm_appointments' => 0,
    'maintenance_mode' => 0,
    'max_login_attempts' => 3,
    'session_timeout' => 3600,
    'backup_frequency' => 'weekly',
    'theme_color' => '#00bcd4',
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
    try {
        // Validate and sanitize inputs
        $settings_to_update = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'site_phone' => trim($_POST['site_phone'] ?? ''),
            'site_address' => trim($_POST['site_address'] ?? ''),
            'appointment_duration' => (int)($_POST['appointment_duration'] ?? 30),
            'max_appointments_per_day' => (int)($_POST['max_appointments_per_day'] ?? 20),
            'advance_booking_days' => (int)($_POST['advance_booking_days'] ?? 60),
            'cancellation_hours' => (int)($_POST['cancellation_hours'] ?? 24),
            'default_consultation_fee' => (float)($_POST['default_consultation_fee'] ?? 100.00),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '$'),
            'date_format' => trim($_POST['date_format'] ?? 'Y-m-d'),
            'time_format' => trim($_POST['time_format'] ?? 'H:i'),
            'timezone' => trim($_POST['timezone'] ?? 'America/New_York'),
            'working_hours_start' => trim($_POST['working_hours_start'] ?? '09:00'),
            'working_hours_end' => trim($_POST['working_hours_end'] ?? '17:00'),
            'working_days' => json_encode($_POST['working_days'] ?? []),
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
            'auto_confirm_appointments' => isset($_POST['auto_confirm_appointments']) ? 1 : 0,
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 3),
            'session_timeout' => (int)($_POST['session_timeout'] ?? 3600),
            'backup_frequency' => trim($_POST['backup_frequency'] ?? 'weekly'),
            'theme_color' => trim($_POST['theme_color'] ?? '#00bcd4'),
            'logo_url' => trim($_POST['logo_url'] ?? ''),
            'favicon_url' => trim($_POST['favicon_url'] ?? '')
        ];

        // Validate email
        if (!filter_var($settings_to_update['admin_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }

        // Validate numeric values
        if ($settings_to_update['appointment_duration'] < 5 || $settings_to_update['appointment_duration'] > 240) {
            throw new Exception("Appointment duration must be between 5 and 240 minutes");
        }

        // Update each setting in database
        foreach ($settings_to_update as $key => $value) {
            if (DB_TYPE === 'sqlite') {
                $db->query("
                    INSERT OR REPLACE INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?)
                ", [$key, $value]);
            } else {
                $db->query("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ", [$key, $value]);
            }
            $current_settings[$key] = $value;
        }

        $success_message = "Settings updated successfully!";
        
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

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
            <a href="../Appointment/appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
            <a href="../Reviews/review_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Reviews
            </a>            
            <a href="../Report and Analytics/reports.php" class="nav-item">
                <i class="fas fa-chart-bar"></i> Reports & Analytics
            </a>
            <a href="../Settings/settings.php" class="nav-item active">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h1>System Settings</h1>
            <p>Configure and manage your EasyMed system settings</p>
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

        <!-- Settings Navigation Tabs -->
        <div class="content-section">
            <div class="tab-navigation">
                <button type="button" class="tab-button active" data-tab="general">
                    <i class="fas fa-globe"></i> General
                </button>
                <button type="button" class="tab-button" data-tab="appointments">
                    <i class="fas fa-calendar"></i> Appointments
                </button>
                <button type="button" class="tab-button" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </button>
                <button type="button" class="tab-button" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button type="button" class="tab-button" data-tab="appearance">
                    <i class="fas fa-palette"></i> Appearance
                </button>
                <button type="button" class="tab-button" data-tab="system">
                    <i class="fas fa-server"></i> System Info
                </button>
            </div>
        </div>

        <form method="POST" class="settings-form">
            <!-- General Settings Tab -->
            <div class="content-section tab-content active" id="general-tab">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> Site Information</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" id="site_name" name="site_name" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email" class="form-label">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea id="site_description" name="site_description" class="form-textarea" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_phone" class="form-label">Phone Number</label>
                        <input type="text" id="site_phone" name="site_phone" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['site_phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="timezone" class="form-label">Timezone</label>
                        <select id="timezone" name="timezone" class="form-select">
                            <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?php echo $tz; ?>" <?php echo $current_settings['timezone'] === $tz ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="site_address" class="form-label">Address</label>
                        <textarea id="site_address" name="site_address" class="form-textarea" rows="3"><?php echo htmlspecialchars($current_settings['site_address']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Business Hours Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Business Hours</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="working_hours_start" class="form-label">Start Time</label>
                        <input type="time" id="working_hours_start" name="working_hours_start" class="form-input" 
                               value="<?php echo $current_settings['working_hours_start']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="working_hours_end" class="form-label">End Time</label>
                        <input type="time" id="working_hours_end" name="working_hours_end" class="form-input" 
                               value="<?php echo $current_settings['working_hours_end']; ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Working Days</label>
                        <div class="checkbox-group">
                            <?php 
                            $working_days = json_decode($current_settings['working_days'], true) ?: [];
                            $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
                            foreach ($days as $day => $label): 
                            ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="working_days[]" value="<?php echo $day; ?>" 
                                           <?php echo in_array($day, $working_days) ? 'checked' : ''; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments Settings Tab -->
            <div class="content-section tab-content" id="appointments-tab" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Appointment Configuration</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="appointment_duration" class="form-label">Default Duration (minutes)</label>
                        <input type="number" id="appointment_duration" name="appointment_duration" class="form-input" 
                               min="5" max="240" value="<?php echo $current_settings['appointment_duration']; ?>">
                        <div class="form-help">Duration for each appointment slot</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_appointments_per_day" class="form-label">Max Appointments/Day</label>
                        <input type="number" id="max_appointments_per_day" name="max_appointments_per_day" class="form-input" 
                               min="1" max="100" value="<?php echo $current_settings['max_appointments_per_day']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="advance_booking_days" class="form-label">Advance Booking (days)</label>
                        <input type="number" id="advance_booking_days" name="advance_booking_days" class="form-input" 
                               min="1" max="365" value="<?php echo $current_settings['advance_booking_days']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cancellation_hours" class="form-label">Cancellation Notice (hours)</label>
                        <input type="number" id="cancellation_hours" name="cancellation_hours" class="form-input" 
                               min="1" max="168" value="<?php echo $current_settings['cancellation_hours']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="default_consultation_fee" class="form-label">Default Consultation Fee</label>
                        <div class="input-with-symbol">
                            <span class="currency-symbol"><?php echo $current_settings['currency_symbol']; ?></span>
                            <input type="number" id="default_consultation_fee" name="default_consultation_fee" class="form-input" 
                                   step="0.01" min="0" value="<?php echo $current_settings['default_consultation_fee']; ?>" style="padding-left: 2.5rem;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency_symbol" class="form-label">Currency Symbol</label>
                        <input type="text" id="currency_symbol" name="currency_symbol" class="form-input" 
                               maxlength="5" value="<?php echo htmlspecialchars($current_settings['currency_symbol']); ?>">
                    </div>
                </div>
                
                <div class="toggle-group">
                    <div class="toggle-info">
                        <div class="toggle-title">Auto-confirm Appointments</div>
                        <div class="toggle-description">Automatically confirm new appointments without manual review</div>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" id="auto_confirm_appointments" name="auto_confirm_appointments" class="toggle-input"
                               <?php echo $current_settings['auto_confirm_appointments'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </div>
                </div>
            </div>

            <!-- Notifications Settings Tab -->
            <div class="content-section tab-content" id="notifications-tab" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-envelope"></i> Notification Preferences</h2>
                </div>
                <div class="notification-settings">
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">Email Notifications</div>
                            <div class="toggle-description">Send email notifications for appointments and updates</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="email_notifications" name="email_notifications" class="toggle-input"
                                   <?php echo $current_settings['email_notifications'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </div>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">SMS Notifications</div>
                            <div class="toggle-description">Send SMS notifications for appointment reminders</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" class="toggle-input"
                                   <?php echo $current_settings['sms_notifications'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Settings Tab -->
            <div class="content-section tab-content" id="security-tab" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-lock"></i> Security Configuration</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                        <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-input" 
                               min="1" max="10" value="<?php echo $current_settings['max_login_attempts']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                        <input type="number" id="session_timeout" name="session_timeout" class="form-input" 
                               min="300" max="86400" value="<?php echo $current_settings['session_timeout']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="backup_frequency" class="form-label">Backup Frequency</label>
                        <select id="backup_frequency" name="backup_frequency" class="form-select">
                            <option value="daily" <?php echo $current_settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $current_settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $current_settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-title">Maintenance Mode</div>
                                <div class="toggle-description">Enable maintenance mode to restrict access to the site</div>
                            </div>
                            <div class="toggle-switch">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" class="toggle-input"
                                       <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appearance Settings Tab -->
            <div class="content-section tab-content" id="appearance-tab" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-paint-brush"></i> Visual Customization</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="theme_color" class="form-label">Theme Color</label>
                        <div class="color-picker-group">
                            <div class="color-preview" style="background-color: <?php echo $current_settings['theme_color']; ?>" onclick="document.getElementById('theme_color').click()"></div>
                            <input type="color" id="theme_color" name="theme_color" class="color-input" 
                                   value="<?php echo $current_settings['theme_color']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_format" class="form-label">Date Format</label>
                        <select id="date_format" name="date_format" class="form-select">
                            <option value="Y-m-d" <?php echo $current_settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            <option value="m/d/Y" <?php echo $current_settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php echo $current_settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_format" class="form-label">Time Format</label>
                        <select id="time_format" name="time_format" class="form-select">
                            <option value="H:i" <?php echo $current_settings['time_format'] === 'H:i' ? 'selected' : ''; ?>>24 Hour</option>
                            <option value="g:i A" <?php echo $current_settings['time_format'] === 'g:i A' ? 'selected' : ''; ?>>12 Hour</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="logo_url" class="form-label">Logo URL</label>
                        <input type="url" id="logo_url" name="logo_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['logo_url']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="favicon_url" class="form-label">Favicon URL</label>
                        <input type="url" id="favicon_url" name="favicon_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_settings['favicon_url']); ?>">
                    </div>
                </div>
            </div>

            <!-- System Info Tab -->
            <div class="content-section tab-content" id="system-tab" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> System Information</h2>
                </div>
                <div class="system-info-grid">
                    <div class="info-card">
                        <h4><i class="fas fa-server"></i> Server Information</h4>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">PHP Version:</span>
                                <span class="info-value"><?php echo $system_info['php_version']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Database Version:</span>
                                <span class="info-value"><?php echo $system_info['database_version']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Server Software:</span>
                                <span class="info-value"><?php echo $system_info['server_software']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4><i class="fas fa-cog"></i> PHP Configuration</h4>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Upload Max Size:</span>
                                <span class="info-value"><?php echo $system_info['max_upload_size']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Memory Limit:</span>
                                <span class="info-value"><?php echo $system_info['memory_limit']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Max Execution Time:</span>
                                <span class="info-value"><?php echo $system_info['max_execution_time']; ?>s</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4><i class="fas fa-database"></i> Database Statistics</h4>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Total Users:</span>
                                <span class="info-value"><?php echo $db_stats['total_users']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Doctors:</span>
                                <span class="info-value"><?php echo $db_stats['total_doctors']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Patients:</span>
                                <span class="info-value"><?php echo $db_stats['total_patients']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Appointments:</span>
                                <span class="info-value"><?php echo $db_stats['total_appointments']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="content-section">
                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                        <i class="fas fa-undo"></i> Reset Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });

            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            const targetContent = document.getElementById(targetTab + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
                targetContent.style.display = 'block';
            }
        });
    });

    // Color picker functionality
    const colorPicker = document.getElementById('theme_color');
    const colorPreview = document.querySelector('.color-preview');
    
    if (colorPicker && colorPreview) {
        colorPicker.addEventListener('change', function() {
            colorPreview.style.backgroundColor = this.value;
            document.documentElement.style.setProperty('--primary-cyan', this.value);
        });
        
        colorPreview.addEventListener('click', function() {
            colorPicker.click();
        });
    }

    // Form validation
    const form = document.querySelector('.settings-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const email = document.getElementById('admin_email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            // Reset button after 3 seconds if form doesn't submit
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
