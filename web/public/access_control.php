<?php
/**
 * Access Control - Redirect user to appropriate page based on role
 */

require_once __DIR__ . '/../bootstrap.php';

// Periksa apakah pengguna sudah masuk
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['role'];
$currentFile = basename($_SERVER['PHP_SELF']);

// Mapping file ke role yang diizinkan
$roleFileMap = [
    'index.php' => ['admin', 'teacher', 'parent', 'student'],
    'admin_simple.php' => ['admin'],
    'teacher.php' => ['teacher'],
    'parent.php' => ['parent'],
    'student.php' => ['student'],
    'settings.php' => ['admin'],
    'reports.php' => ['admin'],
    'users.php' => ['admin'],
    'rooms.php' => ['admin'],
    'register.php' => ['admin']
];

// Periksa apakah user memiliki akses ke file saat ini
if (isset($roleFileMap[$currentFile])) {
    if (!in_array($userRole, $roleFileMap[$currentFile])) {
        // User tidak memiliki akses ke file ini, redirect ke file yang sesuai
        $redirectFile = '';
        
        switch ($userRole) {
            case 'admin':
                $redirectFile = 'admin_simple.php';
                break;
            case 'teacher':
                $redirectFile = 'teacher.php';
                break;
            case 'parent':
                $redirectFile = 'parent.php';
                break;
            case 'student':
                $redirectFile = 'student.php';
                break;
            default:
                $redirectFile = 'index.php';
        }
        
        // Redirect dengan pesan error
        $errorMessage = urlencode("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
        header("Location: $redirectFile?error=$errorMessage");
        exit;
    }
}

// Jika sampai di sini, user memiliki akses yang valid
?>
