<?php
try {
    $dbPath = __DIR__ . '/../../database/easymed.sqlite';
    $db = new PDO('sqlite:' . $dbPath);
    $sql = "SELECT name, sql FROM sqlite_master WHERE sql LIKE '%REFERENCES users%' OR sql LIKE '%REFERENCES \"users\"%'";
    $st = $db->query($sql);
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    if (empty($rows)) { echo "No tables reference 'users' via FOREIGN KEY.\n"; exit(0); }
    foreach ($rows as $r) {
        echo "Table: " . $r['name'] . "\n";
        echo $r['sql'] . "\n----\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
