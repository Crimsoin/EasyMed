<?php
// Diagnostic: list tables that have FOREIGN KEY references to the `patients` table
try {
    $dbPath = __DIR__ . '/../../database/easymed.sqlite';
    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found: $dbPath");
    }
    $db = new PDO('sqlite:' . $dbPath);
    $sql = "SELECT name, sql FROM sqlite_master WHERE sql LIKE '%REFERENCES patients%' OR sql LIKE '%REFERENCES \"patients\"%'";
    $st = $db->query($sql);
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

    if (empty($rows)) {
        echo "No tables reference 'patients' via FOREIGN KEY.\n";
        exit(0);
    }

    foreach ($rows as $r) {
        echo "Table: " . $r['name'] . "\n";
        echo $r['sql'] . "\n";
        echo "----\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
