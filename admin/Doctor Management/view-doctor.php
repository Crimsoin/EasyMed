<?php
$page_title = "View Doctor Profile";
$additional_css = ['admin/sidebar.css', 'admin/view-doctor-profile.css', 'shared-modal.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$doctor_id) {
    header('Location: doctors.php');
    exit();
}

// Get doctor information
$doctor = $db->fetch("
    SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image, u.is_active, u.created_at,
           d.id as doctor_id, d.specialty, d.license_number, d.experience_years, 
           d.consultation_fee, d.schedule_days, d.schedule_time_start, d.schedule_time_end, 
           d.is_available, d.biography, d.phone,
           p.date_of_birth, p.gender
    FROM users u 
    JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ? AND u.role = 'doctor'
", [$doctor_id]);

if (!$doctor) {
    header('Location: doctors.php');
    exit();
}

// Get doctor statistics
$stats = [
    'total_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)", [$doctor_id])['count'],
    'completed_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status = 'completed'", [$doctor_id])['count'],
    'pending_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status IN ('scheduled', 'confirmed')", [$doctor_id])['count'],
    'cancelled_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status = 'cancelled'", [$doctor_id])['count']
];

// Get consultation history (all appointments)
$recent_appointments = $db->fetchAll("
    SELECT a.*, 
           up.first_name as patient_first_name, up.last_name as patient_last_name,
           up.email as patient_email,
           COALESCE(p.phone, up.phone) as patient_phone,
           COALESCE(p.address, up.address) as patient_address,
           COALESCE(p.date_of_birth, up.date_of_birth) as patient_dob,
           COALESCE(p.gender, up.gender) as patient_gender,
           du.first_name as doctor_first_name, du.last_name as doctor_last_name,
           d.specialty, d.consultation_fee, d.license_number,
           pay.amount as payment_amount, pay.status as payment_status, 
           pay.gcash_reference, pay.receipt_file
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users up ON p.user_id = up.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users du ON d.user_id = du.id
    LEFT JOIN payments pay ON a.id = pay.appointment_id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$doctor['doctor_id']]);

// Normalize patient info and doctor info
foreach ($recent_appointments as &$appt) {
    if (!empty($appt['patient_info'])) {
        $decoded = json_decode($appt['patient_info'], true);
        if (is_array($decoded)) {
            $appt['patient_dob'] = $decoded['date_of_birth'] ?? $appt['patient_dob'];
            $appt['patient_gender'] = $decoded['gender'] ?? $appt['patient_gender'];
            $appt['patient_address'] = $decoded['address'] ?? $appt['patient_address'];
            $appt['patient_phone'] = $decoded['phone_number'] ?? $appt['patient_phone'];
            $appt['patient_email'] = $decoded['email'] ?? $appt['patient_email'];
            $appt['patient_first_name'] = $decoded['first_name'] ?? $appt['patient_first_name'];
            $appt['patient_last_name'] = $decoded['last_name'] ?? $appt['patient_last_name'];
            $appt['illness'] = $decoded['illness'] ?? $appt['illness'];
            $appt['purpose'] = $decoded['purpose'] ?? null;
            $appt['relationship'] = $decoded['relationship'] ?? 'Self';
            $appt['laboratory_image'] = $decoded['laboratory_image'] ?? null;
        }
    }
    if (!empty($appt['receipt_file'])) {
        $appt['receipt_path'] = 'assets/uploads/payment_receipts/' . $appt['receipt_file'];
    }
}
unset($appt);

// Get average rating
$rating_data = $db->fetch("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
", [$doctor_id]);

$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_reviews = $rating_data['total_reviews'];

// Get laboratory offers
$lab_offers = $db->fetchAll("
    SELECT lo.* 
    FROM lab_offers lo
    JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
    JOIN doctors d ON lod.doctor_id = d.id
    WHERE d.user_id = ?
    ORDER BY lo.title ASC
", [$doctor_id]);

require_once '../../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="../Dashboard/dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="../Patient Management/patients.php" class="nav-item">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="doctors.php" class="nav-item active">
                <i class="fas fa-user-md"></i> Doctor Management
            </a>
            <a href="../Feedbacks/feedback_admin.php" class="nav-item">
                <i class="fas fa-star"></i> Feedbacks
            </a>
            <a href="../Settings/settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <div class="admin-content" style="background-color: #f8fafc; padding: 2rem;">
        <?php 
        $viewMode = 'admin';
        include_once '../../includes/components/doctor_details_template.php'; 
        ?>
    </div>
</div>

<!-- Status Toggle Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Confirm Action</h3>
        <p id="statusModalText"></p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeStatusModal()">Cancel</button>
            <form id="statusForm" method="POST" action="doctors.php" style="display: inline;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="doctor_id" id="doctorIdInput">
                <button type="submit" class="btn-confirm">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDoctorStatus(doctorId) {
    const isActive = <?php echo $doctor['is_active'] ? 'true' : 'false'; ?>;
    const actionText = isActive ? 'deactivate' : 'activate';
    const doctorName = "<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>";
    
    document.getElementById('statusModalText').textContent = 
        `Are you sure you want to ${actionText} Dr. ${doctorName}?`;
    document.getElementById('doctorIdInput').value = doctorId;
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking X
document.querySelector('.close').onclick = function() {
    closeStatusModal();
}
</script>

<!-- Appointment Details Modal -->
<?php include_once '../../includes/shared_appointment_details.php'; ?>

<style>
.appointment-item.clickable {
    cursor: pointer;
    transition: all 0.2s ease;
}

.appointment-item.clickable:hover {
    background: #f0f7ff;
    border-left-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}

.close-modal {
    font-size: 1.5rem;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #1e293b;
}
</style>

<script>
function viewAppointment(appointment) {
    if (!appointment) return;
    
    // Standardize for shared renderer
    const standardizedData = {
        id: appointment.id,
        name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
        status: appointment.status,
        date: formatDateJS(appointment.appointment_date),
        time: formatTimeJS(appointment.appointment_time),
        purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
        doctor: 'Dr. ' + appointment.doctor_first_name + ' ' + appointment.doctor_last_name,
        specialty: appointment.specialty,
        license: appointment.license_number,
        fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
        relationship: appointment.relationship || 'Self',
        dob: appointment.patient_dob ? formatDateJS(appointment.patient_dob) : 'N/A',
        gender: appointment.patient_gender,
        email: appointment.patient_email,
        phone: appointment.patient_phone,
        address: appointment.patient_address,
        reason: appointment.illness || appointment.reason_for_visit,
        notes: appointment.notes,
        payment: appointment.payment_amount ? {
            amount: parseFloat(appointment.payment_amount).toFixed(2),
            status: appointment.payment_status,
            ref: appointment.gcash_reference,
            receipt: appointment.receipt_path
        } : null,
        laboratory_image: appointment.laboratory_image,
        reschedule_reason: appointment.reschedule_reason,
        updated_at: appointment.updated_at
    };
    
    showAppointmentOverview(standardizedData, 'admin');
}

function formatDateJS(dateString) { return formatDateModal(dateString); }
function formatTimeJS(timeString) { return formatTimeModal(timeString); }
function calculateAgeJS(dob) { return calculateAgeModal(dob); }
function closeAptModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}
</script>

</body>
</html>
