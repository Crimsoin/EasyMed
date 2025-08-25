<?php
$page_title = "Schedule Management";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/schedule-doctor.css'];
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$db = Database::getInstance();
$doctor_id = $_SESSION['user_id'];

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

// Get appointments for current month to show booked slots
$month_appointments = $db->fetchAll("
    SELECT date(appointment_date) as date, COUNT(*) as count
    FROM appointments 
    WHERE doctor_id = ? AND strftime('%m', appointment_date) = ? AND strftime('%Y', appointment_date) = ? AND status IN ('confirmed', 'pending')
    GROUP BY date(appointment_date)
", [$doctor_id, $month_str, $year_str]);

$appointments_by_date = [];
foreach ($month_appointments as $apt) {
    $appointments_by_date[$apt['date']] = $apt['count'];
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

        <!-- Schedule Management Forms -->
        <div class="content-section">
            <div class="section-header">
                <h2>Manage Schedule</h2>
            </div>
            <div class="section-content">
                <div class="schedule-management">
                    <!-- Weekly Schedule Form -->
                    <div class="schedule-form">
                        <h3><i class="fas fa-calendar-week"></i> Weekly Availability</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_availability">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="day_of_week">Day of Week:</label>
                                    <select name="day_of_week" id="day_of_week" required>
                                        <?php for ($i = 0; $i < 7; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $days_of_week[$i]; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="slot_duration">Slot Duration (minutes):</label>
                                    <select name="slot_duration" id="slot_duration" required>
                                        <option value="15">15 minutes</option>
                                        <option value="20">20 minutes</option>
                                        <option value="30" selected>30 minutes</option>
                                        <option value="45">45 minutes</option>
                                        <option value="60">60 minutes</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_time">Start Time:</label>
                                    <input type="time" name="start_time" id="start_time" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_time">End Time:</label>
                                    <input type="time" name="end_time" id="end_time" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Schedule
                            </button>
                        </form>
                    </div>

                    <!-- Add Break Form -->
                    <div class="schedule-form">
                        <h3><i class="fas fa-coffee"></i> Schedule Break</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_break">
                            <div class="form-group">
                                <label for="break_date">Date:</label>
                                <input type="date" name="break_date" id="break_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="break_start_time">Start Time:</label>
                                    <input type="time" name="break_start_time" id="break_start_time" required>
                                </div>
                                <div class="form-group">
                                    <label for="break_end_time">End Time:</label>
                                    <input type="time" name="break_end_time" id="break_end_time" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="break_reason">Reason:</label>
                                <input type="text" name="break_reason" id="break_reason" placeholder="e.g., Lunch break, Meeting" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Break
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Mark Unavailable Form -->
                <div class="schedule-form-full">
                    <h3><i class="fas fa-times-circle"></i> Mark Day Unavailable</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_unavailable">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="unavailable_date">Date:</label>
                                <input type="date" name="unavailable_date" id="unavailable_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="unavailable_reason">Reason:</label>
                                <input type="text" name="unavailable_reason" id="unavailable_reason" placeholder="e.g., Vacation, Conference, Personal" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-ban"></i> Mark Unavailable
                                </button>
                            </div>
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
                            <a href="?month=<?php echo $current_month - 1; ?>&year=<?php echo $current_year; ?>" class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </div>
                        <h3><?php echo $month_name; ?></h3>
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $current_month + 1; ?>&year=<?php echo $current_year; ?>" class="btn btn-secondary">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                    
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
                            
                            $has_schedule = isset($schedule_by_day[$day_of_week]) && $schedule_by_day[$day_of_week]['is_available'];
                            $appointment_count = $appointments_by_date[$current_date] ?? 0;
                            $has_break = false;
                            $has_unavailable = false;
                            
                            // Check for breaks
                            foreach ($month_breaks as $break) {
                                if ($break['break_date'] === $current_date) {
                                    $has_break = true;
                                    break;
                                }
                            }
                            
                            // Check for unavailable dates
                            foreach ($month_unavailable as $unavailable) {
                                if ($unavailable['unavailable_date'] === $current_date) {
                                    $has_unavailable = true;
                                    break;
                                }
                            }
                            
                            $classes = ['calendar-day'];
                            if ($is_today) $classes[] = 'today';
                        ?>
                            <div class="<?php echo implode(' ', $classes); ?>">
                                <div class="day-number"><?php echo $day; ?></div>
                                <div class="day-schedule">
                                    <?php if ($has_unavailable): ?>
                                        <div class="schedule-block unavailable">Unavailable</div>
                                    <?php elseif ($has_break): ?>
                                        <div class="schedule-block partial">Has Break</div>
                                    <?php elseif ($has_schedule): ?>
                                        <?php if ($appointment_count > 0): ?>
                                            <div class="schedule-block"><?php echo $appointment_count; ?> booked</div>
                                        <?php else: ?>
                                            <div class="schedule-block">Available</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Weekly Schedule -->
        <div class="content-section">
            <div class="section-header">
                <h2>Weekly Schedule</h2>
            </div>
            <div class="section-content">
                <div class="current-schedule">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Working Hours</th>
                                <th>Slot Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days_of_week as $index => $day): 
                                $schedule = $schedule_by_day[$index] ?? null;
                            ?>
                                <tr>
                                    <td class="day-name"><?php echo $day; ?></td>
                                    <td class="status">
                                        <?php if ($schedule && $schedule['is_available']): ?>
                                            <span class="status-badge status-available">Available</span>
                                        <?php else: ?>
                                            <span class="status-badge status-unavailable">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="working-hours">
                                        <?php if ($schedule && $schedule['is_available']): ?>
                                            <?php echo formatTime($schedule['start_time']) . ' - ' . formatTime($schedule['end_time']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="slot-duration">
                                        <?php if ($schedule && $schedule['is_available']): ?>
                                            <?php echo $schedule['slot_duration']; ?> min
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-primary" onclick="editDaySchedule(<?php echo $index; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
function editDaySchedule(dayIndex) {
    // Set the day in the form
    document.getElementById('day_of_week').value = dayIndex;
    
    // Scroll to form
    document.querySelector('.schedule-form').scrollIntoView({ behavior: 'smooth' });
    
    // Focus on start time
    document.getElementById('start_time').focus();
}

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
});
</script>

<?php require_once '../includes/footer.php'; ?>
