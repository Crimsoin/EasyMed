<?php
// Debug script: query easymed.sqlite for appointments on a given date
$path = __DIR__ . '/../database/easymed.sqlite';
if (!file_exists($path)) {
    echo json_encode(['error' => 'DB file not found', 'path' => $path]);
    exit(1);
}
try {
    $pdo = new PDO('sqlite:' . $path);
    $date = '2025-09-04';
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_date = ? ORDER BY appointment_time");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['date' => $date, 'count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
