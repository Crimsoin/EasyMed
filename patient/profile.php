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
        .profile-container { max-width: 1000px; margin: 24px auto; padding: 20px; }
        .profile-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.06); }
        .form-row { display:flex; gap:16px; flex-wrap:wrap; }
        .form-group { flex:1 1 0; min-width:0; margin-bottom:12px; }
        /* narrow column for shorter inputs (email, phone) */
        .form-group--narrow { flex: 0 0 320px; }
        label { display:block; margin-bottom:6px; font-weight:600; color:#333; }
        input[type="text"], input[type="email"], input[type="date"], select, textarea { width:100%; padding:10px 12px; border:1px solid #e6e9ec; border-radius:8px; box-sizing:border-box; }
        textarea { min-height:120px; }
        .actions { display:flex; gap:12px; margin-top:12px; }
        .btn { padding:10px 16px; border-radius:8px; border:none; cursor:pointer; }
        .btn-primary { background:#007bff; color:#fff; }
        .success-message { background:#e6ffed; padding:12px; border-radius:8px; color:#1b5e20; margin-bottom:12px; }
        .error-message { background:#ffecec; padding:12px; border-radius:8px; color:#8b1c1c; margin-bottom:12px; }

        /* Responsive tweaks */
        @media (max-width: 700px) {
            .form-group--narrow { flex: 1 1 100%; }
            .form-row { gap:12px; }
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
                <a href="reviews.php" class="nav-item"> <i class="fas fa-star"></i> Reviews</a>
                <a href="profile.php" class="nav-item active"> <i class="fas fa-user-cog"></i> My Profile</a>
            </nav>
        </div>

        <div class="patient-content">
            <div class="content-header"><h1>My Profile</h1><p>Update your personal and emergency contact details.</p></div>

            <div class="profile-container">
                <div class="profile-card">
                    <?php if ($success): ?><div class="success-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <?php if (!empty($errors)): ?><div class="error-message"><?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?></div><?php endif; ?>

                    <form method="post" action="profile.php">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select name="gender" id="gender" class="form-control">
                                    <option value="">Select gender</option>
                                    <option value="male" <?= (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= (isset($user['gender']) && $user['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="blood_type">Blood Type</label>
                                <select name="blood_type" id="blood_type" class="form-control">
                                    <option value="">Select blood type</option>
                                    <?php $bl = $user['blood_type'] ?? ''; $types = ['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown']; foreach ($types as $t): ?>
                                        <option value="<?= $t ?>" <?= ($bl === $t) ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1 1 60%;">
                                <label for="address">Address</label>
                                <textarea name="address" id="address" class="form-control"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group form-group--narrow">
                                <label for="email">Email</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <hr />
                        <h3>Emergency Contact</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_name">Name</label>
                                <input type="text" name="emergency_name" id="emergency_name" class="form-control" value="<?= htmlspecialchars($user['emergency_contact_name'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="emergency_phone">Phone</label>
                                <input type="text" name="emergency_phone" id="emergency_phone" class="form-control" value="<?= htmlspecialchars($user['emergency_contact_phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_relation">Relationship</label>
                                <input type="text" name="emergency_relation" id="emergency_relation" class="form-control" value="<?= htmlspecialchars($user['emergency_contact_relationship'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Save Profile</button>
                            <a href="dashboard_patients.php" class="btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html>
