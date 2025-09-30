<?php
require_once __DIR__ . '/../bootstrap.php';

// Fungsi untuk mendeteksi wkhtmltopdf
function detectWkhtmltopdf() {
    $possiblePaths = [
        'wkhtmltopdf', // Dalam PATH
        'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
        'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe',
        '/usr/bin/wkhtmltopdf', // Linux/Mac
        '/usr/local/bin/wkhtmltopdf'
    ];

    foreach ($possiblePaths as $path) {
        // Cek apakah file executable ada
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }

        // Coba jalankan command untuk test
        $testCommand = escapeshellarg($path) . ' --version 2>&1';
        $output = shell_exec($testCommand);
        if ($output && strpos($output, 'wkhtmltopdf') !== false) {
            return $path;
        }
    }

    // Coba dengan where command (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $whereOutput = shell_exec('where wkhtmltopdf 2>nul');
        if ($whereOutput && trim($whereOutput) !== '') {
            $lines = explode("\n", trim($whereOutput));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && file_exists($line)) {
                    return $line;
                }
            }
        }
    }

    return false;
}

// Fungsi untuk logging error
function logError($message) {
    $logFile = __DIR__ . '/../storage/logs/pdf_generation.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Path ke file panduan HTML
$htmlFile = __DIR__ . '/../../PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html';

// Cek apakah file HTML ada
if (!file_exists($htmlFile)) {
    logError("File HTML tidak ditemukan: $htmlFile");
    http_response_code(404);
    echo "File panduan tidak ditemukan.";
    exit;
}

// Deteksi wkhtmltopdf
$wkhtmltopdfPath = detectWkhtmltopdf();

if ($wkhtmltopdfPath) {
    logError("wkhtmltopdf ditemukan di: $wkhtmltopdfPath");

    // Set headers untuk download file
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Panduan_Lengkap_Sistem_Kehadiran_RFID.pdf"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Opsi wkhtmltopdf untuk hasil yang lebih baik
    $options = [
        '--page-size A4',
        '--margin-top 20mm',
        '--margin-bottom 20mm',
        '--margin-left 15mm',
        '--margin-right 15mm',
        '--encoding UTF-8',
        '--disable-smart-shrinking',
        '--print-media-type',
        '--no-background',
        '--disable-javascript' // Non-aktifkan JS untuk PDF
    ];

    // Buat command
    $command = escapeshellarg($wkhtmltopdfPath) . ' ' . implode(' ', $options) . ' ' . escapeshellarg($htmlFile) . ' -';

    logError("Menjalankan command: $command");

    // Jalankan command dengan error handling yang lebih baik
    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (is_resource($process)) {
        // Tutup stdin
        fclose($pipes[0]);

        // Baca output
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);

        // Tutup pipes
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Dapatkan exit code
        $exitCode = proc_close($process);

        if ($exitCode === 0 && !empty($output)) {
            logError("PDF berhasil dibuat, ukuran: " . strlen($output) . " bytes");
            echo $output;
            exit;
        } else {
            logError("wkhtmltopdf gagal dengan exit code $exitCode. Error: $errorOutput");
        }
    } else {
        logError("Gagal membuka process wkhtmltopdf");
    }
} else {
    logError("wkhtmltopdf tidak ditemukan di sistem");
}

// Fallback: redirect ke HTML jika PDF gagal
logError("Melakukan fallback ke HTML");
header('Location: /attendance/PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html');
exit;
?>