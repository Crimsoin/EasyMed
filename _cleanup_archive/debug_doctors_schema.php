<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
$db = Database::getInstance();
try {
    $cols = $db->fetchAll("PRAGMA table_info('doctors')");
    echo "Columns in doctors table:\n";
    foreach ($cols as $c) {
        echo "- " . $c['name'] . " (" . $c['type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
