<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/AuthService.php';

// Initialize PDO and config
$pdo = pdo();
$config = $ENV; // Pass the global config array

$authService = new AuthService($pdo, $config);
$authService->requireRole(['student']);

$user = $authService->getCurrentUser();

// Get student's attendance data
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
$todayAttendance->execute([$user['uid_hex'], $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')]);
$todayAttendance = $todayAttendance->fetch(PDO::FETCH_ASSOC);

// Get monthly attendance
$monthStart = (clone $today)->modify('first day of this month');
$monthEnd = (clone $monthStart)->modify('+1 month');

$monthlyAttendance = $pdo->prepare("
    SELECT DATE(ts) as date, MIN(ts) as first_scan, MAX(ts) as last_scan, COUNT(*) as total_scans
    FROM attendance 
    WHERE uid_hex = ? AND ts >= ? AND ts < ? 
    GROUP BY DATE(ts)
    ORDER BY date DESC
    LIMIT 30
");
$monthlyAttendance->execute([$user['uid_hex'], $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
$monthlyAttendance = $monthlyAttendance->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalDays = count($monthlyAttendance);
$presentDays = count(array_filter($monthlyAttendance));
$attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .student-card { border-left: 4px solid #6f42c1; }
        .stats-card { background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; }
        .student-info { background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%); color: white; }
        .attendance-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .calendar-day { 
            aspect-ratio: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 5px; 
            font-size: 0.8rem;
            font-weight: bold;
        }
        .present { background-color: #28a745; color: white; }
        .absent { background-color: #dc3545; color: white; }
        .late { background-color: #ffc107; color: black; }
        .future { background-color: #e9ecef; color: #6c757d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-purple" style="background-color: #6f42c1 !important;">
        <div class="container">
            <a class="navbar-brand" href="student.php">
                <i class="bi bi-person"></i> Student Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person"></i> <?= e($user['name']) ?> (<?= e($user['room']) ?>)
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
    </div>
  </div>
</nav>

    <div class="container-fluid mt-4">
        <!-- Student Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card student-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="bi bi-person-circle"></i> <?= e($user['name']) ?>
                                </h4>
                                <p class="mb-0">
                                    <i class="bi bi-book"></i> Kelas: <?= e($user['room']) ?> | 
                                    <i class="bi bi-credit-card"></i> UID: <?= e($user['uid_hex']) ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <h2 class="mb-0">
                                    <?php if ($todayAttendance): ?>
                                        <span class="badge bg-success fs-6">Sudah Scan Hari Ini</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning fs-6">Belum Scan Hari Ini</span>
                                    <?php endif; ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check fs-1"></i>
                        <h3 class="mt-2"><?= $totalDays ?></h3>
                        <p class="mb-0">Hari Sekolah</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle fs-1"></i>
                        <h3 class="mt-2"><?= $presentDays ?></h3>
                        <p class="mb-0">Hari Hadir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up fs-1"></i>
                        <h3 class="mt-2"><?= $attendanceRate ?>%</h3>
                        <p class="mb-0">Tingkat Hadir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock fs-1"></i>
                        <h3 class="mt-2">
                            <?php if ($todayAttendance): ?>
                                <?= date('H:i', strtotime($todayAttendance['ts'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </h3>
                        <p class="mb-0">Scan Terakhir</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Status -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card student-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-day"></i> Status Hari Ini
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($todayAttendance): ?>
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Sudah Melakukan Scan</h6>
                                <p class="mb-1"><strong>Waktu Scan:</strong> <?= date('H:i:s', strtotime($todayAttendance['ts'])) ?></p>
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
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Belum Melakukan Scan</h6>
                                <p class="mb-0">Silakan lakukan scan RFID untuk mencatat kehadiran</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card student-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar3"></i> Kalender Kehadiran
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="attendance-calendar">
                            <?php
                            $currentMonth = $today->format('Y-m');
                            $firstDay = (clone $today)->modify('first day of this month');
                            $lastDay = (clone $firstDay)->modify('last day of this month');
                            
                            // Create attendance map
                            $attendanceMap = [];
                            foreach ($monthlyAttendance as $att) {
                                $attendanceMap[date('Y-m-d', strtotime($att['date']))] = $att;
                            }
                            
                            // Generate calendar
                            for ($i = 1; $i <= $lastDay->format('d'); $i++) {
                                $date = sprintf('%s-%02d', $currentMonth, $i);
                                $dayOfWeek = date('w', strtotime($date));
                                
                                if ($i == 1) {
                                    // Add empty cells for days before the first day of month
                                    for ($j = 0; $j < $dayOfWeek; $j++) {
                                        echo '<div class="calendar-day future">-</div>';
                                    }
                                }
                                
                                $att = $attendanceMap[$date] ?? null;
                                $class = 'future';
                                
                                if ($att) {
                                    $arrivalTime = strtotime($att['first_scan']);
                                    $lateTime = strtotime($date . ' 07:15:00');
                                    $class = $arrivalTime > $lateTime ? 'late' : 'present';
                                } elseif ($date < $today->format('Y-m-d')) {
                                    $class = 'absent';
                                }
                                
                                echo '<div class="calendar-day ' . $class . '">' . $i . '</div>';
                            }
                            ?>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <span class="badge bg-success me-1">■</span> Hadir | 
                                <span class="badge bg-warning me-1">■</span> Terlambat | 
                                <span class="badge bg-danger me-1">■</span> Tidak Hadir | 
                                <span class="badge bg-secondary me-1">■</span> Belum Datang
                            </small>
                        </div>
                    </div>
    </div>
    </div>
    </div>

        <!-- Monthly Attendance History -->
        <div class="row">
            <div class="col-12">
                <div class="card student-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-week"></i> Riwayat Kehadiran Bulan Ini
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
                                    <?php foreach ($monthlyAttendance as $att): ?>
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
                                    
                                    <?php if (empty($monthlyAttendance)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="bi bi-info-circle"></i> Belum ada data kehadiran bulan ini
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