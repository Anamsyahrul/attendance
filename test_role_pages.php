<?php
/**
 * Test script untuk memverifikasi role-based pages
 */

echo "🧪 TESTING ROLE-BASED PAGES\n";
echo "============================\n\n";

// Test 1: Check if all role pages exist and are accessible
echo "1. Testing role pages accessibility...\n";
$pages = [
    'teacher.php' => 'Teacher Dashboard',
    'parent.php' => 'Parent Dashboard', 
    'student.php' => 'Student Dashboard',
    'admin_simple.php' => 'Admin Dashboard'
];

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

foreach ($pages as $page => $title) {
    $url = "http://localhost/attendance/{$page}";
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        echo "   ✅ {$page} accessible\n";
        
        if (strpos($response, $title) !== false) {
            echo "      ✅ {$title} title found\n";
        } else {
            echo "      ❌ {$title} title not found\n";
        }
    } else {
        echo "   ❌ {$page} not accessible (expected - requires login)\n";
    }
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
    
    // Check user roles distribution
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

// Test 3: Test AuthService functionality
echo "3. Testing AuthService functionality...\n";
try {
    require_once __DIR__ . '/web/bootstrap.php';
    require_once __DIR__ . '/web/classes/AuthService.php';
    
    $pdo = pdo();
    $config = $ENV;
    $authService = new AuthService($pdo, $config);
    
    echo "   ✅ AuthService initialized successfully\n";
    
    // Test login functionality
    $testUser = $authService->login('admin', 'admin', 'admin');
    if ($testUser) {
        echo "   ✅ Admin login working\n";
    } else {
        echo "   ❌ Admin login failed\n";
    }
    
    // Test role permissions
    $permissions = $authService->hasPermission('view_dashboard');
    if ($permissions) {
        echo "   ✅ Permission system working\n";
    } else {
        echo "   ❌ Permission system not working\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ AuthService test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test session functionality
echo "4. Testing session functionality...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Session functionality working\n";
    
    // Test session variables
    $_SESSION['test_role'] = 'test_value';
    if (isset($_SESSION['test_role']) && $_SESSION['test_role'] === 'test_value') {
        echo "   ✅ Session variables working\n";
    } else {
        echo "   ❌ Session variables not working\n";
    }
    
    // Clean up
    unset($_SESSION['test_role']);
} else {
    echo "   ❌ Session functionality not working\n";
}

echo "\n";

// Test 5: Check file permissions
echo "5. Testing file permissions...\n";
$files = [
    'web/public/teacher.php',
    'web/public/parent.php',
    'web/public/student.php',
    'web/public/admin_simple.php',
    'web/classes/AuthService.php',
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

// Test 6: Test PDO initialization in role pages
echo "6. Testing PDO initialization in role pages...\n";
$rolePages = ['teacher.php', 'parent.php', 'student.php'];

foreach ($rolePages as $page) {
    $filePath = "web/public/{$page}";
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        if (strpos($content, '$pdo = pdo();') !== false) {
            echo "   ✅ {$page} has PDO initialization\n";
        } else {
            echo "   ❌ {$page} missing PDO initialization\n";
        }
        
        if (strpos($content, '$config = $ENV;') !== false) {
            echo "   ✅ {$page} has config initialization\n";
        } else {
            echo "   ❌ {$page} missing config initialization\n";
        }
    }
}

echo "\n";

// Test 7: Test session handling in AuthService
echo "7. Testing session handling in AuthService...\n";
$authServiceFile = 'web/classes/AuthService.php';
if (file_exists($authServiceFile)) {
    $content = file_get_contents($authServiceFile);
    
    if (strpos($content, 'session_status() === PHP_SESSION_NONE') !== false) {
        echo "   ✅ AuthService has proper session handling\n";
    } else {
        echo "   ❌ AuthService missing proper session handling\n";
    }
    
    if (strpos($content, 'session_start();') !== false) {
        echo "   ✅ AuthService has session_start calls\n";
    } else {
        echo "   ❌ AuthService missing session_start calls\n";
    }
} else {
    echo "   ❌ AuthService file not found\n";
}

echo "\n";

// Summary
echo "🎯 SUMMARY\n";
echo "==========\n";
echo "Role-based pages test completed!\n";
echo "If all tests passed, the role-based system should be working correctly.\n";
echo "\nTo test manually:\n";
echo "1. Go to: http://localhost/attendance/login.php\n";
echo "2. Try login with different roles:\n";
echo "   - Admin: admin / admin\n";
echo "   - Teacher: teacher1 / password\n";
echo "   - Parent: parent1 / password\n";
echo "   - Student: student1 / password\n";
echo "3. Test dark/light mode toggle\n";
echo "4. Test role-specific features\n";
echo "\n";
?>
