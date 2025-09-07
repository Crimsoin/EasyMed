<?php
// Debug script: list doctors from easymed.sqlite
$path = __DIR__ . '/../database/easymed.sqlite';
if (!file_exists($path)) {
    echo json_encode(['error' => 'DB file not found', 'path' => $path]);
    exit(1);
}
try {
    $pdo = new PDO('sqlite:' . $path);
    // Try users table for role='doctor'
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'doctor'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        // fallback: look for a doctors table
        $stmt2 = $pdo->query("SELECT id, name, email FROM doctors");
        $rows2 = $stmt2 ? $stmt2->fetchAll(PDO::FETCH_ASSOC) : [];
        echo json_encode(['source' => 'doctors_table', 'rows' => $rows2], JSON_PRETTY_PRINT);
        exit;
    }
    echo json_encode(['source' => 'users_table', 'rows' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
