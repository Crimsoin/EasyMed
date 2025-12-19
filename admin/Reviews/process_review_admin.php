<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review_admin.php');
    exit;
}

$db = Database::getInstance();
$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id) {
    header('Location: review_admin.php');
    exit;
}

try {
    if ($action === 'approve') {
        // Set is_approved = 1 for the given review id
        $db->update('reviews', ['is_approved' => 1], 'id = ?', [$id]);
    } elseif ($action === 'delete') {
        // Delete the review row by id
        $db->delete('reviews', 'id = ?', [$id]);
    }
} catch (Exception $e) {
    // log error
}

header('Location: review_admin.php');
exit;
