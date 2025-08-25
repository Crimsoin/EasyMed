<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

echo "<h2>Patient Status Diagnostic</h2>";

try {
    $db = Database::getInstance();
    
    // Get all users with role patient
    $patients = $db->fetchAll('SELECT id, username, first_name, last_name, is_active, role FROM users WHERE role = ? ORDER BY id', ['patient']);
    
    echo "<h3>Current Patient Records:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>Username</th>";
    echo "<th style='padding: 8px;'>Name</th>";
    echo "<th style='padding: 8px;'>Role</th>";
    echo "<th style='padding: 8px;'>is_active (raw)</th>";
    echo "<th style='padding: 8px;'>Status</th>";
    echo "<th style='padding: 8px;'>Test Update</th>";
    echo "</tr>";
    
    foreach ($patients as $patient) {
        $status = $patient['is_active'] ? 'Active' : 'Inactive';
        $statusColor = $patient['is_active'] ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td style='padding: 8px; text-align: center;'>" . $patient['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($patient['username']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
        echo "<td style='padding: 8px;'>" . $patient['role'] . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . $patient['is_active'] . "</td>";
        echo "<td style='padding: 8px; color: $statusColor;'><strong>$status</strong></td>";
        echo "<td style='padding: 8px;'>";
        
        // Test what happens when we try to update this specific patient
        if (isset($_GET['test_id']) && $_GET['test_id'] == $patient['id']) {
            $testAction = $_GET['test_action'] ?? 'toggle';
            
            echo "<strong>Testing " . $testAction . " for patient ID " . $patient['id'] . ":</strong><br>";
            
            if ($testAction === 'activate') {
                $result = $db->update('users', ['is_active' => 1], 'id = ?', [$patient['id']]);
                echo "Activate result: " . $result . " rows affected<br>";
            } elseif ($testAction === 'deactivate') {
                $result = $db->update('users', ['is_active' => 0], 'id = ?', [$patient['id']]);
                echo "Deactivate result: " . $result . " rows affected<br>";
            }
            
            // Refresh the patient data to see the new state
            $updatedPatient = $db->fetch('SELECT id, is_active FROM users WHERE id = ?', [$patient['id']]);
            echo "New status: " . ($updatedPatient['is_active'] ? 'Active' : 'Inactive');
        } else {
            echo "<a href='?test_id=" . $patient['id'] . "&test_action=activate'>Test Activate</a> | ";
            echo "<a href='?test_id=" . $patient['id'] . "&test_action=deactivate'>Test Deactivate</a>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Show the raw SQL that would be executed
    echo "<h3>SQL Debug Information:</h3>";
    if (isset($_GET['test_id'])) {
        $testId = (int)$_GET['test_id'];
        echo "<p><strong>For patient ID " . $testId . ":</strong></p>";
        echo "<p><code>UPDATE users SET is_active = 0 WHERE id = " . $testId . "</code></p>";
        echo "<p><code>UPDATE users SET is_active = 1 WHERE id = " . $testId . "</code></p>";
        
        // Check if this ID actually exists
        $exists = $db->fetch('SELECT COUNT(*) as count FROM users WHERE id = ?', [$testId]);
        echo "<p>Record exists check: " . $exists['count'] . " records found</p>";
        
        // Check role
        $roleCheck = $db->fetch('SELECT role FROM users WHERE id = ?', [$testId]);
        echo "<p>Role check: " . ($roleCheck ? $roleCheck['role'] : 'No record found') . "</p>";
    }
    
    echo "<h3>Database Schema Check:</h3>";
    $columns = $db->fetchAll("DESCRIBE users");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='diagnostic.php'>Refresh</a> | <a href='patients.php'>Back to Patient Management</a></p>";
?>
