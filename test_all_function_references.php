<?php
/**
 * Test script untuk memverifikasi semua referensi fungsi Indonesia
 */

echo "🧪 TESTING ALL FUNCTION REFERENCES\n";
echo "===================================\n\n";

// Test 1: Check all PHP files for old function references
echo "1. Testing for old function references in PHP files...\n";
$oldFunctions = [
    'require_login',
    'is_logged_in',
    'login_user',
    'logout_user',
    'attempt_login',
    'save_env',
    'weekly_off_days',
    'is_holiday',
    'build_override_map',
    'resolve_daily_status'
];

$files = [
    'web/public/settings.php',
    'web/public/reports.php',
    'web/public/users.php',
    'web/public/rooms.php',
    'web/public/register.php',
    'web/public/index.php',
    'web/public/login.php',
    'web/public/admin_simple.php',
    'web/api/recap_print.php',
    'web/api/recap_row.php',
    'web/api/recap_list.php',
    'web/api/recap.php'
];

$foundOldFunctions = false;
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($oldFunctions as $func) {
            if (strpos($content, $func . '(') !== false) {
                echo "   ❌ $file still uses $func()\n";
                $foundOldFunctions = true;
            }
        }
    }
}

if (!$foundOldFunctions) {
    echo "   ✅ No old function references found\n";
}

echo "\n";

// Test 2: Check for Indonesian function references
echo "2. Testing for Indonesian function references...\n";
$indonesianFunctions = [
    'wajib_masuk',
    'sudah_masuk',
    'masuk_pengguna',
    'keluar_pengguna',
    'coba_masuk',
    'simpan_konfigurasi',
    'hari_libur_mingguan',
    'adalah_libur',
    'buat_peta_override',
    'selesaikan_status_harian'
];

$foundIndonesianFunctions = 0;
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($indonesianFunctions as $func) {
            if (strpos($content, $func . '(') !== false) {
                $foundIndonesianFunctions++;
            }
        }
    }
}

echo "   ✅ Found $foundIndonesianFunctions Indonesian function references\n";

echo "\n";

// Test 3: Test specific files that were causing errors
echo "3. Testing specific files that were causing errors...\n";
$problemFiles = [
    'web/public/settings.php',
    'web/public/reports.php',
    'web/public/users.php',
    'web/public/rooms.php',
    'web/public/register.php'
];

foreach ($problemFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        if (strpos($content, 'wajib_masuk()') !== false) {
            echo "   ✅ $file uses wajib_masuk()\n";
        } else {
            echo "   ❌ $file missing wajib_masuk()\n";
        }
        
        if (strpos($content, 'require_login()') !== false) {
            echo "   ❌ $file still uses require_login()\n";
        } else {
            echo "   ✅ $file properly updated\n";
        }
    } else {
        echo "   ❌ $file not found\n";
    }
}

echo "\n";

// Test 4: Test settings.php specifically for save_env
echo "4. Testing settings.php for save_env function...\n";
if (file_exists('web/public/settings.php')) {
    $content = file_get_contents('web/public/settings.php');
    
    if (strpos($content, 'simpan_konfigurasi(') !== false) {
        echo "   ✅ settings.php uses simpan_konfigurasi()\n";
    } else {
        echo "   ❌ settings.php missing simpan_konfigurasi()\n";
    }
    
    if (strpos($content, 'save_env(') !== false) {
        echo "   ❌ settings.php still uses save_env()\n";
    } else {
        echo "   ✅ settings.php properly updated\n";
    }
} else {
    echo "   ❌ settings.php not found\n";
}

echo "\n";

// Test 5: Test bootstrap.php for function definitions
echo "5. Testing bootstrap.php for function definitions...\n";
if (file_exists('web/bootstrap.php')) {
    $content = file_get_contents('web/bootstrap.php');
    
    foreach ($indonesianFunctions as $func) {
        if (strpos($content, "function $func(") !== false) {
            echo "   ✅ $func() defined in bootstrap.php\n";
        } else {
            echo "   ❌ $func() missing from bootstrap.php\n";
        }
    }
    
    foreach ($oldFunctions as $func) {
        if (strpos($content, "function $func(") !== false) {
            echo "   ❌ $func() still defined in bootstrap.php\n";
        } else {
            echo "   ✅ $func() properly removed from bootstrap.php\n";
        }
    }
} else {
    echo "   ❌ bootstrap.php not found\n";
}

echo "\n";

// Test 6: Test file accessibility
echo "6. Testing file accessibility...\n";
$testFiles = [
    'web/public/settings.php',
    'web/public/reports.php',
    'web/public/users.php',
    'web/public/rooms.php',
    'web/public/register.php'
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "   ✅ $file is readable\n";
        } else {
            echo "   ❌ $file is not readable\n";
        }
    } else {
        echo "   ❌ $file does not exist\n";
    }
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Function reference test completed!\n";
echo "If all tests passed, all files should be using Indonesian function names.\n";
echo "\nKey changes:\n";
echo "- All require_login() calls updated to wajib_masuk()\n";
echo "- All save_env() calls updated to simpan_konfigurasi()\n";
echo "- All old function references removed\n";
echo "- All files use consistent Indonesian function names\n";
echo "\n";
?>


