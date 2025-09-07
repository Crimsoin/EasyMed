<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();
$schema = $db->fetchAll('PRAGMA table_info(appointments)');
echo "Current appointments table schema:\n";
foreach ($schema as $column) {
    echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
}
?>
