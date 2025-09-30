@echo off
echo 🔄 MENGKONVERSI PANDUAN KE PDF
echo ==============================
echo.

REM Cek apakah file HTML ada
if not exist "PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html" (
    echo ❌ File HTML tidak ditemukan!
    pause
    exit /b 1
)

echo ✅ File HTML ditemukan
echo.

REM Cek apakah wkhtmltopdf tersedia
where wkhtmltopdf >nul 2>&1
if %errorlevel% == 0 (
    echo ✅ wkhtmltopdf ditemukan
    echo 🔄 Mengkonversi HTML ke PDF...
    echo.
    
    wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm --margin-left 15mm --margin-right 15mm --encoding UTF-8 "PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html" "PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf"
    
    if exist "PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf" (
        echo ✅ PDF berhasil dibuat!
        echo 📁 Ukuran file: 
        for %%A in ("PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf") do echo %%~zA bytes
        echo.
        echo 🎯 Buka file PDF: PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf
    ) else (
        echo ❌ Gagal membuat PDF
    )
) else (
    echo ⚠️ wkhtmltopdf tidak ditemukan
    echo.
    echo 📋 Instruksi manual:
    echo 1. Buka file: PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html
    echo 2. Tekan Ctrl+P (Print)
    echo 3. Pilih 'Save as PDF'
    echo 4. Simpan sebagai: PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf
    echo.
    echo 🔗 Download wkhtmltopdf dari: https://wkhtmltopdf.org/downloads.html
    echo.
    echo 📂 Membuka file HTML...
    start "" "PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html"
)

echo.
echo 📚 PANDUAN LENGKAP TERSEDIA:
echo ============================
echo 📄 HTML: PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html
echo 📄 PDF: PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.pdf
echo.
echo 🎯 Sistem Kehadiran RFID Enterprise - Dokumentasi Lengkap!
echo.
pause
