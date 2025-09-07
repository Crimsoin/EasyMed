<?php
// Debug helper: simulate logged-in doctor and include header to inspect CSS <link> tags
require_once __DIR__ . '/includes/config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'doctor';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Doctor';
$page_title = 'Debug Head';
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/schedule-doctor.css'];
require_once __DIR__ . '/includes/header.php';
// Stop to avoid printing the rest of header contents
exit();
