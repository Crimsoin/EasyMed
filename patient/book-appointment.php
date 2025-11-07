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
            <a href="reviews.php" class="nav-item">
                <i class="fas fa-star"></i> Reviews
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
													background: linear-gradient(135deg, #e3f6fc, #d1f0f8);
													color: #0891a5;
													padding: 0.3rem 0.8rem;
													border-radius: 15px;
													font-size: 0.8rem;
													font-weight: 600;
													margin: 0.2rem 0.3rem 0.2rem 0;
													border: 1px solid rgba(8, 145, 165, 0.2);
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
    <div class="modal-header" style="background:#e3f6fc; border-radius:8px 8px 0 0;">
      <h2 style="margin:0; font-size:1.3rem;">Appointment Request Form</h2>
      <p style="margin:0; font-size:1rem;">Submit your appointment request. The doctor will review and approve it.</p>
      <span class="close">&times;</span>
    </div>
    <div class="modal-body" style="padding:0;">
      <div class="appointment-flex" style="margin:0;">
        <div class="doctor-details-panel" id="modalDoctorPanel">
          <!-- Doctor details will be injected here -->
        </div>
        <div class="appointment-form-panel">
          <form method="post" action="process_appointment.php" class="appointment-form-grid" style="background:#fff; padding:1.5rem; border-radius:0 0 8px 8px;" novalidate>
            <input type="hidden" name="doctor_id" id="modal_doctor_id" value="">
            <div class="form-row">
              <div class="form-group">
                <label for="modal_first_name">First Name:</label>
                <input type="text" name="first_name" id="modal_first_name" class="form-control">
              </div>
              <div class="form-group">
                <label for="modal_last_name">Last Name:</label>
                <input type="text" name="last_name" id="modal_last_name" class="form-control">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_phone">Phone Number:</label>
                <input type="text" name="phone" id="modal_phone" class="form-control" placeholder="+63 912 345 6789">
              </div>
              <div class="form-group">
                <label for="modal_email">Email:</label>
                <input type="text" name="email" id="modal_email" class="form-control" placeholder="example@example.com">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_schedule_day">Choose Schedule Day:</label>
                <!-- Native date picker -->
                <input type="date" name="schedule_day" id="modal_schedule_day" class="form-control" />
              </div>
              <div class="form-group">
                <label for="modal_schedule_time">Set Time:</label>
                <select name="schedule_time" id="modal_schedule_time" class="form-control">
                  <option value="">Select Time</option>
                </select>
                <small id="modal_time_range" style="color:#666; display:block; margin-top:0.3rem; font-size:0.9rem;"></small>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="modal_purpose">Purpose:</label>
                <select name="purpose" id="modal_purpose" class="form-control" required>
                  <option value="consultation">Consultation</option>
                  <option value="laboratory">Laboratory</option>
                </select>
              </div>
            </div>
            <div class="form-row" id="laboratory_row" style="display: none;">
              <div class="form-group">
                <label for="modal_laboratory">Laboratory Service:</label>
                <select name="laboratory" id="modal_laboratory" class="form-control">
                  <option value="">Select Laboratory Service</option>
                  <!-- Options will be populated dynamically based on selected doctor -->
                </select>
              </div>
            </div>
            <!-- Price Display Section -->
            <div class="form-row" style="margin-top: 1rem; margin-bottom: 1rem;">
              <div id="price_display" style="
                width: 100%;
                background: linear-gradient(135deg, #e3f6fc 0%, #d1f0f8 100%);
                border: 2px solid #00b4cc;
                border-radius: 12px;
                padding: 1.2rem 1.5rem;
                text-align: center;
                box-shadow: 0 4px 10px rgba(0, 180, 204, 0.15);
              ">
                <div style="font-size: 0.95rem; color: #2c5563; font-weight: 600; margin-bottom: 0.3rem;">
                  <i class="fas fa-money-bill-wave"></i> Appointment Fee
                </div>
                <div id="price_amount" style="font-size: 1.8rem; color: #0891a5; font-weight: 700;">
                  ₱0.00
                </div>
                <div id="price_label" style="font-size: 0.85rem; color: #666; margin-top: 0.3rem;">
                  Select purpose to see fee
                </div>
              </div>
            </div>
            <div class="form-row">
              <button type="submit" class="btn btn-primary" style="width: 200px;">SUBMIT REQUEST</button>
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
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    margin: 0;
}

#appointmentModal .modal-header {
    background: linear-gradient(135deg, #e3f6fc 0%, #d1f0f8 100%);
    border-bottom: 1px solid #e0e0e0;
    padding: 1.5rem 2rem;
    text-align: center;
}

#appointmentModal .modal-header h2 {
    color: #2c5563;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

#appointmentModal .modal-header p {
    color: #666;
    font-size: 0.95rem;
}

.appointment-flex {
    display: flex;
    gap: 0;
    height: 700px;
    max-height: 700px;
}

.doctor-details-panel {
    background: linear-gradient(135deg, #b3e6fa 0%, #9dd9f3 100%);
    width: 300px;
    min-width: 280px;
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-sizing: border-box;
    text-align: center;
    position: relative;
}

.doctor-details-panel::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 1px;
    height: 100%;
    background: linear-gradient(to bottom, transparent 0%, #90d0f0 20%, #90d0f0 80%, transparent 100%);
}

.doctor-details-panel .avatar-wrap {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.2rem;
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
  border: 3px solid rgba(255,255,255,0.8);
}

/* ensure inner wrapper centers its contents */
.doctor-details-panel .doctor-card-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.doctor-details-panel .avatar-wrap img {
  display: block;
  width: 82px;
  height: 82px;
  object-fit: cover;
  border-radius: 50%;
}

.doctor-details-panel .doctor-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1a4852;
    margin-bottom: 0.4rem;
    line-height: 1.2;
}

.doctor-details-panel .doctor-specialty {
    font-size: 1.1rem;
    color: #0891a5;
    font-weight: 600;
    margin-bottom: 1.8rem;
    padding: 0.3rem 0.8rem;
    background: rgba(255,255,255,0.6);
    border-radius: 20px;
    display: inline-block;
}

.doctor-details-panel .doctor-schedule-list {
  list-style: none;
  padding: 0 0 0 70px;
  width: 100%;
}

.doctor-details-panel .doctor-schedule-list li {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    margin-bottom: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1a4852;
    padding: 0.3rem 0;
}

.doctor-details-panel .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #1a4852;
    margin-right: 15px;
    flex-shrink: 0;
}

.doctor-details-panel .doctor-time {
    font-size: 0.9rem;
    color: #1a4852;
    font-weight: 600;
    margin-top: 0.5rem;
    background: rgba(255,255,255,0.5);
    padding: 0.5rem 1rem;
    border-radius: 15px;
    display: inline-block;
}

.appointment-form-panel {
    flex: 1;
    background: #fff;
    box-sizing: border-box;
    min-width: 400px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    max-height: 690px;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Custom scrollbar styling */
.appointment-form-panel::-webkit-scrollbar {
    width: 8px;
}

.appointment-form-panel::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.appointment-form-panel::-webkit-scrollbar-thumb {
    background: #00b4cc;
    border-radius: 10px;
}

.appointment-form-panel::-webkit-scrollbar-thumb:hover {
    background: #0891a5;
}

.appointment-form-grid {
    display: flex;
    flex-direction: column;
    padding: 2rem;
    max-width: 100%;
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.form-row:last-child {
    margin-top: 1.5rem;
    margin-bottom: 0;
    justify-content: center;
}

/* Single column form rows should stretch full width */
#laboratory_row {
    width: 100%;
}

#laboratory_row .form-group {
    width: 100%;
}

.form-group {
    flex: 1;
    min-width: 0; /* Prevents flex items from overflowing */
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
    color: #2c5563;
    font-size: 0.95rem;
}

.form-group input, 
.form-group select {
    width: 100%;
    padding: 0.8rem 1rem;
    border-radius: 8px;
    border: 2px solid #e1e5e9;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    background: #fff;
    box-sizing: border-box;
}

.form-group input:focus, 
.form-group select:focus {
    outline: none;
    border-color: #00b4cc;
    box-shadow: 0 0 0 3px rgba(0, 180, 204, 0.1);
}

.form-group small {
    color: #888;
    font-size: 0.8rem;
    margin-top: 0.3rem;
    display: block;
}

.btn.btn-primary {
    background: linear-gradient(135deg, #00b4cc 0%, #0891a5 100%);
    border: none;
    padding: 0.9rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(0, 180, 204, 0.3);
}

.btn.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 180, 204, 0.4);
}

/* Responsive Design */
@media (max-width: 768px) {
    .appointment-flex {
        flex-direction: column;
        min-height: auto;
    }
    
    .doctor-details-panel {
        width: 100%;
        min-width: auto;
        padding: 1.5rem;
    }
    
    .doctor-details-panel::after {
        display: none;
    }
    
    .appointment-form-panel {
        min-width: auto;
    }
    
    .form-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .appointment-form-grid {
        padding: 1.5rem;
    }
    
    #appointmentModal .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
}
</style>

<script>
function openAppointmentModal(buttonElement) {
  var doctor = JSON.parse(buttonElement.getAttribute('data-doctor'));
  
  // Fill doctor details panel
  var panelHtml = '<div class="doctor-card-inner">';
  if (doctor) {
    panelHtml += '<div class="avatar-wrap">';
    if (doctor.profile_image) {
      panelHtml += '<img src="<?php echo SITE_URL; ?>/assets/images/' + doctor.profile_image + '" alt="Dr. ' + doctor.first_name + ' ' + doctor.last_name + '" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
    } else {
      panelHtml += '<i class="fas fa-user-md" aria-hidden="true" style="font-size:36px; color:#01707a;"></i>';
    }
    panelHtml += '</div>';
    panelHtml += '<div class="doctor-name">Dr. ' + doctor.first_name + ' ' + doctor.last_name + '</div>';
    panelHtml += '<div class="doctor-specialty">' + doctor.specialty + '</div>';
    
    if (doctor.schedule_days) {
      var days = doctor.schedule_days.split(',');
      panelHtml += '<ul class="doctor-schedule-list" aria-label="Doctor schedule days">';
      days.forEach(function(d) {
        panelHtml += '<li><span class="dot"></span>' + d.trim().toUpperCase() + '</li>';
      });
      panelHtml += '</ul>';
    }
    
    if (doctor.schedule_time_start || doctor.schedule_time_end) {
      var dispStart = formatDisplayTime(normalizeTime(doctor.schedule_time_start));
      var dispEnd = formatDisplayTime(normalizeTime(doctor.schedule_time_end));
      panelHtml += '<div class="doctor-time">(' + dispStart + '-' + dispEnd + ')</div>';
    }
  } else {
    panelHtml += '<div style="text-align:center; color:#888;">Doctor details not found.</div>';
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
        this.style.borderColor = '#00b4cc';
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
  <?php if (!empty($appointment_data)): ?>
  document.getElementById('modal_first_name').value = '<?php echo htmlspecialchars($appointment_data['first_name'] ?? ''); ?>';
  document.getElementById('modal_last_name').value = '<?php echo htmlspecialchars($appointment_data['last_name'] ?? ''); ?>';
  document.getElementById('modal_phone').value = '<?php echo htmlspecialchars($appointment_data['phone'] ?? ''); ?>';
  document.getElementById('modal_email').value = '<?php echo htmlspecialchars($appointment_data['email'] ?? ''); ?>';
  
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
  <?php endif; ?>
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
  
  // Handle form submission to show notification
  var appointmentForm = document.querySelector('.appointment-form-grid');
  if (appointmentForm) {
    appointmentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
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
  var laboratorySelect = document.getElementById('modal_laboratory');
  
  if (purposeSelect) {
    purposeSelect.addEventListener('change', function() {
      if (this.value === 'laboratory') {
        laboratoryRow.style.display = 'block';
        laboratorySelect.setAttribute('required', 'required');
        updatePriceDisplay('laboratory', laboratorySelect.value);
      } else {
        laboratoryRow.style.display = 'none';
        laboratorySelect.removeAttribute('required');
        laboratorySelect.value = ''; // Clear selection
        updatePriceDisplay('consultation');
      }
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
    background: linear-gradient(135deg, #10b981, #059669);
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

<?php require_once '../includes/footer.php'; ?>
