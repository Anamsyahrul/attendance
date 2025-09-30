<?php
/**
 * Notification Manager - Sistem notifikasi lengkap
 */

class NotificationManager {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    /**
     * Kirim notifikasi ke user
     */
    public function sendNotification($userId, $title, $message, $type = 'info') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $title, $message, $type]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kirim notifikasi ke semua user dengan role tertentu
     */
    public function sendNotificationToRole($role, $title, $message, $type = 'info') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                SELECT id, ?, ?, ?, NOW() FROM users WHERE role = ? AND is_active = 1
            ");
            $stmt->execute([$title, $message, $type, $role]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error sending notification to role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kirim notifikasi ke semua admin
     */
    public function sendNotificationToAdmins($title, $message, $type = 'info') {
        return $this->sendNotificationToRole('admin', $title, $message, $type);
    }
    
    /**
     * Kirim notifikasi ke semua guru
     */
    public function sendNotificationToTeachers($title, $message, $type = 'info') {
        return $this->sendNotificationToRole('teacher', $title, $message, $type);
    }
    
    /**
     * Kirim notifikasi ke semua orang tua
     */
    public function sendNotificationToParents($title, $message, $type = 'info') {
        return $this->sendNotificationToRole('parent', $title, $message, $type);
    }
    
    /**
     * Kirim notifikasi ke semua siswa
     */
    public function sendNotificationToStudents($title, $message, $type = 'info') {
        return $this->sendNotificationToRole('student', $title, $message, $type);
    }
    
    /**
     * Ambil notifikasi untuk user
     */
    public function getNotifications($userId, $limit = 10, $unreadOnly = false) {
        try {
            $sql = "
                SELECT * FROM notifications 
                WHERE user_id = ? 
            ";
            
            if ($unreadOnly) {
                $sql .= " AND is_read = 0 ";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Tandai notifikasi sebagai sudah dibaca
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tandai semua notifikasi sebagai sudah dibaca
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hapus notifikasi lama
     */
    public function cleanupOldNotifications($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning up old notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Kirim email notifikasi
     */
    public function sendEmailNotification($toEmail, $toName, $subject, $body) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, body, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$toEmail, $toName, $subject, $body]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error queuing email notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kirim SMS notifikasi
     */
    public function sendSMSNotification($toPhone, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sms_queue (to_phone, message, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$toPhone, $message]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error queuing SMS notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifikasi kehadiran terlambat
     */
    public function notifyLateAttendance($userId, $userName, $lateTime) {
        $title = "Keterlambatan Kehadiran";
        $message = "Siswa {$userName} terlambat {$lateTime} menit pada " . date('d/m/Y H:i');
        
        // Notifikasi ke user
        $this->sendNotification($userId, $title, $message, 'warning');
        
        // Notifikasi ke admin
        $this->sendNotificationToAdmins($title, $message, 'warning');
        
        // Notifikasi ke guru jika ada
        $this->sendNotificationToTeachers($title, $message, 'info');
    }
    
    /**
     * Notifikasi kehadiran tidak hadir
     */
    public function notifyAbsentAttendance($userId, $userName, $date) {
        $title = "Ketidakhadiran";
        $message = "Siswa {$userName} tidak hadir pada " . date('d/m/Y', strtotime($date));
        
        // Notifikasi ke user
        $this->sendNotification($userId, $title, $message, 'error');
        
        // Notifikasi ke admin
        $this->sendNotificationToAdmins($title, $message, 'error');
        
        // Notifikasi ke guru
        $this->sendNotificationToTeachers($title, $message, 'warning');
        
        // Notifikasi ke orang tua
        $this->sendNotificationToParents($title, $message, 'error');
    }
    
    /**
     * Notifikasi sistem
     */
    public function notifySystemEvent($title, $message, $type = 'info') {
        // Notifikasi ke semua admin
        $this->sendNotificationToAdmins($title, $message, $type);
        
        // Log ke audit
        $this->logAudit(0, 'system_notification', $message);
    }
    
    /**
     * Log audit
     */
    private function logAudit($userId, $action, $details) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }
}

