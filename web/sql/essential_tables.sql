-- Essential Tables for Complete System
-- Run this to add essential tables for advanced features

-- 1. Remember Tokens Table
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- 2. Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- 3. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- 4. Email Queue Table
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 5. SMS Queue Table
CREATE TABLE IF NOT EXISTS sms_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 6. Backup Logs Table
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'config') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 7. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- 8. Attendance Rules Table
CREATE TABLE IF NOT EXISTS attendance_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(255) NOT NULL,
    rule_type ENUM('late_threshold', 'absent_threshold', 'early_leave', 'overtime') NOT NULL,
    rule_value VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rule_type (rule_type),
    INDEX idx_is_active (is_active)
);

-- 9. Holiday Calendar Table
CREATE TABLE IF NOT EXISTS holiday_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(255) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('national', 'religious', 'school', 'custom') DEFAULT 'custom',
    is_recurring BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_holiday_date (holiday_date),
    INDEX idx_holiday_type (holiday_type)
);

-- 10. User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_last_activity (last_activity)
);

-- 11. Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('site_name', 'Sistem Kehadiran RFID'),
('site_description', 'Sistem kehadiran berbasis RFID untuk sekolah'),
('timezone', 'Asia/Jakarta'),
('date_format', 'd/m/Y'),
('time_format', 'H:i:s'),
('items_per_page', '25'),
('session_timeout', '3600'),
('max_login_attempts', '5'),
('login_lockout_duration', '900'),
('backup_retention_days', '30'),
('notification_email', '1'),
('notification_sms', '0'),
('maintenance_mode', '0'),
('maintenance_message', 'Sistem sedang dalam perawatan');

-- 12. Insert default attendance rules
INSERT IGNORE INTO attendance_rules (rule_name, rule_type, rule_value) VALUES
('Late Threshold', 'late_threshold', '15'),
('Absent Threshold', 'absent_threshold', '30'),
('Early Leave', 'early_leave', '16:00'),
('Overtime', 'overtime', '17:00');

-- 13. Insert sample holidays
INSERT IGNORE INTO holiday_calendar (holiday_name, holiday_date, holiday_type) VALUES
('Tahun Baru', '2024-01-01', 'national'),
('Hari Raya Idul Fitri', '2024-04-10', 'religious'),
('Hari Raya Idul Fitri', '2024-04-11', 'religious'),
('Hari Buruh', '2024-05-01', 'national'),
('Hari Raya Waisak', '2024-05-23', 'religious'),
('Hari Kemerdekaan', '2024-08-17', 'national'),
('Hari Raya Idul Adha', '2024-06-16', 'religious'),
('Tahun Baru Islam', '2024-07-07', 'religious'),
('Hari Natal', '2024-12-25', 'religious');


