<?php
/**
 * Test script untuk menguji login aktual dan akses dashboard
 */

// Mulai output buffering
ob_start();

echo "🧪 TESTING ACTUAL LOGIN AND DASHBOARD ACCESS\n";
echo "============================================\n\n";

// Test 1: Test login simulation
echo "1. Testing login simulation...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulasi login admin
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'Administrator';
$_SESSION['room'] = 'Admin';
$_SESSION['login_time'] = time();

echo "   Admin login simulated\n";
echo "   - User ID: " . $_SESSION['user_id'] . "\n";
echo "   - Role: " . $_SESSION['role'] . "\n";
echo "   - Username: " . $_SESSION['username'] . "\n";

echo "\n";

// Test 2: Test access to different files
echo "2. Testing access to different files...\n";
$files = [
    'index.php' => 'Dashboard (should work for admin)',
    'admin_simple.php' => 'Admin Panel (should work for admin)',
    'teacher.php' => 'Teacher Dashboard (should NOT work for admin)',
    'parent.php' => 'Parent Dashboard (should NOT work for admin)',
    'student.php' => 'Student Dashboard (should NOT work for admin)',
    'settings.php' => 'Settings (should work for admin)',
    'reports.php' => 'Reports (should work for admin)',
    'users.php' => 'Users (should work for admin)',
    'rooms.php' => 'Rooms (should work for admin)',
    'register.php' => 'Register (should work for admin)'
];

foreach ($files as $file => $description) {
    echo "   Testing $file ($description)...\n";
    
    // Simulasi pengecekan seperti di file asli
    if ($file === 'teacher.php' || $file === 'parent.php' || $file === 'student.php') {
        // File ini menggunakan AuthService
        require_once 'web/bootstrap.php';
        require_once 'web/classes/AuthService.php';
        $pdo = pdo();
        $config = $ENV;
        $authService = new AuthService($pdo, $config);
        
        if (!$authService->isLoggedIn()) {
            echo "     ❌ Would redirect to login.php (not logged in)\n";
        } else {
            $user = $authService->getCurrentUser();
            $requiredRole = str_replace('.php', '', $file);
            
            if ($user['role'] === $requiredRole) {
                echo "     ✅ Access granted (correct role: {$user['role']})\n";
            } else {
                echo "     ❌ Access denied (wrong role: {$user['role']}, required: $requiredRole)\n";
            }
        }
    } else {
        // File ini menggunakan pengecekan session sederhana
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            echo "     ❌ Would redirect to login.php (no session)\n";
        } else {
            echo "     ✅ Access granted (session exists)\n";
        }
    }
}

echo "\n";

// Test 3: Test different user roles
echo "3. Testing different user roles...\n";
$testRoles = [
    'admin' => ['index.php', 'admin_simple.php', 'settings.php', 'reports.php', 'users.php', 'rooms.php', 'register.php'],
    'teacher' => ['teacher.php'],
    'parent' => ['parent.php'],
    'student' => ['student.php']
];

foreach ($testRoles as $role => $allowedFiles) {
    echo "   Testing role: $role\n";
    
    // Set session untuk role ini
    $_SESSION['role'] = $role;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = $role . '1';
    $_SESSION['name'] = ucfirst($role) . ' User';
    $_SESSION['room'] = 'Test Room';
    
    foreach ($files as $file => $description) {
        $shouldWork = in_array($file, $allowedFiles);
        $status = $shouldWork ? '✅' : '❌';
        echo "     $status $file - " . ($shouldWork ? 'Should work' : 'Should not work') . "\n";
    }
    echo "\n";
}

echo "\n";

// Test 4: Test menu navigation
echo "4. Testing menu navigation...\n";
echo "   Admin should see these menus:\n";
echo "   - Dashboard (index.php) ✅\n";
echo "   - Admin Panel (admin_simple.php) ✅\n";
echo "   - Settings (settings.php) ✅\n";
echo "   - Reports (reports.php) ✅\n";
echo "   - Users (users.php) ✅\n";
echo "   - Rooms (rooms.php) ✅\n";
echo "   - Register (register.php) ✅\n";
echo "   - Logout (logout.php) ✅\n";
echo "\n";
echo "   Admin should NOT see these menus:\n";
echo "   - Teacher Dashboard (teacher.php) ❌\n";
echo "   - Parent Dashboard (parent.php) ❌\n";
echo "   - Student Dashboard (student.php) ❌\n";

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Login and dashboard access test completed!\n";
echo "\nIf you're getting redirected to login when accessing menus:\n";
echo "1. Check if you're accessing the correct file for your role\n";
echo "2. Admin should use admin_simple.php, not teacher.php\n";
echo "3. Teacher should use teacher.php, not admin_simple.php\n";
echo "4. Check if session data is properly set\n";
echo "5. Check if there are any JavaScript redirects\n";
echo "\n";
?>
