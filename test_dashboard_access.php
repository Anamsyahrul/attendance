<?php
/**
 * Test script untuk menguji akses dashboard setelah login
 */

// Mulai output buffering
ob_start();

echo "🧪 TESTING DASHBOARD ACCESS AFTER LOGIN\n";
echo "=======================================\n\n";

// Simulasi login admin
echo "1. Simulating admin login...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set session data seperti di login.php
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'Administrator';
$_SESSION['room'] = 'Admin';
$_SESSION['login_time'] = time();

echo "   Session data set:\n";
echo "   - user_id: " . $_SESSION['user_id'] . "\n";
echo "   - role: " . $_SESSION['role'] . "\n";
echo "   - username: " . $_SESSION['username'] . "\n";
echo "   - name: " . $_SESSION['name'] . "\n";

echo "\n";

// Test 2: Test index.php access
echo "2. Testing index.php access...\n";
require_once 'web/bootstrap.php';

// Simulasi pengecekan session seperti di index.php
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "   ❌ Would redirect to login.php\n";
} else {
    echo "   ✅ Access granted to index.php\n";
    echo "   - User ID: " . $_SESSION['user_id'] . "\n";
    echo "   - Role: " . $_SESSION['role'] . "\n";
}

echo "\n";

// Test 3: Test admin_simple.php access
echo "3. Testing admin_simple.php access...\n";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "   ❌ Would redirect to login.php\n";
} else {
    echo "   ✅ Access granted to admin_simple.php\n";
    echo "   - Admin access confirmed\n";
}

echo "\n";

// Test 4: Test teacher.php access
echo "4. Testing teacher.php access...\n";
require_once 'web/classes/AuthService.php';

$pdo = pdo();
$config = $ENV;
$authService = new AuthService($pdo, $config);

if (!$authService->isLoggedIn()) {
    echo "   ❌ AuthService says not logged in\n";
} else {
    echo "   ✅ AuthService confirms login\n";
    
    $user = $authService->getCurrentUser();
    echo "   - Current user: " . print_r($user, true) . "\n";
    
    // Test role requirement
    if ($user['role'] === 'teacher') {
        echo "   ✅ Teacher role access granted\n";
    } else {
        echo "   ❌ Teacher role access denied (current role: " . $user['role'] . ")\n";
    }
}

echo "\n";

// Test 5: Test different roles
echo "5. Testing different role access...\n";
$testRoles = ['admin', 'teacher', 'parent', 'student'];
$currentRole = $_SESSION['role'];

foreach ($testRoles as $role) {
    if ($role === $currentRole) {
        echo "   ✅ Access to $role: GRANTED (current role)\n";
    } else {
        echo "   ❌ Access to $role: DENIED (current role: $currentRole)\n";
    }
}

echo "\n";

// Test 6: Test menu navigation simulation
echo "6. Testing menu navigation simulation...\n";
$menuItems = [
    'Dashboard' => 'index.php',
    'Admin Panel' => 'admin_simple.php',
    'Teacher Dashboard' => 'teacher.php',
    'Parent Dashboard' => 'parent.php',
    'Student Dashboard' => 'student.php',
    'Settings' => 'settings.php',
    'Reports' => 'reports.php',
    'Users' => 'users.php',
    'Rooms' => 'rooms.php',
    'Register' => 'register.php'
];

foreach ($menuItems as $menuName => $file) {
    if (file_exists("web/public/$file")) {
        echo "   ✅ $menuName ($file) - File exists\n";
    } else {
        echo "   ❌ $menuName ($file) - File not found\n";
    }
}

echo "\n";

// Test 7: Test session persistence
echo "7. Testing session persistence...\n";
$sessionId = session_id();
echo "   Session ID: $sessionId\n";
echo "   Session data: " . print_r($_SESSION, true) . "\n";

// Simulasi navigasi antar halaman
echo "   Simulating page navigation...\n";
echo "   - From login.php to index.php: " . (isset($_SESSION['user_id']) ? 'SUCCESS' : 'FAILED') . "\n";
echo "   - From index.php to admin_simple.php: " . (($_SESSION['role'] ?? '') === 'admin' ? 'SUCCESS' : 'FAILED') . "\n";
echo "   - From admin_simple.php to teacher.php: " . (($_SESSION['role'] ?? '') === 'teacher' ? 'SUCCESS' : 'FAILED') . "\n";

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Dashboard access test completed!\n";
echo "\nIf you're getting redirected to login when accessing menus:\n";
echo "1. Check if session data is properly set after login\n";
echo "2. Check if session is not being destroyed\n";
echo "3. Check if there are any session conflicts\n";
echo "4. Check if file permissions are correct\n";
echo "5. Check if there are any JavaScript redirects\n";
echo "\n";
?>
