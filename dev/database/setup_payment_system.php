<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance();
    
    // Create payments table
    $sql = "
    CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'gcash',
        gcash_reference VARCHAR(100),
        receipt_file VARCHAR(255),
        payment_notes TEXT,
        status VARCHAR(20) DEFAULT 'pending_verification',
        verified_by INTEGER NULL,
        verified_at DATETIME NULL,
        submitted_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $db->query($sql);
    echo "✅ Payments table created successfully.\n";
    
    // Add consultation_fee column to doctors table if it doesn't exist
    try {
        $db->query("ALTER TABLE doctors ADD COLUMN consultation_fee DECIMAL(10,2) DEFAULT 1500.00");
        echo "✅ Added consultation_fee column to doctors table.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false) {
            echo "ℹ️ consultation_fee column already exists in doctors table.\n";
        } else {
            throw $e;
        }
    }
    
    // Update existing doctors with default consultation fees if they're null
    $db->query("UPDATE doctors SET consultation_fee = 1500.00 WHERE consultation_fee IS NULL OR consultation_fee = 0");
    echo "✅ Updated default consultation fees for existing doctors.\n";
    
    // Create uploads directory for payment receipts
    $upload_dir = __DIR__ . '/../../assets/uploads/payment_receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✅ Created payment receipts upload directory.\n";
    } else {
        echo "ℹ️ Payment receipts upload directory already exists.\n";
    }
    
    // Create .htaccess to protect uploads
    $htaccess_content = "# Deny direct access to uploaded files\n";
    $htaccess_content .= "Options -Indexes\n";
    $htaccess_content .= "<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py)$\">\n";
    $htaccess_content .= "    Order allow,deny\n";
    $htaccess_content .= "    Deny from all\n";
    $htaccess_content .= "</Files>\n";
    
    file_put_contents($upload_dir . '.htaccess', $htaccess_content);
    echo "✅ Created .htaccess protection for uploads directory.\n";
    
    echo "\n🎉 Payment system setup completed successfully!\n";
    echo "📋 Summary:\n";
    echo "   - Payments table created\n";
    echo "   - Payment status tracked in dedicated payments table\n";
    echo "   - Doctor consultation fees configured\n";
    echo "   - Upload directory secured\n";
    echo "\n🚀 Payment gateway is now ready to use!\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up payment system: " . $e->getMessage() . "\n";
    exit(1);
}
?>
