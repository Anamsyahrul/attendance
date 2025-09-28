<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/../classes/AuthService.php';

// Initialize PDO and config
$pdo = pdo();
$config = $ENV; // Pass the global config array

$authService = new AuthService($pdo, $config);
$authService->requireRole(['teacher']);

$user = $authService->getCurrentUser();
$teacherRoom = $user['room'];

// Handle error message from access control
$errorMessage = '';
if (isset($_GET['error'])) {
    $errorMessage = urldecode($_GET['error']);
}

// Get attendance data for teacher's class
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
$today = new DateTime('today', $tz);
$tomorrow = (clone $today)->modify('+1 day');

// Get students in teacher's class
$students = $pdo->prepare("SELECT * FROM users WHERE room = ? AND role = 'student' ORDER BY name");
$students->execute([$teacherRoom]);
$students = $students->fetchAll(PDO::FETCH_ASSOC);

// Get today's attendance for the class
$attendance = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE uid_hex = ? AND ts >= ? AND ts < ? 
        ORDER BY ts DESC 
        LIMIT 1
    ");
    $stmt->execute([$student['uid_hex'], $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')]);
    $attendance[$student['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Dashboard - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .teacher-card { border-left: 4px solid #28a745; }
        .stats-card { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="teacher.php">
                <i class="bi bi-person-check"></i> Teacher Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person"></i> <?= e($user['name']) ?> (<?= e($teacherRoom) ?>)
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Error Message -->
    <?php if (!empty($errorMessage)): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= e($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-fluid mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill fs-1"></i>
                        <h3 class="mt-2"><?= count($students) ?></h3>
                        <p class="mb-0">Total Siswa</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill fs-1"></i>
                        <h3 class="mt-2"><?= count(array_filter($attendance)) ?></h3>
                        <p class="mb-0">Hadir Hari Ini</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-fill fs-1"></i>
                        <h3 class="mt-2"><?= count(array_filter($attendance, function($a) { return $a && strtotime($a['ts']) > strtotime(date('Y-m-d 07:15:00')); })) ?></h3>
                        <p class="mb-0">Terlambat</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up fs-1"></i>
                        <h3 class="mt-2"><?= round(count(array_filter($attendance)) * 100 / max(1, count($students)), 1) ?>%</h3>
                        <p class="mb-0">Persentase Hadir</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Attendance -->
        <div class="row">
            <div class="col-12">
                <div class="card teacher-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-check"></i> Kehadiran Kelas <?= e($teacherRoom) ?> - <?= $today->format('d/m/Y') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Siswa</th>
                                        <th>UID</th>
                                        <th>Waktu Masuk</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                    <?php $att = $attendance[$student['id']] ?? null; ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= e($student['name']) ?></td>
                                        <td><code><?= e($student['uid_hex']) ?></code></td>
                                        <td>
                                            <?php if ($att): ?>
                                                <?= date('H:i:s', strtotime($att['ts'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($att): ?>
                                                <?php 
                                                $arrivalTime = strtotime($att['ts']);
                                                $lateTime = strtotime(date('Y-m-d 07:15:00'));
                                                if ($arrivalTime > $lateTime): ?>
                                                    <span class="badge bg-warning">Terlambat</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Hadir</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Tidak Hadir</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($att): ?>
                                                <?php 
                                                $arrivalTime = strtotime($att['ts']);
                                                $lateTime = strtotime(date('Y-m-d 07:15:00'));
                                                if ($arrivalTime > $lateTime): ?>
                                                    Terlambat <?= round(($arrivalTime - $lateTime) / 60) ?> menit
                                                <?php else: ?>
                                                    Tepat waktu
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Belum hadir
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
