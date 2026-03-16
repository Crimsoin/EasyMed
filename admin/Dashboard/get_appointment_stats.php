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
            
            $currentStats = getAppointmentStatsByRange($db, $currentDate, $currentDate);
            $previousStats = getAppointmentStatsByRange($db, $previousDate, $previousDate);
            break;
            
        case 'weekly':
            // Standard Week: Monday to Sunday
            $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
            $currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));
            $previousWeekStart = date('Y-m-d', strtotime('monday last week'));
            $previousWeekEnd = date('Y-m-d', strtotime('sunday last week'));
            
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
        'period' => $period,
        'debug' => [
            'range' => $period === 'daily' ? $currentDate : ($currentWeekStart ?? $currentMonthStart) . ' to ' . ($currentWeekEnd ?? $currentMonthEnd)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching appointment stats: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}

function getAppointmentStatsByRange($db, $startDate, $endDate) {
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'cancelled' => 0,
        'no_show' => 0
    ];
    
    // We use date() function in SQL to ensure we are comparing just the date part
    
    // Total appointments (all statuses within the range)
    $stats['total'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ?",
        [$startDate, $endDate]
    )['count'];
    
    // Completed
    $stats['completed'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status = 'completed'",
        [$startDate, $endDate]
    )['count'];
    
    // Pending/Upcoming (pending, scheduled, rescheduled, ongoing)
    $stats['pending'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status IN ('pending', 'scheduled', 'rescheduled', 'ongoing')",
        [$startDate, $endDate]
    )['count'];
    
    // Cancelled
    $stats['cancelled'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status = 'cancelled'",
        [$startDate, $endDate]
    )['count'];

    // No Show
    $stats['no_show'] = $db->fetch(
        "SELECT COUNT(*) as count FROM appointments WHERE date(appointment_date) BETWEEN ? AND ? AND status = 'no_show'",
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