<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

/**
 * Quick Database Clearing Script
 * Alternative method for clearing database data
 */

echo "<h1>🗑️ Quick Database Clear</h1>";

$db = Database::getInstance();

// Quick clear options
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db->beginTransaction();
        
        // Use a safe deletion order to avoid foreign key constraint violations.
        // Child tables must be cleared before parent tables.
        switch ($action) {
            case 'clear_appointments':
                $db->query("DELETE FROM payments");
                $db->query("DELETE FROM reviews");
                $db->query("DELETE FROM appointments");
                echo "<div class='alert alert-success'>✅ All appointments cleared</div>";
                break;

            case 'clear_patients':
                // Clear child tables that reference patients first
                $db->query("DELETE FROM payments");
                $db->query("DELETE FROM reviews");
                $db->query("DELETE FROM appointments");
                $db->query("DELETE FROM patients");
                echo "<div class='alert alert-success'>✅ All patients and their appointments cleared</div>";
                break;

            case 'clear_users':
                // Remove patient-specific data first, then patient user records
                $db->query("DELETE FROM payments");
                $db->query("DELETE FROM reviews");
                $db->query("DELETE FROM appointments");
                $db->query("DELETE FROM patients");
                $db->query("DELETE FROM users WHERE role = 'patient'");
                echo "<div class='alert alert-success'>✅ All patient users and data cleared</div>";
                break;

            case 'clear_schedules':
                $db->query("DELETE FROM doctor_unavailable");
                $db->query("DELETE FROM doctor_breaks");
                $db->query("DELETE FROM doctor_schedules");
                echo "<div class='alert alert-success'>✅ All doctor schedules cleared</div>";
                break;

            case 'clear_all_data':
                // Full clear preserving admin users: delete children first
                $db->query("DELETE FROM payments");
                $db->query("DELETE FROM reviews");
                $db->query("DELETE FROM appointments");
                $db->query("DELETE FROM patients");
                $db->query("DELETE FROM doctor_unavailable");
                $db->query("DELETE FROM doctor_breaks");
                $db->query("DELETE FROM doctor_schedules");
                $db->query("DELETE FROM doctors");
                $db->query("DELETE FROM users WHERE role IN ('patient', 'doctor')");
                echo "<div class='alert alert-success'>✅ All data cleared (admin users preserved)</div>";
                break;

            case 'nuclear_option':
                // Nuke everything including admin users; children first
                $db->query("DELETE FROM payments");
                $db->query("DELETE FROM reviews");
                $db->query("DELETE FROM appointments");
                $db->query("DELETE FROM patients");
                $db->query("DELETE FROM doctor_unavailable");
                $db->query("DELETE FROM doctor_breaks");
                $db->query("DELETE FROM doctor_schedules");
                $db->query("DELETE FROM doctors");
                $db->query("DELETE FROM users");
                echo "<div class='alert alert-warning'>☢️ NUCLEAR CLEAR: Everything deleted including admin users</div>";
                break;
        }
        
        $db->commit();
        echo "<p><a href='quick_clear.php'>← Back</a> | <a href='database_manager.php'>Database Manager</a></p>";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    }
} else {
    // Show clear options
    ?>
    <style>
        .alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-primary { background: #007cba; color: white; }
        .clear-option { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
    </style>

    <div class="clear-option">
        <h3>🗓️ Clear Appointments Only</h3>
        <p>Remove all appointments but keep patients and users</p>
        <form method="post" onsubmit="return confirm('Clear all appointments?')">
            <input type="hidden" name="action" value="clear_appointments">
            <button type="submit" class="btn btn-warning">Clear Appointments</button>
        </form>
    </div>

    <div class="clear-option">
        <h3>👥 Clear Patients & Appointments</h3>
        <p>Remove all patients and their appointments</p>
        <form method="post" onsubmit="return confirm('Clear all patients and appointments?')">
            <input type="hidden" name="action" value="clear_patients">
            <button type="submit" class="btn btn-warning">Clear Patients</button>
        </form>
    </div>

    <div class="clear-option">
        <h3>📋 Clear Patient Users & Data</h3>
        <p>Remove all patient users, patients, and appointments</p>
        <form method="post" onsubmit="return confirm('Clear all patient users and data?')">
            <input type="hidden" name="action" value="clear_users">
            <button type="submit" class="btn btn-warning">Clear Patient Users</button>
        </form>
    </div>

    <div class="clear-option">
        <h3>📅 Clear Doctor Schedules</h3>
        <p>Remove all doctor schedules, breaks, and unavailable times</p>
        <form method="post" onsubmit="return confirm('Clear all doctor schedules?')">
            <input type="hidden" name="action" value="clear_schedules">
            <button type="submit" class="btn btn-warning">Clear Schedules</button>
        </form>
    </div>

    <div class="clear-option">
        <h3>🗑️ Clear All Data (Keep Admin)</h3>
        <p>Remove all data but preserve admin users</p>
        <form method="post" onsubmit="return confirm('Clear all data except admin users?')">
            <input type="hidden" name="action" value="clear_all_data">
            <button type="submit" class="btn btn-danger">Clear All Data</button>
        </form>
    </div>

    <div class="clear-option" style="border-left-color: #dc3545;">
        <h3>☢️ NUCLEAR OPTION</h3>
        <p><strong>⚠️ WARNING:</strong> This will delete EVERYTHING including admin users!</p>
        <form method="post" onsubmit="return confirm('⚠️ NUCLEAR CLEAR: Delete absolutely everything including admin users?')">
            <input type="hidden" name="action" value="nuclear_option">
            <button type="submit" class="btn btn-danger">☢️ NUCLEAR CLEAR</button>
        </form>
    </div>

    <h3>🔗 Other Options</h3>
    <p>
        <a href="database_manager.php" class="btn btn-primary">📊 Full Database Manager</a>
        <a href="setup_sqlite_database.php" class="btn btn-primary">🔄 Recreate Database</a>
        <a href="index.php" class="btn btn-primary">🏠 Main Page</a>
    </p>
    <?php
}
?>
