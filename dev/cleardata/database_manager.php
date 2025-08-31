<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';


echo "<h1>🗄️ EasyMed Database Management</h1>";
echo "<p><strong>Database Location:</strong> " . SQLITE_PATH . "</p>";

$db = Database::getInstance();

// Get current database statistics
echo "<h2>📊 Current Database Status</h2>";

$tables = ['users', 'patients', 'doctors', 'appointments', 'doctor_schedules', 'doctor_breaks', 'doctor_unavailable'];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f8f9fa;'><th>Table</th><th>Records</th><th>Actions</th></tr>";

$totalRecords = 0;
foreach ($tables as $table) {
    try {
        $count = $db->fetch("SELECT COUNT(*) as count FROM $table")['count'];
        $totalRecords += $count;
        
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$count</td>";
        echo "<td>";
        if ($count > 0) {
            echo "<a href='?clear_table=$table' onclick='return confirm(\"Are you sure you want to clear all data from $table?\")' style='color: #dc3545; text-decoration: none;'>🗑️ Clear</a> | ";
            echo "<a href='?view_table=$table' style='color: #007cba; text-decoration: none;'>👁️ View</a>";
        } else {
            echo "<span style='color: #6c757d;'>Empty</span>";
        }
        echo "</td>";
        echo "</tr>";
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td style='color: #dc3545;'>Error: Table not found</td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}

echo "<tr style='background: #e9ecef; font-weight: bold;'>";
echo "<td>TOTAL</td>";
echo "<td>$totalRecords</td>";
echo "<td>";
if ($totalRecords > 0) {
    echo "<a href='?clear_all=1' onclick='return confirm(\"⚠️ WARNING: This will delete ALL data from ALL tables. Are you absolutely sure?\")' style='color: #dc3545; text-decoration: none; font-weight: bold;'>🗑️ CLEAR ALL DATA</a>";
}
echo "</td>";
echo "</tr>";
echo "</table>";

// Handle clear actions
if (isset($_GET['clear_table'])) {
    $table = $_GET['clear_table'];
    if (in_array($table, $tables)) {
        try {
            $db->query("DELETE FROM $table");
            echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px; margin: 20px 0;'>";
            echo "✅ Successfully cleared all data from <strong>$table</strong> table";
            echo "</div>";
            echo "<script>setTimeout(() => window.location.href = 'database_manager.php', 2000);</script>";
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin: 20px 0;'>";
            echo "❌ Error clearing $table: " . $e->getMessage();
            echo "</div>";
        }
    }
}

if (isset($_GET['clear_all'])) {
    try {
        $db->beginTransaction();
        
        // Clear child tables first to avoid foreign key constraint failures
        $clearSequence = [
            'payments',
            'reviews',
            'appointments',
            'patients',
            'doctor_unavailable',
            'doctor_breaks',
            'doctor_schedules',
            'doctors',
            'users'
        ];
        $clearedTables = [];

        foreach ($clearSequence as $table) {
            try {
                $db->query("DELETE FROM $table");
                $clearedTables[] = $table;
            } catch (Exception $e) {
                // Continue with other tables
            }
        }
        
        $db->commit();
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Database Cleared Successfully</h3>";
        echo "<p>Cleared tables: " . implode(', ', $clearedTables) . "</p>";
        echo "<p>All user data, appointments, and schedules have been removed.</p>";
        echo "</div>";
        echo "<script>setTimeout(() => window.location.href = 'database_manager.php', 3000);</script>";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin: 20px 0;'>";
        echo "❌ Error clearing database: " . $e->getMessage();
        echo "</div>";
    }
}

// View table data
if (isset($_GET['view_table'])) {
    $table = $_GET['view_table'];
    if (in_array($table, $tables)) {
        echo "<h3>👁️ Viewing Table: $table</h3>";
        try {
            $data = $db->fetchAll("SELECT * FROM $table LIMIT 10");
            if (!empty($data)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
                
                // Header
                echo "<tr style='background: #f8f9fa;'>";
                foreach (array_keys($data[0]) as $column) {
                    echo "<th style='padding: 8px;'>$column</th>";
                }
                echo "</tr>";
                
                // Data
                foreach ($data as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        $displayValue = strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value;
                        echo "<td style='padding: 8px;'>" . htmlspecialchars($displayValue) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                
                if (count($data) == 10) {
                    echo "<p><small>Showing first 10 records only</small></p>";
                }
            } else {
                echo "<p>Table is empty</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: #dc3545;'>Error: " . $e->getMessage() . "</p>";
        }
        
        echo "<p><a href='database_manager.php'>← Back to Database Manager</a></p>";
    }
}

echo "<h2>🛠️ Manual Database Clearing Methods</h2>";

echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Method 1: Using This Web Interface (Recommended)</h3>";
echo "<ul>";
echo "<li>Use the buttons above to clear individual tables or all data</li>";
echo "<li>Safe and handles foreign key constraints properly</li>";
echo "<li>Provides confirmation dialogs to prevent accidents</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Method 2: Delete Database File</h3>";
echo "<ul>";
echo "<li><strong>File Location:</strong> <code>" . SQLITE_PATH . "</code></li>";
echo "<li><strong>Action:</strong> Delete the file and run setup again</li>";
echo "<li><strong>Command:</strong> <code>Remove-Item \"" . SQLITE_PATH . "\"</code></li>";
echo "<li><strong>Then run:</strong> <a href='setup_sqlite_database.php'>setup_sqlite_database.php</a></li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Method 3: SQLite Command Line</h3>";
echo "<ul>";
echo "<li>Install SQLite command line tool</li>";
echo "<li>Open database: <code>sqlite3 \"" . SQLITE_PATH . "\"</code></li>";
echo "<li>Clear specific table: <code>DELETE FROM table_name;</code></li>";
echo "<li>Clear all tables: <code>.dump</code> then <code>DROP TABLE table_name;</code></li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>⚠️ Important Notes</h3>";
echo "<ul>";
echo "<li><strong>Backup First:</strong> Consider backing up your database before clearing</li>";
echo "<li><strong>Foreign Keys:</strong> Clear child tables before parent tables</li>";
echo "<li><strong>Order Matters:</strong> appointments → patients → users → doctors</li>";
echo "<li><strong>No Undo:</strong> Once cleared, data cannot be recovered</li>";
echo "</ul>";
echo "</div>";

echo "<h3>🔗 Quick Actions</h3>";
echo "<p>";
echo "<a href='setup_sqlite_database.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔄 Recreate Database</a>";
echo "<a href='index.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 Main Page</a>";
echo "</p>";
?>
