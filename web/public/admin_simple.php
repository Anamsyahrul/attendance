<?php
require_once __DIR__ . '/../bootstrap.php';

// Initialize PDO
$pdo = pdo();

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION;
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_backup':
            $backupType = $_POST['backup_type'] ?? 'incremental';
            $message = 'Backup berhasil dibuat (fitur backup akan diimplementasikan)';
            break;
            
        case 'send_notification':
            $type = $_POST['notification_type'] ?? '';
            $recipients = $_POST['recipients'] ?? [];
            $subject = $_POST['subject'] ?? '';
            $message_text = $_POST['message'] ?? '';
            $message = 'Notifikasi berhasil dikirim (fitur notifikasi akan diimplementasikan)';
            break;
            
        case 'create_user':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? 'student';
            $name = $_POST['name'] ?? '';
            $room = $_POST['room'] ?? '';
            
            if ($username && $password && $email && $name) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, password, email, role, name, room, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$username, $hashedPassword, $email, $role, $name, $room])) {
                    $message = 'User berhasil dibuat';
                } else {
                    $message = 'Gagal membuat user';
                }
            } else {
                $message = 'Semua field harus diisi';
            }
            break;
    }
}

// Get data for display
$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll(PDO::FETCH_ASSOC);
$totalUsers = count($users);
$adminUsers = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));
$teacherUsers = count(array_filter($users, function($u) { return $u['role'] === 'teacher'; }));
$parentUsers = count(array_filter($users, function($u) { return $u['role'] === 'parent'; }));
$studentUsers = count(array_filter($users, function($u) { return $u['role'] === 'student'; }));

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .admin-card { border-left: 4px solid #007bff; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .feature-card { transition: transform 0.2s; }
        .feature-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="admin_simple.php">
                <i class="bi bi-shield-check"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person"></i> <?= e($user['name']) ?>
                </span>
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-graph-up"></i> Reports
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill fs-1"></i>
                        <h3 class="mt-2"><?= $totalUsers ?></h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-check fs-1"></i>
                        <h3 class="mt-2"><?= $teacherUsers ?></h3>
                        <p class="mb-0">Teachers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-heart fs-1"></i>
                        <h3 class="mt-2"><?= $parentUsers ?></h3>
                        <p class="mb-0">Parents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person fs-1"></i>
                        <h3 class="mt-2"><?= $studentUsers ?></h3>
                        <p class="mb-0">Students</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card feature-card">
                    <div class="card-header admin-card">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up"></i> Advanced Reporting
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Laporan bulanan, grafik, dan export PDF</p>
                        <a href="reports.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Buka Reports
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card feature-card">
                    <div class="card-header admin-card">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell"></i> Notification System
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Email, SMS, dan push notifications</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#notificationModal">
                            <i class="bi bi-send"></i> Kirim Notifikasi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card feature-card">
                    <div class="card-header admin-card">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-lock"></i> Role-based Access
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Kelola user dengan role Admin, Teacher, Parent, Student</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="bi bi-person-plus"></i> Tambah User
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card feature-card">
                    <div class="card-header admin-card">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-archive"></i> Backup System
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Backup otomatis dan manual database</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#backupModal">
                            <i class="bi bi-download"></i> Kelola Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people"></i> User Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td><?= e($u['username'] ?? 'N/A') ?></td>
                                        <td><?= e($u['name']) ?></td>
                                        <td><?= e($u['email'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'teacher' ? 'primary' : ($u['role'] === 'parent' ? 'success' : 'info')) ?>">
                                                <?= e($u['role'] ?? 'student') ?>
                                            </span>
                                        </td>
                                        <td><?= e($u['room']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editUser(<?= $u['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>" 
                                                        onclick="toggleUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 'false' : 'true' ?>)">
                                                    <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kirim Notifikasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_notification">
                        <div class="mb-3">
                            <label class="form-label">Jenis Notifikasi</label>
                            <select name="notification_type" class="form-select" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Penerima</label>
                            <select name="recipients[]" class="form-select" multiple required>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= e($u['email'] ?? $u['phone'] ?? '') ?>">
                                        <?= e($u['name']) ?> (<?= e($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="parent">Parent</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room/Class</label>
                            <input type="text" name="room" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Backup Modal -->
    <div class="modal fade" id="backupModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kelola Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Buat Backup</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_backup">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Backup</label>
                                    <select name="backup_type" class="form-select">
                                        <option value="incremental">Incremental (7 hari terakhir)</option>
                                        <option value="full">Full Database</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Buat Backup</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <h6>Restore Backup</h6>
                            <p class="text-muted">Fitur restore akan diimplementasikan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            alert('Edit user ' + userId + ' - Coming soon!');
        }
        
        function toggleUser(userId, newStatus) {
            if (confirm('Yakin ingin mengubah status user ini?')) {
                alert('Toggle user ' + userId + ' to ' + newStatus + ' - Coming soon!');
            }
        }
    </script>
</body>
</html>
