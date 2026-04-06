<?php
$page_title = 'View User';
$additional_css = ['admin/sidebar.css', 'view-patient.css', 'shared-modal.css']; // Include patient management specific CSS
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get user ID
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: patients.php');
    exit;
}

// Get user data with additional information
$user = $db->fetch("
    SELECT u.*, 
           d.specialty, d.is_available,
           COALESCE(p.phone, u.phone) as phone, 
           COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth, 
           COALESCE(p.gender, u.gender) as gender
    FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ?", [$userId]);

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: patients.php');
    exit;
}

// Get user statistics
$stats = [];
if ($user['role'] === 'patient') {
    $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$userId])['count'];
    $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'", [$userId])['count'];
    $stats['cancelled_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'cancelled'", [$userId])['count'];
} elseif ($user['role'] === 'doctor') {
    $doctorId = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($doctorId) {
        $stats['total_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctorId])['count'];
        $stats['completed_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctorId])['count'];
        $stats['pending_appointments'] = $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'", [$doctorId])['count'];
    }
}

// Get recent activity (appointments)
$recentActivity = [];
if ($user['role'] === 'patient') {
    // Get patient_id from patients table using user_id
    $patientId = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($patientId) {
        $recentActivity = $db->fetchAll("
            SELECT a.*, 
                   du.first_name as doctor_first_name, du.last_name as doctor_last_name,
                   doc.specialty, doc.consultation_fee, doc.license_number,
                   pay.amount as payment_amount, pay.status as payment_status, 
                   pay.gcash_reference, pay.receipt_file
            FROM appointments a
            JOIN doctors doc ON a.doctor_id = doc.id
            JOIN users du ON doc.user_id = du.id
            LEFT JOIN payments pay ON a.id = pay.appointment_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10", [$patientId]);
            
        // Normalize patient info
        foreach ($recentActivity as &$appt) {
            if (!empty($appt['patient_info'])) {
                $decoded = json_decode($appt['patient_info'], true);
                if (is_array($decoded)) {
                    $appt['patient_dob'] = $decoded['date_of_birth'] ?? null;
                    $appt['patient_gender'] = $decoded['gender'] ?? null;
                    $appt['patient_address'] = $decoded['address'] ?? null;
                    $appt['patient_phone'] = $decoded['phone_number'] ?? null;
                    $appt['patient_email'] = $decoded['email'] ?? null;
                    $appt['patient_first_name'] = $decoded['first_name'] ?? null;
                    $appt['patient_last_name'] = $decoded['last_name'] ?? null;
                    $appt['illness'] = $decoded['illness'] ?? null;
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
    }
} elseif ($user['role'] === 'doctor') {
    $doctorId = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId])['id'] ?? 0;
    if ($doctorId) {
        $recentActivity = $db->fetchAll("
            SELECT a.*, 
                   pu.first_name as patient_first_name, pu.last_name as patient_last_name,
                   pu.email as patient_email,
                   COALESCE(p.phone, pu.phone) as patient_phone,
                   COALESCE(p.address, pu.address) as patient_address
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users pu ON p.user_id = pu.id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10", [$doctorId]);
    }
}

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
            <a href="patients.php" class="nav-item active">
                <i class="fas fa-users"></i> Patient Management
            </a>
            <a href="../Doctor Management/doctors.php" class="nav-item">
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

    <div class="admin-content">
        <?php 
        $viewMode = 'admin';
        // Ensure stats and recentActivity are prepared as expected by template
        include_once '../../includes/components/patient_details_template.php'; 
        ?>
    </div>
</div>

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
</style>

<script>
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            // Handle HH:mm:ss format
            const [hours, minutes] = timeString.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

function viewAppointment(appointment) {
    if (!appointment) return;
    
    // Standardize for shared renderer
    const standardizedData = {
        id: appointment.id,
        name: (appointment.patient_first_name + ' ' + (appointment.patient_last_name || '')),
        status: appointment.status,
        date: formatDate(appointment.appointment_date),
        time: formatTime(appointment.appointment_time),
        purpose: appointment.purpose === 'consultation' ? 'Medical Consultation' : (appointment.reason_for_visit || appointment.purpose),
        doctor: 'Dr. ' + appointment.doctor_first_name + ' ' + appointment.doctor_last_name,
        specialty: appointment.specialty,
        license: appointment.license_number,
        fee: parseFloat(appointment.display_fee || appointment.consultation_fee || 0).toFixed(2),
        relationship: appointment.relationship || 'Self',
        dob: appointment.patient_dob ? formatDate(appointment.patient_dob) : 'N/A',
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

function toggleUserStatus(userId) {
    if (confirm('Are you sure you want to change this user\'s status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'patients.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'patient_id';
        idInput.value = userId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeAptModal() {
    closeBaseModal();
}
</script>

</body>
</html>
