-- Indexes to improve ingest and recap performance (compatible with MySQL without IF NOT EXISTS)

-- users(uid_hex)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_uid') = 0,
  'ALTER TABLE users ADD INDEX idx_users_uid (uid_hex)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- devices(id)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'devices' AND index_name = 'idx_devices_id') = 0,
  'ALTER TABLE devices ADD INDEX idx_devices_id (id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- attendance(uid_hex)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_uid') = 0,
  'ALTER TABLE attendance ADD INDEX idx_attendance_uid (uid_hex)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- attendance(ts)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_ts') = 0,
  'ALTER TABLE attendance ADD INDEX idx_attendance_ts (ts)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- holiday_calendar(holiday_date)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'holiday_calendar' AND index_name = 'idx_holiday_date') = 0,
  'ALTER TABLE holiday_calendar ADD INDEX idx_holiday_date (holiday_date)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- audit_logs(created_at) (table opsional)
SET @sql := IF (
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'audit_logs') = 1 AND
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'audit_logs' AND index_name = 'idx_audit_created_at') = 0,
  'ALTER TABLE audit_logs ADD INDEX idx_audit_created_at (created_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


