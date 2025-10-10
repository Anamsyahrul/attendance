# Changelog

## v0.1.1 — Authentication Guard
- Tambah autentikasi sesi untuk dashboard (login admin / kelompok2, dapat diubah via .env).
- Halaman login & logout baru, navbar menampilkan user, README & .env.sample diperbarui.
- Pengaturan baru untuk memilih hari libur mingguan (Jumat/Sabtu/Minggu) langsung dari dashboard.

## v0.1.0 — Initial Release
- Firmware ESP32: RC522 scan, debounce 2s, SD logging (CSV), queue JSONL, batch POST with HMAC, LED/buzzer, NTP.
- Backend PHP: ingest (HMAC verify), stats/today, CSV export via .htaccess to public/index.php.
- Dashboard Bootstrap: list + filter + pagination, cards, export button, users CRUD minimal.
- SQL: schema + seed (3 users, 1 active device).
- Docs: README, Project Charter, Assumptions, Operating Principles.

