<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/AuthService.php';

// Initialize PDO and config
$pdo = pdo();
$config = $ENV; // Pass the global config array

$authService = new AuthService($pdo, $config);
$authService->requireRole(['parent']);

$user = $authService->getCurrentUser();

// Get child's data (assuming parent has access to one child for now)
$child = $pdo->prepare("SELECT * FROM users WHERE parent_email = ? AND role = 'student' LIMIT 1");
$child->execute([$user['email']]);
$child = $child->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    die('Tidak ada data anak yang ditemukan');
}

// Get attendance data for the child
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
$today = new DateTime('today', $tz);
$tomorrow = (clone $today)->modify('+1 day');

// Get today's attendance
$todayAttendance = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE uid_hex = ? AND ts >= ? AND ts < ? 
    ORDER BY ts DESC 
    LIMIT 1
");
$todayAttendance->execute([$child['uid_hex'], $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')]);
$todayAttendance = $todayAttendance->fetch(PDO::FETCH_ASSOC);

// Get weekly attendance
$weekStart = (clone $today)->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+7 days');

$weeklyAttendance = $pdo->prepare("
    SELECT DATE(ts) as date, MIN(ts) as first_scan, MAX(ts) as last_scan, COUNT(*) as total_scans
    FROM attendance 
    WHERE uid_hex = ? AND ts >= ? AND ts < ? 
    GROUP BY DATE(ts)
    ORDER BY date DESC
");
$weeklyAttendance->execute([$child['uid_hex'], $weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s')]);
$weeklyAttendance = $weeklyAttendance->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Parent Dashboard - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .parent-card { border-left: 4px solid #17a2b8; }
        .stats-card { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); color: white; }
        .child-info { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="parent.php">
                <i class="bi bi-person-heart"></i> Parent Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person"></i> <?= e($user['name']) ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Child Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card child-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="bi bi-person-circle"></i> <?= e($child['name']) ?>
                                </h4>
                                <p class="mb-0">
                                    <i class="bi bi-book"></i> Kelas: <?= e($child['room']) ?> | 
                                    <i class="bi bi-credit-card"></i> UID: <?= e($child['uid_hex']) ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <h2 class="mb-0">
                                    <?php if ($todayAttendance): ?>
                                        <span class="badge bg-success fs-6">Hadir Hari Ini</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6">Belum Hadir</span>
                                    <?php endif; ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Status -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card parent-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-day"></i> Status Hari Ini
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($todayAttendance): ?>
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Sudah Hadir</h6>
                                <p class="mb-1"><strong>Waktu Masuk:</strong> <?= date('H:i:s', strtotime($todayAttendance['ts'])) ?></p>
                                <p class="mb-0">
                                    <?php 
                                    $arrivalTime = strtotime($todayAttendance['ts']);
                                    $lateTime = strtotime(date('Y-m-d 07:15:00'));
                                    if ($arrivalTime > $lateTime): ?>
                                        <span class="text-warning">
                                            <i class="bi bi-clock"></i> Terlambat <?= round(($arrivalTime - $lateTime) / 60) ?> menit
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success">
                                            <i class="bi bi-check-circle"></i> Tepat waktu
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-exclamation-triangle"></i> Belum Hadir</h6>
                                <p class="mb-0">Anak Anda belum melakukan scan masuk hari ini</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card parent-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up"></i> Statistik Minggu Ini
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $totalDays = count($weeklyAttendance);
                        $presentDays = count(array_filter($weeklyAttendance));
                        $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
                        ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h3 class="text-primary"><?= $totalDays ?></h3>
                                <small>Hari Sekolah</small>
                            </div>
                            <div class="col-4">
                                <h3 class="text-success"><?= $presentDays ?></h3>
                                <small>Hari Hadir</small>
                            </div>
                            <div class="col-4">
                                <h3 class="text-info"><?= $attendanceRate ?>%</h3>
                                <small>Tingkat Hadir</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Attendance -->
        <div class="row">
            <div class="col-12">
                <div class="card parent-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-week"></i> Riwayat Kehadiran Minggu Ini
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Hari</th>
                                        <th>Waktu Masuk</th>
                                        <th>Waktu Pulang</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weeklyAttendance as $att): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($att['date'])) ?></td>
                                        <td><?= date('l', strtotime($att['date'])) ?></td>
                                        <td>
                                            <?php if ($att['first_scan']): ?>
                                                <?= date('H:i:s', strtotime($att['first_scan'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($att['last_scan'] && $att['last_scan'] !== $att['first_scan']): ?>
                                                <?= date('H:i:s', strtotime($att['last_scan'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($att['first_scan']): ?>
                                                <?php 
                                                $arrivalTime = strtotime($att['first_scan']);
                                                $lateTime = strtotime($att['date'] . ' 07:15:00');
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
                                            <?php if ($att['first_scan']): ?>
                                                <?php 
                                                $arrivalTime = strtotime($att['first_scan']);
                                                $lateTime = strtotime($att['date'] . ' 07:15:00');
                                                if ($arrivalTime > $lateTime): ?>
                                                    Terlambat <?= round(($arrivalTime - $lateTime) / 60) ?> menit
                                                <?php else: ?>
                                                    Tepat waktu
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Tidak hadir
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($weeklyAttendance)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="bi bi-info-circle"></i> Belum ada data kehadiran minggu ini
                                        </td>
                                    </tr>
                                    <?php endif; ?>
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
