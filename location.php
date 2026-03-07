<?php
$page_title = "Location & Contact";
$page_description = "Find EasyMed Private Clinic location, contact information, and operating hours";
require_once 'includes/header.php';

// Get clinic settings
$clinic_name = getClinicSetting('clinic_name', 'EasyMed Private Clinic');
$clinic_address = getClinicSetting('clinic_address', 'Hometown Hotel, 2R67+8W7, Gandara, Samar');
$clinic_phone = getClinicSetting('clinic_phone', '+63 987 654 3210');
$clinic_email = getClinicSetting('clinic_email', 'easymed.notifications@gmail.com');
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-map-marker-alt"></i> Location & Contact</h1>
            <p>Find us and get in touch for your healthcare needs</p>
        </div>
    </div>
</section>

<!-- Location and Map Section -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: start;">
            <!-- Contact Information -->
            <div>
                <div class="card">
                    <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i> Clinic Information
                    </h3>
                    
                    <div style="margin-bottom: 2rem;">
                        <h4 style="color: var(--text-dark); margin-bottom: 1rem;">
                            <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($clinic_name); ?>
                        </h4>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; align-items: flex-start; margin-bottom: 0.5rem;">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary-cyan); margin-right: 0.5rem; margin-top: 0.2rem;"></i>
                                <div>
                                    <strong>Address:</strong>
                                    <?php echo nl2br(htmlspecialchars($clinic_address)); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                <i class="fas fa-phone" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                <div>
                                    <strong>Phone:</strong> 
                                    <a href="tel:<?php echo htmlspecialchars($clinic_phone); ?>" 
                                       style="color: var(--primary-cyan); text-decoration: none;">
                                        <?php echo htmlspecialchars($clinic_phone); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                <i class="fas fa-envelope" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                <div>
                                    <strong>Email:</strong> 
                                    <a href="mailto:<?php echo htmlspecialchars($clinic_email); ?>" 
                                       style="color: var(--primary-cyan); text-decoration: none;">
                                        <?php echo htmlspecialchars($clinic_email); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <!-- Quick Actions -->
                <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center;">
                    <h4 style="color: white; margin-bottom: 1rem;">
                        <i class="fas fa-calendar-check"></i> Book an Appointment
                    </h4>
                    <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1.5rem;">
                        Schedule your visit with our experienced medical professionals.
                    </p>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                        <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php" 
                           class="btn" style="background-color: white; color: var(--primary-cyan);">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    <?php else: ?>
                        <button class="btn" onclick="EasyMed.openModal('loginModal')" 
                                style="background-color: white; color: var(--primary-cyan);">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Map Section -->
            <div>
                <div class="card">
                    <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                        <i class="fas fa-map"></i> Find Us on the Map
                    </h3>
                    
                    <!-- Google Maps Embed -->
                    <div style="position: relative; height: 400px; border-radius: 8px; overflow: hidden; margin-bottom: 1rem;">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d243.90481920368703!2d124.81474558673536!3d12.010639768410114!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2sph!4v1762447521946!5m2!1sen!2sph" 
                            width="100%" 
                            height="100%" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                        <a href="https://maps.google.com/?q=<?php echo urlencode($clinic_address); ?>" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-directions"></i> Get Directions
                        </a>
                        <a href="https://maps.google.com/?q=<?php echo urlencode($clinic_address); ?>" 
                           target="_blank" 
                           class="btn btn-secondary btn-sm">
                            <i class="fas fa-external-link-alt"></i> Open in Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<script>
// Contact form handler
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Show success message (in a real implementation, you would send this to a server)
    EasyMed.showAlert('Thank you for your message! We will get back to you soon.', 'success');
    this.reset();
});
</script>

<?php require_once 'includes/footer.php'; ?>
