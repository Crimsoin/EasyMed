<?php
$page_title = "Book Appointment";
$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
	header('Location: ' . SITE_URL . '/index.php');
	exit();
}

require_once '../includes/header.php';

// Handle appointment messages
$appointment_errors = $_SESSION['appointment_errors'] ?? [];
$appointment_success = $_SESSION['appointment_success'] ?? null;
$appointment_data = $_SESSION['appointment_data'] ?? [];

// Clear session messages
unset($_SESSION['appointment_errors']);
unset($_SESSION['appointment_success']);
unset($_SESSION['appointment_data']);

// Pre-fill user data if $appointment_data is empty (i.e. not returning from an error state)
if (empty($appointment_data)) {
    $db_instance = Database::getInstance();
    $current_user_id = $_SESSION['user_id'];
    $user_details = $db_instance->fetch("
        SELECT u.first_name, u.last_name, u.email, 
               COALESCE(p.phone, u.phone) as phone_number,
               COALESCE(p.address, u.address) as address,
               COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth,
               COALESCE(p.gender, u.gender) as gender
        FROM users u 
        LEFT JOIN patients p ON u.id = p.user_id 
        WHERE u.id = ?
    ", [$current_user_id]);
    
    if ($user_details) {
        $appointment_data = [
            'first_name' => $user_details['first_name'],
            'last_name' => $user_details['last_name'],
            'email' => $user_details['email'],
            'phone_number' => $user_details['phone_number'],
            'address' => $user_details['address'],
            'date_of_birth' => $user_details['date_of_birth'],
            'gender' => $user_details['gender']
        ];
    }
}
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
			<a href="book-appointment.php" class="nav-item active">
				<i class="fas fa-calendar-plus"></i> Book Appointment
			</a>
			<a href="appointments.php" class="nav-item">
				<i class="fas fa-calendar-alt"></i> My Appointments
			</a>
            <a href="feedbacks.php" class="nav-item">
                <i class="fas fa-star"></i> Feedbacks
            </a>

			<a href="profile.php" class="nav-item">
				<i class="fas fa-user-cog"></i> My Profile
			</a>
		</nav>
	</div>

	<div class="patient-content">
		<div class="content-header">
			<h1>Book an Appointment</h1>
			<p>Choose a doctor and schedule your appointment</p>
		</div>

		<!-- Success/Error Messages -->
		<?php if (!empty($appointment_errors)): ?>
			<div class="alert alert-error" style="background: #fee; border: 1px solid #fcc; color: #c33; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
				<h4><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</h4>
				<ul style="margin: 0.5rem 0 0 1.5rem;">
					<?php foreach ($appointment_errors as $error): ?>
						<li><?php echo htmlspecialchars($error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ($appointment_success): ?>
			<div class="alert alert-success" style="background: #efe; border: 1px solid #cfc; color: #363; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
				<h4><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($appointment_success['message']); ?></h4>
				<p style="margin: 0.5rem 0 0 0;">
					<strong>Reference:</strong> <?php echo htmlspecialchars($appointment_success['reference']); ?><br>
					<strong>Doctor:</strong> <?php echo htmlspecialchars($appointment_success['doctor']); ?><br>
					<strong>Date:</strong> <?php echo htmlspecialchars($appointment_success['date']); ?> (<?php echo htmlspecialchars($appointment_success['day']); ?>)<br>
					<strong>Time:</strong> <?php echo htmlspecialchars($appointment_success['time']); ?>
				</p>
			</div>
		<?php endif; ?>

		<!-- Doctor Selection Cards -->
		<div class="content-section">
			<div class="section-header">
				<h2>Available Doctors</h2>
			</div>
			<div class="section-content">
				<?php
				$db = Database::getInstance();
				$doctors = $db->fetchAll("
					SELECT u.id as user_id, u.first_name, u.last_name, u.profile_image,
						   d.id as doctor_id, d.specialty, d.schedule_days, d.schedule_time_start, d.schedule_time_end,
						   d.consultation_fee, d.biography
					FROM users u
					JOIN doctors d ON u.id = d.user_id
					WHERE u.role = 'doctor' AND u.is_active = 1 AND d.is_available = 1
					ORDER BY u.first_name
				");
				?>
				<div class="doctors-grid">
					<?php if (!empty($doctors)): ?>
						<?php foreach ($doctors as $doctor): ?>
							<div class="card doctor-card" style="margin-bottom: 2rem;">
								<div class="doctor-image-container" style="text-align: center; margin-bottom: 1rem;">
									<?php if (!empty($doctor['profile_image']) && file_exists('../assets/images/' . $doctor['profile_image'])): ?>
										<img src="<?php echo SITE_URL; ?>/assets/images/<?php echo htmlspecialchars($doctor['profile_image']); ?>"
											 alt="Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>"
											 class="doctor-image">
									<?php else: ?>
										<div class="doctor-image" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); display: flex; align-items: center; justify-content: center;">
											<i class="fas fa-user-md" style="font-size: 3rem; color: white;"></i>
										</div>
									<?php endif; ?>
								</div>
								<div class="doctor-info">
									<h3 class="doctor-name">
										Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
									</h3>
									<p class="doctor-specialty">
										<i class="fas fa-stethoscope"></i>
										<?php echo htmlspecialchars($doctor['specialty']); ?>
									</p>
									
									<?php
									// Get laboratory offers for this doctor
									$doctor_offers = $db->fetchAll("
										SELECT lo.title 
										FROM lab_offers lo
										JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
										WHERE lod.doctor_id = ? AND lo.is_active = 1
										ORDER BY lo.title
									", [$doctor['doctor_id']]);
									
									if (!empty($doctor_offers)): ?>
										<div class="doctor-offers" style="margin-bottom: 1rem;">
											<?php foreach ($doctor_offers as $offer): ?>
												<span class="offer-badge" style="
													display: inline-block;
													background: linear-gradient(135deg, #eff6ff, #dbeafe);
													color: #2563eb;
													padding: 0.3rem 0.8rem;
													border-radius: 15px;
													font-size: 0.8rem;
													font-weight: 600;
													margin: 0.2rem 0.3rem 0.2rem 0;
													border: 1px solid rgba(37, 99, 235, 0.2);
												">
													<?php echo htmlspecialchars($offer['title']); ?>
												</span>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
                  <!-- biography removed from doctor card -->
									<div class="doctor-schedule">
										<h4 style="color: var(--primary-cyan); margin-bottom: 0.5rem; font-size: 1rem;">
											<i class="fas fa-calendar-alt"></i> Schedule
										</h4>
										<div class="schedule-item">
											<strong>Days:</strong>
											<?php
											$days = explode(',', $doctor['schedule_days']);
											echo htmlspecialchars(implode(', ', $days));
											?>
										</div>
										<div class="schedule-item">
											<strong>Time:</strong>
											<?php echo formatTime($doctor['schedule_time_start']) . ' - ' . formatTime($doctor['schedule_time_end']); ?>
										</div>
										<div class="schedule-item">
											<strong>Consultation Fee:</strong>
											<span style="color: var(--primary-cyan); font-weight: bold;">
												₱<?php echo number_format($doctor['consultation_fee'], 2); ?>
											</span>
										</div>
									</div>
									<div style="margin-top: 1rem; text-align: center;">
									<?php
									// Get laboratory offers for this doctor to include in data attribute with prices
									$doctor_lab_offers = $db->fetchAll("
										SELECT lo.title, lo.price 
										FROM lab_offers lo
										JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
										WHERE lod.doctor_id = ? AND lo.is_active = 1
										ORDER BY lo.title
									", [$doctor['doctor_id']]);
									
									// Store both titles and full offer data with prices
									$doctor['lab_offers'] = array_column($doctor_lab_offers, 'title');
									$doctor['lab_offers_data'] = $doctor_lab_offers; // Include prices
									?>
										<button class="btn btn-primary btn-sm" 
											onclick="openAppointmentModal(this)" 
											data-doctor='<?php echo json_encode($doctor, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
											<i class="fas fa-calendar-plus"></i> Book with Dr. <?php echo htmlspecialchars($doctor['last_name']); ?>
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else: ?>
						<div class="card text-center">
							<h3 style="color: var(--text-light);">
								<i class="fas fa-info-circle"></i> No doctors available at the moment
							</h3>
							<p>Please check back later or contact the clinic directly.</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- Modal for Appointment Form -->
<div id="appointmentModal" class="modal">
  <div class="modal-content" style="max-width: 900px; width: 90%;">
    <div class="modal-header">
      <h2 style="margin:0; font-size:1.3rem;">Appointment Request Form</h2>
      <p style="margin:0; font-size:1rem;">Submit your appointment request. The doctor will review and approve it.</p>
      <span class="close">&times;</span>
    </div>
    <div class="modal-body">
      <div class="appointment-flex">
        <div class="doctor-details-panel" id="modalDoctorPanel">
          <!-- Doctor details will be injected here -->
        </div>
        <div class="appointment-form-panel">
          <form method="post" action="process_appointment.php" class="appointment-form-grid" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="doctor_id" id="modal_doctor_id" value="">
            <!-- Section: Patient Information -->
            <div class="form-section-header">
                <i class="fas fa-user-circle"></i> Patient Information
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_first_name">First Name</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-signature"></i>
                    <input type="text" name="first_name" id="modal_first_name" class="form-control" required>
                </div>
              </div>
              <div class="form-group">
                <label for="modal_last_name">Last Name</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-signature"></i>
                    <input type="text" name="last_name" id="modal_last_name" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_phone_number">Phone Number</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-phone-alt"></i>
                    <input type="text" name="phone_number" id="modal_phone_number" class="form-control" required>
                </div>
              </div>
              <div class="form-group">
                <label for="modal_email">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="modal_email" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_address">Complete Address</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-map-marker-alt" style="top: 1.2rem;"></i>
                    <textarea name="address" id="modal_address" class="form-control" rows="2" required></textarea>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_dob">Date of Birth</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-birthday-cake"></i>
                    <input type="date" name="patient_dob" id="modal_dob" class="form-control" required>
                </div>
              </div>
              <div class="form-group">
                <label for="modal_gender">Gender</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-venus-mars"></i>
                    <select name="patient_gender" id="modal_gender" class="form-control" required>
                      <option value="">Select Gender</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                </div>
              </div>
            </div>

            <!-- Section: Appointment Details -->
            <div class="form-section-header" style="margin-top: 1.5rem;">
                <i class="fas fa-calendar-check"></i> Schedule & Purpose
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_schedule_day">Appointment Date</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-calendar-day"></i>
                    <input type="date" name="schedule_day" id="modal_schedule_day" class="form-control" required>
                </div>
              </div>
              <div class="form-group">
                <label for="modal_schedule_time">Preferred Time</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-clock"></i>
                    <select name="schedule_time" id="modal_schedule_time" class="form-control" required>
                      <option value="">Select Time Slot</option>
                    </select>
                </div>
                <small id="modal_time_range" style="color:#666; display:block; margin-top:0.3rem; font-size:0.85rem; font-weight: 500;"></small>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_relationship">Relationship to Patient</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-users"></i>
                    <select name="relationship" id="modal_relationship" class="form-control" required>
                      <option value="self">Self (My own appointment)</option>
                      <option value="mother">Mother</option>
                      <option value="father">Father</option>
                      <option value="sister">Sister</option>
                      <option value="brother">Brother</option>
                      <option value="grandmother">Grandmother</option>
                      <option value="grandfather">Grandfather</option>
                      <option value="cousin">Cousin</option>
                      <option value="uncle">Uncle</option>
                      <option value="auntie">Auntie</option>
                    </select>
                </div>
              </div>
              <div class="form-group">
                <label for="modal_illness">Reason for Visit / Illness</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-notes-medical"></i>
                    <input type="text" name="illness" id="modal_illness" class="form-control" placeholder="e.g., Fever, Checkup" required>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_purpose">Service Type</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-briefcase-medical"></i>
                    <select name="purpose" id="modal_purpose" class="form-control" required>
                      <option value="consultation">Medical Consultation</option>
                      <option value="laboratory">Laboratory Service</option>
                    </select>
                </div>
              </div>
            </div>
            <div class="form-row" id="laboratory_row" style="display: none;">
              <div class="form-group">
                <label for="modal_laboratory">Select Laboratory Test</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-flask"></i>
                    <select name="laboratory" id="modal_laboratory" class="form-control">
                      <option value="">Choose available test...</option>
                    </select>
                </div>
              </div>
            </div>
            <div class="form-row" id="laboratory_image_row" style="display: none;">
              <div class="form-group">
                <label for="modal_laboratory_image">Upload Laboratory Request Image</label>
                <div class="input-icon-wrapper">
                    <input type="file" name="laboratory_image" id="modal_laboratory_image" class="form-control" accept="image/*">
                </div>
                <small style="color:#666; display:block; margin-top:0.3rem; font-size:0.85rem; font-weight: 500;">Accepted formats: JPG, JPEG, PNG, WEBP</small>
                <div id="laboratory_image_preview" class="laboratory-image-preview" style="display: none;">
                    <img id="laboratory_image_preview_tag" src="" alt="Laboratory request preview">
                </div>
              </div>
            </div>

            <!-- Section: Policy & Payment -->
            <div class="form-section-header" style="margin-top: 1.5rem;">
                <i class="fas fa-file-invoice-dollar"></i> Policy & Summary
            </div>
            <div class="form-row">
                <div class="policy-agreement-box">
                    <div class="policy-header">
                        <i class="fas fa-info-circle"></i> No Refund Policy
                    </div>
                    <div class="policy-content">
                        All payments processed through our platform are final and <strong>non-refundable</strong>. Please verify your selected schedule and doctor availability before proceeding.
                    </div>
                    <label class="custom-checkbox-container">
                        <input type="checkbox" name="agreed_no_refund_policy" id="modal_no_refund" required>
                        <span class="checkmark"></span>
                        I have read and agree to the No Refund Policy
                    </label>
                </div>
            </div>

            <!-- Price Display Section -->
            <div class="form-row">
              <div id="price_display" class="price-receipt-card">
                <div class="receipt-header">
                  <span class="fee-label">TOTAL AMOUNT DUE</span>
                </div>
                <div id="price_amount" class="fee-amount">
                  ₱0.00
                </div>
                <div id="price_label" class="fee-description">
                  Please select a service type
                </div>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" id="submit_appointment_btn" class="btn btn-submit-request" disabled>
                <i class="fas fa-paper-plane"></i> SUBMIT APPOINTMENT REQUEST
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Modal Specific Styles */
#appointmentModal {
    align-items: center;
    justify-content: center;
}

#appointmentModal[style*="display: block"] {
    display: flex !important;
}

#appointmentModal .modal-content {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0,0,0,0.18);
    border: none;
    margin: 0;
}

#appointmentModal .modal-header {
    background: #4a5568;
    border-bottom: none;
    padding: 1.5rem 2.5rem;
    text-align: left;
    position: relative;
    color: white;
}

#appointmentModal .modal-header h2 {
    color: white;
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#appointmentModal .modal-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    margin-left: 0;
}

#appointmentModal .modal-body {
    padding: 0;
}

#appointmentModal .close {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 1.2rem;
    font-weight: bold;
    line-height: 1;
    cursor: pointer;
    z-index: 10;
}

#appointmentModal .close:hover {
    background: #fed7d7;
    color: #c53030;
}

.appointment-flex {
    display: flex;
    gap: 0;
    height: calc(100vh - 140px);
    max-height: 800px;
    min-height: 550px;
}

.doctor-details-panel {
    background: radial-gradient(circle at top right, rgba(255,255,255,0.1) 0%, transparent 40%),
                radial-gradient(circle at bottom left, rgba(255,255,255,0.05) 0%, transparent 40%),
                linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    width: 320px;
    min-width: 320px;
    padding: 2.5rem 2rem;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    color: white;
    min-height: 0;
}

.doctor-details-panel .avatar-wrap {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    border: 4px solid rgba(255,255,255,0.2);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
}

.doctor-card-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
}

.doctor-details-panel .avatar-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.doctor-details-panel .avatar-wrap i {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: unset;
    margin-top: 5px;
}

.doctor-details-panel .doctor-name {
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 0.25rem;
}

.doctor-details-panel .doctor-specialty {
    font-size: 0.9rem;
    color: #bfdbfe;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 2rem;
}

.doctor-info-list {
    width: 100%;
    list-style: none;
    padding: 0;
}

.doctor-info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.95rem;
    background: rgba(255,255,255,0.08);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
}

.doctor-info-item i {
    color: #93c5fd;
    font-size: 1.1rem;
    margin-top: 0.2rem;
}

.doctor-info-text {
    flex: 1;
    min-width: 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
}

.doctor-info-text strong {
    display: block;
    font-size: 0.75rem;
    color: #93c5fd;
    text-transform: uppercase;
    margin-bottom: 0.2rem;
}

.appointment-form-panel {
    flex: 1;
    background: #f8fafc;
    overflow-y: auto;
    min-height: 0;
}

.form-section-header {
    font-size: 0.9rem;
    font-weight: 800;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #edf2f7;
}

.form-section-header i {
    color: var(--primary-cyan);
    font-size: 1.1rem;
}

.appointment-form-grid {
    display: flex;
    flex-direction: column;
    padding: 2rem;
    width: 100%;
    box-sizing: border-box;
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.25rem;
    width: 100%;
}

.form-row:last-child {
    margin-bottom: 0;
}

.form-group {
    flex: 1;
    min-width: 0;
    margin-bottom: 0 !important;
}

.form-group label {
    font-weight: 700;
    color: #2d3748;
    font-size: 0.9rem;
    margin-bottom: 0.4rem;
}

.input-icon-wrapper {
    position: relative;
    width: 100%;
}

.input-icon-wrapper i {
    display: none;
}

.input-icon-wrapper .form-control {
    width: 100%;
    box-sizing: border-box;
    padding-left: 1rem !important;
    background: #fff;
    border: 2px solid #edf2f7;
    height: auto;
    min-height: 48px;
    border-radius: 10px;
    font-size: 0.95rem;
    color: #1a202c;
    transition: all 0.2s;
}

.input-icon-wrapper textarea.form-control {
    padding-top: 0.8rem;
}

.input-icon-wrapper .form-control:focus {
    border-color: var(--primary-cyan);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(0, 180, 204, 0.08);
}

.input-icon-wrapper .form-control:focus + i {
    color: var(--primary-cyan);
}

.laboratory-image-preview {
  margin-top: 0.9rem;
  padding: 0.75rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.laboratory-image-preview img {
  display: block;
  width: 100%;
  max-width: 320px;
  max-height: 240px;
  object-fit: contain;
  border-radius: 10px;
  border: 1px solid #cbd5e0;
  background: #f8fafc;
}

.policy-agreement-box {
    width: 100%;
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: 12px;
    padding: 1.25rem;
    box-sizing: border-box;
}

.policy-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #c53030;
    font-weight: 800;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}

.policy-content {
    font-size: 0.85rem;
    color: #742a2a;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.custom-checkbox-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: #2d3748;
    font-weight: 600;
}

.custom-checkbox-container input {
    width: 20px;
    height: 20px;
    accent-color: #e53e3e;
    cursor: pointer;
}

.price-receipt-card {
    width: 100%;
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    color: white;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 1px solid rgba(255,255,255,0.05);
    box-sizing: border-box;
}

.receipt-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    opacity: 0.8;
}

.fee-label {
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 1px;
}

.fee-amount {
    font-size: 2.2rem;
    font-weight: 800;
    color: #4fd1c5;
    margin-bottom: 0.25rem;
}

.fee-description {
    font-size: 0.9rem;
    color: #a0aec0;
    text-transform: capitalize;
}

.form-actions {
    margin: 2.5rem 0 0 0 !important;
    padding: 2rem 0 1rem 0;
    display: flex;
    justify-content: center;
    width: 100%;
    border-top: 2px dashed #edf2f7;
    background: transparent;
    border-radius: 0;
    box-sizing: border-box;
}

.btn-submit-request {
    background: linear-gradient(135deg, var(--primary-cyan) 0%, #0891b2 100%);
    color: white;
    border: none;
    padding: 1.25rem 2rem;
    border-radius: 12px;
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    box-shadow: 0 10px 25px rgba(8, 145, 178, 0.4);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    width: 100%;
    box-sizing: border-box;
}

.btn-submit-request:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(8, 145, 178, 0.5);
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
}

.btn-submit-request:active {
    transform: translateY(0);
    box-shadow: 0 5px 15px rgba(8, 145, 178, 0.4);
}

.btn-submit-request:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
    opacity: 0.8;
}

.btn-submit-request:disabled:hover {
    transform: none;
    box-shadow: none;
    background: #e2e8f0;
}

/* Spinner Animation */
.fa-spin {
    animation: fa-spin 2s infinite linear;
}
@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(359deg); }
}

@media (max-width: 768px) {
    #appointmentModal {
        align-items: flex-start !important;
        padding: 0;
    }

    #appointmentModal .modal-content {
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 0 !important;
        height: 100vh;
        max-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #appointmentModal .modal-header {
        flex-shrink: 0;
        padding: 1rem 1.25rem;
    }

    #appointmentModal .modal-body {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .appointment-flex {
        flex-direction: column;
        height: auto;
    }

    .doctor-details-panel {
        width: 100%;
        min-width: auto;
        padding: 1.5rem 1.25rem;
    }

    .doctor-details-panel .avatar-wrap {
        width: 80px;
        height: 80px;
        margin-bottom: 1rem;
    }

    .doctor-details-panel .doctor-name {
        font-size: 1.1rem;
    }

    .doctor-details-panel .doctor-specialty {
        margin-bottom: 1rem;
    }

    .doctor-info-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .doctor-info-item {
        margin-bottom: 0;
        padding: 0.75rem;
        font-size: 0.85rem;
    }

    .appointment-form-panel {
        overflow-y: visible;
    }

    .appointment-form-grid {
        padding: 1.25rem;
    }

    .form-row {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .doctor-info-list {
        grid-template-columns: 1fr;
    }

    .appointment-form-grid {
        padding: 1rem;
    }

    .form-section-header {
        font-size: 0.8rem;
    }
}
</style>

<script>
function openAppointmentModal(buttonElement) {
  var doctor = JSON.parse(buttonElement.getAttribute('data-doctor'));
  
  // Reset checkbox and button state for new doctor selection
  var noRefundCheck = document.getElementById('modal_no_refund');
  var submitBtn = document.getElementById('submit_appointment_btn');
  if (noRefundCheck && submitBtn) {
    noRefundCheck.checked = false;
    submitBtn.disabled = true;
  }
  
  // Fill doctor details panel
  var panelHtml = '<div class="doctor-card-inner">';
  if (doctor) {
    panelHtml += '<div class="avatar-wrap">';
    if (doctor.profile_image) {
      panelHtml += '<img src="<?php echo SITE_URL; ?>/assets/images/' + doctor.profile_image + '" alt="Dr. ' + doctor.first_name + ' ' + doctor.last_name + '">';
    } else {
      panelHtml += '<i class="fas fa-user-md" style="font-size:48px; color:#fff;"></i>';
    }
    panelHtml += '</div>';
    panelHtml += '<div class="doctor-name">Dr. ' + doctor.first_name + ' ' + doctor.last_name + '</div>';
    panelHtml += '<div class="doctor-specialty">' + doctor.specialty + '</div>';
    
    panelHtml += '<div class="doctor-info-list">';
    
    // Consultation Fee
    panelHtml += '<div class="doctor-info-item"><i class="fas fa-money-bill-wave"></i><div class="doctor-info-text"><strong>Consultation Fee</strong>₱' + parseFloat(doctor.consultation_fee).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</div></div>';

    // Schedule Days
    if (doctor.schedule_days) {
      var formattedDays = doctor.schedule_days.split(',').join(', ');
      panelHtml += '<div class="doctor-info-item"><i class="fas fa-calendar-alt"></i><div class="doctor-info-text"><strong>Available Days</strong>' + formattedDays + '</div></div>';
    }
    
    // Schedule Time
    if (doctor.schedule_time_start || doctor.schedule_time_end) {
      var dispStart = formatDisplayTime(normalizeTime(doctor.schedule_time_start));
      var dispEnd = formatDisplayTime(normalizeTime(doctor.schedule_time_end));
      panelHtml += '<div class="doctor-info-item"><i class="fas fa-clock"></i><div class="doctor-info-text"><strong>Working Hours</strong>' + dispStart + ' - ' + dispEnd + '</div></div>';
    }

    
    panelHtml += '</div>';
  } else {
    panelHtml += '<div style="text-align:center; color:#333; width: 100%;">Doctor details not found.</div>';
  }
  panelHtml += '</div>';
  
  document.getElementById('modalDoctorPanel').innerHTML = panelHtml;
  document.getElementById('modal_doctor_id').value = doctor.user_id;
  // expose current doctor to calendar/time helpers and price calculator
  window._currentModalDoctor = doctor;
  
  // Initialize price display with consultation fee
  updatePriceDisplay('consultation');
  
  // Populate schedule days (kept for compatibility)
  populateScheduleDays(doctor.schedule_days);
  // Setup native date input: min/max and allowed weekdays
  setupDateInput(doctor.schedule_days);
  // If calendar produced no visible buttons (edge cases), show a date input fallback
  // ensure date input change populates times
  var dateInput = document.getElementById('modal_schedule_day');
  if (dateInput) {
    dateInput.addEventListener('change', function(){
      if (!this.value) return;
      populateScheduleTimesForDate(new Date(this.value));
    });
  }

  // helper to hide any previous calendar container if present
  var oldCal = document.getElementById('modal_calendar'); if (oldCal) oldCal.style.display = 'none';
  

function setupDateInput(scheduleDays) {
  var input = document.getElementById('modal_schedule_day');
  if (!input || input.tagName.toLowerCase() !== 'input') return;
  var today = new Date();
  var advance = 30;
  try { var adv = document.getElementById('advance_booking_days'); if (adv) advance = parseInt(adv.value)||advance; } catch(e){}
  var minDate = new Date();
  // allow same-day booking when there are remaining future time slots
  minDate.setDate(today.getDate()+0); // allow today
  var maxDate = new Date(); maxDate.setDate(today.getDate()+advance);
  input.min = minDate.toISOString().slice(0,10);
  input.max = maxDate.toISOString().slice(0,10);

  // store allowed weekdays on input for validation
  var allowed = [];
  if (scheduleDays) scheduleDays.split(',').forEach(function(d){ allowed.push(d.trim().toLowerCase()); });
  input.dataset.allowedDays = JSON.stringify(allowed);

  // validate selection on change
  input.addEventListener('change', function(){
    var v = this.value; if (!v) return;
    var dt = new Date(v);
    var dow = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][dt.getDay()];
    var allowed = JSON.parse(this.dataset.allowedDays || '[]');
    if (allowed.length && allowed.indexOf(dow) === -1) {
      alert('Selected date is not on the doctor\'s working days. Please choose another date.');
      this.value = '';
      document.getElementById('modal_schedule_time').innerHTML = '<option value="">Select Time</option>';
      document.getElementById('modal_time_range').textContent = '';
      return;
    }
    // valid date: populate times
    populateScheduleTimesForDate(dt);
  });
}
  // Populate schedule times
  populateScheduleTimes(doctor.schedule_time_start, doctor.schedule_time_end);
  
  // Populate laboratory offers for this specific doctor
  populateDoctorLaboratoryOffers(doctor.doctor_id);
  
  // Show modal
  document.getElementById('appointmentModal').style.display = 'block';
  
  // Prefill form data
  prefillFormData();
}

// Calendar rendering for Choose Schedule Day
function renderModalCalendar(scheduleDays) {
  var container = document.getElementById('modal_calendar');
  var input = document.getElementById('modal_schedule_day');
  container.innerHTML = '';

  var advanceDays = 30; // default
  try {
    var advEl = document.getElementById('advance_booking_days');
    if (advEl) advanceDays = parseInt(advEl.value) || advanceDays;
  } catch (e) {}

  var allowedDow = [];
  if (scheduleDays) {
    scheduleDays.split(',').forEach(function(d) { allowedDow.push(d.trim().toLowerCase()); });
  }

  var today = new Date();
  var ul = document.createElement('div');
  ul.style.display = 'flex';
  ul.style.flexWrap = 'wrap';
  ul.style.gap = '6px';

  for (var i = 0; i <= advanceDays; i++) {
    var dt = new Date();
    dt.setDate(today.getDate() + i);
    var dowName = dt.toLocaleDateString('en-US',{ weekday: 'long' }).toLowerCase();
    var disabled = (allowedDow.length > 0 && allowedDow.indexOf(dowName) === -1);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.style.padding = '6px 8px';
    btn.style.borderRadius = '6px';
    btn.style.border = '1px solid #e1e5e9';
    btn.style.background = disabled ? '#f4f5f6' : '#fff';
    btn.style.cursor = disabled ? 'not-allowed' : 'pointer';
    btn.textContent = (dt.getMonth()+1) + '/' + dt.getDate();
    btn.title = dt.toDateString();
    if (!disabled) {
      (function(d){ btn.addEventListener('click', function(){
        input.value = d.toISOString().slice(0,10);
        // set schedule_time options based on dow
        var dow = d.getDay();
        // convert dow to weekday name matching scheduleDays
        populateScheduleTimesForDate(d);
        // highlight selected
        Array.from(container.querySelectorAll('button')).forEach(function(b){ b.style.boxShadow = ''; b.style.borderColor = '#e1e5e9'; });
        this.style.boxShadow = '0 0 0 3px rgba(0,180,204,0.12)';
        this.style.borderColor = '#3b82f6';
      }); })(dt);
    }
    ul.appendChild(btn);
  }

  container.appendChild(ul);
}

function populateScheduleTimesForDate(dateObj) {
  // Determine day-of-week and populate times using existing populateScheduleTimes helper
  var dow = dateObj.getDay();
  // map numeric dow to string day used in doctor.schedule_days (e.g., monday)
  var days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
  var dowName = days[dow];
  // Find the currently selected doctor's schedule info from the modalDoctorPanel data
  var doctorJson = window._currentModalDoctor || (document.querySelector('button[data-doctor]') ? JSON.parse(document.querySelector('button[data-doctor]').getAttribute('data-doctor')) : null);
  if (!doctorJson) return;
  var scheduleDays = (doctorJson.schedule_days || '').split(',').map(function(s){ return s.trim().toLowerCase(); });
  if (scheduleDays.indexOf(dowName) === -1) {
    // no schedule this day
    document.getElementById('modal_schedule_time').innerHTML = '<option value="">No available time</option>';
    document.getElementById('modal_time_range').textContent = '';
    return;
  }
  populateScheduleTimes(doctorJson.schedule_time_start, doctorJson.schedule_time_end);
}

function populateScheduleDays(scheduleDays) {
  var scheduleDay = document.getElementById('modal_schedule_day');
  scheduleDay.innerHTML = '<option value="">Select Day</option>';
  if (scheduleDays) {
    var days = scheduleDays.split(',');
    days.forEach(function(d) {
      var option = document.createElement('option');
      option.value = d.trim();
      option.textContent = d.trim();
      scheduleDay.appendChild(option);
    });
  }
}

function populateScheduleTimes(startTime, endTime) {
  var timeInput = document.getElementById('modal_schedule_time');
  var timeRangeText = document.getElementById('modal_time_range');
  timeInput.innerHTML = '<option value="">Select Time</option>';
  
  if (startTime && endTime) {
    var rawMin = normalizeTime(startTime);
    var rawMax = normalizeTime(endTime);
    
    var startMinutes = timeToMinutes(rawMin);
    var endMinutes = timeToMinutes(rawMax);
    
    var startHour = Math.floor(startMinutes / 60);
    if (startMinutes % 60 > 0) startHour += 1;
    
    var endHour = Math.floor(endMinutes / 60);
    
    // If the currently selected date is today, filter out past times
    var selectedDateVal = document.getElementById('modal_schedule_day') ? document.getElementById('modal_schedule_day').value : '';
    var allowOnlyFuture = false;
    var nowMinutes = null;
    var bufferMinutes = 30; // don't allow slots starting within the next 30 minutes
    if (selectedDateVal) {
      var sel = new Date(selectedDateVal + 'T00:00:00');
      var todayCheck = new Date();
      if (sel.getFullYear() === todayCheck.getFullYear() && sel.getMonth() === todayCheck.getMonth() && sel.getDate() === todayCheck.getDate()) {
        allowOnlyFuture = true;
        nowMinutes = todayCheck.getHours() * 60 + todayCheck.getMinutes() + bufferMinutes;
      }
    }

    for (var hour = startHour; hour <= endHour; hour++) {
      var timeValue = String(hour).padStart(2,'0') + ':00';
      var optionMinutes = timeToMinutes(timeValue);
      if (allowOnlyFuture && optionMinutes <= nowMinutes) continue; // skip past/too-soon slots
      var option = document.createElement('option');
      option.value = timeValue;
      option.textContent = formatDisplayTime(timeValue);
      timeInput.appendChild(option);
    }
    
    var minTime = String(startHour).padStart(2,'0') + ':00';
    var maxTime = String(endHour).padStart(2,'0') + ':00';
    timeRangeText.textContent = '(' + formatDisplayTime(minTime) + '-' + formatDisplayTime(maxTime) + ')';
  } else {
    timeRangeText.textContent = '';
  }
}

function populateDoctorLaboratoryOffers(doctorId) {
  // Get the current doctor's lab offers from the button data
  var doctorButtons = document.querySelectorAll('button[data-doctor]');
  var currentDoctorOffersData = [];
  
  doctorButtons.forEach(function(button) {
    var doctorData = JSON.parse(button.getAttribute('data-doctor'));
    if (doctorData.doctor_id == doctorId) {
      currentDoctorOffersData = doctorData.lab_offers_data || [];
    }
  });
  
  var laboratorySelect = document.getElementById('modal_laboratory');
  laboratorySelect.innerHTML = '<option value="">Select Laboratory Service</option>';
  
  if (currentDoctorOffersData.length > 0) {
    currentDoctorOffersData.forEach(function(offer) {
      var option = document.createElement('option');
      option.value = offer.title;
      option.setAttribute('data-price', offer.price);
      option.textContent = offer.title + ' - ₱' + parseFloat(offer.price).toFixed(2);
      laboratorySelect.appendChild(option);
    });
  } else {
    var option = document.createElement('option');
    option.value = "";
    option.textContent = "No laboratory services available";
    option.disabled = true;
    laboratorySelect.appendChild(option);
  }
}

// Function to update price display based on selection
function updatePriceDisplay(purpose, laboratoryService) {
  var priceAmount = document.getElementById('price_amount');
  var priceLabel = document.getElementById('price_label');
  var doctor = window._currentModalDoctor;
  
  if (!doctor) {
    priceAmount.textContent = '₱0.00';
    priceLabel.textContent = 'Doctor information not available';
    return;
  }
  
  if (purpose === 'consultation') {
    var consultationFee = parseFloat(doctor.consultation_fee || 0);
    priceAmount.textContent = '₱' + consultationFee.toFixed(2);
    priceLabel.textContent = 'Consultation Fee';
  } else if (purpose === 'laboratory') {
    if (laboratoryService) {
      // Get the price from the selected laboratory option
      var labSelect = document.getElementById('modal_laboratory');
      var selectedOption = labSelect.options[labSelect.selectedIndex];
      var labPrice = parseFloat(selectedOption.getAttribute('data-price') || 0);
      priceAmount.textContent = '₱' + labPrice.toFixed(2);
      priceLabel.textContent = 'Laboratory Fee - ' + laboratoryService;
    } else {
      priceAmount.textContent = '₱0.00';
      priceLabel.textContent = 'Please select a laboratory service';
    }
  } else {
    priceAmount.textContent = '₱0.00';
    priceLabel.textContent = 'Select purpose to see fee';
  }
}

function prefillFormData() {
  document.getElementById('modal_first_name').value = '<?php echo htmlspecialchars($appointment_data['first_name'] ?? ''); ?>';
  document.getElementById('modal_last_name').value = '<?php echo htmlspecialchars($appointment_data['last_name'] ?? ''); ?>';
  document.getElementById('modal_phone_number').value = '<?php echo htmlspecialchars($appointment_data['phone_number'] ?? ''); ?>';
  document.getElementById('modal_email').value = '<?php echo htmlspecialchars($appointment_data['email'] ?? ''); ?>';
  document.getElementById('modal_address').value = '<?php echo htmlspecialchars($appointment_data['address'] ?? ''); ?>';
  document.getElementById('modal_dob').value = '<?php echo htmlspecialchars($appointment_data['date_of_birth'] ?? ''); ?>';
  document.getElementById('modal_gender').value = '<?php echo htmlspecialchars($appointment_data['gender'] ?? ''); ?>';
  document.getElementById('modal_relationship').value = '<?php echo htmlspecialchars($appointment_data['relationship'] ?? 'self'); ?>';
  document.getElementById('modal_illness').value = '<?php echo htmlspecialchars($appointment_data['illness'] ?? ''); ?>';
  
  if (document.getElementById('modal_no_refund')) {
    var checkbox = document.getElementById('modal_no_refund');
    checkbox.checked = <?php echo isset($appointment_data['agreed_no_refund_policy']) ? 'true' : 'false'; ?>;
    
    // Update button state based on prefilled checkbox
    var submitBtn = document.getElementById('submit_appointment_btn');
    if (submitBtn) {
      submitBtn.disabled = !checkbox.checked;
    }
  }

  setTimeout(function() {
    var scheduleDay = '<?php echo htmlspecialchars($appointment_data['schedule_day'] ?? ''); ?>';
    var scheduleTime = '<?php echo htmlspecialchars($appointment_data['schedule_time'] ?? ''); ?>';
    var purpose = '<?php echo htmlspecialchars($appointment_data['purpose'] ?? ''); ?>';
    var laboratory = '<?php echo htmlspecialchars($appointment_data['laboratory'] ?? ''); ?>';
    
    if (scheduleDay) document.getElementById('modal_schedule_day').value = scheduleDay;
    if (scheduleTime) document.getElementById('modal_schedule_time').value = scheduleTime;
    if (purpose) {
      document.getElementById('modal_purpose').value = purpose;
      // Trigger change event to show/hide laboratory field
      var event = new Event('change');
      document.getElementById('modal_purpose').dispatchEvent(event);
    }
    if (laboratory) document.getElementById('modal_laboratory').value = laboratory;
  }, 100);
}

function normalizeTime(time) {
  if (!time) return '';
  var parts = time.split(':');
  return parts[0].padStart(2,'0') + ':' + parts[1].padStart(2,'0');
}

function timeToMinutes(time) {
  var parts = time.split(':');
  return parseInt(parts[0],10) * 60 + parseInt(parts[1],10);
}

function formatDisplayTime(hm) {
  if (!hm) return '';
  var parts = hm.split(':');
  var hr = parseInt(parts[0], 10);
  var min = parts[1].padStart(2, '0');
  var ampm = hr >= 12 ? 'PM' : 'AM';
  var displayHr = hr % 12 === 0 ? 12 : hr % 12;
  return displayHr + ':' + min + ampm;
}

// Initialize modal functionality
document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('appointmentModal');
  var closeBtn = modal.querySelector('.close');
  
  closeBtn.onclick = function() {
    modal.style.display = 'none';
  }
  
  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = 'none';
    }
  }
  
  // Handle No Refund checkbox change to toggle submit button
  var noRefundCheckbox = document.getElementById('modal_no_refund');
  var submitBtn = document.getElementById('submit_appointment_btn');
  if (noRefundCheckbox && submitBtn) {
    noRefundCheckbox.addEventListener('change', function() {
      submitBtn.disabled = !this.checked;
    });
  }

  // Handle Relationship change to fill account data for 'self'
  var relationshipSelect = document.getElementById('modal_relationship');
  if (relationshipSelect) {
    relationshipSelect.addEventListener('change', function() {
      if (this.value.toLowerCase() === 'self') {
        // Refill with account data only when selecting 'self'
        document.getElementById('modal_first_name').value = '<?php echo htmlspecialchars($user_details['first_name'] ?? ''); ?>';
        document.getElementById('modal_last_name').value = '<?php echo htmlspecialchars($user_details['last_name'] ?? ''); ?>';
        document.getElementById('modal_phone_number').value = '<?php echo htmlspecialchars($user_details['phone_number'] ?? ''); ?>';
        document.getElementById('modal_email').value = '<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>';
        document.getElementById('modal_address').value = '<?php echo htmlspecialchars($user_details['address'] ?? ''); ?>';
        document.getElementById('modal_dob').value = '<?php echo htmlspecialchars($user_details['date_of_birth'] ?? ''); ?>';
        document.getElementById('modal_gender').value = '<?php echo htmlspecialchars($user_details['gender'] ?? ''); ?>';
      }
      // We no longer clear fields for non-self selections. 
      // This allows users to keep shared information (like address/last name) or 
      // fix a dropdown mistake without losing their typed data.
    });
  }

  // Simple but effective form validation
  function validateForm(form) {
    var errors = [];
    var firstErrorField = null;
    
    // Clear previous errors
    form.querySelectorAll('.form-control').forEach(el => {
      el.style.borderColor = '#edf2f7';
      el.style.backgroundColor = '#fff';
    });
    form.querySelectorAll('.custom-error-msg').forEach(el => el.remove());

    // Helper to mark invalid fields
    function markInvalid(id, message) {
      const field = document.getElementById(id);
      if (field) {
        field.style.borderColor = '#f56565';
        field.style.backgroundColor = '#fff5f5';
        
        const errorMsg = document.createElement('div');
        errorMsg.className = 'custom-error-msg';
        errorMsg.style.color = '#e53e3e';
        errorMsg.style.fontSize = '0.75rem';
        errorMsg.style.marginTop = '0.25rem';
        errorMsg.style.fontWeight = '600';
        errorMsg.textContent = message;
        
        // Find wrapper to append error message
        const wrapper = field.closest('.input-icon-wrapper') || field.parentElement;
        wrapper.appendChild(errorMsg);
        
        if (!firstErrorField) firstErrorField = field;
        errors.push(message);
      }
    }

    // Required Field Checks
    if (!document.getElementById('modal_first_name').value.trim()) markInvalid('modal_first_name', 'First name is required');
    if (!document.getElementById('modal_last_name').value.trim()) markInvalid('modal_last_name', 'Last name is required');
    if (!document.getElementById('modal_phone_number').value.trim()) markInvalid('modal_phone_number', 'Phone number is required');
    
    const emailField = document.getElementById('modal_email');
    if (!emailField.value.trim()) {
      markInvalid('modal_email', 'Email address is required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
      markInvalid('modal_email', 'Please enter a valid email address');
    }

    if (!document.getElementById('modal_address').value.trim()) markInvalid('modal_address', 'Complete address is required');
    if (!document.getElementById('modal_dob').value) markInvalid('modal_dob', 'Date of birth is required');
    if (!document.getElementById('modal_gender').value) markInvalid('modal_gender', 'Please select a gender');
    if (!document.getElementById('modal_schedule_day').value) markInvalid('modal_schedule_day', 'Appointment date is required');
    if (!document.getElementById('modal_schedule_time').value) markInvalid('modal_schedule_time', 'Preferred time slot is required');
    if (!document.getElementById('modal_illness').value.trim()) markInvalid('modal_illness', 'Reason for visit is required');

    // Conditional Laboratory Checks
    const purpose = document.getElementById('modal_purpose').value;
    if (purpose === 'laboratory') {
      if (!document.getElementById('modal_laboratory').value) markInvalid('modal_laboratory', 'Please select a laboratory test');
      if (!document.getElementById('modal_laboratory_image').files.length) markInvalid('modal_laboratory_image', 'Laboratory request image is required');
    }

    if (!document.getElementById('modal_no_refund').checked) {
      markInvalid('modal_no_refund', 'You must agree to the No Refund Policy');
    }

    if (firstErrorField) {
      firstErrorField.focus();
      firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return errors.length === 0;
  }

  // Handle form submission to show notification
  var appointmentForm = document.querySelector('.appointment-form-grid');
  if (appointmentForm) {
    appointmentForm.addEventListener('submit', function(e) {
      e.preventDefault();

      // Run validation
      if (!validateForm(this)) {
        return false;
      }
      
      // Show loading state on button
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SUBMITTING...';
        submitBtn.style.opacity = '0.8';
        submitBtn.style.cursor = 'not-allowed';
      }
      
      // Show success notification
      showSuccessNotification();
      
      // Submit the form after a brief delay
      setTimeout(function() {
        appointmentForm.submit();
      }, 1500);
    });
  }
  
  // Handle purpose selection to show/hide laboratory dropdown
  var purposeSelect = document.getElementById('modal_purpose');
  var laboratoryRow = document.getElementById('laboratory_row');
  var laboratoryImageRow = document.getElementById('laboratory_image_row');
  var laboratorySelect = document.getElementById('modal_laboratory');
  var laboratoryImageInput = document.getElementById('modal_laboratory_image');
  var laboratoryImagePreview = document.getElementById('laboratory_image_preview');
  var laboratoryImagePreviewTag = document.getElementById('laboratory_image_preview_tag');

  function clearLaboratoryImagePreview() {
    if (laboratoryImagePreviewTag) {
      laboratoryImagePreviewTag.src = '';
    }
    if (laboratoryImagePreview) {
      laboratoryImagePreview.style.display = 'none';
    }
  }
  
  if (purposeSelect) {
    purposeSelect.addEventListener('change', function() {
      if (this.value === 'laboratory') {
        laboratoryRow.style.display = 'block';
        laboratoryImageRow.style.display = 'block';
        laboratorySelect.setAttribute('required', 'required');
        laboratoryImageInput.setAttribute('required', 'required');
        updatePriceDisplay('laboratory', laboratorySelect.value);
      } else {
        laboratoryRow.style.display = 'none';
        laboratoryImageRow.style.display = 'none';
        laboratorySelect.removeAttribute('required');
        laboratoryImageInput.removeAttribute('required');
        laboratorySelect.value = ''; // Clear selection
        laboratoryImageInput.value = '';
        clearLaboratoryImagePreview();
        updatePriceDisplay('consultation');
      }
    });
  }

  if (laboratoryImageInput) {
    laboratoryImageInput.addEventListener('change', function() {
      var file = this.files && this.files[0] ? this.files[0] : null;

      if (!file) {
        clearLaboratoryImagePreview();
        return;
      }

      if (!file.type || file.type.indexOf('image/') !== 0) {
        clearLaboratoryImagePreview();
        alert('Please select a valid image file.');
        this.value = '';
        return;
      }

      var reader = new FileReader();
      reader.onload = function(event) {
        if (laboratoryImagePreviewTag) {
          laboratoryImagePreviewTag.src = event.target.result;
        }
        if (laboratoryImagePreview) {
          laboratoryImagePreview.style.display = 'flex';
        }
      };
      reader.readAsDataURL(file);
    });
  }
  
  // Handle laboratory selection to update price
  if (laboratorySelect) {
    laboratorySelect.addEventListener('change', function() {
      var purpose = document.getElementById('modal_purpose').value;
      updatePriceDisplay(purpose, this.value);
    });
  }
  
  // Auto-open modal if there were form errors
  <?php if (!empty($appointment_errors) && !empty($appointment_data['doctor_id'])): ?>
  var errorDoctorId = <?php echo intval($appointment_data['doctor_id']); ?>;
  var doctorButtons = document.querySelectorAll('button[data-doctor]');
  doctorButtons.forEach(function(button) {
    var doctorData = JSON.parse(button.getAttribute('data-doctor'));
    if (doctorData.user_id == errorDoctorId) {
      openAppointmentModal(button);
    }
  });
  <?php endif; ?>
});

// Helper function for time formatting
function formatDisplayTime(hm) {
  if (!hm) return '';
  var parts = hm.split(':');
  var hr = parseInt(parts[0], 10);
  var min = parts[1].padStart(2, '0');
  var ampm = hr >= 12 ? 'PM' : 'AM';
  var displayHr = hr % 12 === 0 ? 12 : hr % 12;
  return displayHr + ':' + min + ampm;
}

// Function to show success notification
function showSuccessNotification() {
  // Create notification element
  var notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #3b82f6, #1e3a8a);
    color: white;
    padding: 1.2rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 350px;
    animation: slideInRight 0.3s ease-out;
  `;
  
  notification.innerHTML = `
    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
    <div style="flex: 1;">
      <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.3rem;">
        Appointment Request Sent!
      </div>
      <div style="font-size: 0.9rem; opacity: 0.95;">
        Please Complete your Payment
      </div>
    </div>
  `;
  
  // Add animation keyframes
  if (!document.getElementById('notification-styles')) {
    var style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
      @keyframes slideInRight {
        from {
          transform: translateX(400px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
      @keyframes slideOutRight {
        from {
          transform: translateX(0);
          opacity: 1;
        }
        to {
          transform: translateX(400px);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }
  
  document.body.appendChild(notification);
  
  // Auto remove after animation
  setTimeout(function() {
    notification.style.animation = 'slideOutRight 0.3s ease-in';
    setTimeout(function() {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 1200);
}
</script>

</body>
</html>
