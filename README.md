# Sistem Monitoring Kehadiran (ESP32 + RC522 + microSD + PHP/MySQL)

NON-NEGOTIABLE GOALS
- Offline-first: log ke microSD; sinkron otomatis saat online.
- Aman: HMAC antar perangkat–server; kredensial via .env.
- Simpel & cepat di-deploy (Windows + Laragon/XAMPP).
- Timezone Asia/Jakarta (UTC+7) di seluruh komponen.
- Output file lengkap, tanpa placeholder.

Ringkasan
Sistem absensi RFID menggunakan ESP32 + RC522. Tiap scan disimpan ke microSD (CSV) dan diantrikan. Saat Wi‑Fi tersedia, antrian dikirim batch ke server lokal (Laragon/XAMPP) via API terotentikasi HMAC. Dashboard Bootstrap menampilkan daftar kehadiran, filter tanggal, ringkasan, dan export CSV.

Struktur
- firmware/attendance_esp32/attendance_esp32.ino
- firmware/attendance_esp32/config.h
- web/api/ingest.php
- web/api/stats/today.php
- web/public/index.php (juga melayani export CSV via /attendance/api/attendance.csv)
- web/public/users.php
- web/public/assets/style.css
- web/sql/schema.sql
- web/sql/seed.sql
- web/bootstrap.php
- web/.htaccess
- web/.env.sample.php
- docs/PROJECT_CHARTER.md, docs/ASSUMPTIONS.md, docs/OPERATING_PRINCIPLES.md
- CHANGELOG.md, PROMPT_ANCHOR.txt

Perangkat & Biaya (target < Rp 300k)
- ESP32 DevKit v1
- RC522 RFID + kartu/tag Mifare
- Modul microSD (SPI)
- LED/buzzer

Wiring RC522 ↔ ESP32 (contoh DevKit v1)
- RC522 SDA/SS → GPIO5
- RC522 SCK    → GPIO18
- RC522 MOSI   → GPIO23
- RC522 MISO   → GPIO19
- RC522 RST    → GPIO27
- RC522 3.3V   → 3V3
- RC522 GND    → GND

Wiring microSD (SPI) ↔ ESP32
- SD CS   → GPIO4
- SD SCK  → GPIO18 (shared)
- SD MOSI → GPIO23 (shared)
- SD MISO → GPIO19 (shared)
- SD VCC  → 3V3/5V (sesuai modul)
- SD GND  → GND

Buzzer/LED
- LED → GPIO2 (onboard)
- Buzzer +R → GPIO15 (via resistor), - ke GND

Firmware (Arduino/ESP32)
Library (Arduino IDE → Library Manager):
- ESP32 core (2.0.11+)
- MFRC522 by GithubCommunity (2.0.1+)
- ArduinoJson by Benoit Blanchon (6.21+)
- Adafruit SSD1306 (2.5.9+) dan Adafruit GFX Library (1.11+)
- RTClib by Adafruit (2.1.3+)

Fitur Firmware
- Debounce scan UID 2 detik.
- Log CSV di microSD: timestamp_iso8601,uid_hex,device_id.
- Antri event di /events_queue.jsonl; flush saat Wi‑Fi online.
- Batch POST JSON ke http://<HOST>/attendance/api/ingest.php dengan HMAC-SHA256.
- Indikator LED: Hijau (berhasil), Merah (gagal); buzzer beep.
- OLED 0.96" I2C: tampilkan jam, status Wi‑Fi, dan saat scan tampilkan Nama/UID + status.
- RTC DS3231: menjaga waktu akurat saat offline; otomatis sinkron saat NTP tersedia.
- Optional: mapping nama lokal dari microSD file /students.csv (format: uid_hex,name,room).
- Konfigurasi di firmware/attendance_esp32/config.h (Wi‑Fi, API_BASE, DEVICE_ID/SECRET, pinout).

Build & Flash (Arduino IDE)
1) Buka Arduino IDE → File → Open → firmware/attendance_esp32/attendance_esp32.ino
2) Tools:
   - Board: "ESP32 Dev Module" (atau sesuai board)
   - Flash size & Port: pilih sesuai
3) Edit firmware/attendance_esp32/config.h:
   - WIFI_SSID, WIFI_PASS
   - API_BASE (mis. "http://192.168.1.10/attendance/api")
   - DEVICE_ID, DEVICE_SECRET (harus match row di database devices)
4) Sketch → Upload.

Opsional (PlatformIO)
- Buat proyek baru ESP32, salin dua file firmware. Pastikan libs terpasang.

Server Lokal (Laragon/XAMPP)
1) Folder ini sudah siap di c:\\laragon\\www\\attendance
2) Salin web/.env.sample.php menjadi web/.env.php, lalu sesuaikan:
   - DB_HOST=127.0.0.1, DB_NAME=attendance, DB_USER=root, DB_PASS= (default Laragon kosong)
   - APP_TZ=Asia/Jakarta
   - ADMIN_USER=admin, ADMIN_PASS=kelompok2 (ubah di sini untuk ganti kredensial login dashboard)
   - WEEKLY_OFF_DAYS=6,7 (mis. 6=Sabtu, 7=Minggu; kosongkan jika tidak ada libur rutin)
   - AUTO_CREATE_UNKNOWN=false (set true jika ingin auto user untuk UID baru)
3) Buat database dan import schema + seed:
   - Jalankan MySQL/MariaDB
   - Import: web/sql/schema.sql lalu web/sql/seed.sql
4) Pastikan Apache aktif. Akses http://localhost/attendance/public/ (atau http://localhost/attendance/)
   - Saat diminta login, gunakan username <code>admin</code> dan password <code>kelompok2</code>.
   - Setelah masuk, buka menu Pengaturan untuk mengganti username/password admin kapan saja.
   - Tentukan juga hari libur mingguan (mis. Jumat atau Sabtu-Minggu) langsung dari Pengaturan; tambah tanggal khusus di field libur manual.

API & Keamanan
- Endpoint: POST /attendance/api/ingest.php
- Body JSON: { device_id, nonce, ts, hmac, events: [{ uid, ts }] }
- HMAC-SHA256 atas string: "device_id|ts|nonce|<events_json_minified>" dengan DEVICE_SECRET.
- Backend verifikasi device aktif dan HMAC. Mapping UID → users, insert ke attendance.
- Stats: GET /attendance/api/stats/today.php
- Export CSV: GET /attendance/api/attendance.csv?from=YYYY-MM-DD&to=YYYY-MM-DD (disalurkan ke public/index.php?export=1 oleh .htaccess)

Dashboard (Bootstrap 5)
- Halaman utama: filter tanggal, cari nama/UID, pagination, kartu ringkasan.
- Export CSV: tombol yang memanggil endpoint di atas.
- Users: CRUD sederhana (tambah, list, delete minimal).

Timezone
- Default: Asia/Jakarta. PHP set di bootstrap; ESP32 setenv("WIB-7").

Troubleshooting Umum
- MFRC522 tidak terdeteksi: cek wiring SDA/SS & RST; pastikan 3.3V, bukan 5V.
- SD gagal mount: cek CS pin (config.h), format FAT32, periksa supply.
- Kompilasi Arduino gagal (mbedtls/md.h): pastikan gunakan core ESP32 (bukan AVR), Board: ESP32 Dev.
- HTTP timeout: cek API_BASE, Wi‑Fi SSID/PASS, firewall Windows.
- HMAC invalid: pastikan DEVICE_SECRET di firmware sama dengan di tabel devices.
- Waktu 1970-01-01: butuh sync NTP; sambungkan Wi‑Fi sebentar agar waktu akurat.

Checklist Verifikasi
- [ ] Firmware kompilasi sukses (library terpasang).
- [ ] microSD menulis CSV saat scan tanpa Wi‑Fi.
- [ ] Saat Wi‑Fi on, event ter‑POST dan antrian kosong.
- [ ] schema.sql + seed.sql berhasil di‑import; .env.php dikonfigurasi.
- [ ] ingest.php menerima payload sample HMAC-valid → { ok: true }.
- [ ] Dashboard menampilkan data, filter dan export CSV berfungsi.

Contoh Payload Uji (curl)
- Set DEVICE_SECRET sesuai DB.
- Message = device_id|ts|nonce|events_json
- HMAC = hex sha256.

curl -X POST "http://localhost/attendance/api/ingest.php" \
  -H "Content-Type: application/json" \
  -d '{
    "device_id":"esp32-01",
    "nonce":"abc123",
    "ts":"1717400000",
    "events":[{"uid":"04a1b2c3d4","ts":"2025-05-01T08:00:00+07:00"}],
    "hmac":"<isi hmac heks>"
  }'

Catatan
- File .env.php hanya berisi kredensial. Jangan commit.
- Index dan query sudah disiapkan untuk filter tanggal dan pencarian sederhana.

Langkah Setup Cepat (Beginner)
1) Siapkan server lokal
   - Pastikan Laragon jalan. Buka http://localhost/attendance/ → jika error, restart Apache dan MySQL.
   - Cek .env: attendance/web/.env.php (DB root tanpa password).
2) Import database
   - phpMyAdmin → buat DB attendance (utf8mb4_unicode_ci) → import schema.sql lalu seed.sql.
3) Siapkan firmware
   - Arduino IDE → install library yang disebut di atas.
   - Buka firmware/attendance_esp32/attendance_esp32.ino
   - Edit config.h: WIFI_SSID/PASS, API_BASE (contoh http://192.168.1.10/attendance/api), DEVICE_ID/SECRET (samakan dengan DB).
4) Wiring
   - RC522 via SPI (lihat tabel di config.h / README bagian wiring).
   - microSD via SPI (CS=GPIO4).
   - OLED I2C: SDA=21, SCL=22, VCC=3V3, GND=GND.
   - RTC DS3231 I2C: SDA=21, SCL=22, VCC=3V3, GND=GND.
   - LED Hijau=GPIO25 (dengan resistor), LED Merah=GPIO26, Buzzer=GPIO15.
5) Optional: Nama siswa di OLED
   - Buat file students.csv di microSD (root) dengan isi contoh:
     04a1b2c3d4,Alice,12A
     03deadbeef1,Bob,12B
     02cafebabe2,Charlie,12C
   - Masukkan microSD ke modul, boot ulang ESP32.
6) Upload firmware
   - Sambungkan ESP32 → pilih COM Port → Upload.
7) Uji
   - Layar OLED menampilkan jam + Wi‑Fi status. Scan kartu → terdengar beep, OLED tampilkan Nama/UID & status (Queued/Saved offline).
   - Buka dashboard → data tampil setelah sinkron (Wi‑Fi ON). Gunakan School Recap untuk rekap harian per kelas.
