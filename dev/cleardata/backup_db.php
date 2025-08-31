<?php
$dbPath = __DIR__ . '/../../database/easymed.sqlite';
if (!file_exists($dbPath)) {
    echo "ERROR: DB not found at $dbPath\n";
    exit(1);
}
$ts = date('Ymd-Hi_s');
$dst = dirname($dbPath) . DIRECTORY_SEPARATOR . basename($dbPath) . '.' . $ts . '.bak';
if (copy($dbPath, $dst)) {
    echo "BACKUP_CREATED:" . $dst . "\n";
    exit(0);
} else {
    echo "ERROR: Backup failed\n";
    exit(2);
}
