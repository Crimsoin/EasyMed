<?php
$page_title = "Payment Gateway";
$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

// Check if payment data exists in session or if appointment_id is provided
$payment_data = $_SESSION['payment_data'] ?? null;
$appointment_id_param = intval($_GET['appointment_id'] ?? 0);

if (!$payment_data && $appointment_id_param > 0) {
    // Load appointment data from database
    $db = Database::getInstance();
    $appointment = $db->fetch("
        SELECT a.*, p.user_id as patient_user_id, 
               d.id as doctor_internal_id, d.consultation_fee, 
               u.id as doctor_user_id, u.first_name, u.last_name,
               JSON_EXTRACT(a.patient_info, '$.reference_number') as reference_number,
               JSON_EXTRACT(a.patient_info, '$.purpose') as purpose,
               JSON_EXTRACT(a.patient_info, '$.laboratory') as laboratory
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE a.id = ? AND p.user_id = ?
    ", [$appointment_id_param, $_SESSION['user_id']]);
    
    if ($appointment) {
        $purpose = trim($appointment['purpose'] ?? '', '"');
        $laboratory_name = trim($appointment['laboratory'] ?? '', '"');
        $fee = $appointment['consultation_fee'];
        $fee_label = 'Consultation Fee';
        
        // If purpose is laboratory, get the lab offer price
        if ($purpose === 'laboratory' && !empty($laboratory_name)) {
            $lab_offer = $db->fetch("
                SELECT lo.price, lo.title, lo.id, lod.doctor_id
                FROM lab_offers lo
                JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
                WHERE lo.title = ? AND lod.doctor_id = ? AND lo.is_active = 1
            ", [$laboratory_name, $appointment['doctor_internal_id']]);
            
            if ($lab_offer && !empty($lab_offer['price'])) {
                $fee = $lab_offer['price'];
                $fee_label = 'Laboratory Fee';
            }
        }
        
        $payment_data = [
            'appointment_id' => $appointment['id'],
            'doctor_name' => "Dr. {$appointment['first_name']} {$appointment['last_name']}",
            'consultation_fee' => $fee,
            'fee_label' => $fee_label,
            'purpose' => $purpose,
            'laboratory' => $laboratory_name,
            'appointment_date' => $appointment['appointment_date'],
            'appointment_time' => $appointment['appointment_time'],
            'reference_number' => trim($appointment['reference_number'], '"') // Remove JSON quotes
        ];
    }
}

if (!$payment_data) {
    header('Location: book-appointment.php');
    exit();
}

$appointment_id = $payment_data['appointment_id'];
$doctor_name = $payment_data['doctor_name'];
$consultation_fee = $payment_data['consultation_fee'];
$fee_label = $payment_data['fee_label'] ?? 'Consultation Fee';
$purpose = $payment_data['purpose'] ?? 'consultation';
$laboratory = $payment_data['laboratory'] ?? '';
$appointment_date = $payment_data['appointment_date'];
$appointment_time = $payment_data['appointment_time'];
$reference_number = $payment_data['reference_number'];

require_once '../includes/header.php';

// Get clinic settings
$clinic_phone = getClinicSetting('clinic_phone', '+63-2-8123-4567');
$clinic_email = getClinicSetting('clinic_email', 'info@easymed.com');
$gcash_number = getClinicSetting('gcash_number', '09123456789');
?>

<div class="patient-container">
    <div class="patient-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user"></i> Patient Portal</h3>
            <p style="margin: 0.5rem 0 0 0; color: #ffffffff; font-size: 0.9rem; font-weight: 500;">
                <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
            </p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_patients.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="book-appointment.php" class="nav-item">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </a>
            <a href="appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="reviews.php" class="nav-item">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-cog"></i> My Profile
            </a>
        </nav>
    </div>

    <div class="patient-content">
        <!-- Payment Header -->
        <div class="content-header">
            <h1><i class="fas fa-credit-card"></i> Complete Your Payment</h1>
            <p>Secure your appointment by completing the payment process</p>
        </div>

        <?php 
        // Display error messages
        if (isset($_SESSION['payment_errors']) && !empty($_SESSION['payment_errors'])): 
        ?>
            <div class="alert alert-error" style="margin-bottom: 2rem; padding: 1rem 1.5rem; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b;">
                <h4 style="margin: 0 0 0.5rem 0; color: #991b1b;">
                    <i class="fas fa-exclamation-triangle"></i> Payment Error
                </h4>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($_SESSION['payment_errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['payment_errors']); ?>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <!-- Appointment Summary -->
            <div class="card">
                <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem; border-bottom: 2px solid var(--light-cyan); padding-bottom: 0.5rem;">
                    <i class="fas fa-clipboard-list"></i> Appointment Summary
                </h3>
                
                <div style="display: grid; gap: 1rem;">
                    <div class="summary-item">
                        <label><i class="fas fa-user-md"></i> Doctor:</label>
                        <span><?php echo htmlspecialchars($doctor_name); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label><i class="fas fa-calendar"></i> Date:</label>
                        <span><?php echo date('F j, Y', strtotime($appointment_date)); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label><i class="fas fa-clock"></i> Time:</label>
                        <span><?php echo formatTime($appointment_time); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label><i class="fas fa-hashtag"></i> Reference:</label>
                        <span><?php echo htmlspecialchars($reference_number); ?></span>
                    </div>
                    
                    <?php if ($purpose === 'laboratory' && !empty($laboratory)): ?>
                    <div class="summary-item">
                        <label><i class="fas fa-flask"></i> Laboratory Service:</label>
                        <span><?php echo htmlspecialchars($laboratory); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-item total-fee">
                        <label><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($fee_label); ?>:</label>
                        <span style="font-size: 1.3rem; font-weight: bold; color: var(--primary-cyan);">
                            ₱<?php echo number_format($consultation_fee, 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- GCash Payment -->
            <div class="card">
                <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem; border-bottom: 2px solid var(--light-cyan); padding-bottom: 0.5rem;">
                    <i class="fas fa-mobile-alt"></i> GCash Payment
                </h3>
                
                <!-- QR Code Display -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="background-color: var(--light-gray); padding: 2rem; border-radius: 10px; margin-bottom: 1rem;">
                        <!-- Your GCash QR Code -->
                        <img src="<?php echo SITE_URL; ?>/assets/images/Gcash-QR.png" 
                             alt="EasyMed Clinic GCash QR Code" 
                             style="width: 250px; height: 250px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); object-fit: contain;">
                    </div>
                    
                    <div style="background-color: rgba(0, 188, 212, 0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="margin: 0; font-size: 0.9rem; text-align: center;">
                            <strong>Scan QR Code or Send to GCash:</strong> <?php echo htmlspecialchars($gcash_number); ?>
                        </p>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; text-align: center; color: var(--text-light);">
                            <em>Use the QR code above for faster payment processing</em>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                <i class="fas fa-list-ol"></i> Payment Instructions
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div>
                    <h4 style="color: var(--text-dark); margin-bottom: 1rem;">Step-by-Step Process:</h4>
                    <ol style="color: var(--text-dark); line-height: 1.8; padding-left: 1.5rem;">
                        <li>Open your GCash app on your mobile phone</li>
                        <li>Tap <strong>"Send Money"</strong> or <strong>"Pay QR"</strong></li>
                        <li>Scan the QR code or enter the GCash number manually</li>
                        <li>Enter the exact amount: <strong>₱<?php echo number_format($consultation_fee, 2); ?></strong></li>
                        <li>Add the reference number: <strong><?php echo htmlspecialchars($reference_number); ?></strong></li>
                        <li>Complete the payment and save the receipt</li>
                        <li>Upload your payment proof below</li>
                    </ol>
                </div>
                
                <div>
                    <h4 style="color: var(--text-dark); margin-bottom: 1rem;">Important Notes:</h4>
                    <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 8px;">
                        <div style="margin-bottom: 1rem;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                            <strong>Pay the exact amount only</strong>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <i class="fas fa-hashtag" style="color: var(--primary-cyan); margin-right: 0.5rem;"></i>
                            <strong>Include the reference number in your payment</strong>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <i class="fas fa-camera" style="color: var(--success); margin-right: 0.5rem;"></i>
                            <strong>Take a clear screenshot of your receipt</strong>
                        </div>
                        <div>
                            <i class="fas fa-clock" style="color: var(--info); margin-right: 0.5rem;"></i>
                            <strong>Upload proof within 24 hours</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Proof Upload -->
        <div class="card">
            <h3 style="color: var(--primary-cyan); margin-bottom: 1.5rem;">
                <i class="fas fa-upload"></i> Upload Payment Proof
            </h3>
            
            <form method="post" action="process-payment.php" enctype="multipart/form-data" id="paymentForm">
                <input type="hidden" name="appointment_id" value="<?php echo intval($appointment_id); ?>">
                <input type="hidden" name="reference_number" value="<?php echo htmlspecialchars($reference_number); ?>">
                
                <div style="display: grid; gap: 1.5rem;">
                    <div class="form-group">
                        <label for="payment_receipt" class="form-label">
                            <i class="fas fa-file-image"></i> Payment Receipt/Screenshot
                        </label>
                        <input type="file" id="payment_receipt" name="payment_receipt" 
                               accept="image/*,.pdf" required class="form-control">
                        <small style="color: var(--text-light); font-size: 0.85rem;">
                            Accepted formats: JPG, PNG, PDF (Max 5MB)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="gcash_reference" class="form-label">
                            <i class="fas fa-hashtag"></i> GCash Reference Number
                        </label>
                        <input type="text" id="gcash_reference" name="gcash_reference" 
                               placeholder="Enter GCash transaction reference number" 
                               required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_notes" class="form-label">
                            <i class="fas fa-sticky-note"></i> Additional Notes (Optional)
                        </label>
                        <textarea id="payment_notes" name="payment_notes" 
                                  placeholder="Any additional information about your payment..."
                                  rows="3" class="form-control"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Skip for Now
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Submit Payment Proof
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Contact Support -->
        <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center; margin-top: 2rem;">
            <h4 style="color: white; margin-bottom: 1rem;">
                <i class="fas fa-headset"></i> Need Help with Payment?
            </h4>
            <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1rem;">
                Our support team is here to assist you with any payment issues.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="tel:<?php echo htmlspecialchars($clinic_phone); ?>" 
                   class="btn" style="background-color: white; color: var(--primary-cyan);">
                    <i class="fas fa-phone"></i> Call Support
                </a>
                <a href="mailto:<?php echo htmlspecialchars($clinic_email); ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> Email Support
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--light-gray);
    border-radius: 6px;
}

.summary-item label {
    font-weight: 600;
    color: var(--text-dark);
}

.summary-item span {
    color: var(--text-dark);
    text-align: right;
}

.total-fee {
    background-color: rgba(0, 188, 212, 0.1);
    border: 2px solid var(--light-cyan);
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.form-control {
    padding: 0.75rem;
    border: 2px solid var(--light-gray);
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-cyan);
    box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.1);
}

@media (max-width: 768px) {
    .patient-container {
        grid-template-columns: 1fr;
    }
    
    .patient-sidebar {
        display: none;
    }
    
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// File upload validation
document.getElementById('payment_receipt').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            e.target.value = '';
            return;
        }
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload only JPG, PNG, or PDF files');
            e.target.value = '';
            return;
        }
    }
});

// Form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const receipt = document.getElementById('payment_receipt').files[0];
    const reference = document.getElementById('gcash_reference').value.trim();
    
    if (!receipt) {
        e.preventDefault();
        alert('Please upload your payment receipt');
        return;
    }
    
    if (!reference) {
        e.preventDefault();
        alert('Please enter the GCash reference number');
        return;
    }
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
});

// Auto-clear session timer
setTimeout(function() {
    if (confirm('Your payment session will expire soon. Do you want to continue?')) {
        // Extend session
        fetch('extend-session.php', {method: 'POST'})
            .catch(err => console.log('Session extension failed'));
    }
}, 15 * 60 * 1000); // 15 minutes
</script>

</body>
</html>
