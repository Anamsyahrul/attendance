<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/fpdf.php';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="panduan_setup_kehadiran.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$pdf = new FPDF();
$pdf->SetTitle('Panduan Instalasi Sistem Kehadiran');
$pdf->SetAuthor(env('SCHOOL_NAME', 'Sistem Kehadiran RFID'));

// Halaman Cover dengan desain sederhana dan elegan
$pdf->AddPage();

// Background putih bersih
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 210, 297, 'F');

// Frame elegan yang sederhana
$pdf->SetDrawColor(107, 114, 128); // Soft gray
$pdf->SetLineWidth(1);
$pdf->Rect(30, 30, 150, 237, 'D');

// Cover bersih tanpa elemen dekoratif

// Judul utama dengan typography yang clean dan center
$pdf->SetTextColor(31, 41, 55); // Soft dark
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetXY(30, 80);
$pdf->Cell(150, 22, utf8_decode('PANDUAN INSTALASI'), 0, 1, 'C');

// Subtitle sistem di tengah
$pdf->SetTextColor(31, 41, 55); // Soft dark
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetXY(30, 120);
$pdf->Cell(150, 18, utf8_decode('SISTEM KEHADIRAN'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 20);
$pdf->SetXY(30, 138);
$pdf->Cell(150, 16, utf8_decode('BERBASIS RFID'), 0, 1, 'C');

// Nama Sekolah di bawah
$pdf->SetTextColor(107, 114, 128); // Soft gray
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetXY(30, 180);
$pdf->Cell(150, 16, utf8_decode(env('SCHOOL_NAME', 'Sekolah Anda')), 0, 1, 'C');

// Informasi versi sederhana
$pdf->SetTextColor(75, 85, 99); // Soft dark gray
$pdf->SetFont('Arial', '', 13);
$pdf->SetXY(30, 220);
$pdf->Cell(150, 9, utf8_decode('Versi 1.0 | ' . date('d F Y')), 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->SetXY(30, 230);
$pdf->Cell(150, 8, utf8_decode('Untuk Pemula - Langkah Demi Langkah'), 0, 1, 'C');

// Halaman kedua - Daftar Isi dengan warna soft dan layout presisi
$pdf->AddPage();

// Header dengan styling yang soft dan presisi
$pdf->SetFillColor(107, 114, 128); // Soft gray
$pdf->Rect(20, 20, 170, 16, 'F');
$pdf->SetDrawColor(156, 163, 175); // Soft gray border
$pdf->SetLineWidth(0.5);
$pdf->Rect(20, 20, 170, 16, 'D');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(20, 25);
$pdf->Cell(170, 8, utf8_decode('DAFTAR ISI'), 0, 1, 'C');

$pdf->SetTextColor(55, 65, 81); // Soft dark gray
$pdf->SetFont('Arial', '', 11);

$toc = [
    '1. Persiapan Awal dan Tools yang Dibutuhkan',
    '2. Perakitan Hardware di Breadboard',
    '3. Menyiapkan MicroSD Card',
    '4. Mengisi Firmware ke ESP32',
    '5. Konfigurasi Laragon/XAMPP',
    '6. Menjalankan Dashboard Web',
    '7. Sinkronisasi & Cara Kerja Offline-First',
    '8. Pengujian Akhir Sistem',
    '9. Troubleshooting Lengkap',
    '10. FAQ (Frequently Asked Questions)',
    'Lampiran A - Workflow Harian Operator',
    'Lampiran B - Diagram Wiring Lengkap'
];

$y = 50;
foreach ($toc as $index => $item) {
    // Soft alternating background colors
    if ($index % 2 == 0) {
        $pdf->SetFillColor(248, 250, 252); // Soft light gray
    } else {
        $pdf->SetFillColor(255, 255, 255); // White
    }
    
    $pdf->Rect(25, $y - 2, 160, 12, 'F');
    
    // Add soft border
    $pdf->SetDrawColor(229, 231, 235); // Soft light border
    $pdf->SetLineWidth(0.2);
    $pdf->Rect(25, $y - 2, 160, 12, 'D');
    
    $pdf->SetXY(35, $y);
    $pdf->Cell(150, 8, utf8_decode($item), 0, 1);
    $y += 14;
}

// Halaman ketiga - Pengantar dengan warna soft dan layout presisi
$pdf->AddPage();

// Header dengan styling yang soft dan presisi
$pdf->SetFillColor(107, 114, 128); // Soft gray
$pdf->Rect(20, 20, 170, 16, 'F');
$pdf->SetDrawColor(156, 163, 175); // Soft gray border
$pdf->SetLineWidth(0.5);
$pdf->Rect(20, 20, 170, 16, 'D');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(20, 25);
$pdf->Cell(170, 8, utf8_decode('PENGANTAR'), 0, 1, 'C');

$pdf->SetTextColor(55, 65, 81); // Soft dark gray
$pdf->SetFont('Arial', '', 11);
$pdf->SetXY(30, 50);

$intro = [
    'Selamat datang di panduan instalasi Sistem Kehadiran Berbasis RFID!',
    '',
    'Dokumen ini dirancang khusus untuk pemula yang belum pernah menggunakan ESP32 atau sistem RFID sebelumnya. Setiap langkah dijelaskan secara detail dengan tips dan peringatan penting.',
    '',
    'Sistem ini menggunakan teknologi offline-first, artinya data kehadiran tetap tersimpan meskipun internet terputus, dan akan otomatis tersinkronisasi saat koneksi kembali normal.',
    '',
    'Waktu yang dibutuhkan untuk instalasi lengkap: 2-3 jam untuk pemula.',
    '',
    'PERINGATAN PENTING: Bacalah seluruh panduan sebelum memulai instalasi untuk menghindari kesalahan yang dapat merusak perangkat.',
];

foreach ($intro as $line) {
    if ($line === '') {
        $pdf->Ln(6);
        continue;
    }
    
    // Special styling for warning dengan warna soft (tanpa kotak untuk presisi)
    if (strpos($line, 'PERINGATAN PENTING') !== false) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(153, 27, 27); // Soft dark red
        $pdf->SetXY(30, $pdf->GetY());
    } else {
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(55, 65, 81); // Soft dark gray
        $pdf->SetXY(30, $pdf->GetY());
    }
    
    $pdf->MultiCell(160, 5, utf8_decode($line));
    $pdf->Ln(4);
}

function addSection(FPDF $pdf, string $title, array $lines, bool $isNewPage = false): void {
    if ($isNewPage) {
        $pdf->AddPage();
    }
    
    // Header section dengan warna soft dan presisi
    $pdf->SetFillColor(107, 114, 128); // Soft gray
    $pdf->Rect(20, $pdf->GetY(), 170, 16, 'F');
    
    // Soft border
    $pdf->SetDrawColor(156, 163, 175); // Soft gray border
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(20, $pdf->GetY(), 170, 16, 'D');
    
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetXY(25, $pdf->GetY() + 3);
    $pdf->Cell(160, 10, utf8_decode($title), 0, 1);
    $pdf->Ln(6);
    
    // Konten dengan styling yang soft dan presisi
    $pdf->SetTextColor(55, 65, 81); // Soft dark gray
    $pdf->SetFont('Arial', '', 11);
    
    foreach ($lines as $line) {
        if ($line === '') {
            $pdf->Ln(4);
            continue;
        }
        
        // Clean up the line from ALL emoji and confusing characters
        $cleanLine = str_replace('?', '', $line);
        $cleanLine = str_replace('❓', '', $cleanLine);
        $cleanLine = str_replace('💡', '', $cleanLine);
        $cleanLine = str_replace('⚠️', '', $cleanLine);
        $cleanLine = str_replace('✅', '', $cleanLine);
        $cleanLine = str_replace('🔧', '', $cleanLine);
        $cleanLine = str_replace('📋', '', $cleanLine);
        $cleanLine = str_replace('🌐', '', $cleanLine);
        $cleanLine = str_replace('🔄', '', $cleanLine);
        $cleanLine = str_replace('🧪', '', $cleanLine);
        $cleanLine = str_replace('📦', '', $cleanLine);
        $cleanLine = str_replace('🖥️', '', $cleanLine);
        $cleanLine = str_replace('1️⃣', '1.', $cleanLine);
        $cleanLine = str_replace('2️⃣', '2.', $cleanLine);
        $cleanLine = str_replace('3️⃣', '3.', $cleanLine);
        $cleanLine = str_replace('4️⃣', '4.', $cleanLine);
        $cleanLine = str_replace('5️⃣', '5.', $cleanLine);
        $cleanLine = str_replace('6️⃣', '6.', $cleanLine);
        $cleanLine = str_replace('•', '-', $cleanLine); // Replace bullet with dash
        
        // Replace special characters that cause question marks
        $cleanLine = str_replace('\xE2\x86\x92', '->', $cleanLine); // Rightward arrow
        $cleanLine = str_replace('\xE2\x86\x90', '<-', $cleanLine); // Leftward arrow
        $cleanLine = str_replace('\xE2\x86\x91', '^', $cleanLine); // Upward arrow
        $cleanLine = str_replace('\xE2\x86\x93', 'v', $cleanLine); // Downward arrow
        $cleanLine = str_replace('\xC2\xAB', '<<', $cleanLine); // Left-pointing double angle quotation mark
        $cleanLine = str_replace('\xC2\xBB', '>>', $cleanLine); // Right-pointing double angle quotation mark
        $cleanLine = str_replace('\xE2\x80\x9C', '"', $cleanLine); // Left double quotation mark
        $cleanLine = str_replace('\xE2\x80\x9D', '"', $cleanLine); // Right double quotation mark
        $cleanLine = str_replace('\xE2\x80\x99', "'", $cleanLine); // Right single quotation mark
        $cleanLine = str_replace('\xE2\x80\x98', "'", $cleanLine); // Left single quotation mark
        $cleanLine = str_replace('\xE2\x80\xA6', '...', $cleanLine); // Horizontal ellipsis
        $cleanLine = str_replace('\xE2\x80\x93', '-', $cleanLine); // En dash
        $cleanLine = str_replace('\xE2\x80\x94', '--', $cleanLine); // Em dash
        
        $cleanLine = str_replace('Q:', 'PERTANYAAN:', $cleanLine);
        $cleanLine = str_replace('A:', 'JAWABAN:', $cleanLine);
        
        // Additional cleanup for any remaining problematic characters
        $cleanLine = preg_replace('/[^\x00-\x7F]/', '', $cleanLine); // Remove any remaining non-ASCII characters
        
        // Deteksi tips dan peringatan dengan warna soft (tanpa kotak untuk presisi)
        if (strpos($cleanLine, 'TIP') !== false || strpos($cleanLine, 'TIPS') !== false) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(146, 64, 14); // Soft dark orange
        } elseif (strpos($cleanLine, 'PERINGATAN') !== false) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(153, 27, 27); // Soft dark red
        } elseif (strpos($cleanLine, 'BERHASIL') !== false) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(21, 128, 61); // Soft dark green
        } elseif (strpos($cleanLine, 'KOMPONEN') !== false || strpos($cleanLine, 'LANGKAH') !== false || strpos($cleanLine, 'INSTALASI') !== false || strpos($cleanLine, 'KONFIGURASI') !== false || strpos($cleanLine, 'MENGGUNAKAN') !== false || strpos($cleanLine, 'CARA KERJA') !== false || strpos($cleanLine, 'PENGUJIAN') !== false || strpos($cleanLine, 'MASALAH') !== false || strpos($cleanLine, 'PERTANYAAN') !== false || strpos($cleanLine, 'MONITORING') !== false || strpos($cleanLine, 'TINDAKAN') !== false || strpos($cleanLine, 'ALAT') !== false) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(79, 70, 229); // Soft purple for section headers
        } else {
            $pdf->SetFont('Arial', '', 11);
            $pdf->SetTextColor(55, 65, 81); // Soft dark gray
        }
        
        $pdf->SetXY(30, $pdf->GetY());
        $pdf->MultiCell(150, 5, utf8_decode($cleanLine));
    $pdf->Ln(2);
    }
    $pdf->Ln(8);
}

addSection($pdf, '1. Persiapan Awal dan Tools yang Dibutuhkan', [
    'Sebelum memulai instalasi, pastikan Anda memiliki semua komponen dan software yang diperlukan. Langkah ini sangat penting untuk menghindari masalah di kemudian hari.',
    '',
    '📦 KOMPONEN HARDWARE YANG DIBUTUHKAN:',
    '• ESP32 DevKit V1 (1 buah) - Mikrokontroler utama',
    '• Modul RFID RC522 (1 buah) - Untuk membaca kartu RFID',
    '• Modul MicroSD Card Reader (1 buah) - Untuk menyimpan data offline',
    '• Kartu MIFARE Classic 13.56MHz (5 buah) - Kartu RFID untuk siswa',
    '• Buzzer 5V (1 buah) - Untuk suara notifikasi',
    '• LED Hijau dan Merah (masing-masing 1 buah) - Indikator status',
    '• Resistor 220Ω (2 buah) - Untuk LED',
    '• Kabel Jumper Male-Female (20 buah) - Untuk koneksi',
    '• Breadboard Half Size (1 buah) - Tempat merangkai komponen',
    '• OLED Display 0.96" I2C (1 buah) - Menampilkan informasi',
    '• RTC DS3231 Module (1 buah) - Jam real-time',
    '',
    '💻 SOFTWARE YANG DIBUTUHKAN:',
    '• Arduino IDE versi terbaru (unduh dari arduino.cc)',
    '• Laragon atau XAMPP untuk server web lokal',
    '• Kabel USB data (bukan charger) untuk ESP32',
    '',
    '⚠️ PERINGATAN: Pastikan kabel USB yang digunakan adalah kabel data, bukan hanya kabel charger. Kabel charger tidak dapat mentransfer data.',
    '',
    '🔧 INSTALASI ARDUINO IDE DAN LIBRARY:',
    '1) Unduh Arduino IDE dari https://www.arduino.cc/en/software',
    '2) Install Arduino IDE dengan pengaturan default',
    '3) Buka Arduino IDE, klik File → Preferences',
    '4) Di bagian "Additional Boards Manager URLs", tambahkan:',
    '   https://espressif.github.io/arduino-esp32/package_esp32_index.json',
    '5) Klik Tools → Board → Boards Manager',
    '6) Cari "esp32" oleh Espressif Systems dan klik Install',
    '7) Pastikan versi yang terinstall minimal 2.0.11',
    '',
    '📚 INSTALASI LIBRARY YANG DIPERLUKAN:',
    'Klik Tools → Manage Libraries, lalu install library berikut:',
    '• MFRC522 oleh GithubCommunity (untuk RFID)',
    '• ArduinoJson oleh Benoît Blanchon (untuk JSON)',
    '• Adafruit SSD1306 (untuk OLED display)',
    '• Adafruit GFX Library (untuk grafik OLED)',
    '• RTClib oleh Adafruit (untuk RTC)',
    '',
    '💡 TIP: Jika mengalami masalah saat instalasi library, pastikan koneksi internet stabil dan coba restart Arduino IDE.',
], true);

addSection($pdf, '2. Perakitan Hardware di Breadboard', [
    'Perakitan hardware adalah langkah paling kritis dalam instalasi sistem. Ikuti panduan ini dengan teliti untuk menghindari kerusakan komponen.',
    '',
    '⚠️ PERINGATAN PENTING:',
    '• Pastikan ESP32 dalam keadaan MATI saat merangkai kabel',
    '• Gunakan kabel dengan warna berbeda untuk memudahkan identifikasi',
    '• Jangan memaksakan koneksi jika tidak pas',
    '• Periksa setiap koneksi sebelum menghidupkan sistem',
    '',
    '🔌 DIAGRAM KONEKSI LENGKAP:',
    'Berikut adalah tabel koneksi yang harus diikuti dengan teliti:',
], true);

// Membuat tabel wiring dengan warna soft dan layout presisi
$pdf->SetFillColor(107, 114, 128); // Soft gray
$pdf->Rect(20, $pdf->GetY(), 170, 16, 'F');
$pdf->SetDrawColor(156, 163, 175); // Soft gray border
$pdf->SetLineWidth(0.5);
$pdf->Rect(20, $pdf->GetY(), 170, 16, 'D');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(20, $pdf->GetY() + 3);
$pdf->Cell(170, 8, utf8_decode('TABEL KONEKSI HARDWARE'), 0, 1, 'C');

$pdf->Ln(4);

$wiring = [
    ['RC522 SDA (SS)', 'GPIO 5 (VSPI CS)'],
    ['RC522 SCK', 'GPIO 18 (VSPI SCK)'],
    ['RC522 MOSI', 'GPIO 23 (VSPI MOSI)'],
    ['RC522 MISO', 'GPIO 19 (VSPI MISO)'],
    ['RC522 RST', 'GPIO 4'],
    ['RC522 3.3V', '3V3 ESP32'],
    ['RC522 GND', 'GND ESP32'],
    ['microSD CS', 'GPIO 15'],
    ['microSD SCK', 'GPIO 18 (paralel RC522)'],
    ['microSD MOSI', 'GPIO 23 (paralel RC522)'],
    ['microSD MISO', 'GPIO 19 (paralel RC522)'],
    ['microSD VCC', '3V3 ESP32'],
    ['microSD GND', 'GND ESP32'],
    ['OLED VCC', '3V3 ESP32'],
    ['OLED GND', 'GND ESP32'],
    ['OLED SDA', 'GPIO 21'],
    ['OLED SCL', 'GPIO 22'],
    ['RTC VCC', '3V3 ESP32'],
    ['RTC GND', 'GND ESP32'],
    ['RTC SDA', 'GPIO 21 (paralel OLED)'],
    ['RTC SCL', 'GPIO 22 (paralel OLED)'],
    ['LED Hijau (+)', 'GPIO 12 via resistor 220Ω ke LED, kaki lain ke GND'],
    ['LED Merah (+)', 'GPIO 14 via resistor 220Ω ke LED, kaki lain ke GND'],
    ['Buzzer +', 'GPIO 27 (buzzer minus ke GND)'],
];

$pdf->SetTextColor(55, 65, 81); // Soft dark gray
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(248, 250, 252); // Soft light gray
$pdf->SetXY(20, $pdf->GetY());
$pdf->Cell(85, 8, utf8_decode('Komponen'), 1, 0, 'C', true);
$pdf->Cell(85, 8, utf8_decode('Sambungan ke ESP32'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$rowCount = 0;
foreach ($wiring as $row) {
    // Soft alternating colors
    $fillColor = ($rowCount % 2 == 0) ? [255, 255, 255] : [248, 250, 252];
    $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
    
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(85, 6, utf8_decode($row[0]), 1, 0, 'L', true);
    $pdf->Cell(85, 6, utf8_decode($row[1]), 1, 1, 'L', true);
    $rowCount++;
}

$pdf->Ln(5);

// Tips perakitan dengan warna soft (tanpa kotak untuk presisi)
$pdf->SetTextColor(146, 64, 14); // Soft dark orange
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(30, $pdf->GetY());
$pdf->Cell(150, 7, utf8_decode('TIPS PERAKITAN UNTUK PEMULA:'), 0, 1);

$pdf->SetFont('Arial', '', 11);
$tips = [
    '1. Mulai dari koneksi power (3V3 dan GND) terlebih dahulu',
    '2. Gunakan kabel merah untuk VCC dan hitam untuk GND',
    '3. Untuk SPI (RC522 dan microSD), gunakan kabel dengan warna sama',
    '4. Resistor 220Ω harus dipasang pada kaki positif LED',
    '5. Pastikan kaki LED panjang (+) dan pendek (-) terpasang benar',
    '6. Buzzer memiliki polaritas, pastikan kaki + dan - benar'
];

foreach ($tips as $tip) {
    $pdf->SetXY(35, $pdf->GetY());
    $pdf->Cell(150, 5, utf8_decode($tip), 0, 1);
}

$pdf->Ln(4);

// Peringatan penting dengan warna soft (tanpa kotak untuk presisi)
$pdf->SetTextColor(153, 27, 27); // Soft dark red
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(30, $pdf->GetY());
$pdf->Cell(150, 7, utf8_decode('PERINGATAN:'), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->SetXY(35, $pdf->GetY());
$pdf->MultiCell(150, 5, utf8_decode('Setelah terpasang, periksa ulang tidak ada kabel longgar atau bertumpuk yang dapat menyebabkan short circuit. Short circuit dapat merusak ESP32 dan komponen lainnya.'));

$pdf->Ln(6);

addSection($pdf, '3. Menyiapkan MicroSD Card', [
    'MicroSD card berfungsi sebagai penyimpanan offline untuk data kehadiran. Ini adalah fitur penting yang memungkinkan sistem tetap berfungsi meskipun internet terputus.',
    '',
    '📋 LANGKAH-LANGKAH PENYIAPAN MICROSD:',
    '',
    '1️⃣ FORMAT MICROSD CARD:',
    '• Masukkan microSD ke komputer menggunakan card reader',
    '• Buka Windows Explorer, klik kanan pada drive microSD',
    '• Pilih "Format..." dari menu',
    '• Pilih File System: FAT32',
    '• Allocation unit size: Default',
    '• Centang "Quick Format"',
    '• Klik "Start" dan tunggu proses selesai',
    '',
    '💡 TIP: Gunakan microSD dengan kapasitas maksimal 16GB untuk kompatibilitas terbaik dengan ESP32.',
    '',
    '2️⃣ STRUKTUR FOLDER (OPSIONAL):',
    '• Buat folder "ATTENDANCE" di root microSD',
    '• Folder ini akan digunakan untuk menyimpan file log',
    '• Firmware akan otomatis membuat file-file berikut:',
    '  - /attendance_log.csv (log kehadiran harian)',
    '  - /events_queue.jsonl (antrian data untuk sinkronisasi)',
    '',
    '3️⃣ FILE STUDENTS.CSV (OPSIONAL):',
    'Jika Anda sudah memiliki data siswa, buat file students.csv dengan format:',
    'uid_hex,nama,kelas',
    'Contoh:',
    'A1B2C3D4,John Doe,XII IPA 1',
    'E5F6G7H8,Jane Smith,XII IPS 1',
    '',
    '⚠️ PERINGATAN: Pastikan UID hex dalam format huruf besar dan tanpa spasi.',
    '',
    '✅ BERHASIL: MicroSD siap digunakan jika dapat dibaca dan ditulis dengan normal.',
], true);

addSection($pdf, '4. Mengisi Firmware ke ESP32', [
    'Firmware adalah program yang akan mengontrol semua fungsi ESP32. Langkah ini memerlukan ketelitian dalam konfigurasi agar sistem dapat berfungsi dengan baik.',
    '',
    '🔧 LANGKAH-LANGKAH UPLOAD FIRMWARE:',
    '',
    '1️⃣ KONEKSI ESP32:',
    '• Sambungkan ESP32 ke komputer menggunakan kabel USB data',
    '• Pastikan ESP32 dalam keadaan mati saat menghubungkan',
    '• Tunggu beberapa detik hingga Windows mengenali perangkat',
    '',
    '2️⃣ MEMBUKA PROJECT ARDUINO:',
    '• Buka Arduino IDE',
    '• Klik File → Open',
    '• Navigasi ke folder firmware/attendance_esp32',
    '• Pilih file attendance_esp32.ino',
    '• Tunggu hingga semua file terkait dimuat',
    '',
    '3️⃣ KONFIGURASI SETTINGS:',
    '• Klik Tools → Board → ESP32 Dev Module',
    '• Klik Tools → Port → pilih port COM yang sesuai',
    '• Klik Tools → Upload Speed → 115200',
    '• Klik Tools → CPU Frequency → 240MHz (WiFi/BT)',
    '',
    '4️⃣ EDIT FILE CONFIG.H:',
    '• Buka file config.h di Arduino IDE',
    '• Edit parameter berikut sesuai kebutuhan:',
    '  - WIFI_SSID: nama WiFi sekolah',
    '  - WIFI_PASS: password WiFi sekolah',
    '  - DEVICE_ID: ID unik perangkat (mis: "DEVICE_001")',
    '  - DEVICE_SECRET: kunci rahasia perangkat',
    '  - API_BASE: alamat server (mis: "http://192.168.1.10/attendance/api")',
    '',
    '💡 TIP: Simpan DEVICE_SECRET dengan aman, ini akan digunakan untuk autentikasi.',
    '',
    '5️⃣ UPLOAD FIRMWARE:',
    '• Klik tombol Upload (ikon panah) atau tekan Ctrl+U',
    '• Tunggu proses kompilasi selesai',
    '• Jika muncul pesan "Connecting...", tekan tombol BOOT di ESP32',
    '• Tunggu hingga muncul pesan "Done uploading"',
    '',
    '⚠️ PERINGATAN: Jangan cabut kabel USB saat proses upload berlangsung.',
    '',
    '6️⃣ INSTALASI MICROSD:',
    '• Setelah upload selesai, cabut kabel USB',
    '• Masukkan microSD ke modul microSD reader',
    '• Pasang modul microSD ke ESP32',
    '• Sambungkan kembali kabel USB',
    '',
    '✅ BERHASIL: ESP32 akan restart dan mulai menjalankan firmware.',
], true);

addSection($pdf, '5. Konfigurasi Laragon/XAMPP', [
    'Server web lokal diperlukan untuk menjalankan dashboard sistem kehadiran. Laragon adalah pilihan yang mudah untuk pemula karena sudah include Apache, MySQL, dan PHP.',
    '',
    '🖥️ LANGKAH-LANGKAH KONFIGURASI SERVER:',
    '',
    '1️⃣ MENJALANKAN LARAGON:',
    '• Buka aplikasi Laragon',
    '• Klik tombol "Start All" untuk menjalankan Apache dan MySQL',
    '• Tunggu hingga status berubah menjadi hijau',
    '• Pastikan tidak ada error di log Laragon',
    '',
    '💡 TIP: Jika port 80 atau 3306 sudah digunakan, Laragon akan otomatis menggunakan port alternatif.',
    '',
    '2️⃣ MEMBUAT DATABASE:',
    '• Buka Terminal Laragon (klik kanan tray icon → Terminal)',
    '• Jalankan perintah berikut satu per satu:',
    '',
    'mysql -u root -p',
    '(tekan Enter jika tidak ada password)',
    '',
    'CREATE DATABASE attendance CHARACTER SET utf8mb4;',
    'USE attendance;',
    'SOURCE C:/laragon/www/attendance/web/sql/schema.sql;',
    'SOURCE C:/laragon/www/attendance/web/sql/seed.sql;',
    '',
    '• Keluar dari MySQL dengan perintah: exit',
    '',
    '⚠️ PERINGATAN: Pastikan path ke file SQL sudah benar sesuai lokasi instalasi.',
    '',
    '3️⃣ KONFIGURASI FILE ENVIRONMENT:',
    '• Buka folder web di project attendance',
    '• Salin file .env.sample.php menjadi .env.php',
    '• Edit file .env.php dengan parameter berikut:',
    '',
    'DB_HOST=127.0.0.1',
    'DB_NAME=attendance',
    'DB_USER=root',
    'DB_PASS=',
    'APP_TZ=Asia/Jakarta',
    'DEVICE_SECRET=secret_key_yang_sama_dengan_firmware',
    '',
    '💡 TIP: DEVICE_SECRET harus sama persis dengan yang ada di firmware ESP32.',
    '',
    '4️⃣ VERIFIKASI INSTALASI:',
    '• Pastikan folder attendance berada di C:\\laragon\\www\\',
    '• Buka browser dan akses: http://localhost/attendance/public/',
    '• Jika muncul halaman login, konfigurasi berhasil',
    '',
    '✅ BERHASIL: Server web siap digunakan untuk dashboard sistem kehadiran.',
], true);

addSection($pdf, '6. Menjalankan Dashboard Web', [
    'Dashboard web adalah antarmuka utama untuk mengelola sistem kehadiran. Melalui dashboard, Anda dapat melihat data real-time, mengelola siswa, dan menghasilkan laporan.',
    '',
    '🌐 LANGKAH-LANGKAH MENGGUNAKAN DASHBOARD:',
    '',
    '1️⃣ AKSES DASHBOARD:',
    '• Buka browser web (Chrome, Firefox, atau Edge)',
    '• Ketik alamat: http://localhost/attendance/public/',
    '• Tunggu halaman login dimuat',
    '• Login dengan kredensial default (admin/admin)',
    '',
    '💡 TIP: Ganti password default setelah login pertama untuk keamanan.',
    '',
    '2️⃣ KONFIGURASI AWAL:',
    '• Klik menu "Pengaturan" di sidebar',
    '• Isi informasi sekolah:',
    '  - Nama Sekolah: [Nama Sekolah Anda]',
    '  - Jam Masuk: 07:00',
    '  - Jam Pulang: 15:30',
    '  - Informasi Kontak: nomor telepon sekolah',
    '• Klik "Simpan Pengaturan"',
    '',
    '3️⃣ MENDAFTARKAN SISWA:',
    '• Klik menu "Daftar Kartu" di sidebar',
    '• Halaman ini akan mengaktifkan Mode Pendaftaran di ESP32',
    '• Tempelkan kartu RFID baru di dekat ESP32',
    '• UID kartu akan otomatis terisi',
    '• Isi nama lengkap dan kelas siswa',
    '• Klik "Simpan" untuk menyimpan data',
    '',
    '⚠️ PERINGATAN: Pastikan ESP32 sudah terhubung ke WiFi sebelum mendaftarkan siswa.',
    '',
    '4️⃣ MONITORING REAL-TIME:',
    '• Klik menu "Dashboard" untuk melihat halaman utama',
    '• Aktifkan "Muat Otomatis" untuk update real-time',
    '• Setiap scan kartu akan muncul di log',
    '• Status kehadiran akan otomatis terupdate',
    '',
    '5️⃣ FITUR-FITUR DASHBOARD:',
    '• Log Mentah: melihat semua aktivitas scan',
    '• Rekap Harian: ringkasan kehadiran per hari',
    '• Ekspor PDF/CSV: untuk laporan guru',
    '• Statistik: grafik kehadiran siswa',
    '',
    '✅ BERHASIL: Dashboard siap digunakan untuk monitoring sistem kehadiran.',
], true);

addSection($pdf, '7. Sinkronisasi & Cara Kerja Offline-First', [
    'Sistem ini menggunakan teknologi offline-first yang memungkinkan data tetap tersimpan meskipun internet terputus. Ini adalah fitur penting untuk memastikan tidak ada data kehadiran yang hilang.',
    '',
    '🔄 CARA KERJA SISTEM OFFLINE-FIRST:',
    '',
    '1️⃣ PENYIMPANAN DATA LOKAL:',
    '• Setiap scan kartu RFID langsung disimpan ke microSD',
    '• Data disimpan dalam format CSV di file attendance_log.csv',
    '• Data juga disimpan dalam antrian JSON di events_queue.jsonl',
    '• Penyimpanan lokal terjadi dalam waktu < 1 detik',
    '',
    '💡 TIP: Data di microSD aman dari gangguan listrik atau internet.',
    '',
    '2️⃣ SINKRONISASI KE SERVER:',
    '• ESP32 secara otomatis mencoba sinkronisasi setiap 30 detik',
    '• Data dikirim dalam batch untuk efisiensi',
    '• Menggunakan HMAC untuk keamanan data',
    '• Data yang berhasil terkirim dihapus dari antrian',
    '',
    '3️⃣ HANDLING GANGGUAN KONEKSI:',
    '• Jika WiFi terputus, data tetap tersimpan di microSD',
    '• Saat koneksi kembali, ESP32 otomatis melanjutkan sinkronisasi',
    '• Tidak ada data yang hilang meskipun internet mati berhari-hari',
    '• Sistem akan mencoba sinkronisasi hingga berhasil',
    '',
    '4️⃣ MONITORING STATUS SINKRONISASI:',
    '• LED hijau: sinkronisasi berhasil',
    '• LED merah: ada masalah koneksi atau sinkronisasi',
    '• OLED display menampilkan status WiFi dan sinkronisasi',
    '• Dashboard web menampilkan data real-time dari server',
    '',
    '⚠️ PERINGATAN: Jangan cabut microSD saat sistem sedang menulis data.',
    '',
    '✅ KEUNTUNGAN SISTEM OFFLINE-FIRST:',
    '• Data kehadiran tidak pernah hilang',
    '• Sistem tetap berfungsi tanpa internet',
    '• Sinkronisasi otomatis saat koneksi tersedia',
    '• Backup otomatis di microSD',
], true);

addSection($pdf, '8. Pengujian Akhir Sistem', [
    'Pengujian akhir diperlukan untuk memastikan semua komponen sistem berfungsi dengan baik sebelum digunakan dalam produksi. Ikuti langkah-langkah pengujian ini dengan teliti.',
    '',
    '🧪 LANGKAH-LANGKAH PENGUJIAN:',
    '',
    '1️⃣ PENGUJIAN KARTU TIDAK TERDAFTAR:',
    '• Tempelkan kartu RFID yang belum terdaftar di dekat ESP32',
    '• Dengarkan bunyi buzzer (bunyi gagal)',
    '• Pastikan LED merah menyala',
    '• Cek OLED display menampilkan "Kartu tidak dikenal"',
    '• Buka dashboard → menu "Daftar Kartu"',
    '• Pastikan UID kartu muncul di daftar kartu tidak dikenal',
    '',
    '✅ BERHASIL: Sistem dapat mendeteksi kartu tidak terdaftar dengan benar.',
    '',
    '2️⃣ PENGUJIAN PENDAFTARAN KARTU:',
    '• Di dashboard, klik "Daftar Kartu"',
    '• Tempelkan kartu yang sama di dekat ESP32',
    '• UID akan otomatis terisi',
    '• Isi nama dan kelas siswa',
    '• Klik "Simpan"',
    '• Tempelkan kartu lagi untuk menguji',
    '• Pastikan LED hijau menyala dan bunyi sukses',
    '',
    '✅ BERHASIL: Kartu berhasil terdaftar dan dapat digunakan.',
    '',
    '3️⃣ PENGUJIAN DASHBOARD:',
    '• Buka dashboard utama',
    '• Aktifkan "Muat Otomatis"',
    '• Scan beberapa kartu yang sudah terdaftar',
    '• Pastikan data muncul di "Log Mentah"',
    '• Cek "Rekap Harian" menampilkan status Hadir/Terlambat',
    '• Pastikan waktu scan akurat',
    '',
    '✅ BERHASIL: Dashboard menampilkan data real-time dengan benar.',
    '',
    '4️⃣ PENGUJIAN OFFLINE-FIRST:',
    '• Matikan WiFi ESP32 (ubah password di config.h)',
    '• Upload ulang firmware',
    '• Lakukan beberapa scan kartu',
    '• Pastikan LED merah menyala (tidak ada koneksi)',
    '• Cek microSD, pastikan data tersimpan',
    '• Hidupkan kembali WiFi',
    '• Tunggu beberapa menit',
    '• Pastikan data tunggakan terkirim ke server',
    '',
    '✅ BERHASIL: Sistem offline-first berfungsi dengan baik.',
    '',
    '5️⃣ PENGUJIAN KOMPONEN LAINNYA:',
    '• Pastikan OLED display menampilkan informasi',
    '• Cek LED hijau dan merah berfungsi',
    '• Pastikan buzzer mengeluarkan suara',
    '• Verifikasi RTC menampilkan waktu yang benar',
    '• Test microSD dapat dibaca dan ditulis',
    '',
    '🎉 SELAMAT! Sistem kehadiran RFID siap digunakan.',
], true);

addSection($pdf, '9. Troubleshooting Lengkap', [
    'Bagian ini berisi solusi untuk masalah-masalah umum yang mungkin terjadi saat instalasi atau penggunaan sistem.',
    '',
    '🔧 MASALAH RFID TIDAK TERBACA:',
    '• Periksa kabel SDA dan RST pada modul RC522',
    '• Pastikan kartu menggunakan frekuensi 13.56 MHz',
    '• Beri jarak 1-2 cm antara kartu dan antena',
    '• Cek apakah kartu MIFARE Classic (bukan kartu lain)',
    '• Pastikan modul RC522 mendapat daya 3.3V',
    '',
    '💾 MASALAH MICROSD:',
    '• Pastikan microSD menggunakan level logika 3.3V',
    '• Gunakan kartu dengan kapasitas maksimal 16GB',
    '• Format ulang dengan FAT32',
    '• Cek kabel CS, SCK, MOSI, MISO',
    '• Pastikan microSD tidak rusak',
    '',
    '📶 MASALAH KONEKSI WIFI:',
    '• Periksa SSID dan password di config.h',
    '• Pastikan WiFi sekolah aktif dan dapat diakses',
    '• Cek kekuatan sinyal WiFi di lokasi ESP32',
    '• Restart ESP32 jika tidak bisa konek',
    '• Pastikan WiFi tidak menggunakan WPA3 (gunakan WPA2)',
    '',
    '🔐 MASALAH AUTENTIKASI API:',
    '• DEVICE_SECRET di firmware harus sama dengan .env.php',
    '• Pastikan DEVICE_ID unik dan sudah terdaftar',
    '• Cek apakah server web berjalan dengan baik',
    '• Verifikasi alamat API_BASE sudah benar',
    '',
    '⏰ MASALAH WAKTU:',
    '• Pastikan RTC DS3231 mendapat daya listrik',
    '• Saat pertama kali, firmware menggunakan waktu kompilasi',
    '• Sinkronisasi manual melalui menu Settings',
    '• Pastikan timezone di .env.php sudah benar',
    '',
    '💡 TIPS TROUBLESHOOTING:',
    '• Selalu periksa koneksi kabel terlebih dahulu',
    '• Gunakan Serial Monitor Arduino untuk debug',
    '• Restart sistem jika ada masalah',
    '• Simpan backup konfigurasi yang sudah berhasil',
], true);

addSection($pdf, '10. FAQ (Frequently Asked Questions)', [
    'Berikut adalah pertanyaan yang sering diajukan oleh pengguna sistem kehadiran RFID.',
    '',
    '📋 PERTANYAAN UMUM:',
    '',
    'PERTANYAAN: Berapa banyak siswa yang bisa didaftarkan',
    'JAWABAN: Sistem dapat menangani hingga 10.000 siswa tanpa masalah performa.',
    '',
    'PERTANYAAN: Apakah sistem bisa digunakan tanpa internet',
    'JAWABAN: Ya, sistem menggunakan teknologi offline-first. Data tersimpan di microSD dan akan tersinkronisasi saat internet tersedia.',
    '',
    'PERTANYAAN: Berapa lama baterai ESP32 bertahan',
    'JAWABAN: ESP32 harus terhubung ke listrik terus-menerus. Gunakan adaptor 5V/2A untuk daya yang stabil.',
    '',
    'PERTANYAAN: Bisakah menggunakan kartu RFID yang sudah ada',
    'JAWABAN: Ya, asalkan menggunakan frekuensi 13.56 MHz dan format MIFARE Classic.',
    '',
    'PERTANYAAN: Bagaimana cara backup data',
    'JAWABAN: Data otomatis tersimpan di microSD dan database MySQL. Lakukan backup database secara berkala.',
    '',
    'PERTANYAAN: Apakah sistem bisa digunakan untuk multiple kelas',
    'JAWABAN: Ya, sistem mendukung multiple kelas dan dapat dikustomisasi sesuai kebutuhan sekolah.',
    '',
    'PERTANYAAN: Bagaimana cara mengubah jam masuk/pulang',
    'JAWABAN: Login ke dashboard → menu Pengaturan → ubah jam masuk/pulang → Simpan.',
    '',
    'PERTANYAAN: Bisakah sistem digunakan untuk absensi guru',
    'JAWABAN: Ya, sistem dapat digunakan untuk absensi guru dengan membuat kategori terpisah.',
    '',
    'PERTANYAAN: Bagaimana cara reset sistem jika ada masalah',
    'JAWABAN: Format ulang microSD, reset database MySQL, dan upload ulang firmware ESP32.',
    '',
    'PERTANYAAN: Apakah ada limitasi jarak scan kartu',
    'JAWABAN: Jarak optimal adalah 1-2 cm. Jarak lebih jauh dapat menyebabkan kegagalan scan.',
], true);

$pdf->AddPage();
addSection($pdf, 'Lampiran A - Workflow Harian Operator', [
    'Panduan operasional harian untuk operator sistem kehadiran RFID.',
    '',
    '🌅 RUTINITAS PAGI:',
    '1) Nyalakan ESP32 (otomatis menjalankan firmware)',
    '2) Pastikan LED hijau berkedip singkat tanda boot sukses',
    '3) Cek OLED display menampilkan jam dan status WiFi',
    '4) Buka dashboard web untuk monitoring',
    '5) Pastikan koneksi WiFi stabil',
    '',
    '📚 SAAT SISWA MASUK:',
    '1) Minta siswa menempelkan kartu di dekat antena RC522',
    '2) Dengarkan bunyi buzzer sukses (atau gagal)',
    '3) Pastikan LED hijau menyala untuk scan berhasil',
    '4) Pantau dashboard untuk memastikan nama muncul',
    '5) Jika ada siswa baru, buka menu Daftar Kartu',
    '',
    '🔄 MONITORING HARIAN:',
    '1) Pantau dashboard secara berkala',
    '2) Cek log mentah untuk aktivitas scan',
    '3) Pastikan data tersinkronisasi dengan baik',
    '4) Perhatikan LED status pada ESP32',
    '5) Cek OLED display untuk informasi real-time',
    '',
    '🌆 RUTINITAS SORE:',
    '1) Di akhir hari, cek rekap harian',
    '2) Pastikan status pulang terupdate',
    '3) Jika ada siswa belum scan pulang sampai jam 21:00, status otomatis menjadi Bolos',
    '4) Ekspor laporan PDF/CSV untuk guru BK atau wali kelas',
    '5) Backup data harian jika diperlukan',
    '',
    '⚠️ TINDAKAN DARURAT:',
    '1) Jika ESP32 mati, cek kabel power dan restart',
    '2) Jika WiFi terputus, data tetap tersimpan di microSD',
    '3) Jika ada masalah, hubungi teknisi atau lihat troubleshooting',
    '4) Selalu siapkan backup microSD cadangan',
], true);

$pdf->AddPage();
addSection($pdf, 'Lampiran B - Diagram Wiring Lengkap', [
    'Diagram visual untuk memudahkan perakitan hardware sistem kehadiran RFID.',
    '',
    '🔌 DIAGRAM KONEKSI ESP32:',
    '',
    'ESP32 DevKit V1 Pinout:',
    '• GPIO 4  → RC522 RST',
    '• GPIO 5  → RC522 SDA (SS)',
    '• GPIO 18 → RC522 SCK + microSD SCK',
    '• GPIO 19 → RC522 MISO + microSD MISO',
    '• GPIO 21 → OLED SDA + RTC SDA',
    '• GPIO 22 → OLED SCL + RTC SCL',
    '• GPIO 23 → RC522 MOSI + microSD MOSI',
    '• GPIO 15 → microSD CS',
    '• GPIO 12 → LED Hijau (+) via resistor 220Ω',
    '• GPIO 14 → LED Merah (+) via resistor 220Ω',
    '• GPIO 27 → Buzzer (+)',
    '• 3V3     → VCC semua modul',
    '• GND     → Ground semua modul',
    '',
    '💡 TIPS PERAKITAN:',
    '1. Mulai dari koneksi power (3V3 dan GND)',
    '2. Lanjutkan dengan koneksi SPI (RC522 dan microSD)',
    '3. Pasang koneksi I2C (OLED dan RTC)',
    '4. Terakhir pasang LED dan buzzer',
    '5. Periksa semua koneksi sebelum menghidupkan',
    '',
    '⚠️ PERINGATAN:',
    '• Pastikan polaritas LED benar (kaki panjang = +)',
    '• Resistor 220Ω harus dipasang pada kaki positif LED',
    '• Buzzer memiliki polaritas, pastikan + dan - benar',
    '• Jangan menghubungkan 5V ke pin ESP32',
    '',
    '🔧 ALAT YANG DIBUTUHKAN:',
    '• Multimeter untuk mengukur tegangan',
    '• Obeng kecil untuk mengencangkan koneksi',
    '• Kabel jumper dengan warna berbeda',
    '• Breadboard half size',
    '• Resistor 220Ω (2 buah)',
], true);

// Halaman penutup dengan desain sederhana dan elegan
$pdf->AddPage();

// Background putih bersih
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 210, 297, 'F');

// Frame elegan yang sederhana
$pdf->SetDrawColor(107, 114, 128); // Soft gray
$pdf->SetLineWidth(1);
$pdf->Rect(30, 30, 150, 237, 'D');

// Thank you message sederhana
$pdf->SetTextColor(31, 41, 55); // Soft dark
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetXY(30, 120);
$pdf->Cell(150, 15, utf8_decode('TERIMA KASIH'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetXY(30, 135);
$pdf->Cell(150, 12, utf8_decode('Instalasi Berhasil!'), 0, 1, 'C');

// System info sederhana
$pdf->SetTextColor(75, 85, 99); // Soft dark gray
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(30, 180);
$pdf->Cell(150, 8, utf8_decode('Sistem Kehadiran RFID'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(30, 190);
$pdf->Cell(150, 6, utf8_decode(env('SCHOOL_NAME', 'Sekolah Anda')), 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(30, 200);
$pdf->Cell(150, 5, utf8_decode('Siap digunakan untuk meningkatkan efisiensi'), 0, 1, 'C');

// Footer sederhana
$pdf->SetTextColor(107, 114, 128); // Soft gray
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY(30, 250);
$pdf->Cell(150, 5, utf8_decode('Versi 1.0 | ' . date('d F Y')), 0, 1, 'C');

$pdf->Output('I', 'panduan_setup_kehadiran.pdf');
exit;
