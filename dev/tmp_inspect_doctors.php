<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/easymed.sqlite');
    $stmt = $db->query("PRAGMA table_info('doctors')");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cols, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
