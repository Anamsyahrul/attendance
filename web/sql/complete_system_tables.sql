-- Complete System Tables for Advanced Features
-- Run this to upgrade the database with all necessary tables

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

-- 7. Restore Logs Table
CREATE TABLE IF NOT EXISTS restore_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_file VARCHAR(500) NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 8. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- 9. Attendance Rules Table
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

-- 10. Holiday Calendar Table
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

-- 11. Device Logs Table
CREATE TABLE IF NOT EXISTS device_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- 12. User Sessions Table
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

-- 13. Add missing columns to users table
ALTER TABLE users 
ADD COLUMN last_login TIMESTAMP NULL,
ADD COLUMN login_count INT DEFAULT 0,
ADD COLUMN failed_login_count INT DEFAULT 0,
ADD COLUMN last_failed_login TIMESTAMP NULL,
ADD COLUMN is_locked BOOLEAN DEFAULT FALSE,
ADD COLUMN locked_until TIMESTAMP NULL,
ADD COLUMN password_changed_at TIMESTAMP NULL,
ADD COLUMN email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN phone_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN two_factor_secret VARCHAR(32),
ADD COLUMN profile_picture VARCHAR(500),
ADD COLUMN date_of_birth DATE,
ADD COLUMN gender ENUM('male', 'female', 'other'),
ADD COLUMN address TEXT,
ADD COLUMN emergency_contact VARCHAR(255),
ADD COLUMN emergency_phone VARCHAR(20),
ADD COLUMN notes TEXT;

-- 14. Add missing columns to attendance table
ALTER TABLE attendance 
ADD COLUMN device_location VARCHAR(255),
ADD COLUMN temperature DECIMAL(4,2),
ADD COLUMN is_manual BOOLEAN DEFAULT FALSE,
ADD COLUMN manual_reason TEXT,
ADD COLUMN verified_by INT,
ADD COLUMN verification_notes TEXT,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 15. Insert default system settings
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

-- 16. Insert default attendance rules
INSERT IGNORE INTO attendance_rules (rule_name, rule_type, rule_value) VALUES
('Late Threshold', 'late_threshold', '15'),
('Absent Threshold', 'absent_threshold', '30'),
('Early Leave', 'early_leave', '16:00'),
('Overtime', 'overtime', '17:00');

-- 17. Insert sample holidays
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

-- 18. Create indexes for better performance
CREATE INDEX idx_attendance_ts ON attendance(ts);
CREATE INDEX idx_attendance_uid_hex ON attendance(uid_hex);
CREATE INDEX idx_attendance_device_id ON attendance(device_id);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_is_active ON users(is_active);

-- 19. Create views for common queries
CREATE OR REPLACE VIEW attendance_summary AS
SELECT 
    u.id as user_id,
    u.name,
    u.room,
    DATE(a.ts) as attendance_date,
    MIN(a.ts) as first_scan,
    MAX(a.ts) as last_scan,
    COUNT(*) as scan_count,
    CASE 
        WHEN MIN(a.ts) > CONCAT(DATE(a.ts), ' 07:30:00') THEN 'late'
        WHEN COUNT(*) = 0 THEN 'absent'
        ELSE 'present'
    END as status
FROM users u
LEFT JOIN attendance a ON u.uid_hex = a.uid_hex AND DATE(a.ts) = CURDATE()
GROUP BY u.id, u.name, u.room, DATE(a.ts);

-- 20. Create stored procedures
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CleanupExpiredTokens()
BEGIN
    DELETE FROM remember_tokens WHERE expires_at < NOW();
END //

CREATE PROCEDURE IF NOT EXISTS CleanupOldSessions()
BEGIN
    DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
END //

CREATE PROCEDURE IF NOT EXISTS CleanupOldLogs()
BEGIN
    DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    DELETE FROM device_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //

DELIMITER ;

-- 21. Create events for automatic cleanup
CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupExpiredTokens();

CREATE EVENT IF NOT EXISTS cleanup_old_sessions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupOldSessions();

CREATE EVENT IF NOT EXISTS cleanup_old_logs
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupOldLogs();

-- 22. Grant necessary permissions
GRANT EXECUTE ON PROCEDURE CleanupExpiredTokens TO 'root'@'localhost';
GRANT EXECUTE ON PROCEDURE CleanupOldSessions TO 'root'@'localhost';
GRANT EXECUTE ON PROCEDURE CleanupOldLogs TO 'root'@'localhost';
