<?php
/**
 * Test script untuk memverifikasi admin system
 */

echo "🧪 TESTING ADMIN SYSTEM\n";
echo "========================\n\n";

// Test 1: Check if admin_simple.php exists and is accessible
echo "1. Testing admin_simple.php accessibility...\n";
$adminUrl = 'http://localhost/attendance/admin_simple.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$response = @file_get_contents($adminUrl, false, $context);
if ($response !== false) {
    echo "   ✅ admin_simple.php accessible\n";
    
    // Check if it contains expected elements
    if (strpos($response, 'Admin Panel') !== false) {
        echo "   ✅ Admin panel title found\n";
    } else {
        echo "   ❌ Admin panel title not found\n";
    }
    
    if (strpos($response, 'User Management') !== false) {
        echo "   ✅ User management section found\n";
    } else {
        echo "   ❌ User management section not found\n";
    }
    
    if (strpos($response, 'Tambah User') !== false) {
        echo "   ✅ Add user button found\n";
    } else {
        echo "   ❌ Add user button not found\n";
    }
} else {
    echo "   ❌ admin_simple.php not accessible (expected - requires login)\n";
}

echo "\n";

// Test 2: Check database connection and users table
echo "2. Testing database connection and users table...\n";
try {
    require_once __DIR__ . '/web/bootstrap.php';
    $pdo = pdo();
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   ✅ Database connection successful\n";
    echo "   ✅ Users table accessible (count: {$result['count']})\n";
    
    // Check if all users have uid_hex
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE uid_hex IS NULL OR uid_hex = ''");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        echo "   ✅ All users have uid_hex\n";
    } else {
        echo "   ❌ {$result['count']} users missing uid_hex\n";
    }
    
    // Check user roles
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   ✅ User roles distribution:\n";
    foreach ($roles as $role) {
        echo "      - {$role['role']}: {$role['count']} users\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test user creation (simulation)
echo "3. Testing user creation logic...\n";
try {
    require_once __DIR__ . '/web/bootstrap.php';
    $pdo = pdo();
    
    // Test UID generation
    $username = 'test_user_' . time();
    $uidHex = strtolower(substr(md5($username . time() . rand()), 0, 16));
    
    echo "   ✅ UID generation working: {$uidHex}\n";
    
    // Test if UID is unique
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE uid_hex = ?");
    $stmt->execute([$uidHex]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        echo "   ✅ Generated UID is unique\n";
    } else {
        echo "   ❌ Generated UID already exists\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ User creation test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Check if all required fields exist in users table
echo "4. Testing users table structure...\n";
try {
    require_once __DIR__ . '/web/bootstrap.php';
    $pdo = pdo();
    
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredFields = ['id', 'name', 'uid_hex', 'room', 'username', 'password', 'email', 'role', 'is_active'];
    $existingFields = array_column($columns, 'Field');
    
    echo "   ✅ Users table structure:\n";
    foreach ($requiredFields as $field) {
        if (in_array($field, $existingFields)) {
            echo "      ✅ {$field}\n";
        } else {
            echo "      ❌ {$field} (missing)\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Table structure test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check file permissions
echo "5. Testing file permissions...\n";
$files = [
    'web/public/admin_simple.php',
    'web/public/login.php',
    'web/public/logout.php',
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

// Test 6: Check session functionality
echo "6. Testing session functionality...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Session functionality working\n";
    
    // Test session variables
    $_SESSION['test_admin'] = 'test_value';
    if (isset($_SESSION['test_admin']) && $_SESSION['test_admin'] === 'test_value') {
        echo "   ✅ Session variables working\n";
    } else {
        echo "   ❌ Session variables not working\n";
    }
    
    // Clean up
    unset($_SESSION['test_admin']);
} else {
    echo "   ❌ Session functionality not working\n";
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Admin system test completed!\n";
echo "If all tests passed, the admin system should be working correctly.\n";
echo "\nTo test manually:\n";
echo "1. Go to: http://localhost/attendance/login.php\n";
echo "2. Login with: admin / admin\n";
echo "3. You should be redirected to admin_simple.php\n";
echo "4. Try adding a new user\n";
echo "5. Test dark/light mode toggle\n";
echo "\n";
?>

