<?php
$page_title = "Payment Information";
$page_description = "Payment methods and instructions for EasyMed Private Clinic appointments";
require_once 'includes/header.php';

// Get clinic settings
$clinic_phone = getClinicSetting('clinic_phone', '+63-2-8123-4567');
$clinic_email = getClinicSetting('clinic_email', 'info@easymed.com');
$gcash_qr_path = getClinicSetting('gcash_qr_code', 'assets/images/gcash-qr.png');
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
                    <?php if (file_exists($gcash_qr_path)): ?>
                        <img src="<?php echo SITE_URL; ?>/<?php echo $gcash_qr_path; ?>" 
                             alt="GCash QR Code" 
                             style="max-width: 200px; width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
                    <?php else: ?>
                        <!-- Placeholder QR Code -->
                        <div style="width: 200px; height: 200px; background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: white;">
                            <div style="text-align: center;">
                                <i class="fas fa-qrcode" style="font-size: 3rem; margin-bottom: 0.5rem;"></i>
                                <div style="font-size: 0.9rem;">GCash QR Code</div>
                            </div>
                        </div>
                    <?php endif; ?>
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

<!-- Consultation Fees -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Consultation Fees</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <?php
            // Get doctors and their fees
            $db = Database::getInstance();
            $doctors = $db->fetchAll("
                SELECT u.first_name, u.last_name, d.specialty, d.consultation_fee, d.is_available
                FROM users u 
                JOIN doctors d ON u.id = d.user_id 
                WHERE u.role = 'doctor' AND u.is_active = 1
                ORDER BY d.specialty, u.first_name
            ");
            
            if (!empty($doctors)):
                foreach ($doctors as $doctor):
            ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div>
                            <h4 style="color: var(--primary-cyan); margin: 0;">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </h4>
                            <p style="color: var(--text-light); margin: 0; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($doctor['specialty']); ?>
                            </p>
                        </div>
                        <?php if ($doctor['is_available']): ?>
                            <span style="background-color: var(--success); color: white; padding: 0.2rem 0.6rem; border-radius: 15px; font-size: 0.8rem;">
                                Available
                            </span>
                        <?php else: ?>
                            <span style="background-color: var(--error); color: white; padding: 0.2rem 0.6rem; border-radius: 15px; font-size: 0.8rem;">
                                Unavailable
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background-color: var(--light-gray); border-radius: 8px;">
                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">
                            Consultation Fee
                        </div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-cyan);">
                            ₱<?php echo number_format($doctor['consultation_fee'], 2); ?>
                        </div>
                    </div>
                    
                    <?php if ($doctor['is_available']): ?>
                        <div style="margin-top: 1rem; text-align: center;">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                                <a href="<?php echo SITE_URL; ?>/patient/book-appointment.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-calendar-check"></i> Book Appointment
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm" onclick="EasyMed.openModal('loginModal')">
                                    <i class="fas fa-calendar-check"></i> Book Appointment
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <div class="card text-center">
                    <h3 style="color: var(--text-light);">No consultation fees available</h3>
                    <p>Please contact the clinic for current pricing information.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Payment Policies -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Payment Policies</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-clock"></i> Payment Timing
                </h4>
                <ul style="list-style: none; padding: 0; color: var(--text-dark);">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Payment required before appointment confirmation
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Payment verification within 24 hours
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Late payments may result in appointment cancellation
                    </li>
                </ul>
            </div>
            
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-undo-alt"></i> Refund Policy
                </h4>
                <ul style="list-style: none; padding: 0; color: var(--text-dark);">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Full refund for cancellations 24+ hours in advance
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-times-circle" style="color: var(--error); margin-right: 0.5rem;"></i>
                        No refund for same-day cancellations
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-clock" style="color: var(--warning); margin-right: 0.5rem;"></i>
                        Refunds processed within 3-5 business days
                    </li>
                </ul>
            </div>
            
            <div class="card">
                <h4 style="color: var(--primary-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-shield-alt"></i> Payment Security
                </h4>
                <ul style="list-style: none; padding: 0; color: var(--text-dark);">
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Secure GCash payment processing
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        Payment receipts securely stored
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                        No credit card information stored
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Payment FAQ</h2>
        
        <div style="max-width: 800px; margin: 0 auto;">
            <div style="display: grid; gap: 1rem;">
                <details class="card" style="cursor: pointer;">
                    <summary style="font-weight: 600; color: var(--primary-cyan); padding: 0.5rem 0;">
                        <i class="fas fa-question-circle" style="margin-right: 0.5rem;"></i>
                        What payment methods do you accept?
                    </summary>
                    <div style="padding-top: 1rem; color: var(--text-dark);">
                        Currently, we accept payments through GCash only. We are working to add more payment options in the future for your convenience.
                    </div>
                </details>
                
                <details class="card" style="cursor: pointer;">
                    <summary style="font-weight: 600; color: var(--primary-cyan); padding: 0.5rem 0;">
                        <i class="fas fa-question-circle" style="margin-right: 0.5rem;"></i>
                        How long does payment verification take?
                    </summary>
                    <div style="padding-top: 1rem; color: var(--text-dark);">
                        Payment verification typically takes 2-4 hours during business hours. For payments made outside business hours, verification will be completed the next business day.
                    </div>
                </details>
                
                <details class="card" style="cursor: pointer;">
                    <summary style="font-weight: 600; color: var(--primary-cyan); padding: 0.5rem 0;">
                        <i class="fas fa-question-circle" style="margin-right: 0.5rem;"></i>
                        Can I pay at the clinic?
                    </summary>
                    <div style="padding-top: 1rem; color: var(--text-dark);">
                        To ensure contactless transactions and faster service, we require advance payment through GCash. This also helps us confirm your appointment and prepare for your visit.
                    </div>
                </details>
                
                <details class="card" style="cursor: pointer;">
                    <summary style="font-weight: 600; color: var(--primary-cyan); padding: 0.5rem 0;">
                        <i class="fas fa-question-circle" style="margin-right: 0.5rem;"></i>
                        What if I can't use GCash?
                    </summary>
                    <div style="padding-top: 1rem; color: var(--text-dark);">
                        Please contact our clinic directly at <?php echo htmlspecialchars($clinic_phone); ?> to discuss alternative payment arrangements. We'll work with you to find a suitable solution.
                    </div>
                </details>
                
                <details class="card" style="cursor: pointer;">
                    <summary style="font-weight: 600; color: var(--primary-cyan); padding: 0.5rem 0;">
                        <i class="fas fa-question-circle" style="margin-right: 0.5rem;"></i>
                        Is my payment information secure?
                    </summary>
                    <div style="padding-top: 1rem; color: var(--text-dark);">
                        Yes, all payments are processed through GCash's secure platform. We do not store any financial information on our servers, only the payment confirmation receipts you provide.
                    </div>
                </details>
            </div>
        </div>
    </div>
</section>

<!-- Contact for Payment Issues -->
<section class="section">
    <div class="container">
        <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center;">
            <h3 style="color: white; margin-bottom: 1rem;">
                <i class="fas fa-headset"></i> Need Help with Payment?
            </h3>
            <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem;">
                Our staff is ready to assist you with any payment-related questions or issues.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="tel:<?php echo htmlspecialchars($clinic_phone); ?>" 
                   class="btn" style="background-color: white; color: var(--primary-cyan);">
                    <i class="fas fa-phone"></i> Call Us
                </a>
                <a href="mailto:<?php echo htmlspecialchars($clinic_email); ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> Email Us
                </a>
                <a href="<?php echo SITE_URL; ?>/location.php" 
                   class="btn btn-secondary">
                    <i class="fas fa-map-marker-alt"></i> Visit Us
                </a>
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
