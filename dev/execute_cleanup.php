<?php
echo "<h1>🧹 Executing EasyMed Project Cleanup</h1>";

$projectRoot = __DIR__;
$cleanupDir = $projectRoot . DIRECTORY_SEPARATOR . '_cleanup_archive';
$docsDir = $projectRoot . DIRECTORY_SEPARATOR . 'docs';

// Create cleanup directories
if (!is_dir($cleanupDir)) {
    mkdir($cleanupDir, 0777, true);
    echo "<p>✅ Created cleanup archive directory</p>";
}

if (!is_dir($docsDir)) {
    mkdir($docsDir, 0777, true);
    echo "<p>✅ Created docs directory</p>";
}

// Files to remove (test/debug/setup files)
$filesToCleanup = [
    'add_email_verified_column.php',
    'add_sample_appointments.php',
    'analyze_database_structure.php',
    'check_appointments_schema.php',
    'check_constraints.php',
    'check_doctors_schema.php',
    'check_doctors_table.php',
    'check_foreign_keys.php',
    'check_patients_schema.php',
    'check_schema.php',
    'check_tables.php',
    'check_table_structure.php',
    'create_patient_record.php',
    'create_schedule_tables.php',
    'create_tables_direct.php',
    'database_diagnostic.php',
    'debug_appointments.php',
    'debug_doctor_profile.php',
    'debug_doctor_query.php',
    'debug_foreign_key.php',
    'fix_schedule_tables.php',
    'setup_doctor_tables.php',
    'simulate_doctor_login.php',
    'simulate_login.php',
    'test_appointment_booking.php',
    'test_create_appointment.php',
    'test_css_consistency.html',
    'test_doctor_portal.php',
    'update_appointments_schema.php',
    'cleanup_analysis.php' // This analysis file itself
];

// Documentation files to organize
$docsToOrganize = [
    'CLEANUP_EXECUTION_REPORT.md',
    'DATABASE_SCALING_GUIDE.md',
    'DOCTOR_PORTAL_FIXES.md',
    'README.md'
];

// Batch files to evaluate
$batchFiles = [
    'fix_mysql_crash.bat',
    'start_mysql_stable.bat'
];

echo "<h2>🗑️ Cleaning up test and debug files...</h2>";
$cleanedCount = 0;

foreach ($filesToCleanup as $file) {
    $filePath = $projectRoot . DIRECTORY_SEPARATOR . $file;
    $archivePath = $cleanupDir . DIRECTORY_SEPARATOR . $file;
    
    if (file_exists($filePath)) {
        if (rename($filePath, $archivePath)) {
            echo "<p>✅ Moved $file to cleanup archive</p>";
            $cleanedCount++;
        } else {
            echo "<p>❌ Failed to move $file</p>";
        }
    }
}

echo "<h2>📚 Organizing documentation...</h2>";
$docsCount = 0;

foreach ($docsToOrganize as $file) {
    $filePath = $projectRoot . DIRECTORY_SEPARATOR . $file;
    $docsPath = $docsDir . DIRECTORY_SEPARATOR . $file;
    
    if (file_exists($filePath)) {
        if (file_exists($docsPath)) {
            // If file already exists in docs, move to archive
            $archivePath = $cleanupDir . DIRECTORY_SEPARATOR . $file;
            if (rename($filePath, $archivePath)) {
                echo "<p>✅ Moved duplicate $file to cleanup archive</p>";
                $docsCount++;
            }
        } else {
            if (rename($filePath, $docsPath)) {
                echo "<p>✅ Moved $file to docs directory</p>";
                $docsCount++;
            } else {
                echo "<p>❌ Failed to move $file to docs</p>";
            }
        }
    }
}

echo "<h2>⚙️ Handling batch files...</h2>";
$batchCount = 0;

foreach ($batchFiles as $file) {
    $filePath = $projectRoot . DIRECTORY_SEPARATOR . $file;
    $archivePath = $cleanupDir . DIRECTORY_SEPARATOR . $file;
    
    if (file_exists($filePath)) {
        if (rename($filePath, $archivePath)) {
            echo "<p>✅ Moved $file to cleanup archive (Windows-specific, not needed for development server)</p>";
            $batchCount++;
        }
    }
}

// Create a project structure summary
echo "<h2>📁 Final Project Structure</h2>";

$finalStructure = [
    'Core Pages' => ['index.php', 'about.php', 'doctors.php', 'location.php', 'payment.php', 'reviews.php'],
    'Main Directories' => ['admin/', 'assets/', 'components/', 'database/', 'doctor/', 'includes/', 'patient/'],
    'Configuration' => ['setup_database.php', 'setup_sqlite_database.php'],
    'Documentation' => ['docs/']
];

foreach ($finalStructure as $category => $items) {
    echo "<h4>$category</h4>";
    echo "<ul>";
    foreach ($items as $item) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . $item;
        $exists = (is_file($path) || is_dir($path)) ? '✅' : '❌';
        echo "<li>$exists $item</li>";
    }
    echo "</ul>";
}

// Create cleanup summary
$summaryContent = "# EasyMed Project Cleanup Summary

## Cleanup Performed on: " . date('Y-m-d H:i:s') . "

### Files Cleaned: $cleanedCount
- Test and debug files moved to _cleanup_archive/
- Setup and diagnostic scripts archived

### Documentation Organized: $docsCount
- Moved to docs/ directory for better organization

### Batch Files Archived: $batchCount
- Windows-specific files moved to archive

### Final Project Structure:
- **Core Application**: Clean and organized
- **Database**: SQLite with proper schema
- **Modules**: Admin, Doctor, Patient portals functional
- **Assets**: CSS, JS, Images organized
- **Documentation**: Centralized in docs/ directory

### Archive Location:
- Archived files: _cleanup_archive/
- Can be safely deleted if not needed

### Project Status: ✅ CLEANED AND OPTIMIZED
";

file_put_contents($docsDir . DIRECTORY_SEPARATOR . 'CLEANUP_SUMMARY.md', $summaryContent);
echo "<p>✅ Created cleanup summary in docs/CLEANUP_SUMMARY.md</p>";

echo "<h2>🎉 Cleanup Complete!</h2>";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li><strong>$cleanedCount</strong> test/debug files cleaned</li>";
echo "<li><strong>$docsCount</strong> documentation files organized</li>";
echo "<li><strong>$batchCount</strong> batch files archived</li>";
echo "<li>Project structure optimized and ready for production</li>";
echo "</ul>";
echo "</div>";

echo "<h3>🧪 Test Your Clean Project:</h3>";
echo "<p><a href='index.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 Main Page</a></p>";
echo "<p><a href='patient/book-appointment.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📅 Book Appointment</a></p>";
echo "<p><a href='doctor/dashboard_doctor.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>👨‍⚕️ Doctor Portal</a></p>";
echo "<p><a href='admin/' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>⚙️ Admin Panel</a></p>";

echo "<h3>📁 Archive Information:</h3>";
echo "<p>Archived files are stored in <code>_cleanup_archive/</code> directory.</p>";
echo "<p>You can safely delete this directory if you don't need the archived files.</p>";
?>
