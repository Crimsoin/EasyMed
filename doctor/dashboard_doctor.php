<?php
$page_title = "Doctor Dashboard";
$additional_css = ['doctor/sidebar-doctor.css', 'doctor/dashboard-doctor.css', 'shared-modal.css'];

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();

// Resolve doctor record id (doctors.id) from logged in user
$doctor_user_id = $_SESSION['user_id'];
$doctor_record = $db->fetch("SELECT id, specialty FROM doctors WHERE user_id = ?", [$doctor_user_id]);
if (!$doctor_record) {
    die("Doctor profile not found.");
}
$doctor_id = $doctor_record['id'];
$doctor_specialty = $doctor_record['specialty'] ?? 'Medical Practitioner';

// Handle appointment status updates
if ($_POST && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    
    // Get appointment and patient details before updating
    $appointment_details = $db->fetch("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ?", [$appointment_id, $doctor_id]);
    
    if ($appointment_details) {
        switch ($action) {
            case 'complete':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', [
                    'status' => 'completed',
                    'notes' => $notes
                ], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as completed successfully.";
                // Refresh data to reflect status change
                header("Location: dashboard_doctor.php?success=" . urlencode($success_message));
                exit();
            case 'no_show':
                $db->update('appointments', ['status' => 'no_show'], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as No Show.";
                header("Location: dashboard_doctor.php?success=" . urlencode($success_message));
                exit();
            case 'update_findings':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', ['notes' => $notes], 'id = ?', [$appointment_id]);
                $success_message = "Appointment findings updated successfully.";
                break;
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}


// Get doctor's appointments for today
$today_appointments = $db->fetchAll("
    SELECT a.*,
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           u.email as patient_email, 
           p.phone as patient_phone, p.date_of_birth as patient_dob, p.address as patient_address,
           a.patient_info,
           pay.status as payment_status, pay.amount as payment_amount, pay.gcash_reference, pay.receipt_file
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN payments pay ON a.id = pay.appointment_id
    WHERE a.doctor_id = ? AND date(a.appointment_date) = date('now')
    ORDER BY a.appointment_time ASC
", [$doctor_id]);

// Get upcoming appointments (next 7 days)
$upcoming_appointments = $db->fetchAll("
    SELECT a.*,
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           u.email as patient_email, 
           p.phone as patient_phone, p.date_of_birth as patient_dob, p.address as patient_address,
           a.patient_info,
           pay.status as payment_status, pay.amount as payment_amount, pay.gcash_reference, pay.receipt_file
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN payments pay ON a.id = pay.appointment_id
    WHERE a.doctor_id = ?
    AND date(a.appointment_date) > date('now')
    AND date(a.appointment_date) <= date('now', '+7 days')
    AND a.status IN ('scheduled', 'confirmed', 'pending', 'rescheduled')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 10
", [$doctor_id]);

// Normalize appointment arrays to include a safe 'reason' key (avoid undefined index warnings)
foreach ($today_appointments as &$appt) {
    if (!empty($appt['patient_info'])) {
        $decoded = json_decode($appt['patient_info'], true);
        if (is_array($decoded)) {
             $appt['patient_dob'] = $appt['patient_dob'] ?? $decoded['date_of_birth'] ?? null;
             $appt['patient_gender'] = $appt['patient_gender'] ?? $decoded['gender'] ?? 'N/A';
             $appt['address'] = $appt['address'] ?? $decoded['address'] ?? $appt['patient_address'] ?? 'N/A';
        }
    }
    $appt['reason'] = !empty($appt['illness']) ? $appt['illness'] : ($appt['reason_for_visit'] ?? 'Consultation');
    $appt['doctor_first_name'] = $_SESSION['first_name'];
    $appt['doctor_last_name'] = $_SESSION['last_name'];
    $appt['doctor_specialty'] = $doctor_specialty;
    
    // Add receipt_path if receipt_file exists
    if (!empty($appt['receipt_file'])) {
        $appt['receipt_path'] = 'assets/uploads/payment_receipts/' . $appt['receipt_file'];
    } else {
        $appt['receipt_path'] = null;
    }
}
unset($appt);

foreach ($upcoming_appointments as &$appt) {
    if (!empty($appt['patient_info'])) {
        $decoded = json_decode($appt['patient_info'], true);
        if (is_array($decoded)) {
             $appt['patient_dob'] = $appt['patient_dob'] ?? $decoded['date_of_birth'] ?? null;
             $appt['patient_gender'] = $appt['patient_gender'] ?? $decoded['gender'] ?? 'N/A';
             $appt['address'] = $appt['address'] ?? $decoded['address'] ?? $appt['patient_address'] ?? 'N/A';
        }
    }
    $appt['reason'] = !empty($appt['illness']) ? $appt['illness'] : ($appt['reason_for_visit'] ?? 'Consultation');
    $appt['doctor_first_name'] = $_SESSION['first_name'];
    $appt['doctor_last_name'] = $_SESSION['last_name'];
    $appt['doctor_specialty'] = $doctor_specialty;
    
    // Add receipt_path if receipt_file exists
    if (!empty($appt['receipt_file'])) {
        $appt['receipt_path'] = 'assets/uploads/payment_receipts/' . $appt['receipt_file'];
    } else {
        $appt['receipt_path'] = null;
    }
}
unset($appt);

// Get statistics
$stats = [
    'today' => count($today_appointments),
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'", [$doctor_id])['count'],
    'this_week' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND date(appointment_date) > date('now') AND date(appointment_date) <= date('now', '+7 days')", [$doctor_id])['count'],
    'total_patients' => $db->fetch("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?", [$doctor_id])['count']
];

require_once '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
            <p>Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_doctor.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="schedule.php" class="nav-item">
                <i class="fas fa-clock"></i> Schedule
            </a>
            <a href="patients.php" class="nav-item">
                <i class="fas fa-users"></i> My Patients
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        </nav>
    </div>

    <div class="doctor-content">
        <div class="content-header">
            <h1><i class="fas fa-home"></i> Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <p>Here's your practice overview for today</p>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background-color: #dcfce7; color: #15803d; border-radius: 8px;">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
            
            <!-- Premium Real-time Clock Dashboard -->
            <div class="datetime-display" style="
                margin: 1.5rem 0; 
                padding: 1.5rem 2rem; 
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.03) 100%); 
                border-radius: 16px; 
                border: 1px solid rgba(37, 99, 235, 0.15);
                box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.05);
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: relative;
                overflow: hidden;">
                
                <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                    <div style="
                        width: 60px; 
                        height: 60px; 
                        background: white; 
                        border-radius: 14px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.1);
                        color: var(--primary-cyan);
                        font-size: 1.75rem;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.02em;" id="current-date"></div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                padding: 4px 10px;
                                background: #dcfce7;
                                color: #166534;
                                border-radius: 20px;
                                font-size: 0.7rem;
                                font-weight: 800;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;">
                                <span style="width: 6px; height: 6px; background: #166534; border-radius: 50%; display: inline-block; animation: pulse 2s infinite;"></span>
                                Live System Time
                            </span>
                            <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary-cyan); font-family: 'JetBrains Mono', 'Courier New', monospace;" id="current-time"></div>
                        </div>
                    </div>
                </div>

                <!-- Abstract Decorative Element -->
                <div style="position: absolute; right: -20px; top: -20px; opacity: 0.03; font-size: 8rem; pointer-events: none;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>

            <style>
            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.5); opacity: 0.5; }
                100% { transform: scale(1); opacity: 1; }
            }
            #current-time { transition: all 0.2s ease; }
            </style>
        </div>

        <!-- Quick Stats -->
        <div class="content-section">
            <div class="section-header">
                <h2>Today's Overview</h2>
            </div>
            <div class="section-content stats-content">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['today']; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['this_week']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="content-section">
            <div class="section-header">
                <h2>Today's Schedule - <?php echo formatDate(date('Y-m-d')); ?></h2>
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-calendar"></i> View All Appointments
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($today_appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-check"></i>
                        <h3>No appointments today</h3>
                        <p>You have a free day! Enjoy your time off.</p>
                    </div>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($today_appointments as $appointment): ?>
                            <?php 
                                $appt_json = $p_info = [];
                                    if (!empty($appointment['patient_info'])) $p_info = json_decode($appointment['patient_info'], true);
                                    $appt_json = htmlspecialchars(json_encode([
                                        'name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                        'account_name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                        'date' => formatDate($appointment['appointment_date']),
                                        'time' => formatTime($appointment['appointment_time']),
                                        'email' => $appointment['patient_email'] ?? 'N/A',
                                        'phone' => $appointment['patient_phone'] ?? 'N/A',
                                        'address' => $appointment['address'] ?? 'N/A',
                                        'gender' => ucfirst($appointment['patient_gender'] ?? 'N/A'),
                                        'age' => !empty($appointment['patient_dob']) ? calculateAge($appointment['patient_dob']) : 'N/A',
                                        'reason' => $appointment['reason'],
                                        'purpose' => ucfirst($appointment['purpose'] ?? 'Consultation'),
                                        'relationship' => ucfirst($appointment['relationship'] ?? 'Self'),
                                        'status' => ucfirst($appointment['status']),
                                        'id' => $appointment['id'],
                                        'notes' => $appointment['notes'] ?? '',
                                        'can_complete' => in_array(strtolower($appointment['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                        'can_no_show' => in_array(strtolower($appointment['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                        'can_add_findings' => strtolower($appointment['status']) === 'completed',
                                        'doctor_first_name' => $appointment['doctor_first_name'],
                                        'doctor_last_name' => $appointment['doctor_last_name'],
                                        'specialty' => $appointment['doctor_specialty'],
                                        'payment_status' => $appointment['payment_status'] ?? 'PENDING',
                                        'payment_amount' => $appointment['payment_amount'] ?? 0,
                                        'gcash_reference' => $appointment['gcash_reference'] ?? 'N/A',
                                        'receipt_path' => $appointment['receipt_path'] ?? null
                                    ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="appointment-card" onclick="showAppointmentDetails(<?php echo $appt_json; ?>)" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                <div class="appointment-info">
                                    <div class="patient-info">
                                        <h4><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                    </div>
                                    <div class="appointment-details">
                                        <div class="date-time">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?>
                                        </div>
                                        <div class="reason">
                                            <i class="fas fa-clipboard"></i>
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </div>
                                        <?php if (!empty($appointment['notes']) && $appointment['status'] === 'completed'): ?>
                                        <div class="reason" style="margin-top: 5px; color: #1e40af; background: #eff6ff; padding: 8px; border-radius: 4px; border-left: 3px solid #3b82f6;">
                                            <i class="fas fa-file-medical"></i> <strong>Findings:</strong><br>
                                            <span style="white-space: pre-wrap; display: block; margin-top: 4px;"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="content-section">
            <div class="section-header">
                <h2>Upcoming Appointments</h2>
                <small style="color: #666; font-weight: normal;">Next 7 days (excluding today)</small>
            </div>
            <div class="section-content">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No upcoming appointments</h3>
                        <p>Your schedule is clear for the next week.</p>
                        <?php
                        // Debug information (uncomment to troubleshoot)
                        /*
                        <p style="font-size: 12px; color: #999; margin-top: 10px;">
                            Debug: Doctor ID = <?php echo $doctor_id; ?><br>
                            Total appointments for this doctor: <?php echo $stats['total_patients']; ?><br>
                            Today's date: <?php echo date('Y-m-d'); ?><br>
                            Next week: <?php echo date('Y-m-d', strtotime('+7 days')); ?>
                        </p>
                        */
                        ?>
                    </div>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <?php 
                                $appt_json = $p_info = [];
                                    if (!empty($appointment['patient_info'])) $p_info = json_decode($appointment['patient_info'], true);
                                    $appt_json = htmlspecialchars(json_encode([
                                        'name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                        'account_name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                        'date' => formatDate($appointment['appointment_date']),
                                        'time' => formatTime($appointment['appointment_time']),
                                        'email' => $appointment['patient_email'] ?? 'N/A',
                                        'phone' => $appointment['patient_phone'] ?? 'N/A',
                                        'address' => $appointment['address'] ?? 'N/A',
                                        'gender' => ucfirst($appointment['patient_gender'] ?? 'N/A'),
                                        'age' => !empty($appointment['patient_dob']) ? calculateAge($appointment['patient_dob']) : 'N/A',
                                        'reason' => $appointment['reason'],
                                        'purpose' => ucfirst($appointment['purpose'] ?? 'Consultation'),
                                        'relationship' => ucfirst($appointment['relationship'] ?? 'Self'),
                                        'status' => ucfirst($appointment['status']),
                                        'id' => $appointment['id'],
                                        'notes' => $appointment['notes'] ?? '',
                                        'can_complete' => in_array(strtolower($appointment['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                        'can_no_show' => in_array(strtolower($appointment['status']), ['scheduled', 'ongoing', 'confirmed', 'pending']),
                                        'can_add_findings' => strtolower($appointment['status']) === 'completed',
                                        'doctor_first_name' => $appointment['doctor_first_name'],
                                        'doctor_last_name' => $appointment['doctor_last_name'],
                                        'specialty' => $appointment['doctor_specialty'],
                                        'payment_status' => $appointment['payment_status'] ?? 'PENDING',
                                        'payment_amount' => $appointment['payment_amount'] ?? 0,
                                        'gcash_reference' => $appointment['gcash_reference'] ?? 'N/A',
                                        'receipt_path' => $appointment['receipt_path'] ?? null
                                    ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="appointment-card" onclick="showAppointmentDetails(<?php echo $appt_json; ?>)" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                <div class="appointment-info">
                                    <div class="patient-info">
                                        <h4><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                    </div>
                                    <div class="appointment-details">
                                        <div class="date-time">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?>
                                        </div>
                                        <div class="reason">
                                            <i class="fas fa-clipboard"></i>
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </div>
                                        <?php if (!empty($appointment['notes']) && $appointment['status'] === 'completed'): ?>
                                        <div class="reason" style="margin-top: 5px; color: #1e40af; background: #eff6ff; padding: 8px; border-radius: 4px; border-left: 3px solid #3b82f6;">
                                            <i class="fas fa-file-medical"></i> <strong>Findings:</strong><br>
                                            <span style="white-space: pre-wrap; display: block; margin-top: 4px;"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </div>
</div>

<script>
// Update Live Clock
function updateLiveClock() {
    const now = new Date();
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (!dateElement || !timeElement) return;

    // Format date
    dateElement.textContent = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Format time
    timeElement.textContent = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
}

// Update immediately and then every second
updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%; max-height: 90vh; overflow-y: auto; border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; padding: 24px 40px; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-file-medical"></i> Appointment Overview
            </h3>
            <span class="close-modal" onclick="closeModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.5rem; cursor: pointer; transition: all 0.2s;"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="modalContent" style="padding: 0; background: #fdfdfd;">
            <!-- Content injected by JavaScript -->
        </div>
        <div class="modal-footer" id="modalFooter" style="background: #f8fafc; border-top: 1px solid #edf2f7; padding: 24px 40px; border-radius: 0 0 20px 20px; display: flex; gap: 12px; align-items: center; justify-content: flex-end;">
            <!-- Buttons injected by JavaScript -->
        </div>
    </div>
</div>

<div id="findingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Final Findings</h3>
            <span class="close-modal" onclick="closeFindingsModal()"><i class="fas fa-times"></i></span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_findings">
                <input type="hidden" name="appointment_id" id="findingsAptId">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-pen"></i> Doctor's Notes & Findings</div>
                    <textarea name="notes" id="findingsNotesArea" class="findings-textarea" placeholder="Enter patient diagnosis, prescriptions, or summary here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeFindingsModal()">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary">Save Findings</button>
            </div>
        </form>
    </div>
</div>

<script>

function showAppointmentDetails(data) {
    const modal = document.getElementById('appointmentModal');
    const content = document.getElementById('modalContent');
    const footer = document.getElementById('modalFooter');
    
    // Helper for initials
    const initials = data.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    
    content.innerHTML = `
        <div class="appointment-details-premium" style="background: #fdfdfd; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif;">
            
            <!-- 1. Patient Hero Banner -->
            <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 32px 40px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 24px;">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.75rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);">
                        ${initials}
                    </div>
                    <div>
                        <h1 style="color: #0f172a; font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.04em;">${data.name}</h1>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                            <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">#APT-${data.id.toString().padStart(5, '0')}</span></span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span class="status-badge status-${data.status.toLowerCase()}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">${data.status.toUpperCase()}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding: 40px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px;">
                
                <!-- 2. Appointment Schedule Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar-alt" style="color: white; font-size: 0.9rem;"></i> Core Schedule
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Date</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.date}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Time Slot</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.time}</div>
                        </div>
                        <div style="grid-column: span 2;">
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Service Requested</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-stethoscope" style="color: #cbd5e1; font-size: 0.9rem;"></i>
                                ${data.purpose}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Expert Consultation Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-md" style="color: white; font-size: 0.9rem;"></i> Medical Expert
                    </h3>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <div style="width: 52px; height: 52px; background: #f8fafc; border: 1px solid #e2e8f0; color: #2563eb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            ${data.doctor_first_name[0]}${data.doctor_last_name[0]}
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">Dr. ${data.doctor_first_name} ${data.doctor_last_name}</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">${data.specialty}</div>
                        </div>
                    </div>
                    <div style="padding-top: 16px; border-top: 1px dashed #e2e8f0; display: flex; justify-content: flex-end;">
                        <div style="text-align: right;">
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Portal Status</label>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Verified Doctor</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Patient Information Card -->
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-info-circle" style="color: white; font-size: 0.9rem;"></i> Information Details
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Age Group</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${data.age} Years</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.gender}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Relationship</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.relationship}</div>
                        </div>
                        <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 32px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.email || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Phone Contact</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.phone || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Home Address</label>
                                <div style="font-size: 0.9rem; color: #475569; line-height: 1.5;">${data.address || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Transaction Summary Card -->
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt" style="color: white; font-size: 0.9rem;"></i> Payment Summary
                    </h3>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Amount Recieved</span>
                            <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${parseFloat(data.payment_amount).toFixed(2)}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                <span class="status-badge status-${data.payment_status.toLowerCase()}" style="font-weight: 800; font-size: 0.75rem;">${data.payment_status.toUpperCase()}</span>
                            </div>
                            <div style="text-align: right;">
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">GCash Ref</label>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #2563eb; font-family: monospace;">${data.gcash_reference}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Proof of Payment (Half Width) -->
                ${data.receipt_path ? `
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../${data.receipt_path}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../${data.receipt_path}', '_blank')">
                    </div>
                </div>
                ` : ''}

            <!-- 7. Observations Card (Full Width) -->
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-medical-alt" style="color: white; font-size: 0.9rem;"></i> Clinical Records
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Reason for Visit</label>
                            <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${data.reason}</div>
                        </div>
                        <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 16px;">
                            <label style="display: block; font-size: 0.75rem; color: #2563eb; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Doctor's Findings</label>
                            <div style="font-size: 0.95rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic;">
                                ${data.notes || '"No findings recorded yet."'}
                            </div>
                        </div>
                    </div>
                </div>

                </div>
        </div>
    `;

    // Footer actions
    let footerHtml = `
        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;">Close</button>
        <button type="button" class="modal-btn modal-btn-secondary" onclick="window.print()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;"><i class="fas fa-print"></i> Print</button>
    `;

    if (data.can_complete) {
        footerHtml = `
            <div style="flex: 1; display: flex; gap: 12px;">
                <button type="button" class="modal-btn" onclick='markAsNoShow(${data.id})' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #ef4444; background: #fef2f2; color: #dc2626; transition: all 0.2s;">
                    <i class="fas fa-user-slash"></i> No Show
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick='openFindingsModal(${data.id}, ${JSON.stringify(data.notes || "")}, "complete")' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                    <i class="fas fa-check-circle"></i> Mark as Completed
                </button>
            </div>
            ${footerHtml}
        `;
    } else if (data.can_add_findings) {
        footerHtml = `
            <div style="flex: 1;"></div>
            <button type="button" class="modal-btn modal-btn-primary" onclick='openFindingsModal(${data.id}, ${JSON.stringify(data.notes || "")}, "update_findings")' style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: all 0.2s;">
                <i class="fas fa-pen"></i> Add/Edit Findings
            </button>
            ${footerHtml}
        `;
    }

    footer.innerHTML = footerHtml;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function openFindingsModal(id, currentNotes, action = 'complete') {
    document.getElementById('findingsAptId').value = id;
    document.getElementById('findingsNotesArea').value = currentNotes;
    
    // Update action and button text
    const form = document.querySelector('#findingsModal form');
    const actionInput = form.querySelector('input[name="action"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const headerTitle = document.querySelector('#findingsModal .modal-header h3');
    
    actionInput.value = action;
    if (action === 'complete') {
        submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-clipboard-check"></i> Save Findings';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-pen"></i> Update Findings';
    }

    document.getElementById('findingsModal').style.display = 'block';
    document.getElementById('appointmentModal').style.zIndex = '999';
}

function markAsNoShow(id) {
    if (confirm('Are you sure you want to mark this appointment as No Show?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'no_show';
        
        const idInput = document.createElement('input');
        idInput.name = 'appointment_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeFindingsModal() {
    document.getElementById('findingsModal').style.display = 'none';
    document.getElementById('appointmentModal').style.zIndex = '1000';
}

function closeModal() {
    const modal = document.getElementById('appointmentModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const aptModal = document.getElementById('appointmentModal');
    const findModal = document.getElementById('findingsModal');
    if (event.target == aptModal) {
        closeModal();
    }
    if (event.target == findModal) {
        closeFindingsModal();
    }
}
</script>
</body>
</html>
