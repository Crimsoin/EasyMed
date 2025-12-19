<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated and is admin
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$period = $input['period'] ?? 'daily';

try {
    $db = Database::getInstance();
    
    // Define date ranges based on period
    $currentStats = [];
    $previousStats = [];
    
    switch ($period) {
        case 'daily':
            // Current day
            $currentDate = date('Y-m-d');
            $previousDate = date('Y-m-d', strtotime('-1 day'));
            
            $currentStats = getAppointmentStatsByDate($db, $currentDate);
            $previousStats = getAppointmentStatsByDate($db, $previousDate);
            break;
            
        case 'weekly':
            // Current week (Sunday to Saturday)
            $currentWeekStart = date('Y-m-d', strtotime('sunday last week'));
            $currentWeekEnd = date('Y-m-d', strtotime('saturday this week'));
            $previousWeekStart = date('Y-m-d', strtotime('sunday -2 weeks'));
            $previousWeekEnd = date('Y-m-d', strtotime('saturday last week'));
            
            $currentStats = getAppointmentStatsByRange($db, $currentWeekStart, $currentWeekEnd);
            $previousStats = getAppointmentStatsByRange($db, $previousWeekStart, $previousWeekEnd);
            break;
            
        case 'monthly':
            // Current month
            $currentMonthStart = date('Y-m-01');
            $currentMonthEnd = date('Y-m-t');
            $previousMonthStart = date('Y-m-01', strtotime('first day of last month'));
            $previousMonthEnd = date('Y-m-t', strtotime('last day of last month'));
            
            $currentStats = getAppointmentStatsByRange($db, $currentMonthStart, $currentMonthEnd);
            $previousStats = getAppointmentStatsByRange($db, $previousMonthStart, $previousMonthEnd);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid period specified']);
            exit;
    }
    
    // Calculate percentage changes
    $changes = calculatePercentageChanges($currentStats, $previousStats);
    
    echo json_encode([
        'success' => true,
        'stats' => $currentStats,
        'changes' => $changes,
        'period' => $period
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching appointment stats: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}

function getAppointmentStatsByDate($db, $date) {
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'cancelled' => 0
    ];
    
    // Total appointments
    $stats['total'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = ?",
        [$date]
    )['count'];
    
    // Completed appointments
    $stats['completed'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = ? AND status = 'completed'",
        [$date]
    )['count'];
    
    // Pending/Scheduled appointments
    $stats['pending'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = ? AND status IN ('pending', 'scheduled')",
        [$date]
    )['count'];
    
    // Cancelled appointments
    $stats['cancelled'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) = ? AND status = 'cancelled'",
        [$date]
    )['count'];
    
    return $stats;
}

function getAppointmentStatsByRange($db, $startDate, $endDate) {
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'cancelled' => 0
    ];
    
    // Total appointments
    $stats['total'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ?",
        [$startDate, $endDate]
    )['count'];
    
    // Completed appointments
    $stats['completed'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status = 'completed'",
        [$startDate, $endDate]
    )['count'];
    
    // Pending/Scheduled appointments
    $stats['pending'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status IN ('pending', 'scheduled')",
        [$startDate, $endDate]
    )['count'];
    
    // Cancelled appointments
    $stats['cancelled'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status = 'cancelled'",
        [$startDate, $endDate]
    )['count'];
    
    return $stats;
}

function calculatePercentageChanges($current, $previous) {
    $changes = [];
    
    foreach ($current as $key => $currentValue) {
        $previousValue = $previous[$key] ?? 0;
        
        if ($previousValue == 0) {
            $changes[$key] = $currentValue > 0 ? 100 : 0;
        } else {
            $changes[$key] = round((($currentValue - $previousValue) / $previousValue) * 100, 1);
        }
    }
    
    return $changes;
}
?>