# 🎯 Sistem Kehadiran RFID Enterprise

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/Anamsyahrul/attendance)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://mysql.com)

## 🌟 **FITUR UTAMA**

### 🔐 **Sistem Autentikasi & Keamanan**
- ✅ Rate Limiting (5 percobaan per 15 menit)
- ✅ Remember Me (30 hari)
- ✅ Session Management & Audit Logging
- ✅ Role-based Access Control (Admin, Guru, Orang Tua, Siswa)

### 🔔 **Sistem Notifikasi Real-time**
- ✅ Notification Bell di navbar
- ✅ Auto-refresh setiap 30 detik
- ✅ Mark as Read functionality
- ✅ Email & SMS Queue system

### 💾 **Backup & Recovery System**
- ✅ Full Database Backup (mysqldump)
- ✅ Configuration Backup
- ✅ Automated Cleanup & Restore
- ✅ Backup Management Interface

### 👥 **Manajemen Pengguna Lengkap**
- ✅ CRUD Operations
- ✅ Role Management
- ✅ Advanced Search & Filter
- ✅ Password Reset & User Activation

### 📊 **Sistem Laporan Advanced**
- ✅ Statistics Dashboard (real-time)
- ✅ Interactive Charts (Chart.js)
- ✅ Export PDF/CSV/HTML
- ✅ Monthly Trends & Class Reports

### 📱 **RFID Attendance System**
- ✅ Advanced Late Detection
- ✅ Holiday & Weekend Detection
- ✅ Automatic Notifications
- ✅ Real-time Processing

## 🚀 **INSTALASI CEPAT**

### **1. Setup Server (Laragon)**
```bash
# Clone repository
git clone https://github.com/Anamsyahrul/attendance.git
cd attendance

# Start Laragon
# Buka Laragon dan klik "Start All"

# Import database
mysql -u root attendance < web/sql/schema.sql
mysql -u root attendance < web/sql/essential_tables.sql

# Akses sistem
# http://localhost/attendance/login.php
# Login: admin / admin
```

### **2. Setup ESP32**
```cpp
// Edit config.h
#define WIFI_SSID "YourWiFi"
#define WIFI_PASS "YourPassword"
#define API_BASE "http://192.168.1.10/attendance/api"
#define DEVICE_ID "esp32-01"
#define DEVICE_SECRET "your-secret-key"
```

### **3. Wiring ESP32**
```
RC522 → ESP32
SDA   → GPIO5    SCK   → GPIO18
MOSI  → GPIO23   MISO  → GPIO19
RST   → GPIO27   3.3V  → 3V3
GND   → GND

microSD → ESP32
CS      → GPIO4
SCK     → GPIO18 (shared)
MOSI    → GPIO23 (shared)
MISO    → GPIO19 (shared)
VCC     → 3V3    GND   → GND
```

## 📱 **CARA PENGGUNAAN**

### **🔐 Login Sistem**
```
URL: http://localhost/attendance/login.php
Admin: admin / admin
```

### **📊 Dashboard Admin**
- **Dashboard** - Statistik lengkap sistem
- **Manajemen Pengguna** - Kelola semua pengguna
- **Laporan** - Laporan dengan grafik interaktif
- **Backup & Restore** - Kelola backup database
- **Pengaturan** - Konfigurasi sistem

## 🗄️ **STRUKTUR DATABASE**

### **Tabel Utama**
- `users` - Data pengguna dengan role-based access
- `attendance` - Data kehadiran RFID
- `devices` - Data perangkat ESP32
- `settings` - Konfigurasi sistem

### **Tabel Advanced**
- `remember_tokens` - Token remember me
- `audit_logs` - Log audit sistem
- `notifications` - Sistem notifikasi
- `email_queue` - Antrian email
- `backup_logs` - Log backup
- `system_settings` - Pengaturan sistem
- `attendance_rules` - Aturan kehadiran
- `holiday_calendar` - Kalender libur

## 🔧 **KONFIGURASI**

### **File Konfigurasi**
```php
// web/config.php
'DB_HOST' => '127.0.0.1',
'DB_NAME' => 'attendance',
'DB_USER' => 'root',
'DB_PASS' => '',
'APP_TZ' => 'Asia/Jakarta',
'SCHOOL_NAME' => 'SMA Bustanul Arifin',
'ADMIN_USER' => 'admin',
'ADMIN_PASS' => 'admin',
'LATE_THRESHOLD' => 15, // menit
'SCHOOL_START' => '07:30',
```

## 📊 **STATISTIK SISTEM**

### **Data Saat Ini**
- **202 Pengguna** terdaftar
- **15,691 Record** kehadiran
- **14 Tabel** database
- **6 API Endpoint** aktif
- **10+ Fitur** utama

## 🛠️ **TROUBLESHOOTING**

### **Masalah Umum**
1. **Database tidak terhubung** - Pastikan MySQL/Laragon berjalan
2. **Login tidak berfungsi** - Cek kredensial admin: admin / admin
3. **RFID tidak terdeteksi** - Cek wiring RC522 ke ESP32
4. **Notifikasi tidak muncul** - Cek tabel `notifications` di database

## 📚 **DOKUMENTASI LENGKAP**

### **File Dokumentasi**
- `docs/PROJECT_CHARTER.md` - Charter proyek
- `docs/ASSUMPTIONS.md` - Asumsi sistem
- `docs/OPERATING_PRINCIPLES.md` - Prinsip operasi
- `CHANGELOG.md` - Log perubahan

### **Test Scripts**
- `test_final_system_check.php` - Pengecekan sistem lengkap
- `test_login_system.php` - Test sistem login
- `test_notification_system.php` - Test notifikasi

## 🤝 **KONTRIBUSI**

1. Fork repository
2. Buat branch fitur (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📄 **LISENSI**

Distributed under the MIT License. See `LICENSE` for more information.

## 👥 **TIM PENGEMBANG**

- **Anam Syahrul** - *Lead Developer* - [@Anamsyahrul](https://github.com/Anamsyahrul)
- **AI Assistant** - *System Implementation* - [Claude Sonnet 4]

## 📞 **DUKUNGAN**

- **Email**: ppba.1965@gmail.com
- **GitHub**: [@Anamsyahrul/attendance](https://github.com/Anamsyahrul/attendance)

---

**🎉 Sistem Kehadiran RFID Enterprise - Sempurna untuk Sekolah Modern! 🎉**
