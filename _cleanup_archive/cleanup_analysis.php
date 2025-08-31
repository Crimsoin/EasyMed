<?php
echo "<h1>🧹 EasyMed Project Cleanup Analysis</h1>";

$projectRoot = __DIR__;

// Define file categories for cleanup
$categories = [
    'test_files' => [
        'pattern' => '/^(test_|debug_|check_|analyze_|simulate_|create_tables_|setup_doctor_|fix_|database_diagnostic).*\.php$/',
        'description' => 'Test, debug, and setup files',
        'action' => 'move_to_cleanup'
    ],
    'documentation' => [
        'pattern' => '/\.(md|txt)$/i',
        'description' => 'Documentation files',
        'action' => 'organize'
    ],
    'batch_files' => [
        'pattern' => '/\.(bat|cmd)$/i',
        'description' => 'Windows batch files',
        'action' => 'evaluate'
    ],
    'core_files' => [
        'pattern' => '/^(index|about|doctors|location|payment|reviews)\.php$/',
        'description' => 'Core application files',
        'action' => 'keep'
    ]
];

// Scan root directory
$files = array_diff(scandir($projectRoot), array('.', '..'));
$analysis = [
    'core_files' => [],
    'test_files' => [],
    'documentation' => [],
    'batch_files' => [],
    'directories' => [],
    'other_files' => []
];

foreach ($files as $file) {
    $filePath = $projectRoot . DIRECTORY_SEPARATOR . $file;
    
    if (is_dir($filePath)) {
        $analysis['directories'][] = $file;
        continue;
    }
    
    $categorized = false;
    foreach ($categories as $category => $config) {
        if (preg_match($config['pattern'], $file)) {
            $analysis[$category][] = $file;
            $categorized = true;
            break;
        }
    }
    
    if (!$categorized) {
        $analysis['other_files'][] = $file;
    }
}

// Display analysis
foreach ($analysis as $category => $files) {
    if (!empty($files)) {
        echo "<h3>" . ucwords(str_replace('_', ' ', $category)) . " (" . count($files) . ")</h3>";
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>$file</li>";
        }
        echo "</ul>";
    }
}

// Recommendations
echo "<h2>🎯 Cleanup Recommendations</h2>";

echo "<h3>1. Test/Debug Files to Remove (Safe to delete):</h3>";
echo "<ul>";
foreach ($analysis['test_files'] as $file) {
    echo "<li>$file</li>";
}
echo "</ul>";

echo "<h3>2. Documentation to Organize:</h3>";
echo "<ul>";
foreach ($analysis['documentation'] as $file) {
    echo "<li>$file</li>";
}
echo "</ul>";

echo "<h3>3. Core Application Structure:</h3>";
echo "<ul>";
echo "<li><strong>Frontend Pages:</strong> " . implode(', ', $analysis['core_files']) . "</li>";
echo "<li><strong>Main Directories:</strong> " . implode(', ', $analysis['directories']) . "</li>";
echo "</ul>";

$totalFiles = count($analysis['test_files']) + count($analysis['other_files']);
echo "<h3>📊 Cleanup Impact:</h3>";
echo "<p><strong>Files to clean:</strong> {$totalFiles}</p>";
echo "<p><strong>Directories to organize:</strong> " . count($analysis['directories']) . "</p>";
echo "<p><strong>Core files to keep:</strong> " . count($analysis['core_files']) . "</p>";

echo "<h2>✅ Ready to Execute Cleanup</h2>";
echo "<p><a href='execute_cleanup.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗑️ Execute Cleanup</a></p>";
echo "<p><a href='index.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 Test Main Page</a></p>";
?>
