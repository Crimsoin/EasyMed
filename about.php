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
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1rem;">Our Mission</h3>
                <p>
                    Deliver accurate and reliable laboratory results and provide excellent quality care to clients.
                </p>
            </div>
            
            <div class="card text-center">
                <div style="font-size: 3rem; color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-eye"></i>
                </div>
                <h3 style="color: var(--primary-cyan); margin-bottom: 1rem;">Our Vision</h3>
                <p>
                    provide holistic patient care by well trained and qualified personnel, adhering and maintaining the gold standard in releasing accurate and reliable results.
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


<?php require_once 'includes/footer.php'; ?>
