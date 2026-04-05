<?php
$page_title = "Doctor Profile";
$additional_css = ['doctor/sidebar-doctor.css', 'doctor/profile-doctor.css?v=' . time()];
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

// Get doctor's internal ID from doctors table
$doctorRecord = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_id]);
$doctor_internal_id = $doctorRecord['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $basic_result = updateBasicInfo($db, $doctor_id, $_POST);
                $medical_result = updateMedicalInfo($db, $doctor_id, $_POST);
                
                if ($basic_result['success'] || $medical_result['success']) {
                    $message = "Profile updated successfully!";
                    if ($basic_result['success']) {
                        $_SESSION['first_name'] = $_POST['first_name'];
                        $_SESSION['last_name'] = $_POST['last_name'];
                        $_SESSION['email'] = $_POST['email'];
                    }
                } else {
                    $error = $basic_result['message'] . " " . $medical_result['message'];
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
                
            case 'add_lab_offer':
                $result = addLabOffer($db, $doctor_internal_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_lab_offer':
                $result = updateLabOffer($db, $doctor_internal_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'delete_lab_offer':
                $result = deleteLabOffer($db, $doctor_internal_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'toggle_lab_offer':
                $result = toggleLabOffer($db, $doctor_internal_id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get doctor information and map to expected 'doctor' variable for template
$doctor_info = getDoctorProfile($db, $doctor_id);
$doctor = $doctor_info; // For template compatibility
$doctor['id'] = $doctor_id; // Ensure user ID is present

// Get doctor's lab offers
$lab_offers = getLabOffers($db, $doctor_internal_id);

// Get doctor statistics (Granular stats for template)
$stats = [
    'total_appointments' => $doctor_info['total_appointments'] ?? 0,
    'completed_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctor_internal_id])['count'],
    'pending_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status IN ('scheduled', 'confirmed')", [$doctor_internal_id])['count'],
    'cancelled_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'cancelled'", [$doctor_internal_id])['count']
];

// Get consultation history (Recent appointments for template)
$recent_appointments = $db->fetchAll("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason_for_visit, a.illness,
           (up.first_name || ' ' || up.last_name) as patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users up ON p.user_id = up.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
", [$doctor_internal_id]);

$avg_rating = $doctor_info['avg_rating'] ? round($doctor_info['avg_rating'], 1) : 0;
$total_reviews = $doctor_info['total_reviews'] ?? 0;

// Helper functions
function getDoctorProfile($db, $doctor_id) {
    $sql = "
        SELECT 
            u.id, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.gender, u.created_at, u.profile_image, u.is_active,
            d.id as doctor_id, d.specialty, d.license_number, 
            d.experience_years, d.biography, d.consultation_fee,
            d.schedule_days, d.schedule_time_start, d.schedule_time_end, d.is_available,
            (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) as total_appointments,
            (SELECT AVG(rating) FROM reviews WHERE doctor_id = d.id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE doctor_id = d.id) as total_reviews
        FROM users u
        JOIN doctors d ON d.user_id = u.id
        WHERE u.id = ?
    ";
    
    $result = $db->fetch($sql, [$doctor_id]);
    
    // Map old column names to new ones for backward compatibility if needed
    if ($result) {
        $result['specialization'] = $result['specialization'] ?? '';
        $result['years_of_experience'] = $result['years_of_experience'] ?? 0;
        $result['bio'] = $result['bio'] ?? '';
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
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
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
        $schedule_days = isset($data['schedule_days']) ? implode(', ', (array)$data['schedule_days']) : 'N/A';
        
        $sql = "UPDATE doctors SET 
                specialty = ?, 
                license_number = ?, 
                experience_years = ?, 
                consultation_fee = ?,
                schedule_days = ?,
                schedule_time_start = ?,
                schedule_time_end = ?
                WHERE user_id = ?";
        $result = $db->query($sql, [
            $data['specialization'],
            $data['license_number'],
            (int)$data['years_of_experience'],
            (float)$data['consultation_fee'],
            $schedule_days,
            $data['schedule_time_start'],
            $data['schedule_time_end'],
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

function getLabOffers($db, $doctor_internal_id) {
    try {
        $sql = "SELECT lo.* 
                FROM lab_offers lo
                JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
                WHERE lod.doctor_id = ?
                ORDER BY lo.title ASC";
        return $db->fetchAll($sql, [$doctor_internal_id]);
    } catch (Exception $e) {
        return [];
    }
}

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
            return ['success' => false, 'message' => 'This lab offer already exists.'];
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
            'price' => !empty($data['price']) ? (float)$data['price'] : null
        ], 'id = ?', [$data['offer_id']]);
        
        return ['success' => true, 'message' => 'Lab offer updated successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating lab offer: ' . $e->getMessage()];
    }
}

function deleteLabOffer($db, $doctor_internal_id, $data) {
    try {
        if (empty($data['offer_id'])) {
            return ['success' => false, 'message' => 'Missing offer ID.'];
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
        
        // Check if this is the only doctor with this offer
        $doctor_count = $db->fetch("
            SELECT COUNT(*) as count 
            FROM lab_offer_doctors 
            WHERE lab_offer_id = ?
        ", [$data['offer_id']])['count'];
        
        if ($doctor_count <= 1) {
            // Delete the offer itself if no other doctors have it
            $db->delete('lab_offers', 'id = ?', [$data['offer_id']]);
        } else {
            // Just remove the link
            $db->delete('lab_offer_doctors', 'lab_offer_id = ? AND doctor_id = ?', [$data['offer_id'], $doctor_internal_id]);
        }
        
        return ['success' => true, 'message' => 'Lab offer deleted successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting lab offer: ' . $e->getMessage()];
    }
}

function toggleLabOffer($db, $doctor_internal_id, $data) {
    try {
        if (empty($data['offer_id'])) {
            return ['success' => false, 'message' => 'Missing offer ID.'];
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
        
        // Toggle active status
        $current = $db->fetch("SELECT is_active FROM lab_offers WHERE id = ?", [$data['offer_id']]);
        $new_status = $current['is_active'] ? 0 : 1;
        
        $db->update('lab_offers', [
            'is_active' => $new_status
        ], 'id = ?', [$data['offer_id']]);
        
        $status_text = $new_status ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Lab offer $status_text successfully!"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error toggling lab offer: ' . $e->getMessage()];
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
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success" style="padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; background: #ecfdf5; color: #059669; border: 1px solid #10b9813d; display: flex; align-items: center; gap: 1rem; font-weight: 600;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; background: #fef2f2; color: #dc2626; border: 1px solid #ef44443d; display: flex; align-items: center; gap: 1rem; font-weight: 600;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php 
            $viewMode = 'doctor';
            include_once '../includes/components/doctor_details_template.php'; 
            ?>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div id="profileModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);">
        <div class="modal-content" style="background: white; margin: 2rem auto; border-radius: 20px; width: 95%; max-width: 900px; max-height: 85vh; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow-y: auto; position: relative;">
            <div class="modal-header" style="padding: 1.5rem 2.5rem; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700;"><i class="fas fa-user-edit" style="margin-right: 0.75rem;"></i> Edit Profile Details</h3>
                <span onclick="closeProfileModal()" style="cursor: pointer; font-size: 1.5rem; opacity: 0.8;">&times;</span>
            </div>
            <form method="POST" style="padding: 2.5rem;">
                <input type="hidden" name="action" value="update_profile">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #1e293b; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;"><i class="fas fa-info-circle" style="color: #3b82f6; margin-right: 0.5rem;"></i> Basic Info</h4>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($doctor['first_name'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($doctor['last_name'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($doctor['email'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #1e293b; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;"><i class="fas fa-stethoscope" style="color: #3b82f6; margin-right: 0.5rem;"></i> Professional Info</h4>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Specialty</label>
                            <input type="text" name="specialization" value="<?= htmlspecialchars($doctor['specialty'] ?? $doctor['specialization'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">License Number</label>
                            <input type="text" name="license_number" value="<?= htmlspecialchars($doctor['license_number'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Experience (Yrs)</label>
                                <input type="number" name="years_of_experience" value="<?= htmlspecialchars($doctor['experience_years'] ?? $doctor['years_of_experience'] ?? 0) ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Consultation Fee</label>
                                <input type="number" name="consultation_fee" value="<?= htmlspecialchars($doctor['consultation_fee'] ?? 0) ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?= htmlspecialchars($doctor['date_of_birth'] ?? '') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Gender</label>
                                <select name="gender" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-weight: 500; color: #1e293b; background: white;">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?= ($doctor['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($doctor['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($doctor['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Available Days Row (Full Width) -->
                    <div style="grid-column: span 2; margin-top: 1rem;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 1rem; display: block; border-top: 2px solid #f1f5f9; padding-top: 1.5rem;">
                            <i class="fas fa-calendar-check" style="color: #2563eb; margin-right: 0.5rem;"></i> Available Days
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.75rem; background: #f8fafc; padding: 1.25rem; border: 2px solid #e2e8f0; border-radius: 16px;">
                            <?php 
                            $current_days = array_map('trim', explode(',', $doctor['schedule_days'] ?? ''));
                            $days_map = [
                                'Monday' => 'Mon',
                                'Tuesday' => 'Tue',
                                'Wednesday' => 'Wed',
                                'Thursday' => 'Thu',
                                'Friday' => 'Fri',
                                'Saturday' => 'Sat',
                                'Sunday' => 'Sun'
                            ];
                            foreach ($days_map as $fullName => $shortName): 
                                $is_checked = in_array($fullName, $current_days);
                            ?>
                                <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 0.5rem; background: <?= $is_checked ? '#eff6ff' : 'white' ?>; border: 2px solid <?= $is_checked ? '#3b82f6' : '#e2e8f0' ?>; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; text-align: center; box-shadow: <?= $is_checked ? '0 4px 12px rgba(59, 130, 246, 0.15)' : 'none' ?>;">
                                    <span style="font-size: 0.8rem; font-weight: 700; color: <?= $is_checked ? '#1e40af' : '#64748b' ?>;"><?= $shortName ?></span>
                                    <input type="checkbox" name="schedule_days[]" value="<?= $fullName ?>" <?= $is_checked ? 'checked' : '' ?> style="cursor: pointer; accent-color: #2563eb; width: 18px; height: 18px; margin: 0;">
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Working Hours Row (New) -->
                        <div style="grid-column: span 2; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem;">
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-clock" style="color: #2563eb; margin-right: 0.5rem;"></i> Working Hours Start
                                </label>
                                <input type="time" name="schedule_time_start" value="<?= htmlspecialchars($doctor['schedule_time_start'] ?? '08:00') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 500; color: #1e293b;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-history" style="color: #2563eb; margin-right: 0.5rem;"></i> Working Hours End
                                </label>
                                <input type="time" name="schedule_time_end" value="<?= htmlspecialchars($doctor['schedule_time_end'] ?? '17:00') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 500; color: #1e293b;">
                            </div>
                        </div>
                    </div>
                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 2rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; border-radius: 12px; font-weight: 700; background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%); border: none; color: white; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">Save Changes</button>
                </div>
            </form>

            <!-- Password Change Form (Merged inside Edit Profile) -->
            <form method="POST" style="padding: 0 2.5rem 2.5rem 2.5rem; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
                <input type="hidden" name="action" value="change_password">
                <div style="margin-bottom: 1.5rem; color: #1e3a8a; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-shield-alt" style="color: #2563eb;"></i> Account Security
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Current Password</label>
                        <input type="password" name="current_password" required style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    </div>
                    <div class="form-group"></div>

                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">New Password</label>
                        <input type="password" name="new_password" required minlength="8" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; display: block;">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    </div>
                </div>
                <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; border-radius: 12px; font-weight: 700; background: #1e293b; border: none; color: white; cursor: pointer;">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <?php include_once '../includes/shared_appointment_details.php'; ?>

    <style>
        /* Shared variables from style.css should be handled by the CSS files included above. 
           We only add layout-specific inline overrides here to ensure consistency with 'last time' design. */
        .doctor-container { display: flex; min-height: 100vh; background-color: #f5f5f5; }
        
        .doctor-sidebar { 
            width: 280px; 
            background: linear-gradient(135deg, #2563eb, #1e3a8a); 
            color: white; 
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header { 
            padding: 2rem 1.5rem; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            text-align: center;
        }
        
        .sidebar-header h3 { 
            margin: 0 0 0.5rem 0; 
            font-size: 1.4rem; 
            font-weight: 600; 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.75rem; 
        }
        
        .sidebar-header p { 
            margin: 0; 
            font-size: 0.9rem; 
            color: rgba(255, 255, 255, 0.8); 
        }
        
        .sidebar-nav { padding: 1rem 0; flex-grow: 1; }
        
        .nav-item { 
            padding: 1rem 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            color: rgba(255, 255, 255, 0.8); 
            text-decoration: none; 
            transition: all 0.3s ease; 
            border-left: 3px solid transparent; 
        }
        
        .nav-item:hover, .nav-item.active { 
            background: rgba(255, 255, 255, 0.1); 
            color: white; 
            border-left-color: white; 
        }
        
        .nav-item.active { font-weight: 600; background-color: rgba(255, 255, 255, 0.15); }
        
        .nav-item i { width: 1.5rem; text-align: center; font-size: 1.1rem; }
        
        .doctor-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .appointment-item.clickable:hover { 
            transform: translateY(-3px) !important; 
            background: white !important; 
            border-left: 4px solid #2563eb !important; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05) !important; 
        }
        
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }

        /* Fix for disappearing header on portal pages */
        header { display: block !important; }

        @media (max-width: 768px) {
            .doctor-sidebar { width: 100%; position: relative; height: auto; }
            .doctor-content { margin-left: 0; padding: 1rem; }
            .doctor-container { flex-direction: column; }
        }
    </style>

    <script>
        function openProfileModal() { document.getElementById('profileModal').style.display = 'block'; document.body.style.overflow = 'hidden'; }
        function closeProfileModal() { document.getElementById('profileModal').style.display = 'none'; document.body.style.overflow = 'auto'; }

        // Formatting Helpers
        function formatDate(dateString) { if (!dateString) return 'N/A'; const date = new Date(dateString); return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
        
        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            try {
                const [hours, minutes] = timeString.split(':');
                const date = new Date();
                date.setHours(parseInt(hours), parseInt(minutes));
                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            } catch(e) { return timeString; }
        }

        function viewAppointment(id) {
            fetch(`get_appointment_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) { alert("Error: " + data.error); return; }
                    
                    const appointment = data.appointment;
                    const payment = data.payment;
                    const patientInfo = data.patient_info;
                    
                    const standardizedData = {
                        id: appointment.id,
                        name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
                        status: appointment.status,
                        date: formatDate(appointment.appointment_date),
                        time: formatTime(appointment.appointment_time),
                        purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
                        doctor: 'Dr. ' + (appointment.doctor_first_name || 'My') + ' ' + (appointment.doctor_last_name || 'Profile'),
                        specialty: appointment.specialty,
                        fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
                        relationship: appointment.relationship || 'Self',
                        dob: appointment.patient_dob ? formatDate(appointment.patient_dob) : 'N/A',
                        gender: appointment.patient_gender,
                        email: appointment.patient_email,
                        phone: appointment.phone || appointment.patient_phone,
                        address: appointment.patient_address,
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
                    
                    showAppointmentOverview(standardizedData, 'doctor');
                });
        }

        function closeAptModal() { closeBaseModal(); }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeProfileModal();
                closeSecurityModal();
            }
        };
    </script>
    <?php include '../includes/shared_appointment_details.php'; ?>
</body>
</html>
