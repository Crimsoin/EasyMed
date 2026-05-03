CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    first_name VARCHAR(50),
                    last_name VARCHAR(50),
                    profile_image VARCHAR(255),
                    role VARCHAR(20) DEFAULT 'patient' CHECK (role IN ('admin', 'doctor', 'patient')),
                    is_active INTEGER DEFAULT 1,
                    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('active', 'inactive', 'pending')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                , email_verified INTEGER DEFAULT 0, phone TEXT, date_of_birth TEXT, gender TEXT, address TEXT, otp_code VARCHAR(6), otp_expires_at DATETIME);
CREATE TABLE doctors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    specialty VARCHAR(100),
                    license_number VARCHAR(50),
                    phone VARCHAR(20),
                    experience_years INTEGER DEFAULT 0,
                    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
                    schedule_days TEXT,
                    schedule_time_start TIME,
                    schedule_time_end TIME,
                    biography TEXT,
                    is_available INTEGER DEFAULT 1,
                    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                );
CREATE TABLE patients (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    date_of_birth DATE,
                    gender VARCHAR(20) CHECK (gender IN ('male', 'female', 'other')),
                    phone VARCHAR(20),
                    address TEXT,
                    emergency_contact VARCHAR(100),
                    emergency_phone VARCHAR(20),
                    blood_type VARCHAR(5),
                    allergies TEXT,
                    medical_history TEXT,
                    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                );
CREATE TABLE activity_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    action VARCHAR(100),
                    description TEXT,
                    ip_address VARCHAR(45),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                );
CREATE TABLE notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    title VARCHAR(255),
                    message TEXT,
                    type VARCHAR(50),
                    is_read INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                );
CREATE TABLE clinic_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key VARCHAR(100) UNIQUE,
                    setting_value TEXT,
                    description TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
CREATE TABLE system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_date ON activity_logs(created_at);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE TABLE doctor_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        slot_duration INTEGER NOT NULL DEFAULT 30,
        is_available INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE(doctor_id, day_of_week)
    );
CREATE TABLE doctor_breaks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        break_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        reason TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    );
CREATE TABLE doctor_unavailable (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        unavailable_date DATE NOT NULL,
        reason TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE(doctor_id, unavailable_date)
    );
CREATE TABLE lab_offers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        is_active INTEGER DEFAULT 1,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT
                    , description TEXT, price DECIMAL(10,2));
CREATE TABLE lab_offer_doctors (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        lab_offer_id INTEGER NOT NULL,
                        doctor_id INTEGER NOT NULL,
                        created_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY(lab_offer_id) REFERENCES lab_offers(id) ON DELETE CASCADE,
                        FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
                    );
CREATE TABLE appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INTEGER,
    reason_for_visit TEXT,
    status TEXT CHECK(status IN ('pending', 'rescheduled', 'scheduled', 'completed', 'cancelled', 'no_show', 'ongoing')) DEFAULT 'pending',
    notes TEXT,
    patient_info TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    first_name TEXT, 
    last_name TEXT, 
    phone_number TEXT, 
    email TEXT, 
    address TEXT, 
    relationship TEXT, 
    illness TEXT, 
    purpose TEXT, 
    agreed_no_refund_policy INTEGER DEFAULT 0
, lab_request_file TEXT, patient_dob DATE, patient_gender TEXT, reschedule_reason TEXT);
CREATE INDEX idx_patient_date ON appointments(patient_id, appointment_date);
CREATE INDEX idx_doctor_date ON appointments(doctor_id, appointment_date);
CREATE INDEX idx_status ON appointments(status);
CREATE INDEX idx_appointment_datetime ON appointments(appointment_date, appointment_time);
CREATE TABLE payments (
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
);
CREATE TABLE reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_id INTEGER,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
    is_anonymous INTEGER DEFAULT 0, 
    is_approved INTEGER DEFAULT 1,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);
CREATE INDEX idx_reviews_doctor ON reviews(doctor_id);
