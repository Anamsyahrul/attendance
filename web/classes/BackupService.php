<?php

class BackupService {
    private $pdo;
    private $config;
    private $backupDir;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->backupDir = __DIR__ . '/../backups/';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create full database backup
     */
    public function createFullBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "full_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        // Get database credentials
        $host = $this->config['DB_HOST'];
        $username = $this->config['DB_USER'];
        $password = $this->config['DB_PASS'];
        $database = $this->config['DB_NAME'];
        
        // Create mysqldump command
        $command = "mysqldump -h {$host} -u {$username} -p{$password} {$database} > {$filepath}";
        
        // Execute backup
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            // Compress backup
            $this->compressBackup($filepath);
            
            // Log backup
            $this->logBackup('full', $filename, filesize($filepath));
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Backup failed: ' . implode("\n", $output)
        ];
    }
    
    /**
     * Create incremental backup (only recent data)
     */
    public function createIncrementalBackup($days = 7) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "incremental_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get recent attendance data
        $sql = "SELECT * FROM attendance WHERE ts >= ? ORDER BY ts";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$since]);
        $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create SQL dump
        $sqlContent = "-- Incremental Backup - Last {$days} days\n";
        $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Insert attendance data
        if (!empty($attendanceData)) {
            $sqlContent .= "INSERT INTO attendance (id, user_id, device_id, ts, uid_hex, raw_json) VALUES\n";
            $values = [];
            foreach ($attendanceData as $row) {
                $values[] = "(" . 
                    $row['id'] . ", " .
                    ($row['user_id'] ? $row['user_id'] : 'NULL') . ", " .
                    "'" . addslashes($row['device_id']) . "', " .
                    "'" . $row['ts'] . "', " .
                    "'" . addslashes($row['uid_hex']) . "', " .
                    ($row['raw_json'] ? "'" . addslashes($row['raw_json']) . "'" : 'NULL') .
                ")";
            }
            $sqlContent .= implode(",\n", $values) . ";\n\n";
        }
        
        // Write to file
        file_put_contents($filepath, $sqlContent);
        
        // Compress backup
        $this->compressBackup($filepath);
        
        // Log backup
        $this->logBackup('incremental', $filename, filesize($filepath));
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'records' => count($attendanceData)
        ];
    }
    
    /**
     * Create settings backup
     */
    public function createSettingsBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "settings_backup_{$timestamp}.json";
        $filepath = $this->backupDir . $filename;
        
        // Get all settings
        $sql = "SELECT * FROM settings ORDER BY setting_key";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create settings array
        $settingsData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'settings' => $settings
        ];
        
        // Write to file
        file_put_contents($filepath, json_encode($settingsData, JSON_PRETTY_PRINT));
        
        // Log backup
        $this->logBackup('settings', $filename, filesize($filepath));
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Restore from backup
     */
    public function restoreFromBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }
        
        // Check if it's a compressed file
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'gz') {
            $this->decompressBackup($filepath);
            $filepath = str_replace('.gz', '', $filepath);
        }
        
        // Get database credentials
        $host = $this->config['DB_HOST'];
        $username = $this->config['DB_USER'];
        $password = $this->config['DB_PASS'];
        $database = $this->config['DB_NAME'];
        
        // Execute restore
        $command = "mysql -h {$host} -u {$username} -p{$password} {$database} < {$filepath}";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            // Log restore
            $this->logRestore($filename);
            
            return [
                'success' => true,
                'message' => 'Backup restored successfully'
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Restore failed: ' . implode("\n", $output)
        ];
    }
    
    /**
     * List available backups
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . '*.sql*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'filepath' => $file,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => $this->getBackupType($filename)
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    /**
     * Clean old backups
     */
    public function cleanOldBackups($keepDays = 30) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$keepDays} days"));
        $deleted = 0;
        
        $backups = $this->listBackups();
        foreach ($backups as $backup) {
            if (strtotime($backup['created']) < strtotime($cutoffDate)) {
                if (unlink($backup['filepath'])) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Compress backup file
     */
    private function compressBackup($filepath) {
        $compressedFile = $filepath . '.gz';
        
        if (function_exists('gzopen')) {
            $fp_in = fopen($filepath, 'rb');
            $fp_out = gzopen($compressedFile, 'wb9');
            
            while (!feof($fp_in)) {
                gzwrite($fp_out, fread($fp_in, 1024 * 512));
            }
            
            fclose($fp_in);
            gzclose($fp_out);
            
            // Remove original file
            unlink($filepath);
        }
    }
    
    /**
     * Decompress backup file
     */
    private function decompressBackup($filepath) {
        $decompressedFile = str_replace('.gz', '', $filepath);
        
        if (function_exists('gzopen')) {
            $fp_in = gzopen($filepath, 'rb');
            $fp_out = fopen($decompressedFile, 'wb');
            
            while (!gzeof($fp_in)) {
                fwrite($fp_out, gzread($fp_in, 1024 * 512));
            }
            
            gzclose($fp_in);
            fclose($fp_out);
        }
    }
    
    /**
     * Get backup type from filename
     */
    private function getBackupType($filename) {
        if (strpos($filename, 'full_backup_') === 0) return 'full';
        if (strpos($filename, 'incremental_backup_') === 0) return 'incremental';
        if (strpos($filename, 'settings_backup_') === 0) return 'settings';
        return 'unknown';
    }
    
    /**
     * Log backup operation
     */
    private function logBackup($type, $filename, $size) {
        $sql = "INSERT INTO backup_logs (type, filename, size, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type, $filename, $size]);
    }
    
    /**
     * Log restore operation
     */
    private function logRestore($filename) {
        $sql = "INSERT INTO restore_logs (filename, restored_at) VALUES (?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$filename]);
    }
    
    /**
     * Schedule automatic backup
     */
    public function scheduleBackup($type = 'incremental', $time = '02:00') {
        // This would integrate with cron or task scheduler
        // For now, we'll just log the schedule
        error_log("Backup scheduled: {$type} at {$time}");
        return true;
    }
}
?>

