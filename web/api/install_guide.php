<?php
require_once __DIR__ . '/../bootstrap.php';

// Set headers untuk download file
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Panduan_Lengkap_Sistem_Kehadiran_RFID.pdf"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Path ke file panduan HTML
$htmlFile = __DIR__ . '/../../PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html';

// Cek apakah file HTML ada
if (!file_exists($htmlFile)) {
    http_response_code(404);
    echo "File panduan tidak ditemukan.";
    exit;
}

// Cek apakah wkhtmltopdf tersedia
$wkhtmltopdf = shell_exec('where wkhtmltopdf 2>nul');
if ($wkhtmltopdf && trim($wkhtmltopdf) !== '') {
    // Gunakan wkhtmltopdf untuk konversi
    $command = 'wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm --margin-left 15mm --margin-right 15mm --encoding UTF-8 "' . $htmlFile . '" -';

    // Jalankan command dan output langsung ke browser
    $output = shell_exec($command);
    if ($output === null) {
        // Jika command gagal, redirect ke HTML
        header('Location: /attendance/PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html');
        exit;
    }
    echo $output;
} else {
    // Jika wkhtmltopdf tidak tersedia, redirect ke file HTML
    header('Location: /attendance/PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html');
    exit;
}
?>