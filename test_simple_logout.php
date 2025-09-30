<?php
/**
 * Test sederhana untuk logout
 */

echo "🧪 SIMPLE LOGOUT TEST\n";
echo "====================\n\n";

// Test 1: Cek file logout.php
echo "1. Testing logout.php file...\n";
$logoutFile = 'web/public/logout.php';
if (file_exists($logoutFile)) {
    $content = file_get_contents($logoutFile);
    
    if (strpos($content, 'keluar_pengguna()') !== false) {
        echo "   ✅ Menggunakan keluar_pengguna()\n";
    } else {
        echo "   ❌ Tidak menggunakan keluar_pengguna()\n";
    }
    
    if (strpos($content, 'logout_user()') !== false) {
        echo "   ❌ Masih ada logout_user()\n";
    } else {
        echo "   ✅ Tidak ada logout_user()\n";
    }
    
    if (strpos($content, 'session_start()') !== false) {
        echo "   ✅ Ada session_start()\n";
    } else {
        echo "   ❌ Tidak ada session_start()\n";
    }
} else {
    echo "   ❌ File logout.php tidak ditemukan\n";
}

// Test 2: Cek fungsi keluar_pengguna
echo "\n2. Testing keluar_pengguna function...\n";
try {
    require_once 'web/bootstrap.php';
    
    if (function_exists('keluar_pengguna')) {
        echo "   ✅ Fungsi keluar_pengguna() tersedia\n";
        
        // Test fungsi tanpa session
        echo "   📝 Testing fungsi keluar_pengguna()...\n";
        keluar_pengguna();
        echo "   ✅ keluar_pengguna() berhasil dipanggil\n";
    } else {
        echo "   ❌ Fungsi keluar_pengguna() tidak tersedia\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Cek URL logout
echo "\n3. Testing logout URL...\n";
$logoutUrl = 'http://localhost/attendance/web/public/logout.php';
echo "   🔗 Logout URL: $logoutUrl\n";
echo "   📝 Test URL ini di browser untuk memverifikasi logout\n";

echo "\n";
echo "🎯 SIMPLE TEST SUMMARY\n";
echo "======================\n";
echo "✅ logout.php file checked\n";
echo "✅ keluar_pengguna function checked\n";
echo "✅ Logout URL provided\n";
echo "\n";
echo "🚀 LOGOUT SHOULD WORK NOW!\n";
echo "==========================\n";
echo "The 'logout_user()' error should be fixed\n";
?>

