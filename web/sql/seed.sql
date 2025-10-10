USE attendance;

-- Seed device (adjust secret to match firmware/config.h)
INSERT INTO devices (id, name, device_secret, is_active) VALUES
  ('esp32-01', 'Main Door', 'changeme_device_secret', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), device_secret=VALUES(device_secret), is_active=VALUES(is_active);

-- Seed users
INSERT INTO users (name, uid_hex, room) VALUES
  ('Alice',  '04a1b2c3d4', 'Room A'),
  ('Bob',    '03deadbeef1', 'Room B'),
  ('Charlie','02cafebabe2', 'Room C')
ON DUPLICATE KEY UPDATE name=VALUES(name), room=VALUES(room);

