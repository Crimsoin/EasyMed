<?php
$page_title = "About Us";
$page_description = "Learn more about EasyMed Private Clinic - our mission, vision, and commitment to quality healthcare";
require_once 'includes/header.php';
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-info-circle"></i> About EasyMed Private Clinic</h1>
            <p>Your trusted partner in healthcare excellence</p>
        </div>
    </div>
</section>

<!-- About Content -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: center;">
            <div>
                <h2 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">Our Story</h2>
                <p style="margin-bottom: 1.5rem;">
                    EasyMed Private Clinic was established with a vision to provide exceptional healthcare services 
                    that combine medical excellence with modern convenience. Founded by a team of experienced 
                    healthcare professionals, our clinic has been serving the community with dedication and compassion.
                </p>
                <p style="margin-bottom: 1.5rem;">
                    We understand that healthcare should be accessible, convenient, and patient-centered. That's why 
                    we've developed an innovative appointment management system that allows our patients to book 
                    appointments, access their medical records, and communicate with our medical team effortlessly.
                </p>
                <p>
                    Our commitment extends beyond just treating illnesses – we focus on preventive care, health 
                    education, and building long-term relationships with our patients to ensure their overall well-being.
                </p>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h3 style="color: white; margin-bottom: 1rem;">Professional Healthcare</h3>
                <p style="color: rgba(255, 255, 255, 0.9);">
                    Dedicated to providing the highest quality medical care with compassion and expertise.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Mission, Vision, Values -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-target"></i>
                </div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1rem;">Our Mission</h3>
                <p>
                    To provide comprehensive, compassionate, and accessible healthcare services to our community 
                    while embracing innovation and technology to enhance patient experience and outcomes.
                </p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-eye"></i>
                </div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1rem;">Our Vision</h3>
                <p>
                    To be the leading private healthcare provider known for excellence in patient care, 
                    innovative technology solutions, and commitment to improving community health and well-being.
                </p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-heart"></i>
                </div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1rem;">Our Values</h3>
                <p>
                    Compassion, Excellence, Integrity, Innovation, and Respect guide everything we do. 
                    We believe in treating every patient with dignity and providing care that we would want for our own families.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Services Overview -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Our Medical Services</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-stethoscope" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">General Medicine</h4>
                </div>
                <p>Comprehensive medical consultations, health check-ups, and treatment of common medical conditions by our experienced general practitioners.</p>
            </div>
            
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-child" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">Pediatrics</h4>
                </div>
                <p>Specialized healthcare for infants, children, and adolescents, including wellness visits, vaccinations, and developmental assessments.</p>
            </div>
            
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-heartbeat" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">Cardiology</h4>
                </div>
                <p>Expert cardiovascular care including heart health assessments, ECG testing, and management of heart-related conditions.</p>
            </div>
            
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-vials" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">Laboratory Services</h4>
                </div>
                <p>Complete diagnostic testing services including blood work, urinalysis, and other essential medical laboratory tests.</p>
            </div>
            
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-calendar-check" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">Online Booking</h4>
                </div>
                <p>Convenient 24/7 online appointment scheduling system with automated reminders and easy rescheduling options.</p>
            </div>
            
            <div class="card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="width: 50px; height: 50px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas fa-shield-alt" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--primary-cyan);">Health Records</h4>
                </div>
                <p>Secure digital health records management allowing patients to access their medical history, test results, and prescriptions online.</p>
            </div>
        </div>
    </div>
</section>

<!-- Facilities and Technology -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Our Facilities & Technology</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: center;">
            <div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">Modern Medical Facilities</h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        State-of-the-art examination rooms
                    </li>
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Fully equipped laboratory
                    </li>
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Digital medical equipment
                    </li>
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Comfortable waiting areas
                    </li>
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Wheelchair accessible facilities
                    </li>
                    <li style="margin-bottom: 1rem; display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Secure patient data management
                    </li>
                </ul>
            </div>
            
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-laptop-medical"></i> Digital Innovation
                </h4>
                <p style="margin-bottom: 1.5rem;">
                    Our EasyMed platform represents the future of healthcare management, providing patients 
                    with seamless access to our services through advanced technology.
                </p>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-mobile-alt" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Mobile-responsive design
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-lock" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Secure data encryption
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-bell" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Automated notifications
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-chart-line" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        Health tracking analytics
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Team Overview -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Meet Our Team</h2>
        
        <div style="text-align: center; margin-bottom: 3rem;">
            <p style="font-size: 1.1rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                Our dedicated team of healthcare professionals is committed to providing exceptional medical care 
                with compassion, expertise, and the latest medical knowledge.
            </p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div class="card text-center">
                <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user-md" style="font-size: 3rem; color: white;"></i>
                </div>
                <h4 style="color: var(--primary-cyan);">Experienced Doctors</h4>
                <p>Board-certified physicians with years of experience in their respective specialties.</p>
            </div>
            
            <div class="card text-center">
                <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user-nurse" style="font-size: 3rem; color: white;"></i>
                </div>
                <h4 style="color: var(--primary-cyan);">Caring Nurses</h4>
                <p>Compassionate nursing staff dedicated to patient comfort and quality care.</p>
            </div>
            
            <div class="card text-center">
                <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="fas fa-users" style="font-size: 3rem; color: white;"></i>
                </div>
                <h4 style="color: var(--primary-cyan);">Support Staff</h4>
                <p>Friendly administrative and technical staff ensuring smooth clinic operations.</p>
            </div>
        </div>
        
        <div class="text-center" style="margin-top: 3rem;">
            <a href="<?php echo SITE_URL; ?>/doctors.php" class="btn btn-primary">
                <i class="fas fa-user-md"></i> Meet Our Doctors
            </a>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="section" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white;">
    <div class="container text-center">
        <h2 style="color: white; margin-bottom: 1rem;">Ready to Experience Quality Healthcare?</h2>
        <p style="color: rgba(255, 255, 255, 0.9); font-size: 1.1rem; margin-bottom: 2rem;">
            Join thousands of satisfied patients who trust EasyMed for their healthcare needs.
        </p>
        <div>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <button class="btn" onclick="EasyMed.openModal('registerModal')" 
                        style="background-color: white; color: var(--primary-cyan); margin-right: 1rem;">
                    <i class="fas fa-user-plus"></i> Register Now
                </button>
                <a href="<?php echo SITE_URL; ?>/location.php" 
                   class="btn btn-secondary">
                    <i class="fas fa-map-marker-alt"></i> Visit Us
                </a>
            <?php elseif ($_SESSION['role'] === 'patient'): ?>
                <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php" 
                   class="btn" style="background-color: white; color: var(--primary-cyan); margin-right: 1rem;">
                    <i class="fas fa-calendar-check"></i> Book Appointment
                </a>
                <a href="<?php echo SITE_URL; ?>/location.php" 
                   class="btn btn-secondary">
                    <i class="fas fa-map-marker-alt"></i> Visit Us
                </a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/location.php" 
                   class="btn" style="background-color: white; color: var(--primary-cyan);">
                    <i class="fas fa-map-marker-alt"></i> Visit Us
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
