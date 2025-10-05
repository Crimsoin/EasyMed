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
                        <?php echo htmlspecialchars(getClinicSetting('clinic_address', 'Brgy. Ngoso, Gandara Samar')); ?>
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
