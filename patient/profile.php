<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

// Require login as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $errors = [];
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[] = 'Last name is required.';
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is invalid.';
    } else {
        // Check email uniqueness
        $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
        if ($existing) {
            $errors[] = 'Email address is already in use.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['profile_errors'] = $errors;
        header('Location: profile.php');
        exit();
    }

    try {
    // Update users table (include email)
    $db->query("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?", [$first_name, $last_name, $email, $phone, $address, $user_id]);

        // Check if patient record exists
        $patient = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$user_id]);
        if ($patient) {
            // SQLite patients table uses emergency_contact and emergency_phone
            $db->query(
                "UPDATE patients SET date_of_birth = ?, gender = ?, emergency_contact = ?, emergency_phone = ?, blood_type = ? WHERE user_id = ?",
                [$date_of_birth ?: null, $gender ?: null, $emergency_name ?: null, $emergency_phone ?: null, $blood_type ?: null, $user_id]
            );
        } else {
            $db->query(
                "INSERT INTO patients (user_id, date_of_birth, gender, emergency_contact, emergency_phone, blood_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$user_id, $date_of_birth ?: null, $gender ?: null, $emergency_name ?: null, $emergency_phone ?: null, $blood_type ?: null, date('Y-m-d H:i:s')]
            );
        }

        $_SESSION['profile_success'] = 'Profile updated successfully.';
    } catch (Exception $e) {
        $_SESSION['profile_errors'] = ['Failed to update profile: ' . $e->getMessage()];
    }

    header('Location: profile.php');
    exit();
}

// Load current user + patient data
// Note: SQLite patients table uses `emergency_contact` and `emergency_phone` columns
$user = $db->fetch("SELECT u.*, p.date_of_birth, p.gender, p.emergency_contact AS emergency_contact_name, p.emergency_phone AS emergency_contact_phone, NULL AS emergency_contact_relationship, p.blood_type FROM users u LEFT JOIN patients p ON p.user_id = u.id WHERE u.id = ?", [$user_id]);

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
                <h3><i class="fas fa-user-circle"></i> Patient Portal</h3>
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

        <div class="patient-content">
            <div class="content-header">
                <h1>My Profile</h1>
                <p>Manage your account settings and health information</p>
            </div>

            <div class="profile-container">
                <div class="form-feedback">
                    <?php if ($success): ?>
                        <div class="success-banner">
                            <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="error-banner">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
                                <span>Something went wrong:</span>
                            </div>
                            <ul style="margin: 0; padding-left: 2rem; font-size: 0.95rem;">
                                <?php foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>'; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" action="profile.php">
                    <!-- 1. Personal Identity -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-id-card"></i> Personal Identity</h2>
                        </div>
                        <div class="section-content">
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
                            </div>
                        </div>
                    </div>

                    <!-- 2. Contact Information -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-address-book"></i> Contact Details</h2>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
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

                    <!-- 3. Health Profile -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-heartbeat"></i> Health Profile</h2>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="blood_type">Blood Type</label>
                                    <select name="blood_type" id="blood_type">
                                        <option value="">Select blood type</option>
                                        <?php $bl = $user['blood_type'] ?? ''; $types = ['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown']; foreach ($types as $t): ?>
                                            <option value="<?= $t ?>" <?= ($bl === $t) ? 'selected' : '' ?>><?= $t ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Emergency Contact -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-ambulance"></i> Emergency Contact</h2>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="emergency_name">Representative Name</label>
                                    <input type="text" name="emergency_name" id="emergency_name" value="<?= htmlspecialchars($user['emergency_contact_name'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="emergency_phone">Contact Phone</label>
                                    <input type="text" name="emergency_phone" id="emergency_phone" value="<?= htmlspecialchars($user['emergency_contact_phone'] ?? '') ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label for="emergency_relation">Relationship to Patient</label>
                                    <input type="text" name="emergency_relation" id="emergency_relation" value="<?= htmlspecialchars($user['emergency_contact_relationship'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions-card">
                        <a href="dashboard_patients.php" class="btn-profile-cancel">
                            <i class="fas fa-times"></i> Discard
                        </a>
                        <button type="submit" class="btn-profile-save">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</body>
</html>
