<?php
// SQLite Database Configuration for EasyMed
// This is a more stable alternative to MySQL

class SQLiteDatabase {
    private $pdo;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = __DIR__ . '/database/easymed.sqlite';
        $this->createDatabaseDirectory();
        $this->connect();
        $this->createTables();
    }
    
    private function createDatabaseDirectory() {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    private function connect() {
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            echo "✅ SQLite database connected successfully!\n";
        } catch (PDOException $e) {
            die("❌ SQLite connection failed: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        $tables = [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role VARCHAR(20) DEFAULT 'patient' CHECK (role IN ('admin', 'doctor', 'patient')),
                    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('active', 'inactive', 'pending')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
            
            'doctors' => "
                CREATE TABLE IF NOT EXISTS doctors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    first_name VARCHAR(50) NOT NULL,
                    last_name VARCHAR(50) NOT NULL,
                    specialization VARCHAR(100),
                    license_number VARCHAR(50),
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    experience_years INTEGER DEFAULT 0,
                    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
                    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )",
            
            'patients' => "
                CREATE TABLE IF NOT EXISTS patients (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    first_name VARCHAR(50) NOT NULL,
                    last_name VARCHAR(50) NOT NULL,
                    date_of_birth DATE,
                    gender VARCHAR(20) CHECK (gender IN ('male', 'female', 'other')),
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    address TEXT,
                    emergency_contact VARCHAR(100),
                    emergency_phone VARCHAR(20),
                    blood_type VARCHAR(5),
                    allergies TEXT,
                    medical_history TEXT,
                    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )",
                
            'appointments' => "
                CREATE TABLE IF NOT EXISTS appointments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    patient_id INTEGER NOT NULL,
                    doctor_id INTEGER NOT NULL,
                    appointment_date DATE NOT NULL,
                    appointment_time TIME NOT NULL,
                    duration INTEGER DEFAULT 30,
                    reason_for_visit TEXT,
                    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'scheduled', 'confirmed', 'completed', 'cancelled')),
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (patient_id) REFERENCES patients(id),
                    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
                )"
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
                echo "✅ Table '$tableName' created successfully!\n";
            } catch (PDOException $e) {
                echo "❌ Error creating table '$tableName': " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function insertSampleData() {
        try {
            // Insert admin user
            $stmt = $this->pdo->prepare("
                INSERT OR IGNORE INTO users (username, email, password, role, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(['admin', 'admin@easymed.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'active']);
            
            // Insert sample doctor
            $stmt = $this->pdo->prepare("
                INSERT OR IGNORE INTO users (username, email, password, role, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(['dr_smith', 'dr.smith@easymed.com', password_hash('doctor123', PASSWORD_DEFAULT), 'doctor', 'active']);
            
            $doctorUserId = $this->pdo->lastInsertId();
            if ($doctorUserId) {
                $stmt = $this->pdo->prepare("
                    INSERT OR IGNORE INTO doctors (user_id, first_name, last_name, specialization, phone, email) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$doctorUserId, 'Dr. John', 'Smith', 'General Medicine', '+1234567890', 'dr.smith@easymed.com']);
            }
            
            echo "✅ Sample data inserted successfully!\n";
        } catch (PDOException $e) {
            echo "❌ Error inserting sample data: " . $e->getMessage() . "\n";
        }
    }
}

// Initialize the database
echo "=== EasyMed SQLite Database Setup ===\n";
$db = new SQLiteDatabase();
$db->insertSampleData();
echo "\n=== Setup completed! ===\n";
echo "Database file location: " . __DIR__ . "/database/easymed.sqlite\n";
?>
