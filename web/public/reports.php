<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();

// Hanya admin yang bisa akses
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));

// Tangani pembuatan laporan
if (isset($_GET['generate'])) {
    $reportType = $_GET['type'] ?? 'monthly';
    $month = $_GET['month'] ?? date('Y-m');
    $year = $_GET['year'] ?? date('Y');
    $format = $_GET['format'] ?? 'html';
    
    if ($format === 'pdf') {
        generatePDFReport($reportType, $month, $year);
        exit;
    }
}

function generatePDFReport($type, $month, $year) {
    // Pembuatan PDF akan diimplementasikan
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . $type . '_' . $month . '.pdf"');
    echo "Pembuatan Laporan PDF - Segera Hadir";
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ambil bulan dan tahun yang tersedia
$months = [];
$years = [];
$currentYear = (int)date('Y');
for ($i = $currentYear - 2; $i <= $currentYear + 1; $i++) {
    $years[] = $i;
}

for ($i = 1; $i <= 12; $i++) {
    $months[] = [
        'value' => sprintf('%04d-%02d', $currentYear, $i),
        'label' => date('F Y', mktime(0, 0, 0, $i, 1, $currentYear))
    ];
}

// Ambil data laporan
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedType = $_GET['type'] ?? 'monthly';

$startDate = new DateTime($selectedMonth . '-01', $tz);
$endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

// Monthly attendance data
$monthlyData = getMonthlyAttendanceData($pdo, $startDate, $endDate);
$classStats = getClassStatistics($pdo, $startDate, $endDate);
$dailyStats = getDailyStatistics($pdo, $startDate, $endDate);

function getMonthlyAttendanceData($pdo, $start, $end) {
    $sql = 'SELECT 
                u.id, u.name, u.room,
                COUNT(DISTINCT DATE(a.ts)) as days_present,
                COUNT(a.id) as total_scans,
                MIN(a.ts) as first_scan,
                MAX(a.ts) as last_scan
            FROM users u
            LEFT JOIN attendance a ON u.uid_hex = a.uid_hex 
                AND a.ts >= ? AND a.ts < ?
            GROUP BY u.id, u.name, u.room
            ORDER BY u.room, u.name';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClassStatistics($pdo, $start, $end) {
    $sql = 'SELECT 
                u.room,
                COUNT(DISTINCT u.id) as total_students,
                COUNT(DISTINCT CASE WHEN a.uid_hex IS NOT NULL THEN u.id END) as present_students,
                ROUND(COUNT(DISTINCT CASE WHEN a.uid_hex IS NOT NULL THEN u.id END) * 100.0 / COUNT(DISTINCT u.id), 2) as attendance_percentage
            FROM users u
            LEFT JOIN attendance a ON u.uid_hex = a.uid_hex 
                AND a.ts >= ? AND a.ts < ?
            WHERE u.room != ""
            GROUP BY u.room
            ORDER BY u.room';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDailyStatistics($pdo, $start, $end) {
    $sql = 'SELECT 
                DATE(a.ts) as date,
                COUNT(DISTINCT a.uid_hex) as unique_students,
                COUNT(a.id) as total_scans
            FROM attendance a
            WHERE a.ts >= ? AND a.ts < ?
            GROUP BY DATE(a.ts)
            ORDER BY DATE(a.ts)';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kehadiran - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { position: relative; height: 400px; }
        .report-card { border-left: 4px solid #007bff; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-graph-up"></i> Laporan Kehadiran
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="users.php">
                    <i class="bi bi-people"></i> Pengguna
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Report Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-funnel"></i> Filter Laporan
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Jenis Laporan</label>
                                <select name="type" class="form-select">
                                    <option value="monthly" <?= $selectedType === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                    <option value="yearly" <?= $selectedType === 'yearly' ? 'selected' : '' ?>>Tahunan</option>
                                    <option value="class" <?= $selectedType === 'class' ? 'selected' : '' ?>>Per Kelas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bulan</label>
                                <select name="month" class="form-select">
                                    <?php foreach ($months as $month): ?>
                                        <option value="<?= e($month['value']) ?>" <?= $selectedMonth === $month['value'] ? 'selected' : '' ?>>
                                            <?= e($month['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tahun</label>
                                <select name="year" class="form-select">
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Format</label>
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" name="format" value="html" class="btn btn-outline-primary">
                                        <i class="bi bi-eye"></i> Lihat
                                    </button>
                                    <button type="submit" name="format" value="pdf" class="btn btn-outline-danger">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </button>
                                    <button type="submit" name="format" value="csv" class="btn btn-outline-success">
                                        <i class="bi bi-file-csv"></i> CSV
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill fs-1"></i>
                        <h3 class="mt-2"><?= count($monthlyData) ?></h3>
                        <p class="mb-0">Total Siswa</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill fs-1"></i>
                        <h3 class="mt-2"><?= count(array_filter($monthlyData, function($row) { return $row['days_present'] > 0; })) ?></h3>
                        <p class="mb-0">Siswa Hadir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up fs-1"></i>
                        <h3 class="mt-2"><?= round(count(array_filter($monthlyData, function($row) { return $row['days_present'] > 0; })) * 100 / max(1, count($monthlyData)), 1) ?>%</h3>
                        <p class="mb-0">Persentase Hadir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check fs-1"></i>
                        <h3 class="mt-2"><?= $startDate->format('d M Y') ?></h3>
                        <p class="mb-0">Periode Laporan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bar-chart"></i> Kehadiran Harian
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-pie-chart"></i> Kehadiran Per Kelas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="classChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Report -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-table"></i> Detail Laporan Kehadiran
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Kelas</th>
                                        <th>Hari Hadir</th>
                                        <th>Total Scan</th>
                                        <th>Scan Pertama</th>
                                        <th>Scan Terakhir</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyData as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= e($row['name']) ?></td>
                                        <td><?= e($row['room']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['days_present'] > 0 ? 'success' : 'danger' ?>">
                                                <?= $row['days_present'] ?>
                                            </span>
                                        </td>
                                        <td><?= $row['total_scans'] ?></td>
                                        <td><?= $row['first_scan'] ? date('d/m H:i', strtotime($row['first_scan'])) : '-' ?></td>
                                        <td><?= $row['last_scan'] ? date('d/m H:i', strtotime($row['last_scan'])) : '-' ?></td>
                                        <td>
                                            <?php if ($row['days_present'] > 0): ?>
                                                <span class="badge bg-success">Hadir</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Tidak Hadir</span>
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

    <script>
        // Daily Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?= json_encode($dailyStats) ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.date).toLocaleDateString('id-ID')),
                datasets: [{
                    label: 'Siswa Hadir',
                    data: dailyData.map(d => d.unique_students),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Class Chart
        const classCtx = document.getElementById('classChart').getContext('2d');
        const classData = <?= json_encode($classStats) ?>;
        
        new Chart(classCtx, {
            type: 'doughnut',
            data: {
                labels: classData.map(c => c.room),
                datasets: [{
                    data: classData.map(c => c.attendance_percentage),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
