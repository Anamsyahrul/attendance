<?php
/**
 * Test script untuk memverifikasi login system
 */

echo "🧪 TESTING LOGIN SYSTEM\n";
echo "========================\n\n";

// Test 1: Check if login.php exists and is accessible
echo "1. Testing login.php accessibility...\n";
$loginUrl = 'http://localhost/attendance/login.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$response = @file_get_contents($loginUrl, false, $context);
if ($response !== false) {
    echo "   ✅ login.php accessible\n";
    
    // Check if it contains expected elements
    if (strpos($response, 'Sistem Kehadiran') !== false) {
        echo "   ✅ Page title found\n";
    } else {
        echo "   ❌ Page title not found\n";
    }
    
    if (strpos($response, 'themeToggle') !== false) {
        echo "   ✅ Dark/Light mode toggle found\n";
    } else {
        echo "   ❌ Dark/Light mode toggle not found\n";
    }
    
    if (strpos($response, 'role-card') !== false) {
        echo "   ✅ Role selection cards found\n";
    } else {
        echo "   ❌ Role selection cards not found\n";
    }
} else {
    echo "   ❌ login.php not accessible\n";
}

echo "\n";

// Test 2: Check database connection
echo "2. Testing database connection...\n";
try {
    require_once __DIR__ . '/web/bootstrap.php';
    $pdo = pdo();
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   ✅ Database connection successful\n";
    echo "   ✅ Users table accessible (count: {$result['count']})\n";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "   ✅ Admin user found in database\n";
    } else {
        echo "   ❌ Admin user not found in database\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check if admin_simple.php exists
echo "3. Testing admin_simple.php accessibility...\n";
$adminUrl = 'http://localhost/attendance/admin_simple.php';
$response = @file_get_contents($adminUrl, false, $context);
if ($response !== false) {
    echo "   ✅ admin_simple.php accessible\n";
    
    if (strpos($response, 'Admin Panel') !== false) {
        echo "   ✅ Admin panel title found\n";
    } else {
        echo "   ❌ Admin panel title not found\n";
    }
} else {
    echo "   ❌ admin_simple.php not accessible (expected - requires login)\n";
}

echo "\n";

// Test 4: Check if reports.php exists
echo "4. Testing reports.php accessibility...\n";
$reportsUrl = 'http://localhost/attendance/reports.php';
$response = @file_get_contents($reportsUrl, false, $context);
if ($response !== false) {
    echo "   ✅ reports.php accessible\n";
    
    if (strpos($response, 'Laporan Kehadiran') !== false) {
        echo "   ✅ Reports page title found\n";
    } else {
        echo "   ❌ Reports page title not found\n";
    }
} else {
    echo "   ❌ reports.php not accessible (expected - requires login)\n";
}

echo "\n";

// Test 5: Check if logout.php exists
echo "5. Testing logout.php...\n";
$logoutUrl = 'http://localhost/attendance/logout.php';
$response = @file_get_contents($logoutUrl, false, $context);
if ($response !== false) {
    echo "   ✅ logout.php accessible\n";
} else {
    echo "   ❌ logout.php not accessible\n";
}

echo "\n";

// Test 6: Check file permissions
echo "6. Testing file permissions...\n";
$files = [
    'web/public/login.php',
    'web/public/admin_simple.php',
    'web/public/logout.php',
    'web/public/reports.php',
    'web/bootstrap.php'
];

foreach ($files as $file) {
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

// Test 7: Check session functionality
echo "7. Testing session functionality...\n";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Session functionality working\n";
    
    // Test session variables
    $_SESSION['test'] = 'test_value';
    if (isset($_SESSION['test']) && $_SESSION['test'] === 'test_value') {
        echo "   ✅ Session variables working\n";
    } else {
        echo "   ❌ Session variables not working\n";
    }
    
    // Clean up
    unset($_SESSION['test']);
} else {
    echo "   ❌ Session functionality not working\n";
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Login system test completed!\n";
echo "If all tests passed, the login system should be working correctly.\n";
echo "\nTo test manually:\n";
echo "1. Go to: http://localhost/attendance/login.php\n";
echo "2. Try login with: admin / admin\n";
echo "3. Test dark/light mode toggle\n";
echo "4. Test role selection\n";
echo "\n";
?>

