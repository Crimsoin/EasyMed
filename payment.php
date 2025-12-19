<?php
$page_title = "Payment Information";
$page_description = "Payment methods and instructions for EasyMed Private Clinic appointments";
require_once 'includes/header.php';

// Get clinic settings
$clinic_phone = getClinicSetting('clinic_phone', '+63-2-8123-4567');
$clinic_email = getClinicSetting('clinic_email', 'info@easymed.com');
$gcash_qr_path = 'assets/images/Gcash-QR.png';
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-credit-card"></i> Payment Information</h1>
            <p>Easy and secure payment options for your medical appointments</p>
        </div>
    </div>
</section>

<!-- Payment Methods -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; align-items: start;">
            <!-- GCash Payment -->
            <div class="card text-center">
                <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                    <i class="fas fa-mobile-alt"></i> GCash Payment
                </h3>
                
                <!-- QR Code Display -->
                <div style="background-color: var(--light-gray); padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                    <img src="<?php echo SITE_URL; ?>/assets/images/Gcash-QR.png" 
                         alt="EasyMed Clinic GCash QR Code" 
                         style="max-width: 250px; width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); object-fit: contain;">
                </div>
                
                <div style="text-align: left;">
                    <h4 style="color: var(--primary-cyan); margin-bottom: 1rem; text-align: center;">How to Pay with GCash:</h4>
                    <ol style="color: var(--text-dark); line-height: 1.8;">
                        <li>Open your GCash app</li>
                        <li>Tap "Send Money" or "Pay QR"</li>
                        <li>Scan the QR code above</li>
                        <li>Enter the consultation fee amount</li>
                        <li>Complete the payment</li>
                        <li>Take a screenshot of the payment confirmation</li>
                        <li>Send the proof of payment to us</li>
                    </ol>
                </div>
                
                <div style="background-color: rgba(0, 188, 212, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1.5rem; border-left: 4px solid var(--primary-cyan);">
                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-dark);">
                        <i class="fas fa-info-circle" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                        <strong>Note:</strong> Please keep your payment receipt for verification purposes.
                    </p>
                </div>
            </div>
            
            <!-- Payment Instructions -->
            <div>
                <div class="card">
                    <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i> Payment Instructions
                    </h3>
                    
                    <!-- Step-by-step process -->
                    <div style="margin-bottom: 2rem;">
                        <h4 style="color: var(--text-dark); margin-bottom: 1rem;">Payment Process:</h4>
                        
                        <div style="display: grid; gap: 1rem;">
                            <div style="display: flex; align-items: flex-start; padding: 1rem; background-color: var(--light-gray); border-radius: 8px;">
                                <div style="width: 30px; height: 30px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; color: white; font-weight: bold; flex-shrink: 0;">1</div>
                                <div>
                                    <h5 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">Book Your Appointment</h5>
                                    <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">
                                        Schedule your consultation through our online booking system.
                                    </p>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; padding: 1rem; background-color: var(--light-gray); border-radius: 8px;">
                                <div style="width: 30px; height: 30px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; color: white; font-weight: bold; flex-shrink: 0;">2</div>
                                <div>
                                    <h5 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">Make Payment</h5>
                                    <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">
                                        Pay the consultation fee using GCash by scanning the QR code.
                                    </p>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; padding: 1rem; background-color: var(--light-gray); border-radius: 8px;">
                                <div style="width: 30px; height: 30px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; color: white; font-weight: bold; flex-shrink: 0;">3</div>
                                <div>
                                    <h5 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">Submit Proof of Payment</h5>
                                    <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">
                                        Upload your payment receipt through your patient dashboard or send it to us.
                                    </p>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; padding: 1rem; background-color: var(--light-gray); border-radius: 8px;">
                                <div style="width: 30px; height: 30px; background-color: var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; color: white; font-weight: bold; flex-shrink: 0;">4</div>
                                <div>
                                    <h5 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">Confirmation</h5>
                                    <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">
                                        We'll verify your payment and confirm your appointment within 24 hours.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information for Payment -->
                    <div style="background-color: rgba(0, 188, 212, 0.1); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary-cyan);">
                        <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">Send Payment Proof To:</h4>
                        <div style="display: grid; gap: 0.5rem;">
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-envelope" style="color: var(--primary-cyan); margin-right: 0.5rem; width: 20px;"></i>
                                <a href="mailto:<?php echo htmlspecialchars($clinic_email); ?>" 
                                   style="color: var(--primary-cyan); text-decoration: none;">
                                    <?php echo htmlspecialchars($clinic_email); ?>
                                </a>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-phone" style="color: var(--primary-cyan); margin-right: 0.5rem; width: 20px;"></i>
                                <a href="tel:<?php echo htmlspecialchars($clinic_phone); ?>" 
                                   style="color: var(--primary-cyan); text-decoration: none;">
                                    <?php echo htmlspecialchars($clinic_phone); ?>
                                </a>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-user" style="color: var(--primary-cyan); margin-right: 0.5rem; width: 20px;"></i>
                                <span style="color: var(--text-dark);">Patient Dashboard (Upload Section)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<style>
/* Custom styles for details/summary elements */
details summary {
    list-style: none;
    position: relative;
    padding-right: 2rem;
}

details summary::-webkit-details-marker {
    display: none;
}

details summary::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 1rem;
    transition: transform 0.3s ease;
}

details[open] summary::after {
    transform: rotate(180deg);
}

details summary:hover {
    color: var(--dark-cyan);
}
</style>

<?php require_once 'includes/footer.php'; ?>
