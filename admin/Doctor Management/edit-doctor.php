<?php
$page_title = "Edit Doctor";
$additional_css = ['admin/edit-doctor.css', 'admin/sidebar.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/index.php');
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

// Handle lab offer actions
$lab_offer_message = '';
$lab_offer_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lab_offer_action'])) {
    $action = $_POST['lab_offer_action'];
    
    // Get doctor's internal ID
    $doctor_internal_id = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_id])['id'];
    
    switch ($action) {
        case 'add_lab_offer':
            $result = addLabOffer($db, $doctor_internal_id, $_POST);
            if ($result['success']) {
                $lab_offer_message = $result['message'];
            } else {
                $lab_offer_error = $result['message'];
            }
            break;
            
        case 'update_lab_offer':
            $result = updateLabOffer($db, $doctor_internal_id, $_POST);
            if ($result['success']) {
                $lab_offer_message = $result['message'];
            } else {
                $lab_offer_error = $result['message'];
            }
            break;
            
        case 'delete_lab_offer':
            $result = deleteLabOffer($db, $_POST);
            if ($result['success']) {
                $lab_offer_message = $result['message'];
            } else {
                $lab_offer_error = $result['message'];
            }
            break;
            
        case 'toggle_lab_offer':
            $result = toggleLabOffer($db, $_POST);
            if ($result['success']) {
                $lab_offer_message = $result['message'];
            } else {
                $lab_offer_error = $result['message'];
            }
            break;
    }
}

// Lab offer helper functions
function addLabOffer($db, $doctor_internal_id, $data) {
    try {
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required.'];
        }
        
        // Check if offer already exists for this doctor
        $existing = $db->fetch("
            SELECT lo.id 
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lod.doctor_id = ? AND LOWER(lo.title) = LOWER(?)
        ", [$doctor_internal_id, $data['title']]);
        
        if ($existing) {
            return ['success' => false, 'message' => 'This lab offer already exists for this doctor.'];
        }
        
        // Insert lab offer
        $lab_offer_id = $db->insert('lab_offers', [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => !empty($data['price']) ? (float)$data['price'] : null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Link to doctor
        $db->insert('lab_offer_doctors', [
            'lab_offer_id' => $lab_offer_id,
            'doctor_id' => $doctor_internal_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return ['success' => true, 'message' => 'Lab offer added successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding lab offer: ' . $e->getMessage()];
    }
}

function updateLabOffer($db, $doctor_internal_id, $data) {
    try {
        if (empty($data['offer_id']) || empty($data['title'])) {
            return ['success' => false, 'message' => 'Missing required fields.'];
        }
        
        // Verify this offer belongs to the doctor
        $verify = $db->fetch("
            SELECT lod.id 
            FROM lab_offer_doctors lod
            WHERE lod.lab_offer_id = ? AND lod.doctor_id = ?
        ", [$data['offer_id'], $doctor_internal_id]);
        
        if (!$verify) {
            return ['success' => false, 'message' => 'Unauthorized action.'];
        }
        
        // Update lab offer
        $db->update('lab_offers', [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => !empty($data['price']) ? (float)$data['price'] : null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$data['offer_id']]);
        
        return ['success' => true, 'message' => 'Lab offer updated successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating lab offer: ' . $e->getMessage()];
    }
}

function deleteLabOffer($db, $data) {
    try {
        if (empty($data['offer_id'])) {
            return ['success' => false, 'message' => 'Missing offer ID.'];
        }
        
        // Delete the lab offer link and offer itself if no other doctors have it
        $doctor_count = $db->fetch("
            SELECT COUNT(*) as count 
            FROM lab_offer_doctors 
            WHERE lab_offer_id = ?
        ", [$data['offer_id']])['count'];
        
        if ($doctor_count <= 1) {
            // Delete the offer itself
            $db->delete('lab_offers', 'id = ?', [$data['offer_id']]);
        } else {
            // Just remove the link for this doctor
            $db->delete('lab_offer_doctors', 'lab_offer_id = ?', [$data['offer_id']]);
        }
        
        return ['success' => true, 'message' => 'Lab offer deleted successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting lab offer: ' . $e->getMessage()];
    }
}

function toggleLabOffer($db, $data) {
    try {
        if (empty($data['offer_id'])) {
            return ['success' => false, 'message' => 'Missing offer ID.'];
        }
        
        // Toggle active status
        $current = $db->fetch("SELECT is_active FROM lab_offers WHERE id = ?", [$data['offer_id']]);
        $new_status = $current['is_active'] ? 0 : 1;
        
        $db->update('lab_offers', [
            'is_active' => $new_status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$data['offer_id']]);
        
        $status_text = $new_status ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Lab offer $status_text successfully!"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error toggling lab offer: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['lab_offer_action'])) {
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
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
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
                    <input type="text" id="specialty" name="specialty" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['specialty'] ?? $doctor['specialty']); ?>" 
                           placeholder="e.g., General Medicine, Cardiology, Pediatrics" required>
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
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
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
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
    
    <!-- Laboratory Offers -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3><i class="fas fa-flask"></i> Laboratory Offers</h3>
                    <p style="font-size: 0.9rem; font-weight: normal; margin: 0.5rem 0 0 0;">Manage laboratory tests and diagnostic services</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="openLabOfferModal()" style="white-space: nowrap;">
                    <i class="fas fa-plus"></i> Add Lab Offer
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php 
            // Success/Error Messages for lab offers
            if ($lab_offer_message): ?>
                <div class="alert alert-success" style="margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($lab_offer_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($lab_offer_error): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($lab_offer_error); ?>
                </div>
            <?php endif; ?>
            
            <?php 
            // Get laboratory offers for this doctor
            $lab_offers = $db->fetchAll("
                SELECT lo.* 
                FROM lab_offers lo
                JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
                JOIN doctors d ON lod.doctor_id = d.id
                WHERE d.user_id = ?
                ORDER BY lo.title ASC
            ", [$doctor_id]);
            ?>
            
            <?php if (!empty($lab_offers)): ?>
                <div class="lab-offers-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.25rem;">
                    <?php foreach ($lab_offers as $offer): ?>
                        <div class="lab-offer-card" style="padding: 1.5rem; background: #ffffff; border-radius: 10px; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; gap: 1rem;">
                                <h4 style="margin: 0; color: #1f2937; font-size: 1.15rem; font-weight: 600; flex: 1; line-height: 1.4;">
                                    <?php echo htmlspecialchars($offer['title']); ?>
                                </h4>
                                <span style="display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; white-space: nowrap; <?php echo $offer['is_active'] ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                    <?php echo $offer['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <?php if (!empty($offer['description'])): ?>
                                <p style="margin: 0 0 1rem 0; color: #6b7280; font-size: 0.925rem; line-height: 1.6;">
                                    <?php echo htmlspecialchars($offer['description']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($offer['price'])): ?>
                                <div style="margin: 0 0 1.25rem 0; padding: 0.75rem 1rem; background: #f0f9ff; border-radius: 8px; border-left: 3px solid #00bcd4;">
                                    <p style="margin: 0; color: #0891b2; font-weight: 700; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-coins"></i> ₱<?php echo number_format($offer['price'], 2); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; gap: 0.625rem; padding-top: 0.5rem; border-top: 1px solid #e5e7eb;">
                                <button type="button" class="btn btn-sm btn-secondary" 
                                        onclick='editLabOffer(<?php echo json_encode($offer); ?>)'
                                        style="flex: 1; width: 100%;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm <?php echo $offer['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                        onclick="toggleLabOfferStatus(<?php echo $offer['id']; ?>)"
                                        style="flex: 1; width: 100%;">
                                    <i class="fas fa-<?php echo $offer['is_active'] ? 'eye-slash' : 'eye'; ?>"></i> 
                                    <?php echo $offer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteLabOffer(<?php echo $offer['id']; ?>)"
                                        style="flex: 1; width: 100%;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 2rem; color: var(--text-light);">
                    <i class="fas fa-flask" style="font-size: 3.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                    <p style="font-size: 1.1rem; font-weight: 600; margin: 0 0 0.5rem 0;">No laboratory offers added yet</p>
                    <p style="font-size: 0.95rem; margin: 0;">Click "Add Lab Offer" to create laboratory services for this doctor</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

    </div>
</div>

<!-- Hidden forms for lab offer actions (outside main form) -->
<form id="toggleLabOfferForm" method="POST" style="display: none;">
    <input type="hidden" name="lab_offer_action" value="toggle_lab_offer">
    <input type="hidden" name="offer_id" id="toggle_offer_id">
</form>

<form id="deleteLabOfferForm" method="POST" style="display: none;">
    <input type="hidden" name="lab_offer_action" value="delete_lab_offer">
    <input type="hidden" name="offer_id" id="delete_offer_id">
</form>

<!-- Lab Offer Modal -->
<div id="labOfferModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background-color: white; margin: auto; padding: 0; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalTitle" style="margin: 0; color: var(--text-dark); font-size: 1.4rem;">
                <i class="fas fa-flask"></i> Add Laboratory Offer
            </h3>
            <button onclick="closeLabOfferModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light); padding: 0; width: 30px; height: 30px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="labOfferForm" method="POST" style="padding: 2rem;">
            <input type="hidden" name="lab_offer_action" id="lab_offer_action" value="add_lab_offer">
            <input type="hidden" name="offer_id" id="offer_id" value="">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600;">
                    <i class="fas fa-tag"></i> Title <span style="color: #e74c3c;">*</span>
                </label>
                <input type="text" name="title" id="title" required
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;"
                       placeholder="e.g., Complete Blood Count (CBC)">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600;">
                    <i class="fas fa-align-left"></i> Description
                </label>
                <textarea name="description" id="description" rows="3"
                          style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; resize: vertical;"
                          placeholder="Brief description of the laboratory test..."></textarea>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600;">
                    <i class="fas fa-coins"></i> Price (₱)
                </label>
                <input type="number" name="price" id="price" step="0.01" min="0"
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;"
                       placeholder="e.g., 500.00">
                <small style="color: var(--text-light); font-size: 0.85rem;">Leave blank if price varies</small>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeLabOfferModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Lab Offer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openLabOfferModal(editMode = false, offerData = null) {
        const modal = document.getElementById('labOfferModal');
        const form = document.getElementById('labOfferForm');
        const modalTitle = document.getElementById('modalTitle');
        
        if (editMode && offerData) {
            modalTitle.innerHTML = '<i class="fas fa-flask"></i> Edit Laboratory Offer';
            document.getElementById('lab_offer_action').value = 'update_lab_offer';
            document.getElementById('offer_id').value = offerData.id;
            document.getElementById('title').value = offerData.title;
            document.getElementById('description').value = offerData.description || '';
            document.getElementById('price').value = offerData.price || '';
        } else {
            modalTitle.innerHTML = '<i class="fas fa-flask"></i> Add Laboratory Offer';
            document.getElementById('lab_offer_action').value = 'add_lab_offer';
            document.getElementById('offer_id').value = '';
            form.reset();
        }
        
        modal.style.display = 'flex';
    }
    
    function editLabOffer(offerData) {
        openLabOfferModal(true, offerData);
    }
    
    function toggleLabOfferStatus(offerId) {
        if (confirm('Toggle this lab offer status?')) {
            document.getElementById('toggle_offer_id').value = offerId;
            document.getElementById('toggleLabOfferForm').submit();
        }
    }
    
    function deleteLabOffer(offerId) {
        if (confirm('Are you sure you want to delete this lab offer?')) {
            document.getElementById('delete_offer_id').value = offerId;
            document.getElementById('deleteLabOfferForm').submit();
        }
    }
    
    function closeLabOfferModal() {
        document.getElementById('labOfferModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('labOfferModal');
        if (event.target == modal) {
            closeLabOfferModal();
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
