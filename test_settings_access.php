<?php
/**
 * Test script untuk memeriksa akses ke settings.php
 */

// Mulai output buffering
ob_start();

echo "🧪 TESTING SETTINGS.PHP ACCESS\n";
echo "==============================\n\n";

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

echo "   Admin login simulated\n";
echo "   - user_id: " . $_SESSION['user_id'] . "\n";
echo "   - role: " . $_SESSION['role'] . "\n";

echo "\n";

// Test 2: Test akses ke settings.php
echo "2. Testing access to settings.php...\n";
echo "   URL: http://localhost/attendance/settings.php\n";

// Simulasi pengecekan seperti di settings.php
require_once 'web/bootstrap.php';

if (sudah_masuk()) {
    echo "   ✅ User is logged in - settings.php should be accessible\n";
    echo "   - Function sudah_masuk() returns TRUE\n";
} else {
    echo "   ❌ User is not logged in - settings.php would redirect to login\n";
    echo "   - Function sudah_masuk() returns FALSE\n";
}

echo "\n";

// Test 3: Test wajib_masuk() function
echo "3. Testing wajib_masuk() function...\n";
echo "   This function is called at the beginning of settings.php\n";

// Simulasi panggilan wajib_masuk()
if (sudah_masuk()) {
    echo "   ✅ wajib_masuk() would allow access (user is logged in)\n";
} else {
    echo "   ❌ wajib_masuk() would redirect to login (user is not logged in)\n";
}

echo "\n";

// Test 4: Test file existence
echo "4. Testing file existence...\n";
if (file_exists('web/public/settings.php')) {
    echo "   ✅ settings.php file exists\n";
} else {
    echo "   ❌ settings.php file not found\n";
}

echo "\n";

// Test 5: Test file readability
echo "5. Testing file readability...\n";
if (is_readable('web/public/settings.php')) {
    echo "   ✅ settings.php file is readable\n";
} else {
    echo "   ❌ settings.php file is not readable\n";
}

echo "\n";

// Test 6: Test session persistence
echo "6. Testing session persistence...\n";
$sessionId = session_id();
echo "   Session ID: $sessionId\n";
echo "   Session data: " . print_r($_SESSION, true) . "\n";

echo "\n";

// Test 7: Test menu link
echo "7. Testing menu link...\n";
echo "   Menu link: <a href='./settings.php'>Pengaturan</a>\n";
echo "   This should work if user is logged in\n";

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Settings.php access test completed!\n";
echo "\nIf all tests passed:\n";
echo "- settings.php should be accessible from menu\n";
echo "- No more redirect to login when clicking 'Pengaturan'\n";
echo "- Menu navigation should work correctly\n";
echo "\n";
?>
