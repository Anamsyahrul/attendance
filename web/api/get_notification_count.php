<?php
/**
 * API untuk mendapatkan jumlah notifikasi yang belum dibaca
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

// Periksa apakah pengguna sudah masuk
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = pdo();
    $config = $ENV;
    
    require_once __DIR__ . '/../classes/NotificationManager.php';
    $notificationManager = new NotificationManager($pdo, $config);
    
    $notifications = $notificationManager->getNotifications($_SESSION['user_id'], 5, true);
    $count = count($notifications);
    
    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

