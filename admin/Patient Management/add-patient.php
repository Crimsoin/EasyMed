<?php
$page_title = 'Add Patient';
$additional_css = ['admin/sidebar.css', 'add-patient.css']; // Include sidebar and form-specific CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$error = '';
$success = '';

if ($_POST) {
    // Validate input
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $role = 'patient'; // Fixed role for patient management
    $phone = sanitize($_POST['phone'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    
    // Check if username or email already exists
    $existingUser = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    
    if ($existingUser) {
        $error = 'Username or email already exists';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            $db->beginTransaction();
            
            // Insert into users table (basic user info)
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $role,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $userId = $db->insert('users', $userData);
            
            // Insert into patients table (patient-specific info)
            if ($userId && ($phone || $dateOfBirth || $gender)) {
                $patientData = [
                    'user_id' => $userId,
                    'phone' => $phone ?: null,
                    'date_of_birth' => $dateOfBirth ?: null,
                    'gender' => $gender ?: null
                ];
                
                $db->insert('patients', $patientData);
            }
            
            $db->commit();
            $_SESSION['success'] = 'Patient created successfully';
            header('Location: patients.php');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error creating user: ' . $e->getMessage();
        }
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
            <a href="patients.php" class="nav-item active">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
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
            <h1>Add New Patient</h1>
            <p>Create a new user account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="admin-form">
            <form method="POST" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> First Name
                        </label>
                        <input type="text" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Last Name
                        </label>
                        <input type="text" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-at"></i> Username
                        </label>
                        <input type="text" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" name="password" class="form-control" required>
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone
                        </label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i> Date of Birth
                        </label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-venus-mars"></i> Gender
                        </label>
                        <select name="gender" class="form-control">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Patient
                    </button>
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
