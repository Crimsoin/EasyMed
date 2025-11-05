<?php
$page_title = "Add New Doctor";
$additional_css = ['admin/sidebar.css', 'admin/add-doctor-admin.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $errors = [];
        
        $userData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => 'password123', // Default password
            'phone' => trim($_POST['phone'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'role' => 'doctor'
        ];
        
        $doctorData = [
            'specialty' => trim($_POST['specialty'] ?? ''),
            'license_number' => trim($_POST['license_number'] ?? ''),
            'experience_years' => intval($_POST['experience_years'] ?? 0),
            'consultation_fee' => floatval($_POST['consultation_fee'] ?? 0),
            'biography' => trim($_POST['biography'] ?? '')
        ];
        // Laboratory offers posted as arrays (title only)
        $labOffers = [];
        if (!empty($_POST['offer_title']) && is_array($_POST['offer_title'])) {
            $titles = $_POST['offer_title'];
            for ($i = 0; $i < count($titles); $i++) {
                $title = trim($titles[$i] ?? '');
                if ($title === '') continue;
                $labOffers[] = [
                    'title' => $title
                ];
            }
        }
        
        // Basic validation
        if (empty($userData['first_name'])) $errors[] = 'First name is required';
        if (empty($userData['last_name'])) $errors[] = 'Last name is required';
        if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required';
        }
    // Username is no longer required for doctors; an internal username will be generated automatically.
        if (empty($doctorData['specialty'])) $errors[] = 'Specialty is required';
        if (empty($doctorData['license_number'])) $errors[] = 'License number is required';
        
    // Check if email already exists
    $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$userData['email']]);
        if ($existing) {
            $errors[] = 'Email or username already exists';
        }
        
        if (empty($errors)) {
            $db->beginTransaction();
            try {
                // Insert into users table (basic user info)
                // Generate an internal unique username for compatibility with the existing schema.
                // This is not shown to users; login can still use email.
                $generated_username = 'doc_' . uniqid();

                $userData_insert = [
                    'username' => $generated_username,
                    'email' => $userData['email'],
                    'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'role' => $userData['role'],
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $user_id = $db->insert('users', $userData_insert);
                
                // Insert into patients table (patient-specific info) if there's data
                if ($user_id && ($userData['phone'] || $userData['date_of_birth'] || $userData['gender'])) {
                    $patientData = [
                        'user_id' => $user_id,
                        'phone' => $userData['phone'] ?: null,
                        'date_of_birth' => $userData['date_of_birth'] ?: null,
                        'gender' => $userData['gender'] ?: null
                    ];
                    
                    $db->insert('patients', $patientData);
                }
                
                // Insert doctor profile
                $db->query("INSERT INTO doctors (user_id, specialty, license_number, experience_years, consultation_fee, biography, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                    $user_id,
                    $doctorData['specialty'],
                    $doctorData['license_number'],
                    $doctorData['experience_years'],
                    $doctorData['consultation_fee'],
                    $doctorData['biography'],
                    date('Y-m-d H:i:s')
                ]);

                // get the newly created doctor id
                $doctor_row_id = $db->getConnection()->lastInsertId();

                // If lab offers were provided, ensure tables exist then insert and link them
                if (!empty($labOffers)) {
                    // create tables if they don't exist
                    $db->query("CREATE TABLE IF NOT EXISTS lab_offers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        is_active INTEGER DEFAULT 1,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT
                    );");

                    $db->query("CREATE TABLE IF NOT EXISTS lab_offer_doctors (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        lab_offer_id INTEGER NOT NULL,
                        doctor_id INTEGER NOT NULL,
                        created_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY(lab_offer_id) REFERENCES lab_offers(id) ON DELETE CASCADE,
                        FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
                    );");

                    // insert offers and associate (title only)
                    foreach ($labOffers as $lo) {
                        $db->insert('lab_offers', [
                            'title' => $lo['title'],
                            'is_active' => 1,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        $offer_id = $db->getConnection()->lastInsertId();
                        if ($offer_id) {
                            $db->insert('lab_offer_doctors', [
                                'lab_offer_id' => $offer_id,
                                'doctor_id' => $doctor_row_id,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                }
                
                $db->commit();
                
                // Log activity
                logActivity($_SESSION['user_id'], 'create_doctor', "Created doctor account for Dr. {$userData['first_name']} {$userData['last_name']}");
                
                // Create notification for new doctor
                createNotification($user_id, 'Welcome to EasyMed', 'Your doctor account has been created with default password "password123". Please change your password after first login.', 'success');
                
                $_SESSION['success_message'] = "Doctor account created successfully! Default password: password123";
                header('Location: doctors.php');
                exit();
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error creating doctor account: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
?>
<style>
/* Laboratory offer input with right-side remove X */
.offer-row { margin-bottom: 12px; }
.offer-input-wrapper { position: relative; }
.offer-input-wrapper input.form-control { padding-right: 44px; }
.offer-remove-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #c0392b;
    font-weight: 700;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 4px;
}
.offer-remove-btn:focus { outline: none; }
</style>

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
            <h1><i class="fas fa-user-plus"></i> Add New Doctor</h1>
            <p>Add a new doctor to the system</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="doctor-form">
            <!-- Personal Information -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Account Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-info">
                            <span class="default-password">Default password: <strong>password123</strong></span>
                            <small>The doctor can change this password after first login</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Information -->
            <div class="form-section">
                <h3><i class="fas fa-stethoscope"></i> Professional Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialty" class="form-label">Specialty *</label>
                        <input type="text" id="specialty" name="specialty" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['specialty'] ?? ''); ?>" 
                               placeholder="e.g., Cardiology, Dermatology, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="license_number" class="form-label">License Number *</label>
                        <input type="text" id="license_number" name="license_number" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="experience_years" class="form-label">Years of Experience</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>" min="0" max="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_fee" class="form-label">Consultation Fee (₱)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? ''); ?>" 
                               min="0" step="0.01">
                    </div>
                </div>
            </div>

            <!-- Laboratory Offers removed -->

            <!-- Laboratory Offers -->
            <div class="form-section">
                <h3><i class="fas fa-vials"></i> Laboratory Offers</h3>
                <p>Add lab test packages that this doctor offers (optional).</p>
                <div id="offersContainer">
                    <!-- existing submitted offers will be rendered here -->
                    <?php if (!empty($_POST['offer_title']) && is_array($_POST['offer_title'])):
                        for ($i=0;$i<count($_POST['offer_title']);$i++):
                            $ot = htmlspecialchars($_POST['offer_title'][$i]);
                    ?>
                        <div class="offer-row">
                            <div class="offer-input-wrapper">
                                <input type="text" name="offer_title[]" class="form-control" placeholder="Offer title" value="<?php echo $ot; ?>">
                                <button type="button" class="offer-remove-btn" onclick="this.closest('.offer-row').remove()">✕</button>
                            </div>
                        </div>
                    <?php endfor; endif; ?>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="addOfferRow()">Add Offer</button>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Doctor Account
                </button>
                <a href="../Doctor Management/doctors.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<!-- Laboratory Offers script removed -->
<script>
function addOfferRow(){
    var container = document.getElementById('offersContainer');
    var div = document.createElement('div');
    div.className = 'offer-row';
    div.innerHTML = `
        <div class="offer-input-wrapper">
            <input type="text" name="offer_title[]" class="form-control" placeholder="Offer title">
            <button type="button" class="offer-remove-btn" onclick="this.closest('.offer-row').remove()">✕</button>
        </div>
    `;
    container.appendChild(div);
}
</script>
