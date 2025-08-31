<?php
require_once 'includes/config.php';

$pdo = new PDO('sqlite:' . SQLITE_PATH);
$pdo->exec('ALTER TABLE users ADD COLUMN email_verified INTEGER DEFAULT 0');
echo "Added email_verified column to users table\n";
?>
