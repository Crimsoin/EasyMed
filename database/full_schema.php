<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();

// Get all table names
$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

echo "=== EASYMED DATABASE SCHEMA ===\n\n";

foreach ($tables as $table) {
    $tableName = $table['name'];
    echo "TABLE: {$tableName}\n";
    echo str_repeat("-", strlen("TABLE: {$tableName}")) . "\n";

    // Get table schema
    $schema = $db->fetchAll("PRAGMA table_info({$tableName})");

    foreach ($schema as $column) {
        $nullable = $column['notnull'] ? 'NOT NULL' : 'NULL';
        $default = $column['dflt_value'] ? " DEFAULT {$column['dflt_value']}" : '';
        $pk = $column['pk'] ? ' PRIMARY KEY' : '';

        echo "- {$column['name']} ({$column['type']}){$pk} {$nullable}{$default}\n";
    }

    // Get row count
    $count = $db->fetch("SELECT COUNT(*) as count FROM {$tableName}")['count'];
    echo "Records: {$count}\n\n";
}

echo "=== STATUS ENUM VALUES ===\n";
echo "Appointments status values: pending, rescheduled, scheduled, completed, cancelled, no_show\n";
echo "Payment status values: pending, submitted, verified\n";
?>
