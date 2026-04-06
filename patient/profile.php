<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css', 'view-patient.css', 'shared-modal.css'];

// Require login as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    $errors = [];
    $db = Database::getInstance();
    $user_id = $_SESSION['user_id'];

    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $emergency_name = trim($_POST['emergency_name'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        $emergency_relation = trim($_POST['emergency_relation'] ?? '');
        $blood_type = trim($_POST['blood_type'] ?? '');

        if ($first_name === '') $errors[] = 'First name is required.';
        if ($last_name === '') $errors[] = 'Last name is required.';
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        } else {
            $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
            if ($existing) $errors[] = 'Email address is already in use.';
        }

        if (empty($errors)) {
            try {
                $db->query("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?", [$first_name, $last_name, $email, $phone, $address, $user_id]);
                
                $patient = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$user_id]);
                if ($patient) {
                    $db->query("UPDATE patients SET date_of_birth = ?, gender = ?, emergency_contact = ?, emergency_phone = ?, blood_type = ? WHERE user_id = ?",
                        [$date_of_birth ?: null, $gender ?: null, $emergency_name ?: null, $emergency_phone ?: null, $blood_type ?: null, $user_id]);
                } else {
                    $db->query("INSERT INTO patients (user_id, date_of_birth, gender, emergency_contact, emergency_phone, blood_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$user_id, $date_of_birth ?: null, $gender ?: null, $emergency_name ?: null, $emergency_phone ?: null, $blood_type ?: null, date('Y-m-d H:i:s')]);
                }
                $_SESSION['profile_success'] = 'Profile details updated successfully.';
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) $errors[] = 'Current password is required.';
        if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters long.';
        if ($new_password !== $confirm_password) $errors[] = 'New passwords do not match.';

        if (empty($errors)) {
            $userData = $db->fetch("SELECT password FROM users WHERE id = ?", [$user_id]);
            if (!password_verify($current_password, $userData['password'])) {
                $errors[] = 'Incorrect current password.';
            } else {
                try {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user_id]);
                    logActivity($user_id, 'change_password', 'User changed their password');
                    $_SESSION['profile_success'] = 'Password updated successfully.';
                } catch (Exception $e) {
                    $errors[] = 'Failed to update password: ' . $e->getMessage();
                }
            }
        }
    }

    if (!empty($errors)) $_SESSION['profile_errors'] = $errors;
    header('Location: profile.php');
    exit();
}

// Load current user + patient data
$user = $db->fetch("SELECT u.*, 
                    COALESCE(p.date_of_birth, u.date_of_birth) AS date_of_birth, 
                    COALESCE(p.gender, u.gender) AS gender,
                    p.emergency_contact AS emergency_contact_name, 
                    p.emergency_phone AS emergency_contact_phone, 
                    p.blood_type 
                    FROM users u 
                    LEFT JOIN patients p ON p.user_id = u.id 
                    WHERE u.id = ?", [$user_id]);

// Get statistics for the profile view
$stats = [
    'total_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE p.user_id = ?", [$user_id])['count'],
    'completed_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE p.user_id = ? AND a.status = 'completed'", [$user_id])['count'],
    'upcoming_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE p.user_id = ? AND a.appointment_date >= date('now') AND a.status IN ('scheduled', 'confirmed')", [$user_id])['count'],
    'cancelled_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE p.user_id = ? AND a.status = 'cancelled'", [$user_id])['count']
];

// Get recent activity (recent appointments)
$recentActivity = $db->fetchAll("
    SELECT a.*, 
           du.first_name as doctor_first_name, du.last_name as doctor_last_name,
           doc.specialty
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors doc ON a.doctor_id = doc.id
    JOIN users du ON doc.user_id = du.id
    WHERE p.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5", [$user_id]);

// Flash messages
$success = $_SESSION['profile_success'] ?? null;
$errors = $_SESSION['profile_errors'] ?? null;
unset($_SESSION['profile_success'], $_SESSION['profile_errors']);

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Profile - EasyMed</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        .profile-container { width: 100%; max-width: none; margin: 0; padding: 0; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }
        
        .section-content label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; display: block; }
        
        .section-content input[type="text"], 
        .section-content input[type="email"], 
        .section-content input[type="date"], 
        .section-content input[type="password"], 
        .section-content select, 
        .section-content textarea { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid #edf2f7; 
            border-radius: 12px; 
            font-size: 1rem; 
            font-weight: 600;
            color: #1a202c;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .section-content input:focus, 
        .section-content input[type="password"]:focus, 
        .section-content select:focus, 
        .section-content textarea:focus { 
            outline: none; 
            border-color: #3b82f6; 
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .section-content textarea { min-height: 100px; resize: vertical; }
        
        .profile-actions-card { 
            background: white; 
            padding: 2rem; 
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex; 
            justify-content: flex-end; 
            gap: 16px; 
            margin-top: 2rem;
            border: 1px solid #edf2f7;
        }
        
        .btn-profile-save { 
            background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%); 
            color: white; 
            padding: 14px 40px; 
            border-radius: 12px; 
            font-weight: 800; 
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-profile-save:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(37, 99, 235, 0.35); }
        .btn-profile-save:active { transform: translateY(0); }
        
        .btn-profile-cancel { 
            background: white; 
            color: #64748b; 
            padding: 14px 32px; 
            border-radius: 12px; 
            font-weight: 700; 
            text-decoration: none;
            border: 2px solid #edf2f7;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-profile-cancel:hover { background: #f8fafc; color: #1a202c; border-color: #cbd5e1; }

        /* Ensure banner text stays white regardless of container */
        .content-header h1 { color: white !important; margin: 0; }
        .content-header p { color: rgba(255, 255, 255, 0.9) !important; margin: 0.5rem 0 0 0; }
        .avatar-placeholder { color: white !important; }

        .form-feedback { margin-bottom: 2rem; }
        .success-banner { background: #ecfdf5; border-left: 5px solid #10b981; padding: 1.25rem 1.5rem; border-radius: 12px; color: #065f46; font-weight: 700; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1); }
        .error-banner { background: #fef2f2; border-left: 5px solid #ef4444; padding: 1.25rem 1.5rem; border-radius: 12px; color: #991b1b; font-weight: 700; flex-direction: column; align-items: flex-start; gap: 8px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1); display: flex; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .profile-actions-card { flex-direction: column-reverse; }
            .btn-profile-save, .btn-profile-cancel { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/header.php'; ?>

    <div class="patient-container">
        <div class="patient-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-user"></i> Patient Portal</h3>
                <p style="margin: 0.5rem 0 0 0; color: #ffffffff; font-size: 0.9rem; font-weight: 500;">
                    <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
                </p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_patients.php" class="nav-item"> <i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="book-appointment.php" class="nav-item"> <i class="fas fa-calendar-plus"></i> Book Appointment</a>
                <a href="appointments.php" class="nav-item"> <i class="fas fa-calendar-alt"></i> My Appointments</a>
                <a href="feedbacks.php" class="nav-item"> <i class="fas fa-star"></i> Feedbacks</a>
                <a href="profile.php" class="nav-item active"> <i class="fas fa-user-cog"></i> My Profile</a>
            </nav>
        </div>

        <div class="patient-content" style="background-color: #f8fafc; padding: 2rem;">
            <?php if (isset($_SESSION['profile_success'])): ?>
                <div class="success-banner" style="margin-bottom: 2rem;">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['profile_success']; 
                    unset($_SESSION['profile_success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['profile_errors'])): ?>
                <div class="error-banner" style="margin-bottom: 2rem;">
                    <?php foreach ($_SESSION['profile_errors'] as $error): ?>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['profile_errors']); ?>
                </div>
            <?php endif; ?>

            <?php 
            $viewMode = 'patient';
            include_once '../includes/components/patient_details_template.php'; 
            ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Your Profile</h2>
                <span class="close" onclick="closeEditProfileModal()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 2rem 2rem 2.5rem 2rem;">
                <!-- Profile Details Form -->
                <form method="post" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="content-section" style="box-shadow: none; border: none; margin: 0;">
                        <div class="section-content" style="padding: 0;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select name="gender" id="gender">
                                        <option value="">Select gender</option>
                                        <option value="male" <?= (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= (isset($user['gender']) && $user['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label for="address">Residential Address</label>
                                    <textarea name="address" id="address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin: 1.5rem 0 2rem;">
                        <button type="submit" class="btn-profile-save" style="background: #2563eb; padding: 0.75rem 1.5rem; font-weight: 700; border-radius: 10px;">Save Changes</button>
                    </div>
                </form>

                <!-- Password Change Form -->
                <form method="post" action="profile.php" style="border-top: 2px solid #f1f5f9; padding-top: 1.5rem;">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-section-header" style="margin-bottom: 1.5rem; color: #1e3a8a; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-lock" style="color: #2563eb;"></i> Account Security
                    </div>

                    <div class="section-content" style="box-shadow: none; border: none; margin: 0; padding: 0;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" name="current_password" id="current_password" placeholder="Verify current password" required>
                            </div>
                            <div class="form-group"></div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" placeholder="Min. 6 characters" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn-profile-save" style="background: #1e3a8a; padding: 0.75rem 1.5rem; font-weight: 700; border-radius: 10px;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <?php include_once '../includes/shared_appointment_details.php'; ?>

    <script>
        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editProfileModal');
            if (event.target == modal) {
                closeEditProfileModal();
            }
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            // Handle HH:mm:ss format
            const [hours, minutes] = timeString.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function viewAppointment(idOrData) {
            const id = (typeof idOrData === 'object') ? idOrData.id : idOrData;
            if (!id) {
                alert("Error: Unable to identify appointment ID.");
                return;
            }
            fetch(`../patient/get_appointment_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert("Error: " + data.error);
                        return;
                    }
                    
                    const appointment = data.appointment;
                    const payment = data.payment;
                    const patientInfo = data.patient_info;
                    
                    // Standardize for shared renderer
                    const standardizedData = {
                        id: appointment.id,
                        name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
                        status: appointment.status,
                        date: formatDate(appointment.appointment_date),
                        time: formatTime(appointment.appointment_time),
                        purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
                        doctor: 'Dr. ' + appointment.doctor_first_name + ' ' + appointment.doctor_last_name,
                        specialty: appointment.specialty,
                        license: appointment.license_number,
                        fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
                        relationship: appointment.relationship || 'Self',
                        dob: appointment.patient_dob ? formatDate(appointment.patient_dob) : 'N/A',
                        gender: appointment.patient_gender,
                        email: appointment.patient_email,
                        phone: appointment.patient_phone,
                        address: appointment.patient_address,
                        reason: appointment.illness || appointment.reason_for_visit,
                        notes: appointment.notes,
                        payment: payment ? {
                            amount: parseFloat(payment.amount).toFixed(2),
                            status: payment.status,
                            ref: payment.gcash_reference,
                            receipt: payment.receipt_path
                        } : null,
                                                laboratory_image: patientInfo ? patientInfo.laboratory_image : null,
                        reschedule_reason: appointment.reschedule_reason
                    };
                    
                    showAppointmentOverview(standardizedData, 'patient');
                })
                .catch(error => {
                    console.error('Error fetching appointment details:', error);
                });
        }

        function closeAptModal() {
            closeBaseModal();
        }
    </script>
</body>
</html>
