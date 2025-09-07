<?php
$path = __DIR__ . '/../database/easymed.sqlite';
if (!file_exists($path)) {
    echo json_encode(['error' => 'DB file not found', 'path' => $path]);
    exit(1);
}
try {
    $pdo = new PDO('sqlite:' . $path);
    $out = [];

    // list tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $out['tables'] = $tables;

    // distinct doctor ids in appointments
    $stmt = $pdo->query("SELECT DISTINCT doctor_id FROM appointments ORDER BY doctor_id");
    $didRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $out['appointment_doctor_ids'] = $didRows;

    $out['mapping'] = [];
    foreach ($didRows as $didRaw) {
        $did = $didRaw === null ? null : (int)$didRaw;
        $row = ['doctor_id' => $did, 'appointments_count' => 0, 'users_row' => null, 'doctors_row' => null];

        $s0 = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
        $s0->execute([$did]);
        $row['appointments_count'] = (int)$s0->fetchColumn();

        // try users table -> id = did
        $s1 = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
        $s1->execute([$did]);
        $row['users_row'] = $s1->fetch(PDO::FETCH_ASSOC);

        // try doctors table -> id = did
        if (in_array('doctors', $tables)) {
            $s2 = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
            $s2->execute([$did]);
            $row['doctors_row'] = $s2->fetch(PDO::FETCH_ASSOC);
            // if doctors_row has user_id, fetch that user
            if ($row['doctors_row'] && isset($row['doctors_row']['user_id'])) {
                $s3 = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
                $s3->execute([$row['doctors_row']['user_id']]);
                $row['doctors_row']['user_linked'] = $s3->fetch(PDO::FETCH_ASSOC);
            }
        }

        // also try to find users whose id maps to some other column linking to doctors (attempt common columns)
        foreach (['doctor_user_id','user_id','linked_user_id'] as $col) {
            $try = null;
            try {
                $s4 = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE " . $col . " = ? LIMIT 1");
                $s4->execute([$did]);
                $try = $s4->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $try = null;
            }
            if ($try) {
                $row['users_linked_by_'.$col] = $try;
            }
        }

        $out['mapping'][] = $row;
    }

    echo json_encode($out, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
