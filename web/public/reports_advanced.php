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
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));

// Inisialisasi NotificationManager
require_once __DIR__ . '/../classes/NotificationManager.php';
$notificationManager = new NotificationManager($pdo, $config);

// Tangani pembuatan laporan
$reportType = $_GET['type'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'html';
$room = $_GET['room'] ?? '';
$user = $_GET['user'] ?? '';

// Ambil data untuk dashboard
$stats = [];

// Statistik umum
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users,
        COUNT(DISTINCT a.uid_hex) as users_with_attendance,
        COUNT(a.id) as total_scans
    FROM users u
    LEFT JOIN attendance a ON u.uid_hex = a.uid_hex AND DATE(a.ts) = CURDATE()
");
$stmt->execute();
$stats['general'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistik kehadiran hari ini
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.uid_hex) as present_today,
        COUNT(DISTINCT CASE WHEN JSON_EXTRACT(a.raw_json, '$.is_late') = true THEN a.uid_hex END) as late_today,
        COUNT(DISTINCT CASE WHEN JSON_EXTRACT(a.raw_json, '$.is_late') = false THEN a.uid_hex END) as on_time_today
    FROM attendance a
    WHERE DATE(a.ts) = CURDATE()
");
$stmt->execute();
$stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistik per role
$stmt = $pdo->prepare("
    SELECT 
        u.role,
        COUNT(u.id) as total,
        COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active,
        COUNT(DISTINCT a.uid_hex) as with_attendance
    FROM users u
    LEFT JOIN attendance a ON u.uid_hex = a.uid_hex AND DATE(a.ts) = CURDATE()
    GROUP BY u.role
");
$stmt->execute();
$stats['by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik per kelas
$stmt = $pdo->prepare("
    SELECT 
        u.room,
        COUNT(DISTINCT u.id) as total_students,
        COUNT(DISTINCT a.uid_hex) as present_today,
        ROUND(COUNT(DISTINCT a.uid_hex) * 100.0 / COUNT(DISTINCT u.id), 2) as attendance_rate
    FROM users u
    LEFT JOIN attendance a ON u.uid_hex = a.uid_hex AND DATE(a.ts) = CURDATE()
    WHERE u.role = 'student' AND u.room != ''
    GROUP BY u.room
    ORDER BY attendance_rate DESC
");
$stmt->execute();
$stats['by_room'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data untuk grafik bulanan
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.ts) as date,
        COUNT(DISTINCT a.uid_hex) as present,
        COUNT(DISTINCT CASE WHEN JSON_EXTRACT(a.raw_json, '$.is_late') = true THEN a.uid_hex END) as late,
        COUNT(DISTINCT CASE WHEN JSON_EXTRACT(a.raw_json, '$.is_late') = false THEN a.uid_hex END) as on_time
    FROM attendance a
    WHERE DATE(a.ts) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(a.ts)
    ORDER BY date DESC
");
$stmt->execute();
$stats['monthly_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['generate'])) {
    if ($format === 'pdf') {
        generatePDFReport($reportType, $month, $year);
        exit;
    } elseif ($format === 'csv') {
        generateCSVReport($reportType, $month, $year);
        exit;
    }
}

function generatePDFReport($type, $month, $year) {
    // Pembuatan PDF akan diimplementasikan
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . $type . '_' . $month . '.pdf"');
    echo "Pembuatan Laporan PDF - Segera Hadir";
}

function generateCSVReport($type, $month, $year) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . $type . '_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tanggal', 'Nama', 'Kelas', 'Status', 'Waktu Masuk', 'Keterlambatan']);
    
    // Ambil data kehadiran
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            DATE(a.ts) as date,
            u.name,
            u.room,
            CASE 
                WHEN JSON_EXTRACT(a.raw_json, '$.is_late') = true THEN 'Terlambat'
                ELSE 'Tepat Waktu'
            END as status,
            TIME(a.ts) as time_in,
            JSON_EXTRACT(a.raw_json, '$.late_minutes') as late_minutes
        FROM attendance a
        JOIN users u ON a.uid_hex = u.uid_hex
        WHERE DATE(a.ts) >= ? AND DATE(a.ts) <= ?
        ORDER BY a.ts DESC
    ");
    
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt->execute([$startDate, $endDate]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['date'],
            $row['name'],
            $row['room'],
            $row['status'],
            $row['time_in'],
            $row['late_minutes'] ?: '0'
        ]);
    }
    
    fclose($output);
    exit;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="reports_advanced.php">
                <i class="bi bi-graph-up"></i> Laporan Kehadiran
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $stats['general']['total_users'] ?></h4>
                                <p class="card-text">Total Pengguna</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-4"></i>
                            </div>
                        </div>
                        <small>Aktif: <?= $stats['general']['active_users'] ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $stats['today']['present_today'] ?></h4>
                                <p class="card-text">Hadir Hari Ini</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle display-4"></i>
                            </div>
                        </div>
                        <small>Tepat waktu: <?= $stats['today']['on_time_today'] ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $stats['today']['late_today'] ?></h4>
                                <p class="card-text">Terlambat Hari Ini</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock display-4"></i>
                            </div>
                        </div>
                        <small>Total scan: <?= $stats['general']['total_scans'] ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= count($stats['by_room']) ?></h4>
                                <p class="card-text">Total Kelas</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-building display-4"></i>
                            </div>
                        </div>
                        <small>Dengan kehadiran: <?= $stats['general']['users_with_attendance'] ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Report Generation -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Generate Laporan</h5>
                    </div>
                    <div class="card-body">
                        <form method="get">
                            <div class="mb-3">
                                <label for="type" class="form-label">Jenis Laporan</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                    <option value="yearly" <?= $reportType === 'yearly' ? 'selected' : '' ?>>Tahunan</option>
                                    <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Harian</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="month" class="form-label">Bulan</label>
                                <input type="month" class="form-control" id="month" name="month" value="<?= e($month) ?>">
                            </div>
                            <div class="mb-3">
                                <label for="year" class="form-label">Tahun</label>
                                <input type="number" class="form-control" id="year" name="year" value="<?= e($year) ?>" min="2020" max="2030">
                            </div>
                            <div class="mb-3">
                                <label for="format" class="form-label">Format</label>
                                <select class="form-select" id="format" name="format">
                                    <option value="html" <?= $format === 'html' ? 'selected' : '' ?>>HTML</option>
                                    <option value="pdf" <?= $format === 'pdf' ? 'selected' : '' ?>>PDF</option>
                                    <option value="csv" <?= $format === 'csv' ? 'selected' : '' ?>>CSV</option>
                                </select>
                            </div>
                            <button type="submit" name="generate" class="btn btn-primary w-100">
                                <i class="bi bi-file-earmark-text"></i> Generate Laporan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Role Statistics -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i> Statistik Per Role</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($stats['by_role'] as $role): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-capitalize"><?= e($role['role']) ?></span>
                            <div>
                                <span class="badge bg-primary"><?= $role['total'] ?></span>
                                <span class="badge bg-success"><?= $role['active'] ?></span>
                            </div>
                        </div>
                        <div class="progress mb-2" style="height: 5px;">
                            <div class="progress-bar" style="width: <?= $role['total'] > 0 ? ($role['with_attendance'] / $role['total'] * 100) : 0 ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Charts and Data -->
            <div class="col-md-8">
                <!-- Monthly Chart -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i> Grafik Kehadiran 30 Hari Terakhir</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Room Statistics -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i> Statistik Per Kelas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Total Siswa</th>
                                        <th>Hadir Hari Ini</th>
                                        <th>Tingkat Kehadiran</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['by_room'] as $room): ?>
                                    <tr>
                                        <td><?= e($room['room']) ?></td>
                                        <td><?= $room['total_students'] ?></td>
                                        <td><?= $room['present_today'] ?></td>
                                        <td><?= $room['attendance_rate'] ?>%</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?= $room['attendance_rate'] >= 80 ? 'bg-success' : ($room['attendance_rate'] >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                                     style="width: <?= $room['attendance_rate'] ?>%">
                                                    <?= $room['attendance_rate'] ?>%
                                                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grafik kehadiran bulanan
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?= json_encode($stats['monthly_chart']) ?>;
        
        const labels = monthlyData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('id-ID', { month: 'short', day: 'numeric' });
        }).reverse();
        
        const presentData = monthlyData.map(item => item.present).reverse();
        const lateData = monthlyData.map(item => item.late).reverse();
        const onTimeData = monthlyData.map(item => item.on_time).reverse();
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Hadir',
                        data: presentData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Tepat Waktu',
                        data: onTimeData,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Terlambat',
                        data: lateData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Grafik Kehadiran 30 Hari Terakhir'
                    }
                }
            }
        });
    </script>
</body>
</html>
