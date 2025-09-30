<?php
/**
 * Script untuk mengkonversi HTML ke PDF
 * Menggunakan wkhtmltopdf atau browser print
 */

echo "🔄 MENGKONVERSI PANDUAN KE PDF\n";
echo "===============================\n\n";

$htmlFile = 'PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html';
$pdfFile = 'PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf';

if (!file_exists($htmlFile)) {
    echo "❌ File HTML tidak ditemukan: $htmlFile\n";
    exit(1);
}

echo "✅ File HTML ditemukan: $htmlFile\n";

// Cek apakah wkhtmltopdf tersedia
$wkhtmltopdf = shell_exec('where wkhtmltopdf 2>nul');
if ($wkhtmltopdf) {
    echo "✅ wkhtmltopdf ditemukan\n";
    echo "🔄 Mengkonversi HTML ke PDF...\n";
    
    $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm --margin-left 15mm --margin-right 15mm --encoding UTF-8 \"$htmlFile\" \"$pdfFile\"";
    
    $output = shell_exec($command . ' 2>&1');
    
    if (file_exists($pdfFile)) {
        echo "✅ PDF berhasil dibuat: $pdfFile\n";
        echo "📁 Ukuran file: " . number_format(filesize($pdfFile) / 1024, 2) . " KB\n";
    } else {
        echo "❌ Gagal membuat PDF\n";
        echo "Output: $output\n";
    }
} else {
    echo "⚠️ wkhtmltopdf tidak ditemukan\n";
    echo "📋 Instruksi manual:\n";
    echo "1. Buka file: $htmlFile\n";
    echo "2. Tekan Ctrl+P (Print)\n";
    echo "3. Pilih 'Save as PDF'\n";
    echo "4. Simpan sebagai: $pdfFile\n";
    echo "\n";
    echo "🔗 Atau download wkhtmltopdf dari: https://wkhtmltopdf.org/downloads.html\n";
}

echo "\n";
echo "📚 PANDUAN LENGKAP TERSEDIA:\n";
echo "============================\n";
echo "📄 HTML: $htmlFile\n";
echo "📄 PDF: $pdfFile\n";
echo "\n";
echo "🎯 Sistem Kehadiran RFID Enterprise - Dokumentasi Lengkap!\n";
?>

