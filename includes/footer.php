    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Clinic Info -->
                <div class="footer-section">
                    <h4>
                        <i class="fas fa-stethoscope"></i> EasyMed Clinic
                    </h4>
                    <p>
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars(getClinicSetting('clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines')); ?>
                    </p>
                    <p>
                        <i class="fas fa-phone"></i> 
                        <?php echo htmlspecialchars(getClinicSetting('clinic_phone', '+63-2-8123-4567')); ?>
                    </p>
                    <p>
                        <i class="fas fa-envelope"></i> 
                        <?php echo htmlspecialchars(getClinicSetting('clinic_email', 'info@easymed.com')); ?>
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <div class="footer-links">
                        <a href="<?php echo SITE_URL; ?>/index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <a href="<?php echo SITE_URL; ?>/doctors.php">
                            <i class="fas fa-user-md"></i> Find Doctors
                        </a>
                        <a href="<?php echo SITE_URL; ?>/about.php">
                            <i class="fas fa-info-circle"></i> About Us
                        </a>
                        <a href="<?php echo SITE_URL; ?>/location.php">
                            <i class="fas fa-map-marker-alt"></i> Location
                        </a>
                    </div>
                </div>
                
                <!-- Services -->
                <div class="footer-section">
                    <h4>Our Services</h4>
                    <div class="footer-services">
                        <p>
                            <i class="fas fa-stethoscope"></i> General Medicine
                        </p>
                        <p>
                            <i class="fas fa-child"></i> Pediatrics
                        </p>
                        <p>
                            <i class="fas fa-heartbeat"></i> Cardiology
                        </p>
                        <p>
                            <i class="fas fa-vials"></i> Laboratory Tests
                        </p>
                    </div>
                </div>
                
                <!-- Operating Hours -->
                <div class="footer-section">
                    <h4>Operating Hours</h4>
                    <div class="footer-hours">
                        <p class="hours-item">
                            <strong>Monday - Friday:</strong><br>
                            8:00 AM - 6:00 PM
                        </p>
                        <p class="hours-item">
                            <strong>Saturday:</strong><br>
                            8:00 AM - 4:00 PM
                        </p>
                        <p class="hours-item">
                            <strong>Sunday:</strong><br>
                            Closed
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Social Media & Copyright -->
            <div class="footer-bottom">
                <div class="social-links">
                    <a href="#" class="social-link">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
                <p class="copyright">
                    &copy; <?php echo date('Y'); ?> EasyMed Private Clinic. All rights reserved.
                </p>
                <p class="footer-tagline">
                    Designed with <i class="fas fa-heart"></i> for better healthcare
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
    
    <!-- Additional JavaScript for specific pages -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?php echo $inline_js; ?>
        </script>
    <?php endif; ?>
</body>
</html>
