<?php
$page_title = "Doctor Profile";
$additional_css = ['doctor/sidebar-doctor.css', 'doctor/profile-doctor.css'];
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit();
}

// Get database connection
$db = Database::getInstance();

$doctor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_basic_info':
                $result = updateBasicInfo($db, $doctor_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    // Update session data
                    $_SESSION['first_name'] = $_POST['first_name'];
                    $_SESSION['last_name'] = $_POST['last_name'];
                    $_SESSION['email'] = $_POST['email'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_medical_info':
                $result = updateMedicalInfo($db, $doctor_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'change_password':
                $result = changePassword($db, $doctor_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_preferences':
                $result = updatePreferences($db, $doctor_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get doctor information
$doctor_info = getDoctorProfile($db, $doctor_id);

// Helper functions
function getDoctorProfile($db, $doctor_id) {
    $sql = "
        SELECT 
            u.first_name, u.last_name, u.email,
            d.specialty as specialization, d.license_number, 
            d.experience_years as years_of_experience,
            d.biography as bio, d.consultation_fee,
            COUNT(a.id) as total_appointments
        FROM users u
        JOIN doctors d ON d.user_id = u.id
        LEFT JOIN appointments a ON a.doctor_id = d.id
        WHERE u.id = ?
        GROUP BY u.id
    ";
    
    $result = $db->fetch($sql, [$doctor_id]);
    
    // Map old column names to new ones for backward compatibility
    if ($result) {
        if (empty($result['specialization']) && !empty($result['specialty'])) {
            $result['specialization'] = $result['specialty'];
        }
        if (empty($result['years_of_experience']) && !empty($result['experience_years'])) {
            $result['years_of_experience'] = $result['experience_years'];
        }
        if (empty($result['bio']) && !empty($result['biography'])) {
            $result['bio'] = $result['biography'];
        }
    }
    
    return $result;
}

function updateBasicInfo($db, $doctor_id, $data) {
    try {
        // Validate email uniqueness
        $email_check = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $doctor_id]);
        if ($email_check) {
            return ['success' => false, 'message' => 'Email address is already in use.'];
        }
        
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ? WHERE id = ?";
        $result = $db->query($sql, [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['date_of_birth'],
            $data['gender'],
            $doctor_id
        ]);
        
        if ($result->rowCount() > 0) {
            return ['success' => true, 'message' => 'Basic information updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating information: ' . $e->getMessage()];
    }
}

function updateMedicalInfo($db, $doctor_id, $data) {
    try {
        // Check which columns exist and use appropriate ones
        $sql = "UPDATE doctors SET 
                specialty = ?, 
                specialization = ?, 
                license_number = ?, 
                experience_years = ?, 
                years_of_experience = ?, 
                education = COALESCE(?, education), 
                biography = ?, 
                bio = ?, 
                office_address = COALESCE(?, office_address), 
                consultation_fee = ? 
                WHERE user_id = ?";
        $result = $db->query($sql, [
            $data['specialization'], // for old specialty column
            $data['specialization'], // for new specialization column
            $data['license_number'],
            (int)$data['years_of_experience'], // for old column
            (int)$data['years_of_experience'], // for new column
            $data['education'],
            $data['bio'], // for old biography column
            $data['bio'], // for new bio column
            $data['office_address'],
            (float)$data['consultation_fee'],
            $doctor_id
        ]);
        
        if ($result->rowCount() > 0) {
            return ['success' => true, 'message' => 'Medical information updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating medical information: ' . $e->getMessage()];
    }
}

function changePassword($db, $doctor_id, $data) {
    try {
        // Verify current password
        $current_user = $db->fetch("SELECT password FROM users WHERE id = ?", [$doctor_id]);
        if (!password_verify($data['current_password'], $current_user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        // Validate new password
        if ($data['new_password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'New passwords do not match.'];
        }
        
        if (strlen($data['new_password']) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }
        
        // Update password
        $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $result = $db->query($sql, [$hashed_password, $doctor_id]);
        
        if ($result->rowCount() > 0) {
            return ['success' => true, 'message' => 'Password changed successfully!'];
        } else {
            return ['success' => false, 'message' => 'Error changing password.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()];
    }
}

function updatePreferences($db, $doctor_id, $data) {
    try {
        $schedule_days = isset($data['available_days']) ? implode(',', $data['available_days']) : '';
        $schedule_time_start = $data['schedule_time_start'] ?? null;
        $schedule_time_end = $data['schedule_time_end'] ?? null;
        
        // Update columns that exist in the doctors table
        $sql = "UPDATE doctors SET 
                    schedule_days = ?, 
                    schedule_time_start = ?, 
                    schedule_time_end = ? 
                WHERE user_id = ?";
        $result = $db->query($sql, [
            $schedule_days,
            $schedule_time_start,
            $schedule_time_end,
            $doctor_id
        ]);
        
        if ($result->rowCount() > 0) {
            return ['success' => true, 'message' => 'Schedule preferences updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating preferences: ' . $e->getMessage()];
    }
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

require_once '../includes/header.php';
?>
    
    <div class="doctor-container">
        <div class="doctor-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
                <p>Dr. <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? 'Doctor')); ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_doctor.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-clock"></i> Schedule
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-users"></i> My Patients
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user-cog"></i> Profile
                </a>
            </nav>
        </div>
        
        <main class="doctor-content">
            <!-- Page Header -->
            <div class="content-header">
                <h1><i class="fas fa-user-cog"></i> Doctor Profile</h1>
                <p>Manage your professional profile and account settings</p>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Overview -->
            <div class="profile-overview">
                <div class="profile-summary-card">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <?= getInitials($doctor_info['first_name'] . ' ' . $doctor_info['last_name']) ?>
                        </div>
                        <div class="profile-basic-info">
                            <h2>Dr. <?= htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']) ?></h2>
                            <p class="profile-specialization"><?= htmlspecialchars($doctor_info['specialization'] ?? 'General Practice') ?></p>
                            <p class="profile-license">License: <?= htmlspecialchars($doctor_info['license_number'] ?? 'Not specified') ?></p>
                        </div>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="stat-number"><?= number_format($doctor_info['total_appointments'] ?? 0) ?></div>
                            <div class="stat-label">Total Appointments</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-number"><?= $doctor_info['years_of_experience'] ?? 0 ?></div>
                            <div class="stat-label">Years Experience</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-number"><?= number_format($doctor_info['avg_rating'] ?? 0, 1) ?></div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-number"><?= number_format($doctor_info['total_reviews'] ?? 0) ?></div>
                            <div class="stat-label">Reviews</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="basic-info">
                        <i class="fas fa-user"></i> Basic Information
                    </button>
                    <button class="tab-btn" data-tab="medical-info">
                        <i class="fas fa-stethoscope"></i> Medical Information
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-lock"></i> Security
                    </button>
                    <button class="tab-btn" data-tab="preferences">
                        <i class="fas fa-cog"></i> Preferences
                    </button>
                </div>

                <!-- Basic Information Tab -->
                <div class="tab-content active" id="basic-info">
                    <div class="content-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user"></i> Basic Information</h3>
                            <p>Update your personal information and contact details</p>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_basic_info">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user"></i> First Name
                                    </label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['first_name'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user"></i> Last Name
                                    </label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['last_name'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['email'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone"></i> Phone Number
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label">
                                        <i class="fas fa-calendar"></i> Date of Birth
                                    </label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['date_of_birth'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars"></i> Gender
                                    </label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= ($doctor_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($doctor_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= ($doctor_info['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Basic Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Medical Information Tab -->
                <div class="tab-content" id="medical-info">
                    <div class="content-section">
                        <div class="section-header">
                            <h3><i class="fas fa-stethoscope"></i> Medical Information</h3>
                            <p>Update your professional medical credentials and details</p>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_medical_info">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="specialization" class="form-label">
                                        <i class="fas fa-stethoscope"></i> Specialization
                                    </label>
                                    <input type="text" id="specialization" name="specialization" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['specialization'] ?? '') ?>" 
                                           placeholder="e.g., Cardiology, Pediatrics, General Medicine">
                                </div>
                                
                                <div class="form-group">
                                    <label for="license_number" class="form-label">
                                        <i class="fas fa-id-card"></i> License Number
                                    </label>
                                    <input type="text" id="license_number" name="license_number" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['license_number'] ?? '') ?>" 
                                           placeholder="Medical license number">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="years_of_experience" class="form-label">
                                        <i class="fas fa-calendar-check"></i> Years of Experience
                                    </label>
                                    <input type="number" id="years_of_experience" name="years_of_experience" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['years_of_experience'] ?? '') ?>" 
                                           min="0" max="50">
                                </div>
                                
                                <div class="form-group">
                                    <label for="consultation_fee" class="form-label">
                                        <i class="fas fa-coins"></i> Consultation Fee (₱)
                                    </label>
                                    <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['consultation_fee'] ?? '') ?>" 
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="education" class="form-label">
                                    <i class="fas fa-graduation-cap"></i> Education & Qualifications
                                </label>
                                <textarea id="education" name="education" class="form-control" rows="3" 
                                          placeholder="List your medical degrees, certifications, and qualifications"><?= htmlspecialchars($doctor_info['education'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">
                                    <i class="fas fa-user-md"></i> Professional Bio
                                </label>
                                <textarea id="bio" name="bio" class="form-control" rows="4" 
                                          placeholder="Tell patients about your background, experience, and approach to medicine"><?= htmlspecialchars($doctor_info['bio'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="office_address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Office Address
                                </label>
                                <textarea id="office_address" name="office_address" class="form-control" rows="2" 
                                          placeholder="Your clinic or office address"><?= htmlspecialchars($doctor_info['office_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Medical Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-content" id="security">
                    <div class="content-section">
                        <div class="section-header">
                            <h3><i class="fas fa-lock"></i> Security Settings</h3>
                            <p>Change your password and manage account security</p>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <input type="password" id="current_password" name="current_password" class="form-control">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-key"></i> New Password
                                    </label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           minlength="8">
                                    <small class="form-help">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-key"></i> Confirm New Password
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           minlength="8">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-content" id="preferences">
                    <div class="content-section">
                        <div class="section-header">
                            <h3><i class="fas fa-cog"></i> Preferences</h3>
                            <p>Configure your availability and notification settings</p>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-week"></i> Available Days
                                </label>
                                <div class="checkbox-group">
                                    <?php
                                    $schedule_days = explode(',', $doctor_info['schedule_days'] ?? '');
                                    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                    foreach ($days as $day): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="available_days[]" value="<?= $day ?>" 
                                                   <?= in_array($day, $schedule_days) ? 'checked' : '' ?>>
                                            <span class="checkbox-label"><?= ucfirst($day) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="schedule_time_start" class="form-label">
                                        <i class="fas fa-clock"></i> Start Time
                                    </label>
                                    <input type="time" id="schedule_time_start" name="schedule_time_start" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['schedule_time_start'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="schedule_time_end" class="form-label">
                                        <i class="fas fa-clock"></i> End Time
                                    </label>
                                    <input type="time" id="schedule_time_end" name="schedule_time_end" class="form-control" 
                                           value="<?= htmlspecialchars($doctor_info['schedule_time_end'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Note:</strong> You can set your available days and working hours. Additional preference settings (notifications, timezone) are not yet configured in the database.
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetTab = this.dataset.tab;
                
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(targetTab).classList.add('active');
            });
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>

    <?php require_once '../includes/footer.php'; ?>
