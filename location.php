<?php
$page_title = "Location & Contact";
$page_description = "Find EasyMed Private Clinic location, contact information, and operating hours";
require_once 'includes/header.php';

// Get clinic settings
$clinic_name = getClinicSetting('clinic_name', 'EasyMed Private Clinic');
$clinic_address = getClinicSetting('clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines');
$clinic_phone = getClinicSetting('clinic_phone', '+63-2-8123-4567');
$clinic_email = getClinicSetting('clinic_email', 'info@easymed.com');
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
                                    <strong>Address:</strong><br>
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
                    
                    <!-- Operating Hours -->
                    <div>
                        <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                            <i class="fas fa-clock"></i> Operating Hours
                        </h4>
                        
                        <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 8px;">
                            <div style="display: grid; gap: 0.5rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--medium-gray);">
                                    <span style="font-weight: 500;">Monday - Friday</span>
                                    <span style="color: var(--primary-cyan); font-weight: 500;">8:00 AM - 6:00 PM</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--medium-gray);">
                                    <span style="font-weight: 500;">Saturday</span>
                                    <span style="color: var(--primary-cyan); font-weight: 500;">8:00 AM - 4:00 PM</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0;">
                                    <span style="font-weight: 500;">Sunday</span>
                                    <span style="color: var(--error); font-weight: 500;">Closed</span>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background-color: rgba(0, 188, 212, 0.1); border-radius: 8px; border-left: 4px solid var(--primary-cyan);">
                            <p style="margin: 0; font-size: 0.9rem; color: var(--text-dark);">
                                <i class="fas fa-info-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                                <strong>Note:</strong> For emergencies outside operating hours, please contact our emergency hotline or visit the nearest hospital.
                            </p>
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
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.2167662183745!2d121.01715631484587!3d14.586318289844928!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c9f37d8c7c3b%3A0x8c6d3f3f3f3f3f3f!2sManila%2C%20Metro%20Manila%2C%20Philippines!5e0!3m2!1sen!2sph!4v1629789123456!5m2!1sen!2sph" 
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

<!-- Transportation and Accessibility -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Getting to Our Clinic</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-car"></i> By Private Vehicle
                </h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Free parking available on-site
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Accessible parking spaces
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Convenient location near main roads
                    </li>
                </ul>
            </div>
            
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-bus"></i> Public Transportation
                </h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Multiple bus routes nearby
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Walking distance from MRT/LRT stations
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Jeepney routes available
                    </li>
                </ul>
            </div>
            
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-wheelchair"></i> Accessibility
                </h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Wheelchair accessible entrance
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Elevator access to all floors
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Accessible restroom facilities
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Emergency Contact -->
<section class="section">
    <div class="container">
        <div class="card" style="background: linear-gradient(135deg, #ff6b6b, #ee5a52); color: white; text-align: center;">
            <h3 style="color: white; margin-bottom: 1rem;">
                <i class="fas fa-exclamation-triangle"></i> Emergency Information
            </h3>
            <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1.5rem;">
                For medical emergencies outside our operating hours, please call emergency services or visit the nearest hospital emergency room.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <h4 style="color: white; margin-bottom: 0.5rem;">Emergency Hotline</h4>
                    <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 1.2rem; font-weight: bold;">
                        <i class="fas fa-phone"></i> 911
                    </p>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 0.5rem;">Ambulance Service</h4>
                    <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 1.2rem; font-weight: bold;">
                        <i class="fas fa-ambulance"></i> 117
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Send Us a Message</h2>
        
        <div style="max-width: 600px; margin: 0 auto;">
            <div class="card">
                <form id="contactForm">
                    <div class="form-group">
                        <label for="contactName" class="form-label">Full Name *</label>
                        <input type="text" id="contactName" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactEmail" class="form-label">Email Address *</label>
                        <input type="email" id="contactEmail" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactPhone" class="form-label">Phone Number</label>
                        <input type="tel" id="contactPhone" name="phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="contactSubject" class="form-label">Subject *</label>
                        <select id="contactSubject" name="subject" class="form-control" required>
                            <option value="">Select a subject</option>
                            <option value="appointment">Appointment Inquiry</option>
                            <option value="general">General Information</option>
                            <option value="billing">Billing Question</option>
                            <option value="feedback">Feedback/Complaint</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactMessage" class="form-label">Message *</label>
                        <textarea id="contactMessage" name="message" class="form-control" rows="5" required 
                                  placeholder="Please provide details about your inquiry..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <p style="font-size: 0.9rem; color: var(--text-light); margin: 0;">
                            We'll respond to your message within 24 hours during business days.
                        </p>
                    </div>
                </form>
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
