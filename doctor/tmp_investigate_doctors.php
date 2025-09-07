<?php
// Investigate doctor_id mappings in the SQLite DB
$path = __DIR__ . '/../database/easymed.sqlite';
if (!file_exists($path)) {
    echo json_encode(['error' => 'DB file not found', 'path' => $path]);
    exit(1);
}
try {
    $pdo = new PDO('sqlite:' . $path);
    $out = [];

    // List tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $out['tables'] = $tables;

    // Distinct doctor_ids in appointments
    $stmt = $pdo->query("SELECT DISTINCT doctor_id FROM appointments ORDER BY doctor_id");
    $out['appointment_doctor_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // For each doctor_id, try to find a user and a doctor row
    $out['mapping'] = [];
    foreach ($out['appointment_doctor_ids'] as $did) {
        $did = $did === null ? null : (int)$did;
        $row = ['doctor_id' => $did, 'user' => null, 'doctors_table' => null];
        if ($did !== null) {
            $s1 = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
            $s1->execute([$did]);
            $row['user'] = $s1->fetch(PDO::FETCH_ASSOC);

            // try doctors table if present
            $hasDoctors = in_array('doctors', $tables);
            if ($hasDoctors) {
                $s2 = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
                $s2->execute([$did]);
                $row['doctors_table'] = $s2->fetch(PDO::FETCH_ASSOC);
            }

            // attempt reverse lookup: maybe users table has doctor_id in another column (like doctor_id in users)
            $s3 = $pdo->prepare("SELECT * FROM users WHERE doctor_id = ?");
            try {
                $s3->execute([$did]);
                $row['users_with_doctor_id_col'] = $s3->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $row['users_with_doctor_id_col'] = null;
            }
        }
        $out['mapping'][] = $row;
    }

    echo json_encode($out, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
