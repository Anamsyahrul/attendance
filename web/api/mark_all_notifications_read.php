<?php
/**
 * API untuk menandai semua notifikasi sebagai dibaca
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

// Periksa apakah pengguna sudah masuk
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Periksa method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = pdo();
    $config = $ENV;
    
    require_once __DIR__ . '/../classes/NotificationManager.php';
    $notificationManager = new NotificationManager($pdo, $config);
    
    $success = $notificationManager->markAllAsRead($_SESSION['user_id']);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark all notifications as read']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

