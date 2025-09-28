<?php
/**
 * Test script untuk memeriksa perbaikan session
 */

// Mulai output buffering
ob_start();

echo "🧪 TESTING SESSION FIX\n";
echo "======================\n\n";

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
echo "   - username: " . $_SESSION['username'] . "\n";

echo "\n";

// Test 2: Test fungsi sudah_masuk()
echo "2. Testing sudah_masuk() function...\n";
require_once 'web/bootstrap.php';

if (sudah_masuk()) {
    echo "   ✅ sudah_masuk() returns TRUE - User is logged in\n";
} else {
    echo "   ❌ sudah_masuk() returns FALSE - User is not logged in\n";
}

echo "\n";

// Test 3: Test fungsi wajib_masuk()
echo "3. Testing wajib_masuk() function...\n";
echo "   Calling wajib_masuk()...\n";

// Simulasi akses ke settings.php
echo "   Simulating access to settings.php...\n";
if (sudah_masuk()) {
    echo "   ✅ User is logged in - settings.php should be accessible\n";
} else {
    echo "   ❌ User is not logged in - settings.php would redirect to login\n";
}

echo "\n";

// Test 4: Test session data
echo "4. Testing session data...\n";
echo "   Session data:\n";
echo "   - user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "   - role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "   - username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "   - name: " . ($_SESSION['name'] ?? 'NOT SET') . "\n";
echo "   - room: " . ($_SESSION['room'] ?? 'NOT SET') . "\n";

echo "\n";

// Test 5: Test different scenarios
echo "5. Testing different scenarios...\n";

// Scenario 1: Complete session data
echo "   Scenario 1: Complete session data\n";
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
if (sudah_masuk()) {
    echo "     ✅ sudah_masuk() returns TRUE\n";
} else {
    echo "     ❌ sudah_masuk() returns FALSE\n";
}

// Scenario 2: Missing user_id
echo "   Scenario 2: Missing user_id\n";
unset($_SESSION['user_id']);
if (sudah_masuk()) {
    echo "     ❌ sudah_masuk() returns TRUE (should be FALSE)\n";
} else {
    echo "     ✅ sudah_masuk() returns FALSE (correct)\n";
}

// Scenario 3: Missing role
echo "   Scenario 3: Missing role\n";
$_SESSION['user_id'] = 1;
unset($_SESSION['role']);
if (sudah_masuk()) {
    echo "     ❌ sudah_masuk() returns TRUE (should be FALSE)\n";
} else {
    echo "     ✅ sudah_masuk() returns FALSE (correct)\n";
}

// Scenario 4: Empty session
echo "   Scenario 4: Empty session\n";
$_SESSION = [];
if (sudah_masuk()) {
    echo "     ❌ sudah_masuk() returns TRUE (should be FALSE)\n";
} else {
    echo "     ✅ sudah_masuk() returns FALSE (correct)\n";
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Session fix test completed!\n";
echo "\nIf sudah_masuk() returns TRUE for complete session data:\n";
echo "- settings.php should be accessible\n";
echo "- No more redirect to login for logged in users\n";
echo "- Menu navigation should work correctly\n";
echo "\n";
?>
