# Project Charter — IoT Attendance (ESP32 + Web Lokal)

Goal
Menyediakan sistem monitoring kehadiran biaya rendah (< Rp300k hardware) yang bekerja offline-first dengan ESP32 + RC522 + microSD dan sinkronisasi ke server lokal (Laragon/XAMPP) melalui API aman (HMAC), dengan dashboard ringan.

Scope
- Firmware ESP32 (Arduino): baca RFID, log ke microSD (CSV), antri event JSONL, kirim batch via HTTP saat online.
- Backend PHP/MySQL: endpoint ingest (HMAC), stats, export CSV; dashboard Bootstrap (list/filter/pagination), CRUD users minimal.
- Dokumentasi lengkap untuk wiring, build, setup server, dan troubleshooting.

Non-Goals
- Tidak ada cloud dependency, tidak ada fitur kompleks HR (schedule, shift).
- Tidak ada manajemen hak akses multiuser.

Success Criteria
- Event tersimpan offline dan tersinkron saat koneksi kembali.
- HMAC diverifikasi; data masuk DB dengan benar.
- Dashboard menampilkan data dan export CSV berjalan.

Stakeholders
- Siswa/Mahasiswa PKL sebagai developer/operator.
- Pembimbing/Institusi sebagai pengguna dan evaluator.

