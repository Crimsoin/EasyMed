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

if (!isset($input['action']) || $input['action'] !== 'clear_logs') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Clear all activity logs
    $result = $db->query("DELETE FROM activity_logs");
    
    if ($result) {
        // Log the action of clearing logs
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'admin';
        
        // Add activity log for this action
        $db->insert('activity_logs', [
            'user_id' => $user_id,
            'action' => 'clear_system_logs',
            'description' => 'Administrator cleared all system logs',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'System logs cleared successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to clear system logs'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error clearing system logs: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>