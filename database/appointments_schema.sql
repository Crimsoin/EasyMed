-- EasyMed Appointments Table
-- This script creates the appointments table and related tables for the appointment management system

-- Create appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_patient_date (patient_id, appointment_date),
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_status (status),
    INDEX idx_appointment_datetime (appointment_date, appointment_time)
);

-- Create notifications table for appointment notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Create activity_logs table for system activity logging
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity (user_id, activity_type),
    INDEX idx_created_at (created_at)
);

-- Create clinic_settings table for system settings
CREATE TABLE IF NOT EXISTS clinic_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Insert default clinic settings
INSERT IGNORE INTO clinic_settings (setting_key, setting_value, description) VALUES
('clinic_name', 'EasyMed Clinic', 'Name of the medical clinic'),
('clinic_phone', '(555) 123-4567', 'Main phone number'),
('clinic_email', 'info@easymed.com', 'Main email address'),
('clinic_address', '123 Medical Drive, Health City, HC 12345', 'Physical address'),
('appointment_duration', '30', 'Default appointment duration in minutes'),
('booking_advance_days', '30', 'How many days in advance patients can book'),
('working_hours_start', '08:00', 'Clinic opening time'),
('working_hours_end', '18:00', 'Clinic closing time'),
('timezone', 'America/New_York', 'Clinic timezone');

-- Sample appointment data (optional - remove in production)
-- INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES
-- (3, 2, '2025-08-25', '10:00:00', 'Regular checkup', 'confirmed'),
-- (4, 2, '2025-08-25', '10:30:00', 'Follow-up consultation', 'pending'),
-- (5, 6, '2025-08-26', '14:00:00', 'Skin examination', 'confirmed');
