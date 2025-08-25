-- Doctor Schedule Management Tables
-- Run this SQL to create the necessary tables for schedule functionality

-- Doctor weekly schedules
CREATE TABLE IF NOT EXISTS doctor_schedules (
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
);

-- Doctor breaks (scheduled breaks during working hours)
CREATE TABLE IF NOT EXISTS doctor_breaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    break_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_date (doctor_id, break_date)
);

-- Doctor unavailable dates (full day unavailable)
CREATE TABLE IF NOT EXISTS doctor_unavailable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    unavailable_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doctor_date (doctor_id, unavailable_date)
);

-- Insert sample schedules for existing doctors
INSERT IGNORE INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_available) VALUES
-- Dr. Smith (assuming user_id = 1)
(1, 1, '09:00:00', '17:00:00', 30, TRUE), -- Monday
(1, 2, '09:00:00', '17:00:00', 30, TRUE), -- Tuesday
(1, 3, '09:00:00', '17:00:00', 30, TRUE), -- Wednesday
(1, 4, '09:00:00', '17:00:00', 30, TRUE), -- Thursday
(1, 5, '09:00:00', '15:00:00', 30, TRUE), -- Friday

-- Dr. Johnson (assuming user_id = 2)
(2, 1, '08:00:00', '16:00:00', 20, TRUE), -- Monday
(2, 2, '08:00:00', '16:00:00', 20, TRUE), -- Tuesday
(2, 3, '08:00:00', '16:00:00', 20, TRUE), -- Wednesday
(2, 4, '08:00:00', '16:00:00', 20, TRUE), -- Thursday
(2, 5, '08:00:00', '12:00:00', 20, TRUE), -- Friday

-- Dr. Brown (assuming user_id = 3)
(3, 2, '10:00:00', '18:00:00', 45, TRUE), -- Tuesday
(3, 3, '10:00:00', '18:00:00', 45, TRUE), -- Wednesday
(3, 4, '10:00:00', '18:00:00', 45, TRUE), -- Thursday
(3, 5, '10:00:00', '18:00:00', 45, TRUE), -- Friday
(3, 6, '09:00:00', '13:00:00', 45, TRUE); -- Saturday
