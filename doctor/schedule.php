<?php
$page_title = "Schedule Management";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/schedule-doctor.css', 'shared-modal.css', 'doctor/appointments-doctor.css'];
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();
$db = Database::getInstance();
$session_user_id = $_SESSION['user_id'];

// Map logged-in user to doctors.id (appointments.store doctor_id references doctors.id)
$doctor_row = $db->fetch("SELECT id, user_id, specialty FROM doctors WHERE user_id = ?", [$session_user_id]);
if ($doctor_row && isset($doctor_row['id'])) {
    $doctor_id = (int)$doctor_row['id'];
} else {
    // Fallback: if no doctors table or mapping, use the session user id (legacy behavior)
    $doctor_id = $session_user_id;
}
$doctor_specialty = $doctor_row['specialty'] ?? 'Medical Practitioner';

// Optional override: allow viewing a specific doctor's calendar via ?doctor_id=NN (this should be a doctors.id)
// Note: keep this lightweight for local/dev use; in production enforce permission checks.
if (isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id'])) {
    $doctor_id = (int)$_GET['doctor_id'];
}

// Get current month/year for calendar
$current_month = (int)($_GET['month'] ?? date('n'));
$current_year = (int)($_GET['year'] ?? date('Y'));

// Ensure valid month/year
if ($current_month < 1) { $current_month = 12; $current_year--; }
if ($current_month > 12) { $current_month = 1; $current_year++; }

$month_name = date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));

// Get doctor's weekly schedule
$weekly_schedule = $db->fetchAll("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week", [$doctor_id]);
$schedule_by_day = [];
foreach ($weekly_schedule as $schedule) {
    $schedule_by_day[$schedule['day_of_week']] = $schedule;
}

// Get breaks for current month
$month_str = sprintf('%02d', $current_month);
$year_str = (string)$current_year;

$month_breaks = $db->fetchAll("
    SELECT * FROM doctor_breaks 
    WHERE doctor_id = ? AND strftime('%m', break_date) = ? AND strftime('%Y', break_date) = ?
    ORDER BY break_date, start_time
", [$doctor_id, $month_str, $year_str]);

// Get unavailable dates for current month
$month_unavailable = $db->fetchAll("
    SELECT * FROM doctor_unavailable 
    WHERE doctor_id = ? AND strftime('%m', unavailable_date) = ? AND strftime('%Y', unavailable_date) = ?
    ORDER BY unavailable_date
", [$doctor_id, $month_str, $year_str]);

// Get appointments for current month with detailed information
$month_appointments_detailed = $db->fetchAll("
    SELECT a.*,
           a.updated_at as updated_at,
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           u.email as patient_email,
           p.phone as patient_phone,
           p.gender as patient_gender,
           p.date_of_birth as patient_dob,
           p.address as patient_address,
           pay.status as payment_status, pay.amount as payment_amount, pay.gcash_reference, pay.receipt_file
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN payments pay ON a.id = pay.appointment_id
    WHERE a.doctor_id = ? AND strftime('%m', a.appointment_date) = ? AND strftime('%Y', a.appointment_date) = ?
    ORDER BY a.appointment_date, a.appointment_time
", [$doctor_id, $month_str, $year_str]);

foreach ($month_appointments_detailed as &$appt) {
    $appt['doctor_first_name'] = $_SESSION['first_name'];
    $appt['doctor_last_name'] = $_SESSION['last_name'];
    $appt['doctor_specialty'] = $doctor_specialty;
    $appt['receipt_path'] = !empty($appt['receipt_file']) ? 'assets/uploads/payment_receipts/' . $appt['receipt_file'] : null;
}
unset($appt);

$appointments_by_date_detailed = [];
foreach ($month_appointments_detailed as $apt) {
    $date = $apt['appointment_date'];
    if (!isset($appointments_by_date_detailed[$date])) {
        $appointments_by_date_detailed[$date] = [];
    }
    $appointments_by_date_detailed[$date][] = $apt;
}

// Get breaks by date
$breaks_by_date = [];
foreach ($month_breaks as $break) {
    $date = $break['break_date'];
    if (!isset($breaks_by_date[$date])) {
        $breaks_by_date[$date] = [];
    }
    $breaks_by_date[$date][] = $break;
}

// Handle schedule updates
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = $_POST['appointment_id'] ?? null; // Ensure appointment_id is available for both cases
    if ($appointment_id) {
        switch ($action) {
            case 'complete':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', ['notes' => $notes, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as completed.";
                header("Location: " . $_SERVER['PHP_SELF'] . "?month=" . $month_str . "&year=" . $year_str . "&success=" . urlencode($success_message));
                exit();
            case 'update_findings':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', ['notes' => $notes, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$appointment_id]);
                $success_message = "Appointment findings updated successfully.";
                header("Location: " . $_SERVER['PHP_SELF'] . "?month=" . $month_str . "&year=" . $year_str . "&success=" . urlencode($success_message));
                exit();
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                    
                case 'add_break':
                    $date = $_POST['break_date'];
                    $start_time = $_POST['break_start_time'];
                    $end_time = $_POST['break_end_time'];
                    $reason = $_POST['break_reason'];
                    
                    $db->query("INSERT INTO doctor_breaks (doctor_id, break_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?)",
                               [$doctor_id, $date, $start_time, $end_time, $reason]);
                    $success_message = "Break scheduled successfully!";
                    break;
                    
                case 'mark_unavailable':
                    $date = $_POST['unavailable_date'];
                    $reason = $_POST['unavailable_reason'];
                    
                    $db->query("INSERT INTO doctor_unavailable (doctor_id, unavailable_date, reason) VALUES (?, ?, ?)",
                               [$doctor_id, $date, $reason]);
                    $success_message = "Unavailable date marked successfully!";
                    break;
                    
                case 'delete_break':
                    $break_id = (int)$_POST['break_id'];
                    $db->query("DELETE FROM doctor_breaks WHERE id = ? AND doctor_id = ?", [$break_id, $doctor_id]);
                    $success_message = "Break removed successfully!";
                    break;
                    
                case 'delete_unavailable':
                    $unavailable_id = (int)$_POST['unavailable_id'];
                    $db->query("DELETE FROM doctor_unavailable WHERE id = ? AND doctor_id = ?", [$unavailable_id, $doctor_id]);
                    $success_message = "Unavailable date removed successfully!";
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error updating schedule: " . $e->getMessage();
    }
}

// Keep the old appointment count for backward compatibility
$appointments_by_date = [];
foreach ($month_appointments_detailed as $apt) {
    $date = $apt['appointment_date'];
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = 0;
    }
    $appointments_by_date[$date]++;
}

// Calculate calendar data
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$first_day_of_week = date('w', $first_day_of_month); // 0 = Sunday

// Get statistics
$stats = [
    'working_days' => count(array_filter($schedule_by_day, function($s) { return $s['is_available']; })),
    'total_hours' => 0,
    'breaks_this_month' => count($month_breaks),
    'unavailable_days' => count($month_unavailable)
];

// Calculate total working hours per week
foreach ($schedule_by_day as $schedule) {
    if ($schedule['is_available']) {
        $start = strtotime($schedule['start_time']);
        $end = strtotime($schedule['end_time']);
        $hours = ($end - $start) / 3600;
        $stats['total_hours'] += $hours;
    }
}

$days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

require_once '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
            <p>Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_doctor.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointments.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="schedule.php" class="nav-item active">
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
            <h1><i class="fas fa-clock"></i> Schedule Management</h1>
            <p>Manage your availability, working hours, and time off</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>



        <!-- Set Availability Card -->

        <!-- Calendar View -->
        <div class="content-section">
            <div class="section-header">
                <h2>Calendar View</h2>
            </div>
            <div class="section-content">
                <div class="schedule-calendar">
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <a href="?month=1&year=<?php echo $current_year - 1; ?>" class="nav-btn" title="Previous Year">
                                <i class="fas fa-angle-double-left"></i>
                                <span class="nav-text">Year</span>
                            </a>
                            <a href="?month=<?php echo $current_month - 1; ?>&year=<?php echo $current_year; ?>" class="nav-btn" title="Previous Month">
                                <i class="fas fa-chevron-left"></i>
                                <span class="nav-text">Prev</span>
                            </a>
                        </div>

                        <div class="calendar-title">
                            <h3><i class="far fa-calendar-alt"></i> <?php echo $month_name; ?></h3>
                        </div>

                        <div class="calendar-nav">
                            <a href="?month=<?php echo $current_month + 1; ?>&year=<?php echo $current_year; ?>" class="nav-btn" title="Next Month">
                                <span class="nav-text">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="?month=1&year=<?php echo $current_year + 1; ?>" class="nav-btn" title="Next Year">
                                <span class="nav-text">Year</span>
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Keyboard shortcuts and quick-jump removed -->
                    
                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <?php foreach ($days_of_week as $day): ?>
                            <div class="calendar-day-header"><?php echo substr($day, 0, 3); ?></div>
                        <?php endforeach; ?>
                        
                        <!-- Empty cells for days before month starts -->
                        <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                            <div class="calendar-day other-month"></div>
                        <?php endfor; ?>
                        
                        <!-- Days of the month -->
                        <?php for ($day = 1; $day <= $days_in_month; $day++): 
                            $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                            $day_of_week = date('w', mktime(0, 0, 0, $current_month, $day, $current_year));
                            $is_today = ($current_date === date('Y-m-d'));
                            $is_past = ($current_date < date('Y-m-d'));
                            
                            $has_schedule = isset($schedule_by_day[$day_of_week]) && $schedule_by_day[$day_of_week]['is_available'];
                            $day_appointments = $appointments_by_date_detailed[$current_date] ?? [];
                            $day_breaks = $breaks_by_date[$current_date] ?? [];
                            $appointment_count = count($day_appointments);
                            $has_break = !empty($day_breaks);
                            $has_unavailable = false;
                            
                            // Check for unavailable dates
                            foreach ($month_unavailable as $unavailable) {
                                if ($unavailable['unavailable_date'] === $current_date) {
                                    $has_unavailable = true;
                                    break;
                                }
                            }
                            
                            $classes = ['calendar-day'];
                            if ($is_today) $classes[] = 'today';
                            if ($is_past) $classes[] = 'past';
                        ?>
                            <div class="<?php echo implode(' ', $classes); ?>" onclick="showDayDetails('<?php echo $current_date; ?>')">
                                <div class="day-header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div class="day-number <?php echo $is_today ? 'today-highlight' : ''; ?>"><?php echo $day; ?></div>
                                </div>
                                <div class="day-schedule">
                                    <?php if ($has_unavailable): ?>
                                        <div class="schedule-block unavailable">
                                            <i class="fas fa-times-circle"></i>
                                            <span>Unavailable</span>
                                        </div>
                                    <?php elseif ($has_break && !empty($day_breaks)): ?>
                                        <div class="schedule-block partial">
                                            <i class="fas fa-coffee"></i>
                                            <span><?php echo count($day_breaks); ?> break<?php echo count($day_breaks) > 1 ? 's' : ''; ?></span>
                                        </div>
                                    <?php elseif ($has_schedule): ?>
                                        <?php if ($appointment_count > 0): ?>
                                            <div class="schedule-block booked">
                                                <i class="fas fa-calendar-check"></i>
                                                <span><?php echo $appointment_count; ?> apt<?php echo $appointment_count > 1 ? 's' : ''; ?></span>
                                            </div>
                                            <!-- Show working hours -->
                                            <?php $schedule = $schedule_by_day[$day_of_week]; ?>
                                            <div class="schedule-hours">
                                                <small><?php echo formatTime($schedule['start_time']) . '-' . formatTime($schedule['end_time']); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <div class="schedule-block available">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Available</span>
                                            </div>
                                            <!-- Show working hours -->
                                            <?php $schedule = $schedule_by_day[$day_of_week]; ?>
                                            <div class="schedule-hours">
                                                <small><?php echo formatTime($schedule['start_time']) . '-' . formatTime($schedule['end_time']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Detailed appointment preview (show first 2 appointments) -->
                                <?php if (!empty($day_appointments) && count($day_appointments) > 0): ?>
                                    <div class="appointment-preview">
                                        <?php 
                                        $preview_count = min(2, count($day_appointments));
                                        for ($i = 0; $i < $preview_count; $i++): 
                                            $apt = $day_appointments[$i];
                                        ?>
                                            <div class="preview-item">
                                                <small><?php echo formatTime($apt['appointment_time']); ?> - <?php echo htmlspecialchars(substr($apt['patient_first_name'] . ' ' . $apt['patient_last_name'], 0, 10)); ?><?php echo strlen($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) > 10 ? '...' : ''; ?></small>
                                            </div>
                                        <?php endfor; ?>
                                        <?php if (count($day_appointments) > 2): ?>
                                            <div class="preview-item">
                                                <small><em>+<?php echo count($day_appointments) - 2; ?> more</em></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled Breaks -->
        <?php if (!empty($month_breaks)): ?>
        <div class="content-section">
            <div class="section-header">
                <h2>Scheduled Breaks - <?php echo $month_name; ?></h2>
            </div>
            <div class="section-content">
                <?php foreach ($month_breaks as $break): ?>
                    <div class="break-item">
                        <div class="break-info">
                            <h4><?php echo formatDate($break['break_date']); ?></h4>
                            <p><?php echo formatTime($break['start_time']) . ' - ' . formatTime($break['end_time']); ?> • <?php echo htmlspecialchars($break['reason']); ?></p>
                        </div>
                        <div class="break-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this break?')">
                                <input type="hidden" name="action" value="delete_break">
                                <input type="hidden" name="break_id" value="<?php echo $break['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Unavailable Days -->
        <?php if (!empty($month_unavailable)): ?>
        <div class="content-section">
            <div class="section-header">
                <h2>Unavailable Days - <?php echo $month_name; ?></h2>
            </div>
            <div class="section-content">
                <?php foreach ($month_unavailable as $unavailable): ?>
                    <div class="break-item">
                        <div class="break-info">
                            <h4><?php echo formatDate($unavailable['unavailable_date']); ?></h4>
                            <p><?php echo htmlspecialchars($unavailable['reason']); ?></p>
                        </div>
                        <div class="break-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this unavailable date?')">
                                <input type="hidden" name="action" value="delete_unavailable">
                                <input type="hidden" name="unavailable_id" value="<?php echo $unavailable['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Details Modal -->
<?php include_once '../includes/shared_appointment_details.php'; ?>

<script>
function showAppointmentDetails(data) {
    showAppointmentOverview(data, 'doctor');
}
</script>

<div id="findingsModal" class="modal" style="z-index: 2200;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Final Findings</h3>
            <span class="close-modal" onclick="closeFindingsModal()"><i class="fas fa-times"></i></span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="appointment_id" id="findingsAptId">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-pen"></i> Doctor's Notes & Findings</div>
                    <textarea name="notes" id="findingsNotesArea" class="findings-textarea" placeholder="Enter patient diagnosis, prescriptions, or summary here..." required style="width: 100%; min-height: 150px; padding: 10px; border-radius: 8px; border: 1px solid #ddd;"></textarea>
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
// JavaScript for schedule management
function showDayDetails(date) {
    // Create modal for day details
    const modal = document.createElement('div');
    modal.className = 'modal day-details-modal';
    modal.style.zIndex = '1500'; // Between calendar and details
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 550px; border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-day"></i> Day Schedule
                </h3>
                <span class="close" onclick="closeDayModal()" style="color: rgba(255, 255, 255, 0.8); cursor: pointer; font-size: 1.25rem;"><i class="fas fa-times"></i></span>
            </div>
            <div class="modal-body" id="dayDetailsContent" style="padding: 0; background: #fdfdfd;">
                 <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #2563eb; margin-bottom: 1rem;"></i>
                    <p style="color: #64748b; font-weight: 600;">Loading schedule details...</p>
                </div>
            </div>
            <div class="modal-footer" id="dayDetailsFooter" style="display: none; background: #f8fafc; border-top: 1px solid #edf2f7; padding: 20px 32px; justify-content: flex-end;">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeDayModal()" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569;">Close</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Load day details via AJAX
    loadDayDetails(date);
}

function loadDayDetails(date) {
    try {
        const dayDetailsContent = document.getElementById('dayDetailsContent');
        if (!dayDetailsContent) return;

        // Get appointment data for this date.
        const embeddedAppointments = <?php echo json_encode($appointments_by_date_detailed); ?> || {};
        const embeddedBreaks = <?php echo json_encode($breaks_by_date); ?> || {};
        const embeddedSchedule = <?php echo json_encode($schedule_by_day); ?> || {};
        const currentDoctorId = <?php echo json_encode($doctor_id); ?>;

        const appointments = (typeof window.__appointments !== 'undefined') ? window.__appointments : embeddedAppointments;
        const breaks = (typeof window.__breaks !== 'undefined') ? window.__breaks : embeddedBreaks;
        const schedule = (typeof window.__scheduleByDay !== 'undefined') ? window.__scheduleByDay : embeddedSchedule;

        const dayAppointmentsRaw = (appointments && appointments[date]) ? appointments[date] : [];
        const dayAppointments = Array.isArray(dayAppointmentsRaw) ? dayAppointmentsRaw.filter(function(a){ 
            return String(a.doctor_id) === String(currentDoctorId); 
        }) : [];
        
        // Get day of week for schedule
        const dayOfWeek = new Date(date).getDay();

        // Robust lookup: schedule may be an object keyed by day index, an array of rows, or have string keys
        function findScheduleForDay(scheduleObj, dow) {
            if (!scheduleObj) return null;
            if (scheduleObj[dow]) return scheduleObj[dow];
            if (Object.prototype.hasOwnProperty.call(scheduleObj, String(dow))) return scheduleObj[String(dow)];
            if (Array.isArray(scheduleObj)) {
                for (const item of scheduleObj) {
                    if (item && (item.day_of_week == dow || item.day_of_week === String(dow))) return item;
                }
            }
            try {
                for (const k in scheduleObj) {
                    const item = scheduleObj[k];
                    if (item && (item.day_of_week == dow || item.day_of_week === String(dow))) return item;
                }
            } catch (e) { }
            return null;
        }

        const daySchedule = findScheduleForDay(schedule, dayOfWeek);
        
        let content = `
            <div class="modal-section-hero" style="background: white; border-bottom: 1px solid #edf2f7; padding: 24px 32px; display: flex; align-items: center; gap: 20px;">
                <div class="hero-icon-container" style="width: 56px; height: 56px; background: #eff6ff; color: #2563eb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="far fa-calendar-alt"></i>
                </div>
                <div class="hero-content">
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">${formatDateForDisplay(date)}</h4>
                    <p style="margin: 4px 0 0; color: #64748b; font-size: 0.85rem; font-weight: 600;">Daily schedule overview</p>
                </div>
            </div>

            <div class="modal-section" style="padding: 24px 32px;">
                <div class="modal-section-title" style="font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                    <i class="fas fa-calendar-check" style="color: #2563eb;"></i> APPOINTMENTS (${dayAppointments.length})
                </div>
                
                ${dayAppointments.length > 0 ? `
                    <div class="modal-list-container">
                        ${dayAppointments.map(apt => {
                            const patientInfoFallback = {};
                            let pInfo = patientInfoFallback;
                            try {
                                if (apt.patient_info) pInfo = JSON.parse(apt.patient_info);
                            } catch(e) {}
                            
                            const aptData = JSON.stringify({
                                "name": (apt.patient_first_name + " " + apt.patient_last_name),
                                "account_name": apt.patient_first_name + " " + apt.patient_last_name,
                                "date": formatDateForDisplay(apt.appointment_date),
                                "time": formatTime(apt.appointment_time),
                                "email": apt.patient_email || "N/A",
                                "phone": apt.patient_phone || "N/A",
                                "address": apt.patient_address || pInfo.address || "N/A",
                                "gender": (apt.patient_gender || "N/A").charAt(0).toUpperCase() + (apt.patient_gender || "N/A").slice(1),
                                "dob": apt.patient_dob ? formatDateForDisplay(apt.patient_dob) : "N/A",
                                "reason": apt.illness || apt.reason_for_visit || pInfo.laboratory || "Consultation",
                                "purpose": (pInfo.purpose || "Consultation").charAt(0).toUpperCase() + (pInfo.purpose || "Consultation").slice(1),
                                "laboratory": pInfo.laboratory || "",
                                "relationship": (pInfo.relationship || "Self").charAt(0).toUpperCase() + (pInfo.relationship || "Self").slice(1),
                                "status": (apt.status || "pending").charAt(0).toUpperCase() + (apt.status || "pending").slice(1),
                                "id": apt.id,
                                "notes": apt.notes || "",
                                "can_complete": false,
                                "can_add_findings": apt.status.toLowerCase() === "completed",
                                "doctor_first_name": apt.doctor_first_name,
                                "doctor_last_name": apt.doctor_last_name,
                                "specialty": apt.doctor_specialty,
                                "payment_status": apt.payment_status || "PENDING",
                                "payment_amount": apt.payment_amount || 0,
                                "gcash_reference": apt.gcash_reference || "N/A",
                                "receipt_path": apt.receipt_path || null,
                                "laboratory_image_path": pInfo.laboratory_image || null,
                                "updated_at": apt.updated_at || null
                            }).replace(/'/g, "&apos;");

                            return `
                            <div class="modal-list-item status-${apt.status}" onclick='showAppointmentDetails(${aptData})' style="background: white; border: 1px solid #eef2f6; border-radius: 16px; padding: 20px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; transition: all 0.2s; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                                <div class="list-item-icon" style="width: 48px; height: 48px; background: #f8fafc; border: 1px solid #e2e8f0; color: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="list-item-content" style="flex: 1;">
                                    <div class="list-item-title" style="font-size: 1.05rem; font-weight: 700; color: #0f172a;">${apt.patient_first_name} ${apt.patient_last_name}</div>
                                    <div class="list-item-subtitle" style="font-size: 0.85rem; color: #64748b; font-weight: 600; margin: 2px 0;">${formatTime(apt.appointment_time)} • ${apt.illness || apt.reason_for_visit || 'Routine Consultation'}</div>
                                    <div class="list-item-meta" style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                                        <span style="font-size: 0.8rem; color: #94a3b8;"><i class="fas fa-phone"></i> ${apt.patient_phone || 'N/A'}</span>
                                        <span class="status-badge status-${apt.status}" style="font-size: 0.65rem; padding: 2px 8px; border-radius: 4px; font-weight: 800; letter-spacing: 0.05em;">${apt.status.toUpperCase()}</span>
                                    </div>
                                </div>
                                <div style="color: #cbd5e1; font-size: 0.9rem;">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                        `;}).join('')}
                    </div>
                ` : `
                    <div style="text-align: center; padding: 3rem; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0; margin: 10px 0;">
                        <i class="fas fa-calendar-times" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                        <p style="color: #64748b; font-weight: 600; margin: 0;">No appointments for this day</p>
                    </div>
                `}
            </div>
        `;
        
        dayDetailsContent.innerHTML = content;
        document.getElementById('dayDetailsFooter').style.display = 'flex';
    } catch (error) {
        console.error('Error in loadDayDetails:', error);
        const dayDetailsContent = document.getElementById('dayDetailsContent');
        if (dayDetailsContent) {
            dayDetailsContent.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #d32f2f;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>There was an error loading the schedule details. Please try refreshing the page.</p>
                </div>
            `;
        }
    }
}

function closeAptModal() { closeBaseModal(); }


function formatDateForDisplay(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeString) {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    let h = parseInt(hours);
    const m = minutes;
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return h + ':' + m + ' ' + ampm;
}

function closeDayModal() {
    const modal = document.querySelector('.day-details-modal');
    if (modal) modal.remove();
}

function closeFindingsModal() {
    const modal = document.getElementById('findingsModal');
    if (modal) modal.style.display = 'none';
}

function goToToday() {
    const today = new Date();
    const month = today.getMonth() + 1; // JavaScript months are 0-based
    const year = today.getFullYear();
    window.location.href = `?month=${month}&year=${year}`;
}

// --- Ajax calendar navigation (intercept nav links and Today button) ---
(function() {
    // Parse JSON variables from fetched HTML response text
    function extractJSONVar(text, varName) {
        const re = new RegExp(varName + "\\s*=\\s*(\\{[\s\S]*?\\}|\\[[\s\S]*?\\])\\s*;","m");
        const m = text.match(re);
        if (m && m[1]) {
            try { return JSON.parse(m[1]); } catch (e) { return null; }
        }
        return null;
    }

    async function loadCalendar(url, pushHistory = true) {
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Network response was not ok');
            const text = await res.text();

            // Parse the returned HTML and extract the calendar fragment
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const newCal = doc.querySelector('.schedule-calendar');
            if (!newCal) throw new Error('Could not find calendar in response');

            // Replace current calendar
            const curCal = document.querySelector('.schedule-calendar');
            if (curCal) curCal.innerHTML = newCal.innerHTML;

            // Try to extract JS data objects from the returned HTML's inline scripts
            const appointments = extractJSONVar(text, 'const appointments') || extractJSONVar(text, 'appointments') || null;
            const breaks = extractJSONVar(text, 'const breaks') || extractJSONVar(text, 'breaks') || null;
            const schedule = extractJSONVar(text, 'const schedule') || extractJSONVar(text, 'schedule') || null;

            // Update global variables used by showDayDetails/loadDayDetails
            if (appointments !== null) window.__appointments = appointments;
            if (breaks !== null) window.__breaks = breaks;
            if (schedule !== null) window.__scheduleByDay = schedule;

            // If variables not set, try to extract simple JSON by looking for json_encode outputs
            // The original page uses: const appointments = <?php echo json_encode($appointments_by_date_detailed); ?>;
            // We attempt to match that exact pattern if above failed
            try {
                const apptRe = /const\s+appointments\s*=\s*(\{[\s\S]*?\})\s*;/m;
                const apptMatch = text.match(apptRe);
                if (apptMatch && apptMatch[1]) window.__appointments = JSON.parse(apptMatch[1]);
            } catch (e) { /* ignore */ }

            // Update page title/month header if present (replace calendar title block)
            const newTitle = doc.querySelector('.calendar-title');
            const curTitle = document.querySelector('.calendar-title');
            if (newTitle && curTitle) curTitle.innerHTML = newTitle.innerHTML;

            // Update breaks and scheduled breaks lists if present
            const newBreaksSection = doc.querySelectorAll('.content-section');
            // We don't aggressively replace other sections to avoid complexity.

            // Rebind any behaviors if needed (none required for inline onclick handlers)

            // Update browser URL and history
            if (pushHistory) {
                try { history.pushState(null, '', url); } catch (e) { /* ignore */ }
            }

        } catch (err) {
            console.error('Failed to load calendar via AJAX:', err);
            // Fallback: full redirect
            window.location.href = url;
        }
    }

    // Intercept clicks on calendar nav links and Today button (delegated)
    document.addEventListener('click', function(e) {
        // Nav links
        const a = e.target.closest && e.target.closest('.nav-btn');
        if (a && a.tagName === 'A') {
            e.preventDefault();
            const url = a.getAttribute('href');
            if (url) loadCalendar(url);
            return;
        }

        // Today button
        const btn = e.target.closest && e.target.closest('#btnToday');
        if (btn) {
            e.preventDefault();
            const today = new Date();
            const month = today.getMonth() + 1;
            const year = today.getFullYear();
            const url = `?month=${month}&year=${year}`;
            loadCalendar(url);
            return;
        }
    });
})();

// quick-jump and keyboard shortcuts functions removed

// Close panels when clicking outside
// removed quick-jump and keyboard shortcuts listeners

// Validate time inputs
document.addEventListener('DOMContentLoaded', function() {
    const startTimeInputs = document.querySelectorAll('input[name="start_time"], input[name="break_start_time"]');
    const endTimeInputs = document.querySelectorAll('input[name="end_time"], input[name="break_end_time"]');
    
    function validateTimes() {
        startTimeInputs.forEach((startInput, index) => {
            const endInput = endTimeInputs[index];
            if (startInput && endInput && startInput.value && endInput.value) {
                if (startInput.value >= endInput.value) {
                    endInput.setCustomValidity('End time must be after start time');
                } else {
                    endInput.setCustomValidity('');
                }
            }
        });
    }
    
    startTimeInputs.forEach(input => input.addEventListener('change', validateTimes));
    endTimeInputs.forEach(input => input.addEventListener('change', validateTimes));

    // If the page was loaded with month/year params, ensure the calendar is visible
    try {
        if (location.search.includes('month=') || location.search.includes('year=')) {
            const cal = document.querySelector('.schedule-calendar');
            if (cal) {
                // Delay slightly to allow layout to settle, then scroll the calendar into view
                setTimeout(() => cal.scrollIntoView({ behavior: 'auto', block: 'start' }), 60);
            }
        }
    } catch (e) {
        // ignore errors in older browsers
    }
});

</script>

</body>
</html>
