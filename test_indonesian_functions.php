<?php
/**
 * Test script untuk memverifikasi fungsi-fungsi Indonesia
 */

echo "🧪 TESTING INDONESIAN FUNCTIONS\n";
echo "================================\n\n";

// Test 1: Check if all Indonesian functions exist
echo "1. Testing Indonesian function availability...\n";
require_once __DIR__ . '/web/bootstrap.php';

$functions = [
    'sudah_masuk',
    'masuk_pengguna', 
    'keluar_pengguna',
    'coba_masuk',
    'wajib_masuk',
    'simpan_konfigurasi',
    'hari_libur_mingguan',
    'adalah_libur',
    'buat_peta_override',
    'selesaikan_status_harian'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "   ✅ $func() exists\n";
    } else {
        echo "   ❌ $func() missing\n";
    }
}

echo "\n";

// Test 2: Test database connection
echo "2. Testing database connection...\n";
try {
    $pdo = pdo();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Database connection successful\n";
    echo "   ✅ Users table accessible (count: {$result['count']})\n";
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test Indonesian function calls
echo "3. Testing Indonesian function calls...\n";
try {
    // Test sudah_masuk
    $isLoggedIn = sudah_masuk();
    echo "   ✅ sudah_masuk() working: " . ($isLoggedIn ? 'true' : 'false') . "\n";
    
    // Test hari_libur_mingguan
    $weeklyOff = hari_libur_mingguan();
    echo "   ✅ hari_libur_mingguan() working: " . json_encode($weeklyOff) . "\n";
    
    // Test adalah_libur
    $today = new DateTime('today');
    $isHoliday = adalah_libur($today);
    echo "   ✅ adalah_libur() working: " . ($isHoliday ? 'true' : 'false') . "\n";
    
    // Test buat_peta_override
    $start = new DateTime('today');
    $end = (clone $start)->modify('+1 day');
    $overrideMap = buat_peta_override($pdo, $start, $end);
    echo "   ✅ buat_peta_override() working: " . count($overrideMap) . " entries\n";
    
} catch (Exception $e) {
    echo "   ❌ Function call failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test old function names (should not exist)
echo "4. Testing old function names (should not exist)...\n";
$oldFunctions = [
    'is_logged_in',
    'login_user',
    'logout_user', 
    'attempt_login',
    'require_login',
    'save_env',
    'weekly_off_days',
    'is_holiday',
    'build_override_map',
    'resolve_daily_status'
];

foreach ($oldFunctions as $func) {
    if (function_exists($func)) {
        echo "   ❌ $func() still exists (should be removed)\n";
    } else {
        echo "   ✅ $func() properly removed\n";
    }
}

echo "\n";

// Test 5: Test index.php accessibility
echo "5. Testing index.php accessibility...\n";
$indexUrl = 'http://localhost/attendance/index.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$response = @file_get_contents($indexUrl, false, $context);
if ($response !== false) {
    echo "   ✅ index.php accessible\n";
    
    if (strpos($response, 'Dashboard Kehadiran') !== false) {
        echo "   ✅ Indonesian title found\n";
    } else {
        echo "   ❌ Indonesian title not found\n";
    }
} else {
    echo "   ❌ index.php not accessible (expected - requires login)\n";
}

echo "\n";

// Test 6: Test API endpoints
echo "6. Testing API endpoints...\n";
$apiEndpoints = [
    'recap_print.php',
    'recap_row.php', 
    'recap_list.php',
    'recap.php'
];

foreach ($apiEndpoints as $endpoint) {
    $filePath = "web/api/$endpoint";
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        if (strpos($content, 'buat_peta_override') !== false) {
            echo "   ✅ $endpoint uses buat_peta_override\n";
        } else {
            echo "   ❌ $endpoint missing buat_peta_override\n";
        }
        
        if (strpos($content, 'selesaikan_status_harian') !== false) {
            echo "   ✅ $endpoint uses selesaikan_status_harian\n";
        } else {
            echo "   ❌ $endpoint missing selesaikan_status_harian\n";
        }
        
        if (strpos($content, 'build_override_map') !== false) {
            echo "   ❌ $endpoint still uses old build_override_map\n";
        } else {
            echo "   ✅ $endpoint properly updated\n";
        }
    } else {
        echo "   ❌ $endpoint file not found\n";
    }
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Indonesian function translation test completed!\n";
echo "If all tests passed, the system should be working with Indonesian function names.\n";
echo "\nKey changes:\n";
echo "- All function names translated to Indonesian\n";
echo "- All API files updated to use new function names\n";
echo "- Database connection maintained\n";
echo "- Interface remains functional\n";
echo "\n";
?>


