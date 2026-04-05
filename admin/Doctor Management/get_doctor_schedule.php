<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));

if (!$doctor_id) {
    echo json_encode(['error' => 'Invalid doctor ID']);
    exit;
}

// Clamp month
if ($month < 1) { $month = 12; $year--; }
if ($month > 12){ $month = 1;  $year++; }

$db = Database::getInstance();

// Doctor info
$doctor = $db->fetch("
    SELECT d.id, u.first_name, u.last_name, d.specialty
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
", [$doctor_id]);

if (!$doctor) {
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

// All appointments for doctor in this month
$start = sprintf('%04d-%02d-01', $year, $month);
$end   = date('Y-m-t', strtotime($start));

$appointments = $db->fetchAll("
    SELECT
        a.*,
        COALESCE(pu.first_name, a.first_name) as patient_first_name, 
        COALESCE(pu.last_name, a.last_name) as patient_last_name,
        COALESCE(pu.email, a.email) as patient_email,
        COALESCE(p.phone, pu.phone, a.phone_number) as patient_phone,
        COALESCE(p.address, pu.address, a.address) as patient_address,
        COALESCE(p.gender, pu.gender) as patient_gender,
        COALESCE(p.date_of_birth, pu.date_of_birth) as patient_dob,
        (COALESCE(pu.first_name, a.first_name) || ' ' || COALESCE(pu.last_name, a.last_name)) AS patient_name,
        a.relationship,
        a.illness,
        a.purpose,
        a.patient_info
    FROM appointments a
    LEFT JOIN patients p  ON a.patient_id = p.id
    LEFT JOIN users    pu ON p.user_id = pu.id
    WHERE a.doctor_id = ?
      AND a.appointment_date BETWEEN ? AND ?
      AND a.status NOT IN ('cancelled', 'no_show')
    ORDER BY a.appointment_date, a.appointment_time
", [$doctor_id, $start, $end]);

// Weekly schedule (legacy/base)
$base_schedule = $db->fetch("
    SELECT schedule_days, schedule_time_start, schedule_time_end
    FROM doctors
    WHERE id = ?
", [$doctor_id]);

// Detailed weekly schedule
$detailed_schedule = $db->fetchAll("
    SELECT day_of_week, start_time, end_time, is_available
    FROM doctor_schedules
    WHERE doctor_id = ?
", [$doctor_id]);

// Breaks for this month
$breaks = $db->fetchAll("
    SELECT break_date, start_time, end_time, reason
    FROM doctor_breaks
    WHERE doctor_id = ? AND break_date BETWEEN ? AND ?
", [$doctor_id, $start, $end]);

// Unavailable dates for this month
$unavailable = $db->fetchAll("
    SELECT unavailable_date, reason
    FROM doctor_unavailable
    WHERE doctor_id = ? AND unavailable_date BETWEEN ? AND ?
", [$doctor_id, $start, $end]);

// Group by date
$by_date = [];
foreach ($appointments as $appt) {
    $by_date[$appt['appointment_date']][] = $appt;
}

$breaks_by_date = [];
foreach ($breaks as $b) {
    $breaks_by_date[$b['break_date']][] = $b;
}

$unavailable_dates = [];
foreach ($unavailable as $u) {
    $unavailable_dates[$u['unavailable_date']] = $u['reason'];
}

echo json_encode([
    'doctor'            => $doctor,
    'year'              => $year,
    'month'             => $month,
    'days_in_month'     => (int) date('t', strtotime($start)),
    'first_day_of_week' => (int) date('w', strtotime($start)), // 0=Sun
    'base_schedule'     => $base_schedule,
    'detailed_schedule' => $detailed_schedule,
    'appointments'      => $by_date,
    'breaks'            => $breaks_by_date,
    'unavailable'       => $unavailable_dates
]);
?>
