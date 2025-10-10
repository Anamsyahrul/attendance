<?php
// Copy this file to .env.php and adjust values accordingly.
return [
  'DB_HOST' => 'localhost',
  'DB_NAME' => 'attendance',
  'DB_USER' => 'root',
  'DB_PASS' => '',
  'APP_TZ'  => 'Asia/Jakarta',
  'DEVICE_SECRET' => 'anamganteng123',
  // If true, unknown UID will create a new user with name "Unknown <UID>"
  'AUTO_CREATE_UNKNOWN' => false,
  // School mode settings
  'SCHOOL_MODE' => true,
  // Jam mulai sekolah (HH:MM, 24h). Scan pertama setelah waktu ini dianggap "Terlambat".
  'SCHOOL_START' => '07:15',
  // Jam selesai/pulang sekolah (HH:MM, 24h). Scan terakhir sebelum waktu ini dianggap "Pulang Awal" (heuristik jika keluar wajib scan).
  'SCHOOL_END' => '15:00',
  // Identitas sekolah (opsional, untuk navbar)
  'SCHOOL_NAME' => 'Sekolah Contoh',
  // URL logo sekolah (opsional). Kosongkan jika tidak ada.
  'SCHOOL_LOGO' => '',
  'SCHOOL_ADDRESS' => '',
  'SCHOOL_PHONE' => '',
  'SCHOOL_EMAIL' => '',
  'SCHOOL_WEBSITE' => '',
  'SCHOOL_MOTTO' => '',
  // Kehadiran
  'REQUIRE_CHECKOUT' => false, // jika true, setelah jam pulang tanpa scan pulang = Bolos
  'SCHOOL_SKIP_WEEKENDS' => false, // jika true, Sabtu/Minggu diabaikan (tidak menandai bolos)
  'WEEKLY_OFF_DAYS' => '6,7', // daftar hari libur mingguan (1=Senin ... 7=Minggu)
  // Daftar tanggal libur (YYYY-MM-DD) dipisah koma, contoh: 2025-06-01,2025-06-17
  'HOLIDAYS' => '',
  // Mode pendaftaran (aktifkan untuk mengizinkan scan kartu unknown dengan feedback sukses di perangkat)
  'REGISTRATION_MODE' => false,
];
