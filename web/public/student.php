<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uid = isset($_GET['uid']) ? preg_replace('/[^0-9a-f]/i','', strtolower($_GET['uid'])) : '';

if ($id > 0) {
  $st = $pdo->prepare('SELECT id, name, uid_hex, room FROM users WHERE id = ?');
  $st->execute([$id]);
  $user = $st->fetch(PDO::FETCH_ASSOC);
} elseif ($uid !== '') {
  $st = $pdo->prepare('SELECT id, name, uid_hex, room FROM users WHERE uid_hex = ?');
  $st->execute([$uid]);
  $user = $st->fetch(PDO::FETCH_ASSOC);
} else {
  $user = null;
}

if (!$user) {
  http_response_code(404);
  echo 'Siswa tidak ditemukan';
  exit;
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
try { $fromDt = new DateTime($from ?: 'first day of this month', $tz); } catch (Exception $e) { $fromDt = new DateTime('first day of this month', $tz); }
try { $toDt = new DateTime($to ?: 'last day of this month', $tz); } catch (Exception $e) { $toDt = (clone $fromDt)->modify('last day of this month'); }

// Daily status per day in range
$period = new DatePeriod($fromDt, new DateInterval('P1D'), (clone $toDt)->modify('+1 day'));
$rows = [];
$schoolStart = env('SCHOOL_START','07:15');
$schoolEnd   = env('SCHOOL_END','15:00');
$requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);

foreach ($period as $day) {
  $start = DateTime::createFromFormat('Y-m-d H:i:s', $day->format('Y-m-d').' 00:00:00', $tz);
  $end   = (clone $start)->modify('+1 day');
  $q = $pdo->prepare('SELECT MIN(ts) AS first_ts, MAX(ts) AS last_ts FROM attendance WHERE uid_hex = ? AND ts >= ? AND ts < ?');
  $q->execute([$user['uid_hex'], $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
  $r = $q->fetch(PDO::FETCH_ASSOC) ?: ['first_ts'=>null,'last_ts'=>null];
  $first = $r['first_ts']; $last = $r['last_ts'];
  $lateAt = DateTime::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$schoolStart, $tz);
  $endAt  = DateTime::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$schoolEnd, $tz);
  $statusMasuk = 'Tidak Hadir';
  $statusPulang = '';
  if ($first) {
    $f = new DateTime($first, $tz);
    $statusMasuk = ($f > $lateAt) ? 'Terlambat' : 'Hadir';
    if ($last) {
      $statusPulang = 'Pulang';
    } else {
      $today = new DateTime('today', $tz);
      if ($day < $today) $statusPulang = 'Bolos';
      else {
        // aturan tambahan: setelah 21:00 di hari yg sama tanpa scan pulang => Bolos
        $now = new DateTime('now', $tz);
        $cut21 = DateTime::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' 21:00', $tz);
        if ($now > $cut21 && !is_holiday($day)) {
          $statusPulang = 'Bolos';
        } else if ($requireCheckout) {
          $statusPulang = ($now > $endAt && !is_holiday($day)) ? 'Bolos' : 'Belum Pulang';
        } else {
          $statusPulang = 'Belum Pulang';
        }
      }
    }
  }
  $rows[] = [
    'date' => $day->format('Y-m-d'),
    'first_ts' => $first,
    'last_ts' => $last,
    'masuk' => $statusMasuk,
    'pulang' => $statusPulang,
  ];
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil Siswa</title>
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
        <li class="nav-item"><a class="nav-link active" data-nav="users" href="./users.php"><i class="bi bi-people me-1"></i><span class="btn-text">Siswa</span></a></li>
        <li class="nav-item"><a class="nav-link" data-nav="rooms" href="./rooms.php"><i class="bi bi-building me-1"></i><span class="btn-text">Kelas</span></a></li>
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
  <h3 class="mb-1">Profil Siswa</h3>
  <div class="mb-3 text-muted">Nama: <strong><?= e($user['name']) ?></strong> • UID: <code><?= e($user['uid_hex']) ?></code> • Kelas: <strong><?= e($user['room']) ?></strong></div>

  <form method="get" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
    <div class="col-auto">
      <label class="form-label">Dari</label>
      <input type="date" class="form-control" name="from" value="<?= e($fromDt->format('Y-m-d')) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label">Sampai</label>
      <input type="date" class="form-control" name="to" value="<?= e($toDt->format('Y-m-d')) ?>">
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit"><i class="bi bi-filter me-1"></i>Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Status Hadir</th>
          <th>Status Pulang</th>
          <th>Scan Hadir</th>
          <th>Scan Pulang</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $badge = $r['masuk']==='Hadir'?'badge-present':($r['masuk']==='Terlambat'?'badge-late':'badge-absent');
                $p=$r['pulang']; $pcls = ($p==='Pulang')?'badge-pulang':(($p==='Bolos')?'badge-bolos':(($p==='Belum Pulang')?'badge-belum':'badge-absent')); ?>
          <tr>
            <td><?= e($r['date']) ?></td>
            <td><span class="badge badge-status <?= $badge ?>"><?= e($r['masuk']) ?></span></td>
            <td><span class="badge badge-status <?= $pcls ?>"><?= e($p ?: '—') ?></span></td>
            <td><?= e($r['first_ts'] ?: '—') ?></td>
            <td><?= e($r['last_ts'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-2">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
  </div>

  <footer class="site-footer text-center small">
    <?php $addr=env('SCHOOL_ADDRESS',''); $phone=env('SCHOOL_PHONE',''); $email=env('SCHOOL_EMAIL',''); $site=env('SCHOOL_WEBSITE',''); ?>
    <div><strong><?= e(env('SCHOOL_NAME','')) ?></strong><?= ($m=env('SCHOOL_MOTTO',''))? ' • '.e($m):'' ?></div>
    <div><?= e($addr) ?> <?= $phone? ' • Telp: '.e($phone):'' ?> <?= $email? ' • Email: '.e($email):'' ?> <?= $site? ' • '.e($site):'' ?></div>
  </footer>
</div>
<script type="module">
  import { initTheme, initThemeToggle, initContrast, setActiveNav } from './assets/app.js';
  initTheme(); initThemeToggle(); initContrast(); setActiveNav('users');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
