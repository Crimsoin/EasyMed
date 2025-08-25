<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/easymed.sqlite');
    $tables = ['lab_offers', 'lab_offer_doctors'];
    foreach ($tables as $t) {
        $stmt = $db->query("PRAGMA table_info('" . $t . "')");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        echo "Table: $t\n";
        echo json_encode($cols, JSON_PRETTY_PRINT) . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
