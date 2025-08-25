<?php
// inspect_and_migrate_sqlite.php
// Usage: php inspect_and_migrate_sqlite.php

try {
    $dbPath = __DIR__ . '/../database/easymed.sqlite';
    if (!file_exists($dbPath)) {
        throw new Exception("SQLite DB not found at: $dbPath");
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to SQLite DB: $dbPath\n\n";

    // Get table info
    $stmt = $pdo->query("PRAGMA table_info('users')");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($columns)) {
        echo "No 'users' table found or it's empty.\n";
        exit(1);
    }

    echo "Current columns in 'users' table:\n";
    foreach ($columns as $col) {
        echo sprintf("- %s (type=%s, notnull=%s, dflt_value=%s)\n", $col['name'], $col['type'], $col['notnull'], $col['dflt_value']);
    }

    $needed = [
        'phone' => "ALTER TABLE users ADD COLUMN phone TEXT",
        'date_of_birth' => "ALTER TABLE users ADD COLUMN date_of_birth TEXT",
        'gender' => "ALTER TABLE users ADD COLUMN gender TEXT",
        'address' => "ALTER TABLE users ADD COLUMN address TEXT"
    ];

    $missing = [];
    foreach ($needed as $colName => $alterSql) {
        $found = false;
        foreach ($columns as $col) {
            if (strtolower($col['name']) === strtolower($colName)) {
                $found = true;
                break;
            }
        }
        if (!$found) $missing[$colName] = $alterSql;
    }

    if (empty($missing)) {
        echo "\nAll expected columns exist (phone, date_of_birth). No action needed.\n";
        exit(0);
    }

    foreach ($missing as $colName => $alterSql) {
        echo "\n'{$colName}' column not found. Attempting to add it with: {$alterSql};\n";
        try {
            $pdo->exec($alterSql);
            echo "Column '{$colName}' added successfully.\n";
        } catch (PDOException $e) {
            echo "Failed to add '{$colName}' column: " . $e->getMessage() . "\n";
        }
    }

    // Re-query columns
    $stmt = $pdo->query("PRAGMA table_info('users')");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nUpdated columns in 'users' table:\n";
    foreach ($columns as $col) {
        echo sprintf("- %s (type=%s)\n", $col['name'], $col['type']);
    }
    exit(0);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(3);
}
