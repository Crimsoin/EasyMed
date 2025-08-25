<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/easymed.sqlite');
    $tables = ['lab_offers', 'lab_offer_doctors'];
    $out = [];
    foreach ($tables as $t) {
        $stmt = $db->query("PRAGMA table_info('" . $t . "')");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out[$t] = $cols ?: [];
    }
    echo json_encode($out, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
