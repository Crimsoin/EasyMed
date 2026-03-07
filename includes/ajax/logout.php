<?php
require_once '../config.php';
require_once '../functions.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    // Log the logout activity
    $db = Database::getInstance();
    $db->insert('activity_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'logout',
        'description' => 'User logged out',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

$auth->logout();

// Redirect to home page
header('Location: ' . SITE_URL . '/index.php?logout=1');
exit();
?>
