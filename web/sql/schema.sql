-- Schema for Attendance System
-- Default charset & engine
CREATE DATABASE IF NOT EXISTS attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  uid_hex VARCHAR(32) NOT NULL UNIQUE,
  room VARCHAR(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- Devices (id is string to match firmware DEVICE_ID)
CREATE TABLE IF NOT EXISTS devices (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  device_secret VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Attendance
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  device_id VARCHAR(64) NOT NULL,
  ts DATETIME NOT NULL,
  uid_hex VARCHAR(32) NOT NULL,
  raw_json JSON NULL,
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_att_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_att_ts (ts),
  INDEX idx_att_uid (uid_hex),
  INDEX idx_att_device (device_id)
) ENGINE=InnoDB;

-- Settings untuk konfigurasi sistem
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB;

