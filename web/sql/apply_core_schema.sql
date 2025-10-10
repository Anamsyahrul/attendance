-- Core schema for RFID attendance (safe for MySQL 8)
CREATE DATABASE IF NOT EXISTS attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance;

-- users: extended for roles/login + UID mapping
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE,
  password VARCHAR(255),
  email VARCHAR(100),
  role ENUM('admin','teacher','parent','student') DEFAULT 'student',
  name VARCHAR(100) NOT NULL,
  uid_hex VARCHAR(32) NOT NULL UNIQUE,
  room VARCHAR(100) NOT NULL DEFAULT '',
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- devices
CREATE TABLE IF NOT EXISTS devices (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  device_secret VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- attendance
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  device_id VARCHAR(64) NOT NULL,
  ts DATETIME NOT NULL,
  uid_hex VARCHAR(32) NOT NULL,
  raw_json JSON NULL,
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_att_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- holiday calendar
CREATE TABLE IF NOT EXISTS holiday_calendar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  holiday_name VARCHAR(255) NOT NULL,
  holiday_date DATE NOT NULL,
  holiday_type ENUM('national','religious','school','custom') DEFAULT 'custom',
  is_recurring TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- audit logs (minimal)
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  table_name VARCHAR(100),
  record_id INT NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- indexes via information_schema checks (avoid errors if already exist)
SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='attendance' AND index_name='idx_att_ts')=0,
  'ALTER TABLE attendance ADD INDEX idx_att_ts (ts)', 'SELECT 1');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='attendance' AND index_name='idx_att_uid')=0,
  'ALTER TABLE attendance ADD INDEX idx_att_uid (uid_hex)', 'SELECT 1');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='attendance' AND index_name='idx_att_device')=0,
  'ALTER TABLE attendance ADD INDEX idx_att_device (device_id)', 'SELECT 1');
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='users' AND index_name='idx_users_uid')=0,
  'ALTER TABLE users ADD INDEX idx_users_uid (uid_hex)', 'SELECT 1');
PREPARE s4 FROM @sql; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='devices' AND index_name='idx_devices_id')=0,
  'ALTER TABLE devices ADD INDEX idx_devices_id (id)', 'SELECT 1');
PREPARE s5 FROM @sql; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @sql := IF ((SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name='holiday_calendar' AND index_name='idx_holiday_date')=0,
  'ALTER TABLE holiday_calendar ADD INDEX idx_holiday_date (holiday_date)', 'SELECT 1');
PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

-- seed admin and device
INSERT INTO users (username,password,email,role,name,uid_hex,room,is_active)
SELECT * FROM (
  SELECT 'admin' AS username,
         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password,
         'admin@school.com' AS email,
         'admin' AS role,
         'Administrator' AS name,
         'ffffffff' AS uid_hex,
         'Admin' AS room,
         1 AS is_active
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='admin');

INSERT INTO devices (id,name,device_secret,is_active)
VALUES ('DEVICE-01','Main Gate','anamganteng123',1)
ON DUPLICATE KEY UPDATE name=VALUES(name), device_secret=VALUES(device_secret), is_active=VALUES(is_active);


