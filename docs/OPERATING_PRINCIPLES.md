# Operating Principles

- Offline-first: semua scan disimpan lokal (CSV + antrian). Tidak kehilangan data saat offline.
- Batch sync: kirim batch kecil berkala untuk hemat jaringan dan robust.
- Simplicity: minim dependensi, setup cepat di Windows + Laragon/XAMPP).
- Security-light: HMAC-SHA256, kredensial via .env; tidak menyimpan password di kode.
- Observability: log ringkas via Serial (DEBUG), indikator LED/buzzer.
- Maintainability: struktur folder jelas, fungsi terpisah, prepared statements (PDO), indeks DB sesuai query.
- Local-first time: Asia/Jakarta sebagai TZ; NTP opportunistic.

