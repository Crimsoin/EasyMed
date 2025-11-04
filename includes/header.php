<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'EasyMed - Professional patient appointment management system for private clinics'; ?>">
    <meta name="keywords" content="appointment, clinic, doctor, patient, medical, healthcare, booking">
    <meta name="author" content="EasyMed">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/images/favicon.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Component CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/components/header.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/components/footer.css">
    
    <!-- Additional CSS for specific pages (cache-busted by file modification time) -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <?php
                // Build absolute path on disk to read file modification time for cache-busting
                $css_disk_path = __DIR__ . '/../assets/css/' . $css;
                $ver = file_exists($css_disk_path) ? filemtime($css_disk_path) : time();
            ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo htmlspecialchars($css); ?>?v=<?php echo $ver; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Header Navigation -->
    <header class="header">
        <nav class="nav-container">
            <!-- Logo -->
            <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
                <i class="fas fa-stethoscope"></i> EasyMed
            </a>
            
            <!-- Navigation Menu -->
            <ul class="nav-menu">
                <li>
                    <a href="<?php echo SITE_URL; ?>/index.php" 
                       class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> <span>HOME</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/reviews.php" 
                       class="<?php echo ($current_page === 'reviews.php') ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i> <span>REVIEWS</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/about.php" 
                       class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i> <span>ABOUT US</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/location.php" 
                       class="<?php echo ($current_page === 'location.php') ? 'active' : ''; ?>">
                        <i class="fas fa-map-marker-alt"></i> <span>LOCATION</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/doctors.php" 
                       class="<?php echo ($current_page === 'doctors.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-md"></i> <span>FIND DOCTORS</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/payment.php" 
                       class="<?php echo ($current_page === 'payment.php') ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i> <span>PAYMENT</span>
                    </a>
                </li>
            </ul>
            
            <!-- Authentication Section -->
            <div class="nav-auth">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- User is logged in - System Dropdown -->
                    <div class="dropdown-nav">
                        <a href="#" class="dropdown-toggle">
                            <i class="fas fa-user-circle"></i> <span>System</span>
                            <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.8rem;"></i>
                        </a>
                        <div class="dropdown-menu">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="<?php echo SITE_URL; ?>/admin/Dashboard/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                                <a href="<?php echo SITE_URL; ?>/doctor/dashboard_doctor.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            <?php elseif ($_SESSION['role'] === 'patient'): ?>
                                <a href="<?php echo SITE_URL; ?>/patient/dashboard_patients.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/includes/ajax/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- User is not logged in -->
                    <button class="login-btn" onclick="EasyMed.openModal('loginModal')">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <a href="#" onclick="EasyMed.openModal('registerModal')" 
                       style="color: var(--primary-cyan); text-decoration: none; margin-left: 1rem; margin-top: 0px;">
                        Register
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="main-content">

<!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-sign-in-alt"></i> Welcome Back</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Role Selection -->
            <div id="roleSelection">
                <div class="role-selection">
                    <div class="role-btn" data-role="patient" onclick="EasyMed.selectRole('patient')">
                        <span class="role-icon"><i class="fas fa-user"></i></span>
                        <div class="role-title">Patient</div>
                        <div class="role-desc">Book appointments & manage health</div>
                    </div>
                    <div class="role-btn" data-role="doctor" onclick="EasyMed.selectRole('doctor')">
                        <span class="role-icon"><i class="fas fa-user-md"></i></span>
                        <div class="role-title">Doctor</div>
                        <div class="role-desc">Manage patients & appointments</div>
                    </div>
                    <div class="role-btn" data-role="admin" onclick="EasyMed.selectRole('admin')">
                        <span class="role-icon"><i class="fas fa-user-shield"></i></span>
                        <div class="role-title">Admin</div>
                        <div class="role-desc">System administration</div>
                    </div>
                </div>
            </div>
            
            <!-- Login Form -->
            <div id="loginForm" style="display: none;">
                <form id="loginFormElement">
                    <div class="form-group">
                        <label for="loginUsername" class="form-label">
                            <i class="fas fa-user"></i> Username or Email
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="loginUsername" name="username" class="form-control" 
                                   placeholder="Enter your username or email" required>
                        </div>
                
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="loginPassword" name="password" class="form-control" 
                                   placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="EasyMed.togglePassword('loginPassword')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="rememberMe" class="form-check-input">
                        <label for="rememberMe" class="form-check-label">Remember me</label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg w-100" data-original-text="Login">
                            <i class="fas fa-sign-in-alt"></i> Login to Account
                        </button>
                    </div>
                    
                    <div class="auth-links">
                        <a href="#" onclick="EasyMed.goBackToRoleSelection()" class="auth-link">
                            <i class="fas fa-arrow-left"></i> Back to Role Selection
                        </a>
                        <span style="margin: 0 1rem; color: #ccc;">|</span>
                        <a href="#" class="auth-link">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Registration Modal -->
<div id="registerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Join EasyMed</h2>
            <p class="modal-subtitle">Create your patient account and start managing your health</p>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="registerForm">
                <div class="form-progress">
                    <div class="progress-step active">1</div>
                    <div class="progress-step">2</div>
                    <div class="progress-step">3</div>
                </div>

                <!-- Step 1: Basic Information -->
                <div class="form-step" id="step1">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="regFirstName" class="form-label">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" id="regFirstName" name="first_name" class="form-control" 
                                       placeholder="John" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="regLastName" class="form-label">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" id="regLastName" name="last_name" class="form-control" 
                                       placeholder="Doe" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="regEmail" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="regEmail" name="email" class="form-control" 
                                   placeholder="john.doe@example.com" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="regUsername" class="form-label">
                            <i class="fas fa-at"></i> Username
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-at"></i></span>
                            <input type="text" id="regUsername" name="username" class="form-control" 
                                   placeholder="johndoe123" required>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Security -->
                <div class="form-step" id="step2" style="display: none;">
                    <div class="form-group">
                        <label for="regPassword" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="regPassword" name="password" class="form-control" 
                                   placeholder="Enter secure password" required>
                            <span class="password-toggle" onclick="EasyMed.togglePassword('regPassword')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <small style="color: #666; font-size: 0.8rem;">
                            Password must be at least 8 characters long
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="regConfirmPassword" class="form-label">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="regConfirmPassword" name="confirm_password" class="form-control" 
                                   placeholder="Confirm your password" required>
                            <span class="password-toggle" onclick="EasyMed.togglePassword('regConfirmPassword')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Personal Information -->
                <div class="form-step" id="step3" style="display: none;">
                    <div class="form-group">
                        <label for="regPhone" class="form-label">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                            <input type="tel" id="regPhone" name="phone" class="form-control" 
                                   placeholder="+1 (555) 123-4567">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="regDateOfBirth" class="form-label">
                                <i class="fas fa-calendar"></i> Date of Birth
                            </label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-calendar"></i></span>
                                <input type="date" id="regDateOfBirth" name="date_of_birth" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="regGender" class="form-label">
                                <i class="fas fa-venus-mars"></i> Gender
                            </label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                                <select id="regGender" name="gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="agreeTerms" class="form-check-input" required>
                        <label for="agreeTerms" class="form-check-label">
                            I agree to the <a href="#" class="auth-link">Terms of Service</a> and 
                            <a href="#" class="auth-link">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <input type="hidden" name="role" value="patient">
                
                <!-- Navigation Buttons -->
                <div class="form-group" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;" 
                            onclick="EasyMed.previousStep()">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" id="nextBtn" class="btn btn-primary" 
                            onclick="EasyMed.nextStep()">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-lg" 
                            style="display: none;" data-original-text="Create Account">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </div>
                
                <div class="auth-links">
                    <p>Already have an account? 
                        <a href="#" onclick="EasyMed.closeModal(); EasyMed.openModal('loginModal');" 
                           class="auth-link">
                            <i class="fas fa-sign-in-alt"></i> Login here
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
