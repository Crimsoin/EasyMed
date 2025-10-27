<?php
$page_title = "Edit Doctor";
$additional_css = ['admin/edit-doctor.css', 'admin/sidebar.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$db = Database::getInstance();
$doctor_id = intval($_GET['id'] ?? 0);

if (!$doctor_id) {
    header('Location: doctors.php');
    exit();
}

// Get doctor data
$doctor = $db->fetch("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.profile_image, u.is_active, u.created_at,
           d.specialty, d.license_number, d.experience_years, d.consultation_fee, d.schedule_days, 
           d.schedule_time_start, d.schedule_time_end, d.is_available, d.biography, d.phone,
           p.date_of_birth, p.gender
    FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ? AND u.role = 'doctor'
", [$doctor_id]);

if (!$doctor) {
    $_SESSION['error_message'] = "Doctor not found.";
    header('Location: doctors.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];
        
        $userData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $doctorData = [
            'specialty' => trim($_POST['specialty'] ?? ''),
            'license_number' => trim($_POST['license_number'] ?? ''),
            'experience_years' => intval($_POST['experience_years'] ?? 0),
            'consultation_fee' => floatval($_POST['consultation_fee'] ?? 0),
            'schedule_days' => $_POST['schedule_days'] ?? [],
            'schedule_time_start' => $_POST['schedule_time_start'] ?? '',
            'schedule_time_end' => $_POST['schedule_time_end'] ?? '',
            'biography' => trim($_POST['biography'] ?? ''),
            'is_available' => isset($_POST['is_available']) ? 1 : 0
        ];
        
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($userData['first_name'])) $errors[] = 'First name is required';
        if (empty($userData['last_name'])) $errors[] = 'Last name is required';
        if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required';
        }
    // Username is no longer required/edited for doctors.
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        if (empty($doctorData['specialty'])) $errors[] = 'Specialty is required';
        if (empty($doctorData['license_number'])) $errors[] = 'License number is required';
        
    // Check if email exists for other users
    $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", 
                [$userData['email'], $doctor_id]);
        if ($existing) {
            $errors[] = 'Email or username already exists for another user';
        }
        
        if (empty($errors)) {
            $db->beginTransaction();
            
            try {
                // Update user data (only fields that exist in users table)
                // Don't modify the username column for doctors; leave existing internal username unchanged.
                $userUpdateSql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, is_active = ?, updated_at = datetime('now') WHERE id = ?";
                $userParams = [
                    $userData['first_name'],
                    $userData['last_name'], 
                    $userData['email'],
                    $userData['is_active'],
                    $doctor_id
                ];
                
                $db->query($userUpdateSql, $userParams);
                
                // Update password if provided
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $doctor_id]);
                }
                
                // Handle phone, date_of_birth, gender (these might be in patients table if doctor also has patient record)
                if (!empty($userData['phone']) || !empty($userData['date_of_birth']) || !empty($userData['gender'])) {
                    $existingPatient = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$doctor_id]);
                    
                    if ($existingPatient) {
                        // Update existing patient record
                        $db->query("
                            UPDATE patients SET phone = ?, date_of_birth = ?, gender = ? 
                            WHERE user_id = ?
                        ", [
                            $userData['phone'] ?: null,
                            $userData['date_of_birth'] ?: null,
                            $userData['gender'] ?: null,
                            $doctor_id
                        ]);
                    } else if (!empty($userData['phone']) || !empty($userData['date_of_birth']) || !empty($userData['gender'])) {
                        // Create patient record for additional fields
                        $db->query("
                            INSERT INTO patients (user_id, phone, date_of_birth, gender, status, created_at)
                            VALUES (?, ?, ?, ?, 'active', datetime('now'))
                        ", [
                            $doctor_id,
                            $userData['phone'] ?: null,
                            $userData['date_of_birth'] ?: null,
                            $userData['gender'] ?: null
                        ]);
                    }
                }
                
                // Prepare doctor data
                $schedule_days_str = is_array($doctorData['schedule_days']) ? 
                                   implode(',', $doctorData['schedule_days']) : 
                                   $doctorData['schedule_days'];
                
                // Update or insert doctor profile
                $existingDoctor = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_id]);
                
                if ($existingDoctor) {
                    // Update existing doctor profile
                    $db->query("
                        UPDATE doctors SET 
                            specialty = ?, license_number = ?, experience_years = ?, 
                            consultation_fee = ?, schedule_days = ?, schedule_time_start = ?, 
                            schedule_time_end = ?, biography = ?, is_available = ?, phone = ?
                        WHERE user_id = ?
                    ", [
                        $doctorData['specialty'],
                        $doctorData['license_number'],
                        $doctorData['experience_years'],
                        $doctorData['consultation_fee'],
                        $schedule_days_str,
                        $doctorData['schedule_time_start'],
                        $doctorData['schedule_time_end'],
                        $doctorData['biography'],
                        $doctorData['is_available'],
                        $userData['phone'] ?: null,
                        $doctor_id
                    ]);
                } else {
                    // Insert new doctor profile
                    $db->query("
                        INSERT INTO doctors (
                            user_id, specialty, license_number, experience_years, 
                            consultation_fee, schedule_days, schedule_time_start, 
                            schedule_time_end, biography, is_available, phone, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
                    ", [
                        $doctor_id,
                        $doctorData['specialty'],
                        $doctorData['license_number'],
                        $doctorData['experience_years'],
                        $doctorData['consultation_fee'],
                        $schedule_days_str,
                        $doctorData['schedule_time_start'],
                        $doctorData['schedule_time_end'],
                        $doctorData['biography'],
                        $doctorData['is_available'],
                        $userData['phone'] ?: null
                    ]);
                }
                
                $db->commit();
                
                // Log activity
                logActivity($_SESSION['user_id'], 'update_doctor', "Updated doctor profile for Dr. {$userData['first_name']} {$userData['last_name']}");
                
                $_SESSION['success_message'] = "Doctor profile updated successfully!";
                header('Location: doctors.php');
                exit();
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error updating doctor profile: " . $e->getMessage();
    }
}

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
            <a href="../Doctor Management/doctors.php" class="nav-item active">
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
            <div class="header-info">
                <h1><i class="fas fa-edit"></i> Edit Doctor</h1>
                <p>Update doctor account and profile information</p>
            </div>
            <div class="header-actions">
                <a href="view-doctor.php?id=<?php echo $doctor_id; ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> View Profile
                </a>
                <a href="../Doctor Management/doctors.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="admin-form">
    <!-- Basic Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> Basic Information</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name" class="form-label required">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? $doctor['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? $doctor['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email" class="form-label required">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $doctor['email']); ?>" required>
                </div>
                
                <!-- Username is internal and not editable from this form -->
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" id="password" name="password" class="form-control">
                    <small class="form-text">Leave blank to keep current password. Minimum 6 characters if changing.</small>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $doctor['phone']); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $doctor['date_of_birth']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="gender" class="form-label">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($_POST['gender'] ?? $doctor['gender']) === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($_POST['gender'] ?? $doctor['gender']) === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($_POST['gender'] ?? $doctor['gender']) === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo ($_POST['is_active'] ?? $doctor['is_active']) ? 'checked' : ''; ?>>
                    <span class="checkmark"></span>
                    Active account
                </label>
                <small class="form-text">Uncheck to deactivate this doctor's account</small>
            </div>
        </div>
    </div>
    
    <!-- Professional Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-stethoscope"></i> Professional Information</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="specialty" class="form-label required">Specialty</label>
                    <select id="specialty" name="specialty" class="form-control" required>
                        <option value="">Select Specialty</option>
                        <option value="General Medicine" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'General Medicine' ? 'selected' : ''; ?>>General Medicine</option>
                        <option value="Cardiology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                        <option value="Dermatology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                        <option value="Endocrinology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Endocrinology' ? 'selected' : ''; ?>>Endocrinology</option>
                        <option value="Gastroenterology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Gastroenterology' ? 'selected' : ''; ?>>Gastroenterology</option>
                        <option value="Neurology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                        <option value="Orthopedics" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                        <option value="Pediatrics" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                        <option value="Psychiatry" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                        <option value="Pulmonology" <?php echo ($_POST['specialty'] ?? $doctor['specialty']) === 'Pulmonology' ? 'selected' : ''; ?>>Pulmonology</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="license_number" class="form-label required">Medical License Number</label>
                    <input type="text" id="license_number" name="license_number" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['license_number'] ?? $doctor['license_number']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="experience_years" class="form-label">Years of Experience</label>
                    <input type="number" id="experience_years" name="experience_years" class="form-control" 
                           min="0" max="50" value="<?php echo htmlspecialchars($_POST['experience_years'] ?? $doctor['experience_years']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="consultation_fee" class="form-label">Consultation Fee (₱)</label>
                    <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" 
                           min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? $doctor['consultation_fee']); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schedule & Availability -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Schedule & Availability</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Available Days</label>
                <div class="checkbox-group">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $selected_days = $_POST['schedule_days'] ?? explode(',', $doctor['schedule_days'] ?? '');
                    ?>
                    <?php foreach ($days as $day): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="schedule_days[]" value="<?php echo $day; ?>" 
                                   <?php echo in_array($day, $selected_days) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            <?php echo $day; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="schedule_time_start" class="form-label">Start Time</label>
                    <input type="time" id="schedule_time_start" name="schedule_time_start" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['schedule_time_start'] ?? $doctor['schedule_time_start']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="schedule_time_end" class="form-label">End Time</label>
                    <input type="time" id="schedule_time_end" name="schedule_time_end" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['schedule_time_end'] ?? $doctor['schedule_time_end']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_available" value="1" 
                           <?php echo ($_POST['is_available'] ?? $doctor['is_available']) ? 'checked' : ''; ?>>
                    <span class="checkmark"></span>
                    Available for appointments
                </label>
                <small class="form-text">Uncheck to make doctor unavailable for new appointments</small>
            </div>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Update Doctor Profile
        </button>
        <a href="../Doctor Management/doctors.php" class="btn btn-secondary btn-lg">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
