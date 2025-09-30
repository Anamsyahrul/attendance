<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();

// Hanya admin yang bisa akses
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pdo = pdo();
$config = $ENV;

require_once __DIR__ . '/../classes/BackupManager.php';
$backupManager = new BackupManager($pdo, $config);

$message = '';
$error = '';

// Tangani aksi backup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_full_backup':
            $result = $backupManager->createFullBackup();
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'create_config_backup':
            $result = $backupManager->createConfigBackup();
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'restore_database':
            $backupFile = $_POST['backup_file'] ?? '';
            if ($backupFile) {
                $result = $backupManager->restoreDatabase($backupFile);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = 'Pilih file backup yang akan di-restore';
            }
            break;
            
        case 'cleanup_old_backups':
            $deletedCount = $backupManager->cleanupOldBackups();
            $message = "Berhasil menghapus {$deletedCount} file backup lama";
            break;
    }
}

// Ambil daftar backup
$backups = $backupManager->listBackups();
$backupStatus = $backupManager->getBackupStatus();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Backup - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="backup.php">
                <i class="bi bi-archive"></i> Manajemen Backup
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_simple.php">
                    <i class="bi bi-arrow-left"></i> Kembali ke Admin
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Backup Actions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear me-2"></i> Aksi Backup</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <input type="hidden" name="action" value="create_full_backup">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-database"></i> Backup Database Lengkap
                            </button>
                        </form>

                        <form method="post" class="mb-3">
                            <input type="hidden" name="action" value="create_config_backup">
                            <button type="submit" class="btn btn-info w-100 mb-2">
                                <i class="bi bi-file-zip"></i> Backup Konfigurasi
                            </button>
                        </form>

                        <form method="post" class="mb-3">
                            <input type="hidden" name="action" value="cleanup_old_backups">
                            <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Yakin ingin menghapus backup lama?')">
                                <i class="bi bi-trash"></i> Hapus Backup Lama
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Backup Status -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Status Backup</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backupStatus)): ?>
                        <p class="text-muted">Belum ada data backup</p>
                        <?php else: ?>
                        <?php foreach ($backupStatus as $status): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= e(ucfirst($status['backup_type'])) ?></span>
                            <div>
                                <span class="badge bg-success"><?= $status['successful'] ?></span>
                                <span class="badge bg-danger"><?= $status['failed'] ?></span>
                            </div>
                        </div>
                        <small class="text-muted">
                            Terakhir: <?= date('d/m/Y H:i', strtotime($status['last_backup'])) ?>
                        </small>
                        <hr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Backup List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list me-2"></i> Daftar Backup</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-archive display-1"></i>
                            <p class="mt-3">Belum ada file backup</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th>Ukuran</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Tipe</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            <?= e($backup['filename']) ?>
                                        </td>
                                        <td><?= number_format($backup['size'] / 1024, 2) ?> KB</td>
                                        <td><?= e($backup['created']) ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?= e($backup['type']) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="downloadBackup('<?= e($backup['filename']) ?>')">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="restoreBackup('<?= e($backup['filename']) ?>')">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteBackup('<?= e($backup['filename']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Database</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin restore database dari file backup ini?</p>
                    <p class="text-danger"><strong>Peringatan:</strong> Aksi ini akan mengganti semua data database dengan data dari backup!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="restore_database">
                        <input type="hidden" name="backup_file" id="restoreBackupFile">
                        <button type="submit" class="btn btn-danger">Ya, Restore</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadBackup(filename) {
            window.open('api/download_backup.php?file=' + encodeURIComponent(filename), '_blank');
        }

        function restoreBackup(filename) {
            document.getElementById('restoreBackupFile').value = filename;
            new bootstrap.Modal(document.getElementById('restoreModal')).show();
        }

        function deleteBackup(filename) {
            if (confirm('Yakin ingin menghapus file backup ini?')) {
                fetch('api/delete_backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ filename: filename })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan');
                });
            }
        }
    </script>
</body>
</html>

