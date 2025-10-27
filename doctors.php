<?php
$page_title = "Find Doctors";
$page_description = "Meet our experienced medical professionals and their specialties at EasyMed Private Clinic";
require_once 'includes/header.php';

// Get all active doctors
$db = Database::getInstance();
$doctors = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image,
           d.id as doctor_id, d.specialty, d.license_number, d.biography, 
           d.consultation_fee, d.experience_years, d.schedule_days, 
           d.schedule_time_start, d.schedule_time_end, d.is_available, d.phone
    FROM users u 
    JOIN doctors d ON u.id = d.user_id 
    WHERE u.role = 'doctor' AND u.is_active = 1
    ORDER BY u.first_name
");
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-user-md"></i> Find Our Doctors</h1>
            <p>Meet our experienced medical professionals dedicated to your health and well-being</p>
        </div>
    </div>
</section>

<!-- Doctors Directory -->
<section class="section">
    <div class="container">
        <?php if (!empty($doctors)): ?>
            <div style="margin-bottom: 2rem; text-align: center;">
                <p style="font-size: 1.1rem; color: var(--text-light);">
                    We have <strong><?php echo count($doctors); ?></strong> qualified medical professionals ready to serve you.
                </p>
            </div>
            
            <div style="display: grid; gap: 3rem;">
                <?php foreach ($doctors as $index => $doctor): ?>
                    <div class="card" style="<?php echo $index % 2 === 0 ? '' : 'background: linear-gradient(135deg, rgba(0, 188, 212, 0.05), rgba(77, 208, 225, 0.05));'; ?>">
                        <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 2rem; align-items: start;">
                            <!-- Doctor Photo -->
                            <div style="text-align: center;">
                                <?php if (!empty($doctor['profile_image']) && file_exists('assets/images/' . $doctor['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL; ?>/assets/images/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                                         alt="Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>" 
                                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--light-cyan);">
                                <?php else: ?>
                                    <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid var(--light-cyan);">
                                        <i class="fas fa-user-md" style="font-size: 4rem; color: white;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Availability Status -->
                                <div style="margin-top: 1rem;">
                                    <?php if ($doctor['is_available']): ?>
                                        <span style="background-color: var(--success); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500;">
                                            <i class="fas fa-circle" style="font-size: 0.6rem; margin-right: 0.3rem;"></i>
                                            Available
                                        </span>
                                    <?php else: ?>
                                        <span style="background-color: var(--error); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500;">
                                            <i class="fas fa-circle" style="font-size: 0.6rem; margin-right: 0.3rem;"></i>
                                            Unavailable
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Doctor Information -->
                            <div>
                                <h3 style="color: var(--primary-cyan); margin-bottom: 0.5rem; font-size: 1.8rem;">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </h3>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem;">
                                    <div style="display: flex; align-items: center;">
                                        <i class="fas fa-stethoscope" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                        <span style="font-weight: 500; color: var(--text-dark);">
                                            <?php echo htmlspecialchars($doctor['specialty']); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center;">
                                        <i class="fas fa-certificate" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                        <span style="color: var(--text-light); font-size: 0.9rem;">
                                            License: <?php echo htmlspecialchars($doctor['license_number']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($doctor['experience_years'] > 0): ?>
                                        <div style="display: flex; align-items: center;">
                                            <i class="fas fa-clock" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                            <span style="color: var(--text-light); font-size: 0.9rem;">
                                                <?php echo $doctor['experience_years']; ?> years experience
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contact Information -->
                                <div style="margin-bottom: 1.5rem;">
                                    <h4 style="color: var(--text-dark); margin-bottom: 0.5rem; font-size: 1rem;">Contact</h4>
                                    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                                        <?php if (!empty($doctor['email'])): ?>
                                            <div style="display: flex; align-items: center;">
                                                <i class="fas fa-envelope" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>" 
                                                   style="color: var(--primary-cyan); text-decoration: none; font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars($doctor['email']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($doctor['phone'])): ?>
                                            <div style="display: flex; align-items: center;">
                                                <i class="fas fa-phone" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                                <a href="tel:<?php echo htmlspecialchars($doctor['phone']); ?>" 
                                                   style="color: var(--primary-cyan); text-decoration: none; font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars($doctor['phone']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Schedule Information -->
                                <div>
                                    <h4 style="color: var(--text-dark); margin-bottom: 0.5rem; font-size: 1rem;">Schedule</h4>
                                    <div style="background-color: var(--light-gray); padding: 1rem; border-radius: 8px;">
                                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 1rem; align-items: center;">
                                            <div>
                                                <i class="fas fa-calendar-alt" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                                <strong>Days:</strong>
                                            </div>
                                            <div style="color: var(--text-dark);">
                                                <?php 
                                                $days = explode(',', $doctor['schedule_days']);
                                                echo htmlspecialchars(implode(', ', $days)); 
                                                ?>
                                            </div>
                                            
                                            <div>
                                                <i class="fas fa-clock" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                                <strong>Time:</strong>
                                            </div>
                                            <div style="color: var(--text-dark);">
                                                <?php echo formatTime($doctor['schedule_time_start']) . ' - ' . formatTime($doctor['schedule_time_end']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div style="text-align: center; min-width: 200px;">
                                <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 10px;">
                                    <div style="margin-bottom: 1rem;">
                                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">
                                            Consultation Fee
                                        </div>
                                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-cyan);">
                                            ₱<?php echo number_format($doctor['consultation_fee'], 2); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($doctor['is_available']): ?>
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                                            <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php?doctor=<?php echo $doctor['doctor_id']; ?>" 
                                               class="btn btn-primary w-100" style="margin-bottom: 0.5rem;">
                                                <i class="fas fa-calendar-check"></i> Book Appointment
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-primary w-100" onclick="EasyMed.openModal('loginModal')" style="margin-bottom: 0.5rem;">
                                                <i class="fas fa-calendar-check"></i> Book Appointment
                                            </button>
                                        <?php endif; ?>
                                        
                                        <div style="font-size: 0.8rem; color: var(--text-light); text-align: center;">
                                            Available for appointments
                                        </div>
                                    <?php else: ?>
                                        <button class="btn" disabled 
                                                style="background-color: var(--medium-gray); color: var(--text-light); cursor: not-allowed; width: 100%; margin-bottom: 0.5rem;">
                                            <i class="fas fa-calendar-times"></i> Currently Unavailable
                                        </button>
                                        
                                        <div style="font-size: 0.8rem; color: var(--error); text-align: center;">
                                            Not accepting new appointments
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card text-center">
                <div style="font-size: 4rem; color: var(--light-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3 style="color: var(--text-light); margin-bottom: 1rem;">No Doctors Available</h3>
                <p style="color: var(--text-light);">
                    We're currently updating our medical staff information. Please check back later or contact us directly.
                </p>
                <div style="margin-top: 2rem;">
                    <a href="<?php echo SITE_URL; ?>/location.php" class="btn btn-primary">
                        <i class="fas fa-phone"></i> Contact Us
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Specialties Overview -->
<?php if (!empty($doctors)): ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
