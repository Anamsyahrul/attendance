# 🚀 Panduan Instalasi Sistem Kehadiran RFID untuk Pemula

## 📋 Daftar Isi
1. [Persiapan](#persiapan)
2. [Instalasi Software](#instalasi-software)
3. [Setup Database](#setup-database)
4. [Konfigurasi Sistem](#konfigurasi-sistem)
5. [Perakitan Hardware](#perakitan-hardware)
6. [Testing Sistem](#testing-sistem)
7. [Troubleshooting](#troubleshooting)

---

## 1. 📦 Persiapan

### Yang Anda Butuhkan:
- **Komputer Windows 10/11**
- **Laragon** (untuk server lokal)
- **Browser** (Chrome/Firefox/Edge)
- **Komponen Hardware** (ESP32, RC522, dll)

### Download Files:
1. Download Laragon: https://laragon.org/download/
2. Clone project: `git clone https://github.com/Anamsyahrul/attendance.git`

---

## 2. 💻 Instalasi Software

### Langkah 1: Install Laragon
```
1. Buka file Laragon yang didownload
2. Klik "Next" terus sampai selesai
3. Jalankan Laragon setelah install
```

### Langkah 2: Start Server
```
1. Buka Laragon
2. Klik tombol "Start All" (hijau)
3. Tunggu sampai semua service berjalan
```

### Langkah 3: Copy Project
```
1. Extract folder "attendance" ke C:\laragon\www\
2. Pastikan path: C:\laragon\www\attendance\
```

---

## 3. 🗄️ Setup Database

### Langkah 1: Buka phpMyAdmin
```
1. Di Laragon, klik tombol "Database"
2. Atau buka: http://localhost/phpmyadmin
3. Username: root
4. Password: (kosong)
```

### Langkah 2: Buat Database
```
1. Klik "New" di kiri atas
2. Database name: attendance
3. Klik "Create"
```

### Langkah 3: Import Data
```
1. Klik database "attendance"
2. Klik tab "Import"
3. Pilih file: attendance/web/sql/schema.sql
4. Klik "Go"
5. Lakukan lagi untuk: essential_tables.sql
```

---

## 4. ⚙️ Konfigurasi Sistem

### Langkah 1: Buka Sistem
```
Buka browser dan akses: http://localhost/attendance/login.php
```

### Langkah 2: Login Admin
```
Username: admin
Password: admin
```

### Langkah 3: Setup Awal
```
1. Masuk ke menu "Pengaturan"
2. Isi nama sekolah
3. Atur jam masuk (07:30)
4. Simpan pengaturan
```

---

## 5. 🔧 Perakitan Hardware

### Komponen Dasar:
- 1x ESP32 DevKit v1
- 1x RC522 RFID Reader
- 1x Breadboard
- Kabel jumper (male-male, male-female)
- 2x LED (hijau, merah)
- 1x Buzzer
- 2x Resistor 220Ω
- Power supply 5V

### Langkah Perakitan:

#### A. Siapkan Komponen
```
□ ESP32 DevKit v1
□ Modul RC522 RFID
□ Breadboard
□ Kabel jumper berbagai warna
□ 2x LED (hijau + merah)
□ 1x Buzzer
□ 2x Resistor 220Ω
□ Power supply 5V
```

#### B. Sambungkan RC522 ke ESP32
```
RC522 → ESP32
SDA   → GPIO5  (kabel orange)
SCK   → GPIO18 (kabel kuning)
MOSI  → GPIO23 (kabel biru)
MISO  → GPIO19 (kabel hijau)
RST   → GPIO27 (kabel ungu)
3.3V  → 3V3   (kabel merah)
GND   → GND   (kabel hitam)
```

#### C. Sambungkan LED
```
LED Hijau → GPIO25 → Resistor 220Ω → GND
LED Merah → GPIO26 → Resistor 220Ω → GND
```

#### D. Sambungkan Buzzer
```
Buzzer + → GPIO15
Buzzer - → GND
```

#### E. Sambungkan Power
```
Power Supply +5V → ESP32 VIN
Power Supply GND → ESP32 GND
```

### Checklist Perakitan:
```
□ ESP32 di breadboard
□ RC522 terhubung (8 kabel)
□ LED hijau + resistor ke GPIO25
□ LED merah + resistor ke GPIO26
□ Buzzer ke GPIO15
□ Power supply ke VIN
□ Semua GND terhubung
```

---

## 6. 🧪 Testing Sistem

### Test 1: Web Interface
```
1. Buka: http://localhost/attendance/login.php
2. Login: admin / admin
3. Cek dashboard muncul
```

### Test 2: Database
```
1. Masuk phpMyAdmin
2. Cek database "attendance"
3. Lihat tabel-tabel ada
```

### Test 3: Hardware (Opsional)
```
1. Upload firmware kosong ke ESP32
2. Cek LED power menyala
3. Test blink LED via Arduino IDE
```

---

## 7. 🛠️ Troubleshooting

### Masalah: Laragon tidak bisa start
**Solusi:**
```
1. Jalankan sebagai Administrator
2. Cek port 80/3306 tidak digunakan
3. Restart komputer
```

### Masalah: Database tidak terhubung
**Solusi:**
```
1. Pastikan MySQL running di Laragon
2. Cek username: root, password: kosong
3. Import ulang file SQL
```

### Masalah: Tidak bisa login
**Solusi:**
```
Username: admin
Password: admin
```

### Masalah: Hardware tidak berfungsi
**Solusi:**
```
1. Cek koneksi kabel
2. Pastikan power supply 5V
3. RC522 pakai 3.3V, bukan 5V
```

---

## 🎉 Selesai!

Jika semua langkah di atas berhasil, sistem Anda sudah siap digunakan!

### Langkah Selanjutnya:
1. Tambah pengguna di menu "Manajemen Pengguna"
2. Program kartu RFID
3. Test scan kartu
4. Generate laporan

### Butuh Bantuan?
- Baca panduan lengkap: `PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html`
- Email: ppba.1965@gmail.com
- GitHub: @Anamsyahrul/attendance

---

**Versi: 1.0 - Panduan Pemula**
**Tanggal: Desember 2024**
