<?php
require_once 'includes/config.php';

echo "<h1>EasyMed Database Analysis</h1>";

$db = Database::getInstance();

echo "<h2>Database Type: " . DB_TYPE . "</h2>";

if (DB_TYPE === 'sqlite') {
    echo "<p><strong>SQLite Database Location:</strong> " . SQLITE_PATH . "</p>";
    
    // Get all tables in SQLite database
    echo "<h3>Database Tables:</h3>";
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    
    echo "<ul>";
    foreach ($tables as $table) {
        $tableName = $table['name'];
        if ($tableName !== 'sqlite_sequence') {
            echo "<li><strong>$tableName</strong>";
            
            // Get row count
            $count = $db->fetch("SELECT COUNT(*) as count FROM $tableName")['count'];
            echo " ($count records)";
            
            // Get table structure
            $columns = $db->fetchAll("PRAGMA table_info($tableName)");
            echo "<br><small>Columns: ";
            $columnNames = array_map(function($col) {
                return $col['name'] . " (" . $col['type'] . ")";
            }, $columns);
            echo implode(', ', $columnNames);
            echo "</small>";
            echo "</li>";
        }
    }
    echo "</ul>";
    
    // Show foreign key relationships
    echo "<h3>Foreign Key Relationships:</h3>";
    foreach ($tables as $table) {
        $tableName = $table['name'];
        if ($tableName !== 'sqlite_sequence') {
            $foreignKeys = $db->fetchAll("PRAGMA foreign_key_list($tableName)");
            if (!empty($foreignKeys)) {
                echo "<h4>$tableName:</h4>";
                echo "<ul>";
                foreach ($foreignKeys as $fk) {
                    echo "<li>{$fk['from']} → {$fk['table']}.{$fk['to']}</li>";
                }
                echo "</ul>";
            }
        }
    }
    
} else {
    echo "<p><strong>MySQL Database:</strong> " . DB_NAME . " on " . DB_HOST . "</p>";
    
    // Get all tables in MySQL database
    echo "<h3>Database Tables:</h3>";
    $tables = $db->fetchAll("SHOW TABLES");
    
    echo "<ul>";
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "<li><strong>$tableName</strong>";
        
        // Get row count
        $count = $db->fetch("SELECT COUNT(*) as count FROM $tableName")['count'];
        echo " ($count records)</li>";
    }
    echo "</ul>";
}

// Show sample data from key tables
$keyTables = ['users', 'patients', 'doctors', 'appointments'];

foreach ($keyTables as $tableName) {
    try {
        $sampleData = $db->fetchAll("SELECT * FROM $tableName LIMIT 3");
        if (!empty($sampleData)) {
            echo "<h3>Sample Data from $tableName:</h3>";
            echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
            
            // Header
            echo "<tr>";
            foreach (array_keys($sampleData[0]) as $column) {
                echo "<th style='padding: 4px; background: #f0f0f0;'>$column</th>";
            }
            echo "</tr>";
            
            // Data rows
            foreach ($sampleData as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td style='padding: 4px;'>" . htmlspecialchars(substr($value, 0, 30)) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p>Table $tableName: " . $e->getMessage() . "</p>";
    }
}
?>
