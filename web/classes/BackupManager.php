<?php
/**
 * Backup Manager - Sistem backup dan recovery lengkap
 */

class BackupManager {
    private $pdo;
    private $config;
    private $backupDir;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->backupDir = __DIR__ . '/../backups/';
        
        // Buat direktori backup jika belum ada
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Backup database lengkap
     */
    public function createFullBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "full_backup_{$timestamp}.sql";
            $filepath = $this->backupDir . $filename;
            
            // Dapatkan konfigurasi database
            $host = $this->config['DB_HOST'] ?? 'localhost';
            $user = $this->config['DB_USER'] ?? 'root';
            $pass = $this->config['DB_PASS'] ?? '';
            $db = $this->config['DB_NAME'] ?? 'attendance';
            
            // Buat command mysqldump
            $command = "mysqldump -h {$host} -u {$user}";
            if (!empty($pass)) {
                $command .= " -p{$pass}";
            }
            $command .= " --single-transaction --routines --triggers {$db} > {$filepath}";
            
            // Jalankan backup
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($filepath)) {
                $fileSize = filesize($filepath);
                
                // Log backup
                $this->logBackup('full', $filepath, $fileSize, 'success');
                
                // Hapus backup lama jika melebihi retensi
                $this->cleanupOldBackups();
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $fileSize,
                    'message' => 'Backup berhasil dibuat'
                ];
            } else {
                throw new Exception('Gagal membuat backup database');
            }
        } catch (Exception $e) {
            $this->logBackup('full', '', 0, 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup konfigurasi
     */
    public function createConfigBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "config_backup_{$timestamp}.zip";
            $filepath = $this->backupDir . $filename;
            
            // File konfigurasi yang akan di-backup
            $configFiles = [
                __DIR__ . '/../config.php',
                __DIR__ . '/../bootstrap.php',
                __DIR__ . '/../.htaccess'
            ];
            
            // Buat ZIP file
            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE) === TRUE) {
                foreach ($configFiles as $file) {
                    if (file_exists($file)) {
                        $zip->addFile($file, basename($file));
                    }
                }
                $zip->close();
                
                $fileSize = filesize($filepath);
                $this->logBackup('config', $filepath, $fileSize, 'success');
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $fileSize,
                    'message' => 'Backup konfigurasi berhasil dibuat'
                ];
            } else {
                throw new Exception('Gagal membuat file ZIP');
            }
        } catch (Exception $e) {
            $this->logBackup('config', '', 0, 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore database dari backup
     */
    public function restoreDatabase($backupFile) {
        try {
            $filepath = $this->backupDir . $backupFile;
            
            if (!file_exists($filepath)) {
                throw new Exception('File backup tidak ditemukan');
            }
            
            // Dapatkan konfigurasi database
            $host = $this->config['DB_HOST'] ?? 'localhost';
            $user = $this->config['DB_USER'] ?? 'root';
            $pass = $this->config['DB_PASS'] ?? '';
            $db = $this->config['DB_NAME'] ?? 'attendance';
            
            // Buat command mysql
            $command = "mysql -h {$host} -u {$user}";
            if (!empty($pass)) {
                $command .= " -p{$pass}";
            }
            $command .= " {$db} < {$filepath}";
            
            // Jalankan restore
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->logRestore($backupFile, 'success');
                return [
                    'success' => true,
                    'message' => 'Database berhasil di-restore'
                ];
            } else {
                throw new Exception('Gagal restore database');
            }
        } catch (Exception $e) {
            $this->logRestore($backupFile, 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Daftar file backup
     */
    public function listBackups() {
        try {
            $backups = [];
            $files = glob($this->backupDir . '*.sql');
            
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'filepath' => $file,
                    'size' => filesize($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => 'database'
                ];
            }
            
            // Urutkan berdasarkan tanggal terbaru
            usort($backups, function($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });
            
            return $backups;
        } catch (Exception $e) {
            error_log("Error listing backups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Hapus backup lama
     */
    public function cleanupOldBackups() {
        try {
            $retentionDays = $this->config['BACKUP_RETENTION_DAYS'] ?? 30;
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            $files = glob($this->backupDir . '*.sql');
            $deletedCount = 0;
            
            foreach ($files as $file) {
                if (filemtime($file) < strtotime($cutoffDate)) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
            
            return $deletedCount;
        } catch (Exception $e) {
            error_log("Error cleaning up old backups: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Log backup
     */
    private function logBackup($type, $filepath, $fileSize, $status, $errorMessage = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO backup_logs (backup_type, file_path, file_size, status, error_message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$type, $filepath, $fileSize, $status, $errorMessage]);
        } catch (Exception $e) {
            error_log("Error logging backup: " . $e->getMessage());
        }
    }
    
    /**
     * Log restore
     */
    private function logRestore($backupFile, $status, $errorMessage = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO restore_logs (backup_file, status, error_message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$backupFile, $status, $errorMessage]);
        } catch (Exception $e) {
            error_log("Error logging restore: " . $e->getMessage());
        }
    }
    
    /**
     * Cek status backup
     */
    public function getBackupStatus() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    backup_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MAX(created_at) as last_backup
                FROM backup_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY backup_type
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting backup status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Backup otomatis
     */
    public function autoBackup() {
        try {
            // Cek apakah sudah ada backup hari ini
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM backup_logs 
                WHERE backup_type = 'full' 
                AND DATE(created_at) = CURDATE() 
                AND status = 'success'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Buat backup hari ini
                return $this->createFullBackup();
            } else {
                return [
                    'success' => true,
                    'message' => 'Backup hari ini sudah ada'
                ];
            }
        } catch (Exception $e) {
            error_log("Error in auto backup: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}

