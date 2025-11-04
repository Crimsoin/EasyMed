<?php
$page_title = "Home";
$page_description = "Welcome to EasyMed - Your trusted private clinic for comprehensive healthcare services";
require_once 'includes/header.php';

// Get doctors for display
$db = Database::getInstance();
    $doctors = $db->fetchAll("
        SELECT d.id AS doctor_id, u.first_name, u.last_name, u.profile_image, 
               d.specialty, d.schedule_days, d.schedule_time_start, d.schedule_time_end,
               d.consultation_fee, d.biography
        FROM users u 
        JOIN doctors d ON u.id = d.user_id 
        WHERE u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1
        ORDER BY u.first_name
    ");

    // Fetch lab offers for each doctor (title only)
    if (!empty($doctors)) {
        foreach ($doctors as &$doc) {
            $offers = $db->fetchAll("SELECT lo.title FROM lab_offers lo JOIN lab_offer_doctors lod ON lod.lab_offer_id = lo.id WHERE lod.doctor_id = ? AND lo.is_active = 1 ORDER BY lo.created_at DESC", [$doc['doctor_id']]);
            $doc['offers'] = $offers ? array_column($offers, 'title') : [];
        }
        unset($doc);
    }
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome to Patient Appointment<br>Private Clinic</h1>
            <p>Providing exceptional healthcare services with modern convenience and professional care</p>
            <div style="margin-top: 2rem;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-primary btn-lg" onclick="EasyMed.openModal('registerModal')" style="margin-right: 1rem;">
                        <i class="fas fa-user-plus"></i> Register as Patient
                    </button>
                    <button class="btn btn-secondary btn-lg" onclick="EasyMed.openModal('loginModal')">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </button>
                <?php elseif ($_SESSION['role'] === 'patient'): ?>
                    <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </a>
                    <a href="<?php echo SITE_URL; ?>/patient/dashboard_patients.php" class="btn btn-secondary btn-lg" style="margin-left: 1rem;">
                        <i class="fas fa-tachometer-alt"></i> My Dashboard
                    </a>
                <?php else: ?>
                    <?php if ($_SESSION['role'] === 'doctor'): ?>
                        <a href="<?php echo SITE_URL; ?>/doctor/dashboard_doctor.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/Dashboard/dashboard.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Doctors Section -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Our Medical Professionals</h2>
        
        <?php if (!empty($doctors)): ?>
            <div class="doctors-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-top: 2rem;">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="card doctor-card" style="height: 100%; display: flex; flex-direction: column; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="doctor-image-container" style="text-align: center; margin-bottom: 1.5rem;">
                            <?php if (!empty($doctor['profile_image']) && file_exists('assets/images/' . $doctor['profile_image'])): ?>
                                <img src="<?php echo SITE_URL; ?>/assets/images/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                                     alt="Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>" 
                                     class="doctor-image" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--light-cyan);">
                            <?php else: ?>
                                <div class="doctor-image" style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 4px solid var(--light-cyan);">
                                    <i class="fas fa-user-md" style="font-size: 3rem; color: white;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="doctor-info" style="flex: 1; display: flex; flex-direction: column;">
                            <h3 class="doctor-name" style="margin: 0 0 0.5rem 0; font-size: 1.4rem; color: var(--primary-cyan); text-align: center;">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </h3>
                            <p class="doctor-specialty" style="text-align: center; margin: 0 0 1.5rem 0; color: var(--text-light); font-weight: 500; font-size: 1rem;">
                                <i class="fas fa-stethoscope" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i> 
                                <?php echo htmlspecialchars($doctor['specialty']); ?>
                            </p>
                            <?php if (!empty($doctor['offers'])): ?>
                                <div style="text-align:center; margin-bottom: 1rem;">
                                    <?php foreach ($doctor['offers'] as $offer_title): ?>
                                        <span class="badge" style="display:inline-block; background: #e6f7f7; color: #05696b; padding: 6px 10px; border-radius: 999px; font-size: 0.85rem; margin: 0 6px 6px 0;"><?php echo htmlspecialchars($offer_title); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- doctor biography removed for card simplicity -->
                            
                            <div class="doctor-schedule" style="background: var(--light-gray); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; flex: 1;">
                                <h4 style="color: var(--primary-cyan); margin: 0 0 1rem 0; font-size: 1.1rem; display: flex; align-items: center;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i> Schedule & Fees
                                </h4>
                                <div class="schedule-item" style="margin-bottom: 0.75rem; font-size: 0.95rem;">
                                    <strong style="color: var(--text-dark);">Days:</strong> 
                                    <span style="color: var(--text-light);">
                                        <?php 
                                        $days = explode(',', $doctor['schedule_days']);
                                        echo htmlspecialchars(implode(', ', $days)); 
                                        ?>
                                    </span>
                                </div>
                                <div class="schedule-item" style="margin-bottom: 0.75rem; font-size: 0.95rem;">
                                    <strong style="color: var(--text-dark);">Time:</strong> 
                                    <span style="color: var(--text-light);">
                                        <?php 
                                        if (!empty($doctor['schedule_time_start']) && !empty($doctor['schedule_time_end'])) {
                                            echo formatTime($doctor['schedule_time_start']) . ' - ' . formatTime($doctor['schedule_time_end']);
                                        } else {
                                            echo 'Not set';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="schedule-item" style="margin-bottom: 0; font-size: 0.95rem;">
                                    <strong style="color: var(--text-dark);">Fee:</strong> 
                                    <span style="color: var(--primary-cyan); font-weight: bold; font-size: 1.1rem;">
                                        ₱<?php echo number_format($doctor['consultation_fee'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: auto;">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                                    <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php" class="btn btn-primary" style="width: 100%; padding: 0.875rem 1.5rem; font-weight: 600;">
                                        <i class="fas fa-calendar-check"></i> Book Appointment
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-primary" onclick="EasyMed.openModal('loginModal')" style="width: 100%; padding: 0.875rem 1.5rem; font-weight: 600;">
                                        <i class="fas fa-calendar-check"></i> Book Appointment
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <style>
                .doctor-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1) !important;
                }
                
                @media (max-width: 768px) {
                    .doctors-grid {
                        grid-template-columns: 1fr !important;
                        gap: 1.5rem !important;
                    }
                }
            </style>
        <?php else: ?>
            <div class="card text-center">
                <h3 style="color: var(--text-light);">
                    <i class="fas fa-info-circle"></i> No doctors available at the moment
                </h3>
                <p>Please check back later or contact the clinic directly.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Services Section -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Our Services</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h3>General Consultation</h3>
                <p>Comprehensive medical examinations and consultations for all ages with experienced healthcare professionals.</p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-vials"></i>
                </div>
                <h3>Laboratory Services</h3>
                <p>Complete diagnostic testing services including blood work, urinalysis, and other essential medical tests.</p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3>Specialized Care</h3>
                <p>Expert medical care in various specialties including cardiology, pediatrics, and other medical fields.</p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Easy Appointment Booking</h3>
                <p>Convenient online appointment scheduling system available 24/7 for your healthcare needs.</p>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Why Choose EasyMed?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: center;">
            <div>
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                            <i class="fas fa-user-md" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <h3 style="margin: 0; color: var(--primary-cyan);">Professional Care</h3>
                    </div>
                    <p>Our team of qualified medical professionals provides top-quality healthcare services with compassion and expertise.</p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                            <i class="fas fa-clock" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <h3 style="margin: 0; color: var(--primary-cyan);">Convenient Scheduling</h3>
                    </div>
                    <p>Book appointments online at your convenience with our easy-to-use appointment management system.</p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                            <i class="fas fa-shield-alt" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <h3 style="margin: 0; color: var(--primary-cyan);">Secure & Private</h3>
                    </div>
                    <p>Your medical information is kept confidential and secure with our advanced data protection measures.</p>
                </div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center;">
                <h3 style="color: white; margin-bottom: 1.5rem;">Ready to Get Started?</h3>
                <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem;">
                    Join thousands of satisfied patients who trust EasyMed for their healthcare needs.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
