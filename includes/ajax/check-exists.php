<?php
require_once '../functions.php';

header('Content-Type: application/json');

$type = sanitize($_GET['type'] ?? '');
$value = sanitize($_GET['value'] ?? '');

if (empty($type) || empty($value)) {
    echo json_encode(['exists' => false]);
    exit();
}

$db = Database::getInstance();
$query = "";
if ($type === 'email') {
    $query = "SELECT id, status FROM users WHERE email = ?";
} elseif ($type === 'username') {
    $query = "SELECT id, status FROM users WHERE username = ?";
} else {
    echo json_encode(['exists' => false]);
    exit();
}

$existing = $db->fetch($query, [$value]);

// If the user exists and is ACTIVE, then they really exist.
// If they are PENDING, we consider them "available" for the purpose of re-registration 
// (our Auth::register logic handles the update of pending users).
if ($existing && $existing['status'] === 'active') {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}
exit();
