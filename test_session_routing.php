<?php
/**
 * Test script untuk memeriksa session dan routing
 */

// Mulai output buffering untuk mencegah error headers already sent
ob_start();

echo "🧪 TESTING SESSION AND ROUTING\n";
echo "===============================\n\n";

// Test 1: Check session configuration
echo "1. Testing session configuration...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "   Session status: " . session_status() . "\n";
echo "   Session ID: " . session_id() . "\n";
echo "   Session data: " . print_r($_SESSION, true) . "\n";

echo "\n";

// Test 2: Check if user is logged in
echo "2. Testing login status...\n";
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
echo "   Is logged in: " . ($isLoggedIn ? 'YES' : 'NO') . "\n";

if ($isLoggedIn) {
    echo "   User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    echo "   Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
    echo "   Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
    echo "   Name: " . ($_SESSION['name'] ?? 'NOT SET') . "\n";
    echo "   Room: " . ($_SESSION['room'] ?? 'NOT SET') . "\n";
} else {
    echo "   User not logged in - redirecting to login\n";
}

echo "\n";

// Test 3: Test AuthService
echo "3. Testing AuthService...\n";
require_once 'web/bootstrap.php';
require_once 'web/classes/AuthService.php';

$pdo = pdo();
$config = $ENV;
$authService = new AuthService($pdo, $config);

echo "   AuthService created successfully\n";
echo "   AuthService isLoggedIn(): " . ($authService->isLoggedIn() ? 'YES' : 'NO') . "\n";

$currentUser = $authService->getCurrentUser();
if ($currentUser) {
    echo "   Current user: " . print_r($currentUser, true) . "\n";
} else {
    echo "   No current user\n";
}

echo "\n";

// Test 4: Test file accessibility
echo "4. Testing file accessibility...\n";
$files = [
    'web/public/index.php',
    'web/public/admin_simple.php',
    'web/public/teacher.php',
    'web/public/parent.php',
    'web/public/student.php',
    'web/public/settings.php',
    'web/public/reports.php',
    'web/public/users.php',
    'web/public/rooms.php',
    'web/public/register.php'
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

// Test 5: Test login simulation
echo "5. Testing login simulation...\n";
if (!$isLoggedIn) {
    echo "   Simulating admin login...\n";
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = 'Administrator';
    $_SESSION['room'] = 'Admin';
    $_SESSION['login_time'] = time();
    
    echo "   Session set:\n";
    echo "   - user_id: " . $_SESSION['user_id'] . "\n";
    echo "   - role: " . $_SESSION['role'] . "\n";
    echo "   - username: " . $_SESSION['username'] . "\n";
    
    // Test AuthService again
    $authService = new AuthService($pdo, $config);
    echo "   AuthService isLoggedIn(): " . ($authService->isLoggedIn() ? 'YES' : 'NO') . "\n";
    
    $currentUser = $authService->getCurrentUser();
    if ($currentUser) {
        echo "   Current user after simulation: " . print_r($currentUser, true) . "\n";
    }
}

echo "\n";

// Test 6: Test role-based access
echo "6. Testing role-based access...\n";
if ($isLoggedIn) {
    $role = $_SESSION['role'];
    echo "   Current role: $role\n";
    
    // Test different role requirements
    $testRoles = ['admin', 'teacher', 'parent', 'student'];
    foreach ($testRoles as $testRole) {
        $hasAccess = ($role === $testRole);
        echo "   Access to $testRole: " . ($hasAccess ? 'YES' : 'NO') . "\n";
    }
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Session and routing test completed!\n";
echo "\nIf you're getting redirected to login, check:\n";
echo "1. Session data is properly set\n";
echo "2. AuthService is working correctly\n";
echo "3. File permissions are correct\n";
echo "4. No session conflicts\n";
echo "\n";
?>
