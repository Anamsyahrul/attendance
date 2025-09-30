<?php
/**
 * Test script untuk mensimulasikan akses web browser
 */

// Mulai output buffering
ob_start();

echo "🧪 TESTING WEB BROWSER ACCESS SIMULATION\n";
echo "=========================================\n\n";

// Test 1: Simulasi login admin
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

echo "   Admin login successful\n";
echo "   - User ID: " . $_SESSION['user_id'] . "\n";
echo "   - Role: " . $_SESSION['role'] . "\n";
echo "   - Username: " . $_SESSION['username'] . "\n";

echo "\n";

// Test 2: Simulasi akses ke index.php
echo "2. Simulating access to index.php...\n";
echo "   URL: http://localhost/attendance/index.php\n";
echo "   Expected: Should show dashboard\n";

// Simulasi pengecekan seperti di index.php
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "   ❌ Would redirect to login.php\n";
} else {
    echo "   ✅ Access granted - Dashboard would be shown\n";
    echo "   - User: " . $_SESSION['name'] . "\n";
    echo "   - Role: " . $_SESSION['role'] . "\n";
}

echo "\n";

// Test 3: Simulasi akses ke admin_simple.php
echo "3. Simulating access to admin_simple.php...\n";
echo "   URL: http://localhost/attendance/admin_simple.php\n";
echo "   Expected: Should show admin panel\n";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "   ❌ Would redirect to login.php\n";
} else {
    echo "   ✅ Access granted - Admin panel would be shown\n";
}

echo "\n";

// Test 4: Simulasi akses ke teacher.php (sebagai admin)
echo "4. Simulating access to teacher.php (as admin)...\n";
echo "   URL: http://localhost/attendance/teacher.php\n";
echo "   Expected: Should redirect to login or show error\n";

require_once 'web/bootstrap.php';
require_once 'web/classes/AuthService.php';

$pdo = pdo();
$config = $ENV;
$authService = new AuthService($pdo, $config);

if (!$authService->isLoggedIn()) {
    echo "   ❌ Would redirect to login.php (not logged in)\n";
} else {
    $user = $authService->getCurrentUser();
    if ($user['role'] === 'teacher') {
        echo "   ✅ Access granted - Teacher dashboard would be shown\n";
    } else {
        echo "   ❌ Access denied - Wrong role (current: {$user['role']}, required: teacher)\n";
        echo "   - This is CORRECT behavior - admin should not access teacher page\n";
    }
}

echo "\n";

// Test 5: Simulasi akses ke teacher.php (sebagai teacher)
echo "5. Simulating access to teacher.php (as teacher)...\n";
echo "   URL: http://localhost/attendance/teacher.php\n";
echo "   Expected: Should show teacher dashboard\n";

// Ubah role menjadi teacher
$_SESSION['role'] = 'teacher';
$_SESSION['username'] = 'teacher1';
$_SESSION['name'] = 'Teacher User';

$authService = new AuthService($pdo, $config);
if (!$authService->isLoggedIn()) {
    echo "   ❌ Would redirect to login.php (not logged in)\n";
} else {
    $user = $authService->getCurrentUser();
    if ($user['role'] === 'teacher') {
        echo "   ✅ Access granted - Teacher dashboard would be shown\n";
    } else {
        echo "   ❌ Access denied - Wrong role (current: {$user['role']}, required: teacher)\n";
    }
}

echo "\n";

// Test 6: Simulasi menu navigation
echo "6. Simulating menu navigation...\n";
echo "   Admin user should see these menu items:\n";
echo "   - Dashboard (index.php) ✅\n";
echo "   - Daftar Kartu (register.php) ✅\n";
echo "   - Siswa (users.php) ✅\n";
echo "   - Kelas (rooms.php) ✅\n";
echo "   - Pengaturan (settings.php) ✅\n";
echo "   - Panel Admin (admin_simple.php) ✅\n";
echo "   - Laporan (reports.php) ✅\n";
echo "   - Keluar (logout.php) ✅\n";
echo "\n";
echo "   Admin user should NOT see these menu items:\n";
echo "   - Teacher Dashboard (teacher.php) ❌\n";
echo "   - Parent Dashboard (parent.php) ❌\n";
echo "   - Student Dashboard (student.php) ❌\n";

echo "\n";

// Test 7: Simulasi akses langsung ke URL
echo "7. Simulating direct URL access...\n";
echo "   If user types URL directly:\n";
echo "   - http://localhost/attendance/index.php → Should work (admin)\n";
echo "   - http://localhost/attendance/admin_simple.php → Should work (admin)\n";
echo "   - http://localhost/attendance/teacher.php → Should redirect to login (admin)\n";
echo "   - http://localhost/attendance/parent.php → Should redirect to login (admin)\n";
echo "   - http://localhost/attendance/student.php → Should redirect to login (admin)\n";

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Web browser access simulation completed!\n";
echo "\nIf you're getting redirected to login when accessing menus:\n";
echo "1. Make sure you're logged in as the correct role\n";
echo "2. Admin should access admin_simple.php, not teacher.php\n";
echo "3. Teacher should access teacher.php, not admin_simple.php\n";
echo "4. Check if you're clicking the correct menu items\n";
echo "5. Check if there are any JavaScript errors\n";
echo "6. Check if session is being destroyed\n";
echo "\n";
?>


