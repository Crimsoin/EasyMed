<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reviews.php');
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$patient_user_id = $_SESSION['user_id'];
$doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review_text = trim($_POST['review_text'] ?? '');
$is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0;

$errors = [];
if (!$doctor_id) $errors[] = 'Please select a doctor.';
if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be between 1 and 5.';

if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    header('Location: reviews.php');
    exit();
}

$db = Database::getInstance();

// Insert review (default: not approved)
try {
    // Ensure we have the patient's record id (patients.id) for the foreign key
    $patientRecord = $db->fetch("SELECT id FROM patients WHERE user_id = ?", [$patient_user_id]);
    if (!$patientRecord) {
        // Create a minimal patient record from users table so FK constraint can be satisfied
        $user = $db->fetch("SELECT first_name, last_name, email FROM users WHERE id = ?", [$patient_user_id]);
        $patientInsert = [
            'user_id' => $patient_user_id,
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'email' => $user['email'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        if (method_exists($db, 'insert')) {
            $newPid = $db->insert('patients', $patientInsert);
        } else {
            $newPid = $db->insertData('patients', $patientInsert);
        }
        $patient_id = $newPid;
    } else {
        $patient_id = $patientRecord['id'];
    }

    $insertData = [
        'patient_id' => $patient_id,
        'doctor_id' => $doctor_id,
        'rating' => $rating,
        'review_text' => $review_text,
        'is_anonymous' => $is_anonymous,
        'is_approved' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // The Database wrapper may have insert() or insertData(); try both safely
    if (method_exists($db, 'insert')) {
        $db->insert('reviews', $insertData);
    } else {
        $db->insertData('reviews', $insertData);
    }

    $_SESSION['review_success'] = 'Your review was submitted and is pending approval.';
} catch (Exception $e) {
    $_SESSION['review_errors'] = ['Failed to submit review: ' . $e->getMessage()];
}

header('Location: reviews.php');
exit();
