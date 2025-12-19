<?php
$page_title = 'Edit Patient';
$additional_css = ['admin/patient-management.css']; // Include patient management specific CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$error = '';
$success = '';

// Get patient ID
$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) {
    header('Location: patients.php');
    exit;
}

// Get patient data (only patients)
$patient = $db->fetch("
    SELECT u.* 
    FROM users u 
    WHERE u.id = ? AND u.role = 'patient'", [$patientId]);

if (!$patient) {
    $_SESSION['error'] = 'Patient not found';
    header('Location: patients.php');
    exit;
}

if ($_POST) {
    // Validate input
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if username or email already exists (excluding current patient)
    $existingUser = $db->fetch("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?", 
                              [$username, $email, $patientId]);
    
    if ($existingUser) {
        $error = 'Username or email already exists';
    } else {
        try {
            $db->beginTransaction();
            
            $userData = [
                'username' => $username,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => 'patient', // Fixed role for patient management
                'phone' => $phone,
                'date_of_birth' => $dateOfBirth ?: null,
                'gender' => $gender ?: null,
                'address' => $address,
                'is_active' => $isActive,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) {
                    throw new Exception('Password must be at least 6 characters long');
                }
                $userData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $db->update('users', $userData, 'id = ?', [$patientId]);
            
            $db->commit();
            $_SESSION['success'] = 'Patient updated successfully';
            header('Location: patients.php');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error updating user: ' . $e->getMessage();
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
            <a href="../Patient Management/patients.php" class="nav-item active">
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
            <h1>Edit Patient</h1>
            <p>Update user information for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="admin-form">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> First Name
                        </label>
                        <input type="text" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? $patient['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Last Name
                        </label>
                        <input type="text" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? $patient['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-at"></i> Username
                        </label>
                        <input type="text" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? $patient['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? $patient['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" name="password" class="form-control">
                        <small style="color: #666;">Leave blank to keep current password</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone
                        </label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? $patient['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i> Date of Birth
                        </label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $patient['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-venus-mars"></i> Gender
                        </label>
                        <select name="gender" class="form-control">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($patient['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($patient['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($patient['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt"></i> Address
                    </label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? $patient['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" 
                               <?php echo $patient['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active" class="form-check-label">
                            <i class="fas fa-check-circle"></i> Account Active
                        </label>
                    </div>
                </div>
                
                <div class="form-group" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Patient
                    </button>
                    <a href="../Patient Management/patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                    <a href="view-patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-view">
                        <i class="fas fa-eye"></i> View Patient
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
