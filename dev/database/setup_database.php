<?php
require_once 'includes/config.php';

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    echo "Database created successfully.<br>";
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'doctor', 'patient') NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        date_of_birth DATE,
        gender ENUM('male', 'female', 'other'),
        profile_image VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        email_verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Users table created successfully.<br>";
    
    // Create doctors table
    $sql = "CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        specialty VARCHAR(100) NOT NULL,
        specialization VARCHAR(100),
        license_number VARCHAR(50) UNIQUE NOT NULL,
        biography TEXT,
        bio TEXT,
        consultation_fee DECIMAL(10,2) DEFAULT 0.00,
        experience_years INT DEFAULT 0,
        years_of_experience INT DEFAULT 0,
        education TEXT,
        office_address TEXT,
        available_days VARCHAR(255),
        available_hours VARCHAR(100),
        notification_preferences ENUM('all', 'important', 'none') DEFAULT 'all',
        timezone_preference VARCHAR(50) DEFAULT 'UTC',
        schedule_days SET('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        schedule_time_start TIME,
        schedule_time_end TIME,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Doctors table created successfully.<br>";
    
    // Create patients table
    $sql = "CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        emergency_contact_name VARCHAR(100),
        emergency_contact_phone VARCHAR(20),
        emergency_contact_relationship VARCHAR(50),
        blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown',
        allergies TEXT,
        medical_history TEXT,
        current_medications TEXT,
        insurance_provider VARCHAR(100),
        insurance_policy_number VARCHAR(50),
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        registration_date DATE,
        last_visit_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Patients table created successfully.<br>";
    
    // Create appointments table
    $sql = "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        service_type VARCHAR(100) DEFAULT 'consultation',
        status ENUM('pending', 'scheduled', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'pending',
        notes TEXT,
        diagnosis TEXT,
        prescription TEXT,
        lab_tests TEXT,
        payment_amount DECIMAL(10,2) DEFAULT 0.00,
        payment_proof VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Appointments table created successfully.<br>";
    
    // Create reviews table
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT,
        appointment_id INT,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        review_text TEXT,
        is_anonymous BOOLEAN DEFAULT FALSE,
        is_approved BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "Reviews table created successfully.<br>";
    
    // Create activity_logs table
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        activity_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "Activity logs table created successfully.<br>";
    
    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Notifications table created successfully.<br>";
    
    // Create clinic_settings table
    $sql = "CREATE TABLE IF NOT EXISTS clinic_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Clinic settings table created successfully.<br>";
    
    // Create doctor schedule tables
    $sql = "CREATE TABLE IF NOT EXISTS doctor_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ... 6=Saturday',
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        slot_duration INT NOT NULL DEFAULT 30 COMMENT 'Duration in minutes',
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_doctor_day (doctor_id, day_of_week)
    )";
    $pdo->exec($sql);
    echo "Doctor schedules table created successfully.<br>";
    
    // Create doctor breaks table
    $sql = "CREATE TABLE IF NOT EXISTS doctor_breaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        break_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_doctor_date (doctor_id, break_date)
    )";
    $pdo->exec($sql);
    echo "Doctor breaks table created successfully.<br>";
    
    // Create doctor unavailable dates table
    $sql = "CREATE TABLE IF NOT EXISTS doctor_unavailable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        unavailable_date DATE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_doctor_date (doctor_id, unavailable_date)
    )";
    $pdo->exec($sql);
    echo "Doctor unavailable table created successfully.<br>";
    
    // Update existing doctors table with missing columns
    echo "<br><strong>Updating existing tables with missing columns...</strong><br>";
    
    $alterCommands = [
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS specialization VARCHAR(100) AFTER specialty",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS bio TEXT AFTER biography", 
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS years_of_experience INT DEFAULT 0 AFTER experience_years",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS education TEXT AFTER years_of_experience",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS office_address TEXT AFTER education",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS available_days VARCHAR(255) AFTER office_address",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS available_hours VARCHAR(100) AFTER available_days",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS notification_preferences ENUM('all', 'important', 'none') DEFAULT 'all' AFTER available_hours",
        "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS timezone_preference VARCHAR(50) DEFAULT 'UTC' AFTER notification_preferences"
    ];
    
    foreach ($alterCommands as $command) {
        try {
            $pdo->exec($command);
            echo "Column update executed successfully.<br>";
        } catch (PDOException $e) {
            // Column might already exist, continue
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "Note: " . $e->getMessage() . "<br>";
            }
        }
    }
    echo "Table updates completed.<br><br>";
    
    // Insert default admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, is_active, email_verified) 
            VALUES ('admin', 'admin@easymed.com', ?, 'admin', 'System', 'Administrator', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_password]);
    echo "Default admin user created successfully.<br>";
    
    // Insert sample doctors
    $doctor1_password = password_hash('doctor123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, is_active, email_verified) 
            VALUES ('dr_smith', 'dr.smith@easymed.com', ?, 'doctor', 'John', 'Smith', '+63-912-345-6789', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor1_password]);
    $doctor1_id = $pdo->lastInsertId();
    
    $doctor2_password = password_hash('doctor123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, is_active, email_verified) 
            VALUES ('dr_johnson', 'dr.johnson@easymed.com', ?, 'doctor', 'Sarah', 'Johnson', '+63-917-123-4567', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor2_password]);
    $doctor2_id = $pdo->lastInsertId();
    
    $doctor3_password = password_hash('doctor123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, is_active, email_verified) 
            VALUES ('dr_brown', 'dr.brown@easymed.com', ?, 'doctor', 'Michael', 'Brown', '+63-920-987-6543', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctor3_password]);
    $doctor3_id = $pdo->lastInsertId();
    
    // Insert doctor details
    if ($doctor1_id) {
        $sql = "INSERT INTO doctors (user_id, specialty, license_number, biography, consultation_fee, experience_years, schedule_days, schedule_time_start, schedule_time_end) 
                VALUES (?, 'General Medicine', 'MD-12345', 'Experienced general practitioner with 10+ years of medical practice.', 1500.00, 10, 'Monday,Tuesday,Wednesday,Thursday,Friday', '09:00:00', '17:00:00')
                ON DUPLICATE KEY UPDATE specialty = VALUES(specialty)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor1_id]);
    }
    
    if ($doctor2_id) {
        $sql = "INSERT INTO doctors (user_id, specialty, license_number, biography, consultation_fee, experience_years, schedule_days, schedule_time_start, schedule_time_end) 
                VALUES (?, 'Pediatrics', 'MD-67890', 'Specialist in child healthcare and development with 8 years of experience.', 2000.00, 8, 'Monday,Wednesday,Friday,Saturday', '08:00:00', '16:00:00')
                ON DUPLICATE KEY UPDATE specialty = VALUES(specialty)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor2_id]);
    }
    
    if ($doctor3_id) {
        $sql = "INSERT INTO doctors (user_id, specialty, license_number, biography, consultation_fee, experience_years, schedule_days, schedule_time_start, schedule_time_end) 
                VALUES (?, 'Cardiology', 'MD-11111', 'Board-certified cardiologist specializing in heart disease prevention and treatment.', 3000.00, 12, 'Tuesday,Thursday,Saturday', '10:00:00', '18:00:00')
                ON DUPLICATE KEY UPDATE specialty = VALUES(specialty)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor3_id]);
    }
    
    echo "Sample doctors created successfully.<br>";
    
    // Insert sample patients
    $patient1_password = password_hash('patient123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, date_of_birth, gender, address, is_active, email_verified) 
            VALUES ('maria_garcia', 'maria.garcia@email.com', ?, 'patient', 'Maria', 'Garcia', '+63-917-555-0123', '1985-03-15', 'female', '456 Patient St, Quezon City', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient1_password]);
    $patient1_id = $pdo->lastInsertId();
    
    $patient2_password = password_hash('patient123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, date_of_birth, gender, address, is_active, email_verified) 
            VALUES ('juan_dela_cruz', 'juan.delacruz@email.com', ?, 'patient', 'Juan', 'Dela Cruz', '+63-918-555-0456', '1990-07-22', 'male', '789 Health Ave, Makati City', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient2_password]);
    $patient2_id = $pdo->lastInsertId();
    
    $patient3_password = password_hash('patient123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, date_of_birth, gender, address, is_active, email_verified) 
            VALUES ('ana_santos', 'ana.santos@email.com', ?, 'patient', 'Ana', 'Santos', '+63-919-555-0789', '1978-11-08', 'female', '321 Wellness Rd, Pasig City', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient3_password]);
    $patient3_id = $pdo->lastInsertId();
    
    $patient4_password = password_hash('patient123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, date_of_birth, gender, address, is_active, email_verified) 
            VALUES ('carlos_reyes', 'carlos.reyes@email.com', ?, 'patient', 'Carlos', 'Reyes', '+63-920-555-0012', '1992-05-14', 'male', '654 Care Lane, Taguig City', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient4_password]);
    $patient4_id = $pdo->lastInsertId();
    
    $patient5_password = password_hash('patient123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, date_of_birth, gender, address, is_active, email_verified) 
            VALUES ('lucia_martinez', 'lucia.martinez@email.com', ?, 'patient', 'Lucia', 'Martinez', '+63-921-555-0345', '1988-12-03', 'female', '987 Medical Plaza, Manila', TRUE, TRUE)
            ON DUPLICATE KEY UPDATE email = VALUES(email)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient5_password]);
    $patient5_id = $pdo->lastInsertId();
    
    // Insert patient details
    if ($patient1_id) {
        $sql = "INSERT INTO patients (user_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_type, allergies, medical_history, status, registration_date) 
                VALUES (?, 'Pedro Garcia', '+63-917-555-9876', 'Husband', 'A+', 'None known', 'Hypertension, managed with medication', 'active', date('now'))
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient1_id]);
    }
    
    if ($patient2_id) {
        $sql = "INSERT INTO patients (user_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_type, allergies, medical_history, status, registration_date) 
                VALUES (?, 'Rosa Dela Cruz', '+63-918-555-5432', 'Mother', 'O+', 'Penicillin', 'No significant medical history', 'active', date('now'))
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient2_id]);
    }
    
    if ($patient3_id) {
        $sql = "INSERT INTO patients (user_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_type, allergies, medical_history, status, registration_date) 
                VALUES (?, 'Roberto Santos', '+63-919-555-1111', 'Brother', 'B+', 'Shellfish', 'Diabetes Type 2, controlled with diet and exercise', 'active', date('now'))
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient3_id]);
    }
    
    if ($patient4_id) {
        $sql = "INSERT INTO patients (user_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_type, allergies, medical_history, status, registration_date) 
                VALUES (?, 'Linda Reyes', '+63-920-555-2222', 'Wife', 'AB+', 'None known', 'Asthma, uses inhaler as needed', 'active', date('now'))
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient4_id]);
    }
    
    if ($patient5_id) {
        $sql = "INSERT INTO patients (user_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_type, allergies, medical_history, status, registration_date) 
                VALUES (?, 'Miguel Martinez', '+63-921-555-3333', 'Father', 'O-', 'Latex', 'Previous surgery: appendectomy 2015', 'active', date('now'))
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient5_id]);
    }
    
    echo "Sample patients created successfully.<br>";
    
    // Insert sample appointments to create doctor-patient relationships
    if (!empty($doctors) && $patient1_id && $patient2_id && $patient3_id && $patient4_id && $patient5_id) {
        $doctor_ids = $pdo->query("SELECT id FROM doctors ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $patient_user_ids = [$patient1_id, $patient2_id, $patient3_id, $patient4_id, $patient5_id];
        
        $appointments = [
            // Past appointments
            [$patient1_id, $doctor_ids[0], '2024-08-15', '10:00:00', 'consultation', 'completed', 'Regular checkup completed', 'Good blood pressure control', 'Continue current medication', ''],
            [$patient2_id, $doctor_ids[0], '2024-08-16', '14:30:00', 'consultation', 'completed', 'First time visit', 'General health assessment normal', 'Maintain healthy lifestyle', ''],
            [$patient3_id, $doctor_ids[1], '2024-08-17', '09:15:00', 'consultation', 'completed', 'Diabetes followup', 'Blood sugar levels stable', 'Continue diet management', 'HbA1c test'],
            
            // Recent appointments
            [$patient4_id, $doctor_ids[2], '2024-08-20', '11:00:00', 'consultation', 'completed', 'Asthma consultation', 'Breathing improved with treatment', 'Adjust inhaler usage', 'Spirometry test'],
            [$patient5_id, $doctor_ids[1], '2024-08-21', '15:45:00', 'consultation', 'completed', 'Post-surgery followup', 'Recovery progressing well', 'Light exercise recommended', ''],
            
            // Upcoming appointments
            [$patient1_id, $doctor_ids[0], '2024-08-26', '10:30:00', 'consultation', 'confirmed', 'Monthly blood pressure check', '', '', ''],
            [$patient2_id, $doctor_ids[1], '2024-08-27', '14:00:00', 'consultation', 'pending', 'Routine health screening', '', '', ''],
            [$patient3_id, $doctor_ids[0], '2024-08-28', '09:00:00', 'consultation', 'confirmed', 'Quarterly diabetes review', '', '', ''],
            [$patient4_id, $doctor_ids[2], '2024-08-29', '16:30:00', 'consultation', 'pending', 'Asthma medication review', '', '', ''],
            [$patient5_id, $doctor_ids[1], '2024-08-30', '11:15:00', 'consultation', 'confirmed', 'General wellness check', '', '', ''],
        ];
        
        foreach ($appointments as $appointment) {
            $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, service_type, status, notes, diagnosis, prescription, lab_tests, payment_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1500.00)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($appointment);
        }
        echo "Sample appointments created successfully.<br>";
    }
    
    // Insert sample schedules for doctors
    // Get doctor user IDs
    $doctors = $pdo->query("SELECT id FROM users WHERE role = 'doctor' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($doctors)) {
        // Dr. Smith schedule (Monday-Friday, 9AM-5PM)
        $schedules = [
            [$doctors[0], 1, '09:00:00', '17:00:00', 30], // Monday
            [$doctors[0], 2, '09:00:00', '17:00:00', 30], // Tuesday
            [$doctors[0], 3, '09:00:00', '17:00:00', 30], // Wednesday
            [$doctors[0], 4, '09:00:00', '17:00:00', 30], // Thursday
            [$doctors[0], 5, '09:00:00', '15:00:00', 30], // Friday
        ];
        
        if (isset($doctors[1])) {
            // Dr. Johnson schedule (Monday-Friday, 8AM-4PM)
            $schedules = array_merge($schedules, [
                [$doctors[1], 1, '08:00:00', '16:00:00', 20], // Monday
                [$doctors[1], 2, '08:00:00', '16:00:00', 20], // Tuesday
                [$doctors[1], 3, '08:00:00', '16:00:00', 20], // Wednesday
                [$doctors[1], 4, '08:00:00', '16:00:00', 20], // Thursday
                [$doctors[1], 5, '08:00:00', '12:00:00', 20], // Friday
            ]);
        }
        
        if (isset($doctors[2])) {
            // Dr. Brown schedule (Tuesday-Saturday, 10AM-6PM)
            $schedules = array_merge($schedules, [
                [$doctors[2], 2, '10:00:00', '18:00:00', 45], // Tuesday
                [$doctors[2], 3, '10:00:00', '18:00:00', 45], // Wednesday
                [$doctors[2], 4, '10:00:00', '18:00:00', 45], // Thursday
                [$doctors[2], 5, '10:00:00', '18:00:00', 45], // Friday
                [$doctors[2], 6, '09:00:00', '13:00:00', 45], // Saturday
            ]);
        }
        
        foreach ($schedules as $schedule) {
            $sql = "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_available) 
                    VALUES (?, ?, ?, ?, ?, TRUE)
                    ON DUPLICATE KEY UPDATE 
                    start_time = VALUES(start_time), 
                    end_time = VALUES(end_time), 
                    slot_duration = VALUES(slot_duration)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($schedule);
        }
        echo "Sample doctor schedules created successfully.<br>";
    }
    
    // Insert clinic settings
    $settings = [
        ['clinic_name', 'EasyMed Private Clinic', 'Name of the clinic'],
        ['clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines', 'Physical address of the clinic'],
        ['clinic_phone', '+63-2-8123-4567', 'Main contact number'],
        ['clinic_email', 'info@easymed.com', 'Main email address'],
        ['gcash_qr_code', 'assets/images/gcash-qr.png', 'Path to GCash QR code image'],
        ['appointment_duration', '30', 'Default appointment duration in minutes'],
        ['advance_booking_days', '30', 'How many days in advance patients can book'],
        ['cancellation_hours', '24', 'Minimum hours before appointment for cancellation']
    ];
    
    foreach ($settings as $setting) {
        $sql = "INSERT INTO clinic_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($setting);
    }
    echo "Clinic settings inserted successfully.<br>";
    
    echo "<br><strong>Database setup completed successfully!</strong><br>";
    echo "<br>Default login credentials:<br>";
    echo "Admin: username = 'admin', password = 'admin123'<br>";
    echo "Doctor 1: username = 'dr_smith', password = 'doctor123'<br>";
    echo "Doctor 2: username = 'dr_johnson', password = 'doctor123'<br>";
    echo "Doctor 3: username = 'dr_brown', password = 'doctor123'<br>";
    echo "<br>Sample Patient accounts:<br>";
    echo "Patient 1: username = 'maria_garcia', password = 'patient123'<br>";
    echo "Patient 2: username = 'juan_dela_cruz', password = 'patient123'<br>";
    echo "Patient 3: username = 'ana_santos', password = 'patient123'<br>";
    echo "Patient 4: username = 'carlos_reyes', password = 'patient123'<br>";
    echo "Patient 5: username = 'lucia_martinez', password = 'patient123'<br>";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>
