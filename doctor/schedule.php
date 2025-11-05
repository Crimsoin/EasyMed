<?php
$page_title = "Schedule Management";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/schedule-doctor.css'];
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
$doctor_row = $db->fetch("SELECT id, user_id FROM doctors WHERE user_id = ?", [$session_user_id]);
if ($doctor_row && isset($doctor_row['id'])) {
    $doctor_id = (int)$doctor_row['id'];
} else {
    // Fallback: if no doctors table or mapping, use the session user id (legacy behavior)
    $doctor_id = $session_user_id;
}

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
           a.reason_for_visit as reason_for_visit,
           u.first_name as patient_first_name, u.last_name as patient_last_name,
           p.phone as patient_phone
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND strftime('%m', a.appointment_date) = ? AND strftime('%Y', a.appointment_date) = ?
    ORDER BY a.appointment_date, a.appointment_time
", [$doctor_id, $month_str, $year_str]);

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

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_availability':
                    $day_of_week = (int)$_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $slot_duration = (int)$_POST['slot_duration'];
                    
                    // Check if schedule already exists for this day
                    $existing = $db->fetch("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?", 
                                         [$doctor_id, $day_of_week]);
                    
                    if ($existing) {
                        $db->query("UPDATE doctor_schedules SET start_time = ?, end_time = ?, slot_duration = ?, is_available = 1 WHERE doctor_id = ? AND day_of_week = ?",
                                   [$start_time, $end_time, $slot_duration, $doctor_id, $day_of_week]);
                    } else {
                        $db->query("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_available) VALUES (?, ?, ?, ?, ?, 1)",
                                   [$doctor_id, $day_of_week, $start_time, $end_time, $slot_duration]);
                    }
                    $success_message = "Schedule updated successfully!";
                    break;
                    
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

        <!-- Schedule Statistics -->
        <div class="content-section">
            <div class="section-header">
                <h2>Schedule Overview</h2>
            </div>
            <div class="section-content">
                <div class="schedule-stats">
                    <div class="schedule-stat-item">
                        <div class="schedule-stat-number"><?php echo $stats['working_days']; ?></div>
                        <div class="schedule-stat-label">Working Days</div>
                    </div>
                    <div class="schedule-stat-item">
                        <div class="schedule-stat-number"><?php echo number_format($stats['total_hours'], 1); ?></div>
                        <div class="schedule-stat-label">Hours/Week</div>
                    </div>
                    <div class="schedule-stat-item">
                        <div class="schedule-stat-number"><?php echo $stats['breaks_this_month']; ?></div>
                        <div class="schedule-stat-label">Breaks This Month</div>
                    </div>
                    <div class="schedule-stat-item">
                        <div class="schedule-stat-number"><?php echo $stats['unavailable_days']; ?></div>
                        <div class="schedule-stat-label">Days Off</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Set Availability Card -->
        <div class="content-section">
            <div class="section-header">
                <h2>Set Availability</h2>
            </div>
            <div class="section-content">
                <div class="schedule-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_availability">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="availability_day">Day of Week</label>
                                <select id="availability_day" name="day_of_week">
                                    <?php foreach ($days_of_week as $idx => $dname): ?>
                                        <option value="<?php echo $idx; ?>"><?php echo $dname; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input id="start_time" type="time" name="start_time" required />
                            </div>

                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input id="end_time" type="time" name="end_time" required />
                            </div>

                            <div class="form-group">
                                <label for="slot_duration">Slot Duration (minutes)</label>
                                <input id="slot_duration" type="number" name="slot_duration" min="5" step="5" value="15" required />
                            </div>
                        </div>

                        <div style="display:flex; gap:0.5rem; align-items:center; margin-top:1rem;">
                            <button type="submit" class="btn btn-primary">Save Availability</button>
                            <button type="button" id="clearAvailability" class="btn btn-secondary">Clear Fields</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="content-section">
            <div class="section-header">
                <h2>Calendar View</h2>
            </div>
            <div class="section-content">
                <div class="schedule-calendar">
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $current_month - 1; ?>&year=<?php echo $current_year; ?>" class="btn btn-secondary nav-btn" title="Previous Month">
                                <i class="fas fa-chevron-left"></i>
                                <span class="nav-text">Prev Month</span>
                            </a>
                            <a href="?month=1&year=<?php echo $current_year - 1; ?>" class="btn btn-secondary nav-btn" title="Previous Year">
                                <i class="fas fa-angle-double-left"></i>
                                <span class="nav-text">Prev Year</span>
                            </a>
                        </div>

                        <div class="calendar-title">
                            <h3><?php echo $month_name; ?>
                                <?php if ($current_month == date('n') && $current_year == date('Y')): ?>
                                    <span class="current-indicator" title="Current Month">(Current)</span>
                                <?php endif; ?>
                            </h3>
                            <div class="calendar-controls">
                                <!-- Month/Year dropdowns removed -->
                                <button type="button" id="btnToday" class="btn btn-sm btn-primary" title="Go to Today">
                                    <i class="fas fa-calendar-day"></i> Today
                                </button>
                            </div>
                        </div>

                        <div class="calendar-nav">
                            <a href="?month=1&year=<?php echo $current_year + 1; ?>" class="btn btn-secondary nav-btn" title="Next Year">
                                <span class="nav-text">Next Year</span>
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                            <a href="?month=<?php echo $current_month + 1; ?>&year=<?php echo $current_year; ?>" class="btn btn-secondary nav-btn" title="Next Month">
                                <span class="nav-text">Next Month</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <!-- Quick jump and keyboard shortcuts removed for simplified UI -->
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
                                <div class="day-number"><?php echo $day; ?></div>
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

<script>
// JavaScript for schedule management
function showDayDetails(date) {
    // Create modal for day details
    const modal = document.createElement('div');
    modal.className = 'modal day-details-modal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Schedule Details - ${formatDateForDisplay(date)}</h3>
                <span class="close" onclick="closeDayModal()">&times;</span>
            </div>
            <div class="modal-body" id="dayDetailsContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin"></i> Loading details...
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Load day details via AJAX
    loadDayDetails(date);
}

function loadDayDetails(date) {
    // Get appointment data for this date. Prefer runtime globals populated by AJAX loader
    const embeddedAppointments = <?php echo json_encode($appointments_by_date_detailed); ?>;
    const embeddedBreaks = <?php echo json_encode($breaks_by_date); ?>;
    const embeddedSchedule = <?php echo json_encode($schedule_by_day); ?>;
    const currentDoctorId = <?php echo json_encode($doctor_id); ?>;

    const appointments = (window.__appointments !== undefined) ? window.__appointments : embeddedAppointments;
    const breaks = (window.__breaks !== undefined) ? window.__breaks : embeddedBreaks;
    const schedule = (window.__scheduleByDay !== undefined) ? window.__scheduleByDay : embeddedSchedule;

    const dayAppointmentsRaw = appointments && appointments[date] ? appointments[date] : [];
    const dayAppointments = Array.isArray(dayAppointmentsRaw) ? dayAppointmentsRaw.filter(function(a){ return String(a.doctor_id) === String(currentDoctorId); }) : [];
    const dayBreaks = breaks[date] || [];
    
    // Get day of week for schedule
    const dayOfWeek = new Date(date).getDay();

    // Robust lookup: schedule may be an object keyed by day index, an array of rows, or have string keys
    function findScheduleForDay(scheduleObj, dow) {
        if (!scheduleObj) return null;
        // Direct index access (works if schedule is an array or object with numeric keys)
        if (scheduleObj[dow]) return scheduleObj[dow];
        if (scheduleObj.hasOwnProperty && scheduleObj.hasOwnProperty(String(dow))) return scheduleObj[String(dow)];
        // If it's an array, find entry with day_of_week property
        if (Array.isArray(scheduleObj)) {
            for (const item of scheduleObj) {
                if (item && (item.day_of_week == dow || item.day_of_week === String(dow))) return item;
            }
        }
        // If it's an object with numeric-string keys, search values
        try {
            for (const k in scheduleObj) {
                const item = scheduleObj[k];
                if (item && (item.day_of_week == dow || item.day_of_week === String(dow))) return item;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    const daySchedule = findScheduleForDay(schedule, dayOfWeek);
    
    let content = `
        <div class="day-details">
            <div class="appointments-section">
                <h4><i class="fas fa-calendar-check"></i> Appointments (${dayAppointments.length})</h4>
                ${dayAppointments.length > 0 ? `
                    <div class="appointments-list">
                        ${dayAppointments.map(apt => `
                            <div class="appointment-detail ${apt.status}">
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i> ${formatTime(apt.appointment_time)}
                                </div>
                                <div class="appointment-info">
                                    <strong>${apt.patient_first_name} ${apt.patient_last_name}</strong>
                                    <br>
                                    <small>Phone: ${apt.patient_phone}</small>
                                    ${apt.reason_for_visit ? `<br><small>Reason: ${apt.reason_for_visit}</small>` : ''}
                                </div>
                                <div class="appointment-status status-${apt.status}">
                                    ${apt.status.replace('_', ' ').toUpperCase()}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="no-appointments">No appointments scheduled for this day.</p>'}
            </div>
            
            <!-- Breaks section removed per request -->
        </div>
        
        <div class="modal-actions" style="text-align: center; margin-top: 2rem;">
            <button type="button" class="btn btn-secondary" onclick="closeDayModal()">Close</button>
        </div>
    `;
    
    document.getElementById('dayDetailsContent').innerHTML = content;
}

function closeDayModal() {
    const modal = document.querySelector('.day-details-modal');
    if (modal) {
        modal.remove();
    }
}

function formatDateForDisplay(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
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

// Prefill availability form when a calendar day is clicked
document.addEventListener('click', function(e) {
    const day = e.target.closest && e.target.closest('.calendar-day');
    if (day) {
        // Determine day index from the day element's inner number and current calendar month/year
        const dayNumEl = day.querySelector('.day-number');
        if (!dayNumEl) return;
        const dayNum = parseInt(dayNumEl.textContent, 10);
        if (isNaN(dayNum)) return;

        // Compute the day-of-week for the currently displayed month/year
        const month = <?php echo $current_month; ?>;
        const year = <?php echo $current_year; ?>;
        const dow = new Date(year, month - 1, dayNum).getDay();

        // Attempt to find schedule for this dow using window.__scheduleByDay or embedded schedule
        const embeddedSchedule = <?php echo json_encode($schedule_by_day); ?>;
        const scheduleSource = (window.__scheduleByDay !== undefined) ? window.__scheduleByDay : embeddedSchedule;

        // Use the same finder as loadDayDetails
        function findScheduleForDay(scheduleObj, dow) {
            if (!scheduleObj) return null;
            if (scheduleObj[dow]) return scheduleObj[dow];
            if (scheduleObj.hasOwnProperty && scheduleObj.hasOwnProperty(String(dow))) return scheduleObj[String(dow)];
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

        const s = findScheduleForDay(scheduleSource, dow);
        if (s) {
            const st = s.start_time || '';
            const et = s.end_time || '';
            const sd = s.slot_duration || document.getElementById('slot_duration').value || 15;
            document.getElementById('availability_day').value = String(dow);
            if (st && document.getElementById('start_time')) document.getElementById('start_time').value = st;
            if (et && document.getElementById('end_time')) document.getElementById('end_time').value = et;
            if (sd && document.getElementById('slot_duration')) document.getElementById('slot_duration').value = sd;
        } else {
            // clear
            document.getElementById('availability_day').value = String(dow);
        }
    }
});

// Clear availability form
document.getElementById && document.addEventListener('click', function(e) {
    const clear = e.target.closest && e.target.closest('#clearAvailability');
    if (clear) {
        e.preventDefault();
        if (document.getElementById('start_time')) document.getElementById('start_time').value = '';
        if (document.getElementById('end_time')) document.getElementById('end_time').value = '';
        if (document.getElementById('slot_duration')) document.getElementById('slot_duration').value = '15';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
