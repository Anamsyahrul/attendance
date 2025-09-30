<?php

class NotificationService {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $isHTML = true) {
        $headers = [
            'From: ' . $this->config['SCHOOL_EMAIL'],
            'Reply-To: ' . $this->config['SCHOOL_EMAIL'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if ($isHTML) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
        }
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Send SMS notification (requires SMS gateway)
     */
    public function sendSMS($phone, $message) {
        // This would integrate with SMS gateway like Twilio, Nexmo, etc.
        // For now, we'll log it
        error_log("SMS to {$phone}: {$message}");
        return true;
    }
    
    /**
     * Send push notification
     */
    public function sendPushNotification($userId, $title, $message, $data = []) {
        // This would integrate with push notification service
        // For now, we'll store it in database
        $sql = "INSERT INTO notifications (user_id, title, message, data, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId, $title, $message, json_encode($data)]);
    }
    
    /**
     * Notify late arrival
     */
    public function notifyLateArrival($userId, $arrivalTime, $expectedTime) {
        $user = $this->getUserById($userId);
        if (!$user) return false;
        
        $subject = "Keterlambatan - " . $user['name'];
        $message = $this->getLateArrivalTemplate($user, $arrivalTime, $expectedTime);
        
        // Send to parent if email available
        if (!empty($user['parent_email'])) {
            $this->sendEmail($user['parent_email'], $subject, $message);
        }
        
        // Send to teacher
        $this->notifyTeacher($user['room'], $subject, $message);
        
        return true;
    }
    
    /**
     * Notify absence
     */
    public function notifyAbsence($userId, $date) {
        $user = $this->getUserById($userId);
        if (!$user) return false;
        
        $subject = "Ketidakhadiran - " . $user['name'];
        $message = $this->getAbsenceTemplate($user, $date);
        
        // Send to parent if email available
        if (!empty($user['parent_email'])) {
            $this->sendEmail($user['parent_email'], $subject, $message);
        }
        
        // Send to teacher
        $this->notifyTeacher($user['room'], $subject, $message);
        
        return true;
    }
    
    /**
     * Notify teacher about class attendance
     */
    public function notifyTeacher($room, $subject, $message) {
        $teachers = $this->getTeachersByRoom($room);
        foreach ($teachers as $teacher) {
            if (!empty($teacher['email'])) {
                $this->sendEmail($teacher['email'], $subject, $message);
            }
        }
    }
    
    /**
     * Send daily attendance summary
     */
    public function sendDailySummary($date) {
        $summary = $this->getDailyAttendanceSummary($date);
        
        $subject = "Ringkasan Kehadiran - " . date('d/m/Y', strtotime($date));
        $message = $this->getDailySummaryTemplate($summary);
        
        // Send to admin
        $this->sendEmail($this->config['SCHOOL_EMAIL'], $subject, $message);
        
        return true;
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get teachers by room
     */
    private function getTeachersByRoom($room) {
        $sql = "SELECT * FROM users WHERE role = 'teacher' AND room = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$room]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily attendance summary
     */
    private function getDailyAttendanceSummary($date) {
        $sql = "SELECT 
                    COUNT(DISTINCT u.id) as total_students,
                    COUNT(DISTINCT a.uid_hex) as present_students,
                    COUNT(DISTINCT CASE WHEN a.ts > CONCAT(?, ' ', ?) THEN a.uid_hex END) as late_students
                FROM users u
                LEFT JOIN attendance a ON u.uid_hex = a.uid_hex 
                    AND DATE(a.ts) = ?
                WHERE u.role = 'student'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$date, $this->config['SCHOOL_START'], $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Late arrival email template
     */
    private function getLateArrivalTemplate($user, $arrivalTime, $expectedTime) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .alert { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Notifikasi Keterlambatan</h2>
                <p>SMA Bustanul Arifin</p>
            </div>
            <div class='content'>
                <div class='alert'>
                    <h3>Keterlambatan Terdeteksi</h3>
                    <p><strong>Nama Siswa:</strong> {$user['name']}</p>
                    <p><strong>Kelas:</strong> {$user['room']}</p>
                    <p><strong>Waktu Kedatangan:</strong> " . date('H:i', strtotime($arrivalTime)) . "</p>
                    <p><strong>Waktu Diharapkan:</strong> " . date('H:i', strtotime($expectedTime)) . "</p>
                    <p><strong>Keterlambatan:</strong> " . $this->calculateDelay($arrivalTime, $expectedTime) . " menit</p>
                </div>
                <p>Silakan hubungi sekolah jika ada pertanyaan.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Absence email template
     */
    private function getAbsenceTemplate($user, $date) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .alert { background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Notifikasi Ketidakhadiran</h2>
                <p>SMA Bustanul Arifin</p>
            </div>
            <div class='content'>
                <div class='alert'>
                    <h3>Ketidakhadiran Terdeteksi</h3>
                    <p><strong>Nama Siswa:</strong> {$user['name']}</p>
                    <p><strong>Kelas:</strong> {$user['room']}</p>
                    <p><strong>Tanggal:</strong> " . date('d/m/Y', strtotime($date)) . "</p>
                </div>
                <p>Silakan hubungi sekolah jika ada pertanyaan.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Daily summary email template
     */
    private function getDailySummaryTemplate($summary) {
        $attendanceRate = $summary['total_students'] > 0 
            ? round(($summary['present_students'] / $summary['total_students']) * 100, 2) 
            : 0;
            
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                .stat-box { text-align: center; padding: 20px; background-color: #e9ecef; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Ringkasan Kehadiran Harian</h2>
                <p>SMA Bustanul Arifin - " . date('d/m/Y') . "</p>
            </div>
            <div class='content'>
                <div class='stats'>
                    <div class='stat-box'>
                        <h3>{$summary['total_students']}</h3>
                        <p>Total Siswa</p>
                    </div>
                    <div class='stat-box'>
                        <h3>{$summary['present_students']}</h3>
                        <p>Siswa Hadir</p>
                    </div>
                    <div class='stat-box'>
                        <h3>{$summary['late_students']}</h3>
                        <p>Siswa Terlambat</p>
                    </div>
                    <div class='stat-box'>
                        <h3>{$attendanceRate}%</h3>
                        <p>Tingkat Kehadiran</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Calculate delay in minutes
     */
    private function calculateDelay($arrivalTime, $expectedTime) {
        $arrival = new DateTime($arrivalTime);
        $expected = new DateTime($expectedTime);
        $diff = $arrival->diff($expected);
        return $diff->h * 60 + $diff->i;
    }
}
?>

