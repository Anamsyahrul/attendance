<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();
$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
$today = new DateTime('today', $tz);
$tomorrow = (clone $today)->modify('+1 day');
$schoolStart = env('SCHOOL_START', '07:15');
$lateAt = DateTime::createFromFormat('Y-m-d H:i', $today->format('Y-m-d') . ' ' . $schoolStart, $tz);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$rooms = $pdo->query("SELECT room FROM users WHERE room <> '' GROUP BY room ORDER BY room")->fetchAll(PDO::FETCH_COLUMN);

// Aggregate per room
$stats = [];
foreach ($rooms as $room) {
    $qTotal = $pdo->prepare('SELECT COUNT(*) FROM users WHERE room = ?');
    $qTotal->execute([$room]);
    $total = (int)$qTotal->fetchColumn();
    $qPresent = $pdo->prepare('SELECT COUNT(DISTINCT u.uid_hex) FROM users u JOIN attendance a ON a.uid_hex = u.uid_hex WHERE u.room = ? AND a.ts >= ? AND a.ts < ?');
    $qPresent->execute([$room, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')]);
    $present = (int)$qPresent->fetchColumn();
    $qLate = $pdo->prepare('SELECT COUNT(*) FROM (
        SELECT u.uid_hex, MIN(a.ts) AS first_ts
        FROM users u JOIN attendance a ON a.uid_hex = u.uid_hex
        WHERE u.room = ? AND a.ts >= ? AND a.ts < ?
        GROUP BY u.uid_hex
        HAVING MIN(a.ts) > ?
    ) t');
    $qLate->execute([$room, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s'), $lateAt->format('Y-m-d H:i:s')]);
    $late = (int)$qLate->fetchColumn();
    $stats[] = ['room'=>$room, 'total'=>$total, 'present'=>$present, 'late'=>$late];
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Kelas</title>
  <script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="./assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <?php $school = env('SCHOOL_NAME', 'Attendance'); ?>
    <a class="navbar-brand d-flex align-items-center gap-2" href="./index.php">
      <span><?= e($school) ?></span>
    </a>
    <!-- Mobile toggle button -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <!-- Collapsible navbar content -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" data-nav="dashboard" href="./index.php"><i class="bi bi-speedometer2 me-1"></i><span class="btn-text">Dashboard</span></a></li>
        <li class="nav-item"><a class="nav-link" data-nav="register" href="./register.php"><i class="bi bi-person-plus me-1"></i><span class="btn-text">Daftar Kartu</span></a></li>
        <li class="nav-item"><a class="nav-link" data-nav="users" href="./users.php"><i class="bi bi-people me-1"></i><span class="btn-text">Siswa</span></a></li>
        <li class="nav-item"><a class="nav-link active" data-nav="rooms" href="./rooms.php"><i class="bi bi-building me-1"></i><span class="btn-text">Kelas</span></a></li>
        <li class="nav-item"><a class="nav-link" data-nav="settings" href="./settings.php"><i class="bi bi-gear me-1"></i><span class="btn-text">Pengaturan</span></a></li>

        <li class="nav-item">
          <button id="themeToggle" class="btn" type="button" style="background-color: #6c757d; border: 1px solid #6c757d; color: #ffffff; padding: 0.5rem 1rem; border-radius: 4px; margin: 0 0.5rem; display: inline-block; text-decoration: none; font-size: 0.875rem; font-weight: 500; cursor: pointer; min-width: 100px;"><i class="bi bi-moon-stars me-1"></i><span class="btn-text">Gelap</span></button>
        </li>
        <li class="nav-item">
          <a class="btn" href="./logout.php" style="background-color: #dc3545; border: 1px solid #dc3545; color: #ffffff; padding: 0.5rem 1rem; border-radius: 4px; margin: 0 0.5rem; display: inline-block; text-decoration: none; font-size: 0.875rem; font-weight: 500; cursor: pointer; min-width: 100px;"><i class="bi bi-box-arrow-right me-1"></i><span class="btn-text">Keluar</span></a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Kelas</h3>
    <div class="text-muted">Tanggal: <?= e($today->format('Y-m-d')) ?> (Mulai: <?= e($schoolStart) ?>)</div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Kelas</th>
          <th>Total Siswa</th>
          <th>Hadir</th>
          <th>Terlambat</th>
          <th>Tidak Hadir</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stats as $s): ?>
        <tr>
          <td><?= e($s['room']) ?></td>
          <td><?= (int)$s['total'] ?></td>
          <td><?= (int)$s['present'] ?></td>
          <td><?= (int)$s['late'] ?></td>
          <td><?= max(0, (int)$s['total'] - (int)$s['present']) ?></td>
          <td>
            <?php $appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
              $recapUrl = './index.php?'.http_build_query(['view'=>'recap','room'=>$s['room'],'date'=>$today->format('Y-m-d')]);
            ?>
            <a class="btn btn-sm btn-success" href="<?= e($recapUrl) ?>"><i class="bi bi-mortarboard"></i> Rekap</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script type="module">
  import { initTheme, initThemeToggle, initContrast, setActiveNav } from './assets/app.js';
  initTheme(); initThemeToggle();
  initContrast();
  setActiveNav('rooms');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
