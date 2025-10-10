# Assumptions

Hardware
- ESP32 DevKit v1 (seri WROOM-32)
- RC522 (SPI)
- Modul microSD (SPI)
- Buzzer pasif + LED bawaan board (GPIO2)

Pinout (default, dapat diubah di config.h)
- SPI: SCK=18, MISO=19, MOSI=23
- RC522: SS=5, RST=27
- microSD: CS=4
- LED: 2, BUZZER: 15

Network
- Firmware mengirim ke API_BASE, default contoh http://192.168.1.10/attendance/api
- Perangkat dan server pada jaringan yang sama.

Time
- ESP32 sync NTP saat Wi‑Fi tersedia; offline tetap mencatat waktu lokal; ketepatan setelah sinkronisasi pertama.

Software Versions
- Arduino-ESP32 core 2.0.11+
- MFRC522 2.0.1+
- ArduinoJson 6.21+
- PHP 8.1+, MySQL/MariaDB 10.4+

Security
- HMAC: message = device_id|ts|nonce|<events_json_minified>
- DEVICE_SECRET disimpan di firmware (config.h) & DB devices.

Constraints
- Simpel, tanpa framework berat; dashboard Bootstrap + Vanilla JS.

