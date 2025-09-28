<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

// CSV Export via /attendance/api/attendance.csv (rewritten here by .htaccess)
if (isset($_GET['export'])) {
    $pdo = pdo();
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    $q    = trim($_GET['q'] ?? '');

    $tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
    try {
        $fromDt = new DateTime($from ?: 'today', $tz);
    } catch (Exception $e) {
        $fromDt = new DateTime('today', $tz);
    }
    try {
        $toDt = new DateTime($to ?: 'tomorrow', $tz);
    } catch (Exception $e) {
        $toDt = (clone $fromDt)->modify('+1 day');
    }

    $params = [$fromDt->format('Y-m-d H:i:s'), $toDt->format('Y-m-d H:i:s')];
    $sql = 'SELECT a.ts, u.name AS user_name, a.uid_hex, a.device_id FROM attendance a LEFT JOIN users u ON a.user_id = u.id WHERE a.ts >= ? AND a.ts < ?';
    if ($q !== '') {
        $sql .= ' AND (u.name LIKE ? OR a.uid_hex LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= ' ORDER BY a.ts DESC';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ts', 'user_name', 'uid_hex', 'device_id']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['ts'], $row['user_name'], $row['uid_hex'], $row['device_id']]);
    }
    fclose($out);
    exit;
}

$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
$schoolMode = (bool) env('SCHOOL_MODE', false);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$q    = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? ($schoolMode ? 'recap' : 'raw');
$date = $_GET['date'] ?? '';
$room = $_GET['room'] ?? '';
// status filter from cards: 'hadir' | 'terlambat' | 'tidak_hadir'
$sf   = isset($_GET['sf']) ? strtolower(trim((string)$_GET['sf'])) : '';

try { $fromDt = new DateTime($from ?: 'today', $tz); } catch (Exception $e) { $fromDt = new DateTime('today', $tz); }
try { $toDt = new DateTime($to ?: 'tomorrow', $tz); } catch (Exception $e) { $toDt = (clone $fromDt)->modify('+1 day'); }
try { $dateDt = new DateTime($date ?: 'today', $tz); } catch (Exception $e) { $dateDt = new DateTime('today', $tz); }

// Distinct rooms for filters
$rooms = $pdo->query("SELECT DISTINCT room FROM users WHERE room <> '' ORDER BY room")->fetchAll(PDO::FETCH_COLUMN);

// Raw list (default mode)
$params = [$fromDt->format('Y-m-d H:i:s'), $toDt->format('Y-m-d H:i:s')];
$countSql = 'SELECT COUNT(*) FROM attendance a LEFT JOIN users u ON a.user_id = u.id WHERE a.ts >= ? AND a.ts < ?';
$listSql  = 'SELECT a.id, a.ts, a.uid_hex, a.device_id, u.name AS user_name, u.room AS room FROM attendance a LEFT JOIN users u ON a.user_id = u.id WHERE a.ts >= ? AND a.ts < ?';
if ($q !== '') {
    $countSql .= ' AND (u.name LIKE ? OR a.uid_hex LIKE ?)';
    $listSql  .= ' AND (u.name LIKE ? OR a.uid_hex LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($room !== '') {
    $countSql .= ' AND (u.room = ?)';
    $listSql  .= ' AND (u.room = ?)';
    $params[] = $room;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$listSql .= ' ORDER BY a.ts DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$pages = (int)ceil(max(1,$totalRows) / $limit);

// Stats via API (best effort) for raw view
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // /attendance
$apiUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $appBase . '/api/stats/today.php';
$stats = ['total_scans' => 0, 'unique_users' => 0, 'active_devices' => 0];
if ($view !== 'recap') {
  try {
      $resp = @file_get_contents($apiUrl);
      if ($resp !== false) {
          $jd = json_decode($resp, true);
          if (is_array($jd) && !empty($jd['ok'])) {
              $stats['total_scans'] = $jd['total_scans'] ?? 0;
              $stats['unique_users'] = $jd['unique_users'] ?? 0;
              $stats['active_devices'] = $jd['active_devices'] ?? 0;
          }
      }
  } catch (Throwable $e) {}
}

// Recap computation (daily, by room)
$recap = null;
if ($view === 'recap') {
    $start = $dateDt; $end = (clone $start)->modify('+1 day');
    $schoolStart = env('SCHOOL_START', '07:15');
    $schoolEnd = env('SCHOOL_END', '15:00');
    $lateAt = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolStart, $tz);
    $endAt  = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolEnd, $tz);
    if ($room !== '') {
        $sql = 'SELECT u.id, u.name, u.uid_hex, u.room,
                       (SELECT MIN(a.ts) FROM attendance a WHERE a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ? AND a.device_id = \'manual\' AND (JSON_EXTRACT(a.raw_json, \'$.type\') = \'checkin\' OR JSON_EXTRACT(a.raw_json, \'$.type\') = \'override\')) AS first_ts,
                       (SELECT MAX(a.ts) FROM attendance a WHERE a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ? AND a.device_id = \'manual\' AND (JSON_EXTRACT(a.raw_json, \'$.type\') = \'checkout\' OR JSON_EXTRACT(a.raw_json, \'$.status\') = \'bolos\')) AS last_ts
                FROM users u
                WHERE u.room = ?
                ORDER BY u.name';
        $st = $pdo->prepare($sql);
        $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $room]);
    } else {
        $sql = 'SELECT u.id, u.name, u.uid_hex, u.room,
                       (SELECT MIN(a.ts) FROM attendance a WHERE a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ? AND a.device_id = \'manual\' AND (JSON_EXTRACT(a.raw_json, \'$.type\') = \'checkin\' OR JSON_EXTRACT(a.raw_json, \'$.type\') = \'override\')) AS first_ts,
                       (SELECT MAX(a.ts) FROM attendance a WHERE a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ? AND a.device_id = \'manual\' AND (JSON_EXTRACT(a.raw_json, \'$.type\') = \'checkout\' OR JSON_EXTRACT(a.raw_json, \'$.status\') = \'bolos\')) AS last_ts
                FROM users u
                ORDER BY u.room, u.name';
        $st = $pdo->prepare($sql);
        $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    }
    $rowsRecap = $st->fetchAll(PDO::FETCH_ASSOC);
    $present = 0; $late = 0; $absent = 0; $total = count($rowsRecap);
    $today = new DateTime('today', $tz);
    $isPastDay = ($start < $today);
    $requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);
    $overrideMap = build_override_map($pdo, $start, $end);
    foreach ($rowsRecap as &$rr) {
        [$statusMasuk, $statusPulang] = resolve_daily_status($rr, $tz, $start, $lateAt, $endAt, $isPastDay, $requireCheckout, $overrideMap);
        $rr['masuk_status'] = $statusMasuk;
        $rr['pulang_status'] = $statusPulang;
        if ($statusMasuk === 'Tidak Hadir') {
            $absent++;
        } else {
            $present++;
            if ($statusMasuk === 'Terlambat') {
                $late++;
            }
        }
    }
    unset($rr);
    if ($q !== '') {
        $qLower = mb_strtolower($q, 'UTF-8');
        $rowsRecap = array_values(array_filter($rowsRecap, function($r) use ($qLower) {
            $name = isset($r['name']) ? mb_strtolower($r['name'], 'UTF-8') : '';
            $uid  = isset($r['uid_hex']) ? mb_strtolower($r['uid_hex'], 'UTF-8') : '';
            return (strpos($name, $qLower) !== false) || (strpos($uid, $qLower) !== false);
        }));
    }
    $onlyAbsent = isset($_GET['only_absent']) && $_GET['only_absent'] !== '';
    $rowsForView = $rowsRecap;
    if ($onlyAbsent) {
        $rowsForView = array_values(array_filter($rowsRecap, function($r){ return ($r['masuk_status'] ?? 'Tidak Hadir') === 'Tidak Hadir'; }));
    }
    if ($sf === 'hadir') {
        $rowsForView = array_values(array_filter($rowsForView, function($r){ return ($r['masuk_status'] ?? '') === 'Hadir'; }));
    } elseif ($sf === 'terlambat') {
        $rowsForView = array_values(array_filter($rowsForView, function($r){ return ($r['masuk_status'] ?? '') === 'Terlambat'; }));
    } elseif ($sf === 'tidak_hadir') {
        $rowsForView = array_values(array_filter($rowsForView, function($r){ return ($r['masuk_status'] ?? '') === 'Tidak Hadir'; }));
    }
    $recap = [
        'total' => $total,
        'present' => $present,
        'late' => $late,
        'absent' => $absent,
        'rows' => $rowsForView,
        'date' => $start->format('Y-m-d'),
        'room' => $room,
        'school_start' => $schoolStart,
        'school_end' => $schoolEnd,
    ];
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Kehadiran</title>
  <script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="./assets/style.css" rel="stylesheet">
  <style> .card .fs-3{line-height:1;} </style>
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
  <div class="row g-3 mb-3">
    <div class="col-md-3 col-6 position-relative">
      <div class="card text-bg-primary h-100">
        <div class="card-body d-flex flex-column">
          <div class="fw-bold"><?= $view==='recap' ? 'Total Siswa' : 'Total Pemindaian Hari Ini' ?></div>
          <div class="fs-3 mt-auto">
            <?php if ($view==='recap') {
              $d = e($dateDt->format('Y-m-d')); $rm = e($room);
              echo '<a href="#" class="stretched-link text-white text-decoration-none" data-open-status="all" data-date="'.$d.'" data-room="'.$rm.'">'.(int)($recap['total'] ?? 0).'</a>';
            } else { echo (int)$stats['total_scans']; } ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-6 position-relative">
      <div class="card text-bg-success h-100">
        <div class="card-body d-flex flex-column">
          <div class="fw-bold"><?= $view==='recap' ? 'Hadir' : 'Siswa Hadir Hari Ini' ?></div>
          <div class="fs-3 mt-auto">
            <?php if ($view==='recap') { $d=e($dateDt->format('Y-m-d')); $rm=e($room); echo '<a href="#" class="stretched-link text-white text-decoration-none" data-open-status="hadir" data-date="'.$d.'" data-room="'.$rm.'">'.(int)($recap['present'] ?? 0).'</a>'; } else { echo (int)$stats['unique_users']; } ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-6 position-relative">
      <div class="card text-bg-secondary h-100">
        <div class="card-body d-flex flex-column">
          <div class="fw-bold"><?= $view==='recap' ? 'Terlambat' : 'Perangkat Aktif' ?></div>
          <div class="fs-3 mt-auto">
            <?php if ($view==='recap') { $d=e($dateDt->format('Y-m-d')); $rm=e($room); echo '<a href="#" class="stretched-link text-white text-decoration-none" data-open-status="terlambat" data-date="'.$d.'" data-room="'.$rm.'">'.(int)($recap['late'] ?? 0).'</a>'; } else { echo (int)$stats['active_devices']; } ?>
          </div>
        </div>
      </div>
    </div>
    <?php if ($view==='recap'): ?>
    <div class="col-md-3 col-6 position-relative">
      <div class="card text-bg-danger h-100">
        <div class="card-body d-flex flex-column">
          <div class="fw-bold">Tidak Hadir</div>
          <div class="fs-3 mt-auto">
            <?php $d=e($dateDt->format('Y-m-d')); $rm=e($room); echo '<a href="#" class="stretched-link text-white text-decoration-none" data-open-status="tidak_hadir" data-date="'.$d.'" data-room="'.$rm.'">'.(int)($recap['absent'] ?? 0).'</a>'; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Status List Modal -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="statusModalTitle">Rincian</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="statusLoading" class="text-center my-3" style="display:none"><div class="spinner-border" role="status"></div></div>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead><tr><th>Nama</th><th>UID</th><th>Kelas</th><th>Status Hadir</th><th>Status Pulang</th></tr></thead>
              <tbody id="statusTbody"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <form id="mainFilter" class="mb-3 filters" method="get" action="">
    <!-- Desktop layout -->
    <div class="row row-cols-auto g-2 align-items-end d-none d-md-flex flex-nowrap">
      <div class="col-auto">
        <label class="form-label">Dari</label>
        <input type="date" name="from" class="form-control" value="<?= e($fromDt->format('Y-m-d')) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">Sampai</label>
        <input type="date" name="to" class="form-control" value="<?= e($toDt->format('Y-m-d')) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">Cari (Nama/UID)</label>
        <input type="text" name="q" class="form-control" value="<?= e($q) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label">Kelas</label>
        <select id="roomFilterDesktop" name="room" class="form-select">
          <option value="">All</option>
          <?php foreach ($rooms as $r): ?>
            <option value="<?= e($r) ?>" <?= $room===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex align-items-end gap-2">
        <div class="btn-group" role="group">
          <?php
            $appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
            $pdfUrl = $appBase . '/api/attendance_print.php?from=' . urlencode($fromDt->format('Y-m-d')) . '&to=' . urlencode($toDt->format('Y-m-d')) . ($q ? '&q=' . urlencode($q) : '') . ($room ? '&room=' . urlencode($room) : '');
          ?>
          <a class="btn btn-outline-secondary" target="_blank" href="<?= e($pdfUrl) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Ekspor PDF</a>
          <a class="btn btn-outline-dark" href="?<?= http_build_query(array_merge($_GET,['view'=>'raw'])) ?>"><i class="bi bi-table me-1"></i>Log Mentah</a>
          <a class="btn btn-success" href="?<?= http_build_query(array_merge($_GET,['view'=>'recap','date'=>$dateDt->format('Y-m-d')])) ?>"><i class="bi bi-mortarboard me-1"></i>Rekap</a>
        </div>
        <div class="form-check form-switch ms-2">
          <input class="form-check-input" type="checkbox" id="autoRefresh">
          <label class="form-check-label" for="autoRefresh"><i id="arIcon" class="bi bi-arrow-repeat me-1"></i><span id="arText">Muat otomatis</span></label>
        </div>
      </div>
    </div>
    
    <!-- Mobile layout -->
    <div class="d-md-none">
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label">Dari</label>
          <input type="date" name="from" class="form-control" value="<?= e($fromDt->format('Y-m-d')) ?>">
        </div>
        <div class="col-6">
          <label class="form-label">Sampai</label>
          <input type="date" name="to" class="form-control" value="<?= e($toDt->format('Y-m-d')) ?>">
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label">Cari (Nama/UID)</label>
          <input type="text" name="q" class="form-control" value="<?= e($q) ?>">
        </div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-8">
          <label class="form-label">Kelas</label>
          <select id="roomFilterMobile" name="room" class="form-select">
            <option value="">All</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= e($r) ?>" <?= $room===$r?'selected':'' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-4 d-flex align-items-end">
          <div class="form-check form-switch w-100">
            <input class="form-check-input" type="checkbox" id="autoRefreshMobile">
            <label class="form-check-label" for="autoRefreshMobile"><i class="bi bi-arrow-repeat"></i></label>
          </div>
        </div>
      </div>
      <div class="row g-1">
        <div class="col-4">
          <a class="btn btn-outline-secondary w-100" target="_blank" href="<?= e($pdfUrl) ?>"><i class="bi bi-file-earmark-pdf"></i></a>
        </div>
        <div class="col-4">
          <a class="btn btn-outline-dark w-100" href="?<?= http_build_query(array_merge($_GET,['view'=>'raw'])) ?>"><i class="bi bi-table"></i></a>
        </div>
        <div class="col-4">
          <a class="btn btn-success w-100" href="?<?= http_build_query(array_merge($_GET,['view'=>'recap','date'=>$dateDt->format('Y-m-d')])) ?>"><i class="bi bi-mortarboard"></i></a>
        </div>
      </div>
    </div>
  </form>

  <?php if ($view === 'recap'): ?>
    <div class="print-header">
      <div><strong><?= e(env('SCHOOL_NAME','')) ?></strong></div>
      <div><?= e(env('SCHOOL_ADDRESS','')) ?></div>
      <div>Tanggal: <?= e($dateDt->format('Y-m-d')) ?><?= $room? ' • Kelas: '.e($room):'' ?></div>
    </div>
    <form class="mb-3 filters" method="get" action="">
      <?php
        // Keep existing filters
        foreach (['room'=>$room] as $k=>$v) echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
      ?>
      
      <!-- Desktop layout -->
      <div class="row row-cols-auto g-2 align-items-end d-none d-md-flex flex-nowrap">
        <div class="col-auto">
          <label class="form-label">Tanggal</label>
          <input type="date" id="recapDate" name="date" class="form-control" value="<?= e($dateDt->format('Y-m-d')) ?>">
        </div>
        
        <div class="col-auto d-flex align-items-end gap-2">
          <div class="btn-group" role="group">
            <?php
              $appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
              $recapUrl = $appBase . '/api/recap.php?date=' . urlencode($dateDt->format('Y-m-d')) . ($room?('&room=' . urlencode($room)):'');
              $recapPdfUrl = $appBase . '/api/recap_print.php?date=' . urlencode($dateDt->format('Y-m-d')) . ($room?('&room=' . urlencode($room)):'') . ($q?('&q=' . urlencode($q)):'');
            ?>
            <a class="btn btn-outline-success" href="<?= e($recapUrl) ?>"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
            <a class="btn btn-outline-danger" target="_blank" href="<?= e($recapPdfUrl) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
          </div>
        </div>
      </div>
      
      <!-- Mobile layout -->
      <div class="d-md-none">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label">Tanggal</label>
            <input type="date" id="recapDateMobile" name="date" class="form-control" value="<?= e($dateDt->format('Y-m-d')) ?>">
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="btn-group w-100" role="group">
              <a class="btn btn-outline-success" href="<?= e($recapUrl) ?>"><i class="bi bi-filetype-csv"></i></a>
              <a class="btn btn-outline-danger" target="_blank" href="<?= e($recapPdfUrl) ?>"><i class="bi bi-file-earmark-pdf"></i></a>
              <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
          </div>
        </div>
      </div>
    </form>

    <div id="tableContainerRecap" class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Nama</th>
            <th class="d-none-mobile">UID</th>
            <th>Kelas</th>
            <th>Status Hadir</th>
            <th class="d-none-mobile">Status Pulang</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (($recap['rows'] ?? []) as $r): ?>
          <?php $statusMasuk = $r['masuk_status']; $badge = $statusMasuk==='Hadir'?'badge-present':($statusMasuk==='Terlambat'?'badge-late':'badge-absent'); ?>
          <tr>
            <td><?= e($r['name']) ?></td>
            <td class="d-none-mobile"><code class="cursor-pointer" data-uid="<?= e($r['uid_hex']) ?>"><?= e($r['uid_hex']) ?></code></td>
            <td><?= e($r['room']) ?></td>
            <td><span class="badge badge-status <?= $badge ?>"><?= e($statusMasuk) ?></span></td>
            <?php
              $p = $r['pulang_status'] ?: '—';
              $pcls = 'badge-belum';
              if ($p === 'Pulang') $pcls = 'badge-pulang';
              elseif ($p === 'Bolos') $pcls = 'badge-bolos';
              elseif ($p === 'Belum Pulang') $pcls = 'badge-belum';
            ?>
            <td class="d-none-mobile">
              <div class="d-flex align-items-center w-100">
                <span class="badge badge-status <?= $p==='—'?'badge-absent':$pcls ?>"><?= e($p) ?></span>
                <?php $recDate = e($recap['date'] ?? $dateDt->format('Y-m-d')); $uidh = e($r['uid_hex']); $nm = e($r['name']); ?>
                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-correct="1" data-date="<?= $recDate ?>" data-uid="<?= $uidh ?>" data-name="<?= $nm ?>">
                  <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- Edit Status Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Koreksi Kehadiran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
                        <form id="editForm" class="row g-3">
              <div class="col-12">
                <div class="text-muted small">Siswa: <strong id="editName"></strong> • UID: <code id="editUid"></code></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" id="editDate" required>
              </div>
              <div class="col-12 mt-2">
                <h6 class="mb-1">Status Masuk</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">Atur Status Masuk</label>
                <select class="form-select" id="editActionIn">
                  <option value="checkin">Tandai Hadir</option>
                  <option value="late">Tandai Terlambat</option>
                  <option value="absent">Tandai Tidak Hadir</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Jam Masuk (HH:MM)</label>
                <input type="time" class="form-control" id="editTimeIn" required>
              </div>
              <div class="col-12 text-end">
                <button type="button" id="saveEditBtnIn" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Status Masuk</button>
              </div>
              <div class="col-12">
                <hr class="my-2">
              </div>
              <div class="col-12 mt-1">
                <h6 class="mb-1">Status Pulang</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">Atur Status Pulang</label>
                <select class="form-select" id="editActionOut">
                  <option value="checkout">Tandai Pulang</option>
                  <option value="bolos">Tandai Bolos</option>
                  <option value="clear_checkout">Tandai Belum Pulang</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Jam Pulang (HH:MM)</label>
                <input type="time" class="form-control" id="editTimeOut">
              </div>
              <div class="col-12 text-end">
                <button type="button" id="saveEditBtnOut" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Status Pulang</button>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>

  <div id="tableContainerLog" class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Nama</th>
          <th class="d-none-mobile">UID</th>
          <th>Kelas</th>
          <th class="d-none-mobile">Perangkat</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['ts']) ?></td>
          <td><?= e($r['user_name'] ?: '—') ?></td>
          <td class="d-none-mobile"><code><?= e($r['uid_hex']) ?></code></td>
          <td><?= e($r['room'] ?: '') ?></td>
          <td class="d-none-mobile"><?= e($r['device_id']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <nav>
    <ul class="pagination">
      <?php for ($p = 1; $p <= max(1, $pages); $p++): ?>
        <?php
          $qs = $_GET; $qs['page'] = $p; $url = '?' . http_build_query($qs);
        ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= e($url) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
  <footer class="site-footer text-center small">
    <?php $addr=env('SCHOOL_ADDRESS',''); $phone=env('SCHOOL_PHONE',''); $email=env('SCHOOL_EMAIL',''); $site=env('SCHOOL_WEBSITE',''); ?>
    <div><strong><?= e(env('SCHOOL_NAME','')) ?></strong><?= ($m=env('SCHOOL_MOTTO',''))? ' • '.e($m):'' ?></div>
    <div><?= e($addr) ?> <?= $phone? ' • Telp: '.e($phone):'' ?> <?= $email? ' • Email: '.e($email):'' ?> <?= $site? ' • '.e($site):'' ?></div>
  </footer>
</div>
<script type="module">
  import { initTheme, initThemeToggle, initContrast, setActiveNav, enableAutoRefresh, bindCopyUid } from './assets/app.js';
  initTheme();
  initThemeToggle();
  initContrast();
  setActiveNav('dashboard');
  enableAutoRefresh(10000);
  bindCopyUid();
  // Auto-apply when selecting Kelas (room)
  const roomSel = document.querySelector('#roomFilterDesktop') || document.querySelector('#roomFilterMobile') || document.querySelector('select[name="room"]');
  const mainForm = document.getElementById('mainFilter');
  // Helper: partial fetch update to avoid full reload
  function updateList(partial) {
    const url = new URL(window.location.href);
    if (partial && typeof partial === 'object') {
      Object.entries(partial).forEach(([k,v]) => {
        if (v === undefined || v === null) return;
        if (v === '') url.searchParams.delete(k); else url.searchParams.set(k, v);
      });
    }
    // reset to first page on filter change + cache buster
    url.searchParams.delete('page');
    url.searchParams.set('t', Date.now());
    return fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
      .then(r => r.text())
      .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newTable = doc.querySelector('#tableContainer');
        const curTable = document.querySelector('#tableContainer');
        if (newTable && curTable) curTable.innerHTML = newTable.innerHTML;
        const newPager = doc.querySelector('nav .pagination');
        const curPager = document.querySelector('nav .pagination');
        if (newPager && curPager) curPager.innerHTML = newPager.innerHTML;
        // rebind small helpers after swap
        try { if (typeof bindCopyUid === 'function') bindCopyUid(); } catch(e){}
        history.replaceState(null, '', url.toString());
      })
      .catch(()=>{});
  }
  if (roomSel) {
    roomSel.addEventListener('change', () => {
      updateList({ room: roomSel.value });
    });
  }
  // Auto-update on date change; search uses Enter or debounce (>=2 chars)
  const fromInp = document.querySelector('input[name="from"]');
  const toInp = document.querySelector('input[name="to"]');
  const searchInp = document.querySelector('input[name="q"]');
  const recapDate = document.getElementById('recapDate');
  if (fromInp) fromInp.addEventListener('change', () => { updateList({ from: fromInp.value }); });
  if (toInp) toInp.addEventListener('change', () => { updateList({ to: toInp.value }); });
  if (recapDate) recapDate.addEventListener('change', () => { updateList({ view: 'recap', date: recapDate.value }); });
  if (searchInp && mainForm) {
    let t = null; let composing = false;
    const go = () => { updateList({ q: searchInp.value }); };
    searchInp.addEventListener('compositionstart', () => { composing = true; });
    searchInp.addEventListener('compositionend', () => { composing = false; /* don't submit immediately */ });
    searchInp.addEventListener('input', () => {
      if (composing) return;
      const val = searchInp.value.trim();
      if (t) clearTimeout(t);
      // Submit only if cleared (empty) or length >= 2, with debounce 400ms
      if (val === '' || val.length >= 2) {
        t = setTimeout(go, 400);
      }
    });
    searchInp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); go(); } });
  }
  // Keep focus naturally (no full page reload). If needed after fetch, re-focus:
  document.addEventListener('DOMContentLoaded', () => {
    const si = document.querySelector('input[name="q"]');
    if (si) si.addEventListener('blur', () => { /* noop */ });
  });
  // Realtime watcher (poll last event id)
  (function(){
    const base = '<?= e($appBase) ?>';
    let lastId = 0;
    async function initLast(){
      try {
        const r = await fetch(base + '/api/last_event.php', { headers:{'Accept':'application/json'} });
        const js = await r.json();
        if (js.ok) lastId = js.last_id || 0;
      } catch(e){}
    }
    async function tick(){
      if (document?.body?.classList?.contains('modal-open')) {
        return;
      }
      try {
        const r = await fetch(base + '/api/last_event.php', { headers:{'Accept':'application/json'} });
        const js = await r.json();
        if (!js.ok) return;
        if (!lastId) { lastId = js.last_id || 0; return; }
        if ((js.last_id || 0) !== lastId) {
          lastId = js.last_id || 0;
          await updateList({});
        }
      } catch(e){}
    }
    initLast();
    setInterval(tick, 2000);
  })();
  // Clickable cards -> open status modal with data
  (function(){
    const modalEl = document.getElementById('statusModal');
    let statusModal = null;
    function ensureModal(){ if (!statusModal && window.bootstrap) statusModal = new bootstrap.Modal(modalEl); }
    async function openStatus(kind, date, room){
      ensureModal();
      const titleMap = { all:'Total Siswa', hadir:'Hadir', terlambat:'Terlambat', tidak_hadir:'Tidak Hadir' };
      const titleEl = document.getElementById('statusModalTitle');
      if (titleEl) titleEl.textContent = 'Rincian ' + (titleMap[kind] || '');
      const tbody = document.getElementById('statusTbody');
      const loading = document.getElementById('statusLoading');
      if (tbody) tbody.innerHTML = '';
      if (loading) loading.style.display = '';
      try {
        const params = new URLSearchParams({ date });
        if (room) params.set('room', room);
        if (kind && kind !== 'all') params.set('sf', kind);
        const url = '<?= e($appBase) ?>/api/recap_list.php?' + params.toString();
        const resp = await fetch(url, { headers: { 'Accept':'application/json' } });
        const js = await resp.json();
        if (loading) loading.style.display = 'none';
        if (!js.ok) { if (tbody) tbody.innerHTML = '<tr><td colspan="5">Gagal memuat data</td></tr>'; }
        else if ((js.rows||[]).length === 0) { if (tbody) tbody.innerHTML = '<tr><td colspan="5">Tidak ada data</td></tr>'; }
        else {
          if (tbody) tbody.innerHTML = js.rows.map(r => {
            const badge = r.masuk_status==='Hadir'?'badge-present':(r.masuk_status==='Terlambat'?'badge-late':'badge-absent');
            const pcls = r.pulang_status==='Pulang'?'badge-pulang':(r.pulang_status==='Bolos'?'badge-bolos':(r.pulang_status==='Belum Pulang'?'badge-belum':'badge-absent'));
            return `<tr><td>${r.name||''}</td><td><code>${r.uid_hex||''}</code></td><td>${r.room||''}</td><td><span class="badge badge-status ${badge}">${r.masuk_status||''}</span></td><td><span class="badge badge-status ${pcls}">${r.pulang_status||'—'}</span></td></tr>`;
          }).join('');
        }
        if (statusModal) statusModal.show();
      } catch (e) {
        if (loading) loading.style.display = 'none';
        if (tbody) tbody.innerHTML = '<tr><td colspan="5">Error jaringan</td></tr>';
        if (statusModal) statusModal.show();
      }
    }
    document.addEventListener('click', (e) => {
      const a = e.target.closest('[data-open-status]');
      if (!a) return;
      e.preventDefault();
      const kind = a.getAttribute('data-open-status');
      const date = a.getAttribute('data-date') || '<?= e($dateDt->format('Y-m-d')) ?>';
      const room = a.getAttribute('data-room') || '<?= e($room) ?>';
      openStatus(kind, date, room);
    });
  })();
  // Inline edit status buttons (checkin/checkout)
  // Modal edit handling
  const modalEl = document.getElementById('editModal');
  let editModal = null;
  function ensureModal(){ if (!editModal && window.bootstrap) editModal = new bootstrap.Modal(modalEl); }
  const startDefault = '<?= e(env('SCHOOL_START','07:15')) ?>';
  const endDefault = '<?= e(env('SCHOOL_END','15:00')) ?>';
  const actionInEl = document.getElementById('editActionIn');
  const timeInEl = document.getElementById('editTimeIn');
  const actionOutEl = document.getElementById('editActionOut');
  const timeOutEl = document.getElementById('editTimeOut');
  const saveEditBtnIn = document.getElementById('saveEditBtnIn');
  const saveEditBtnOut = document.getElementById('saveEditBtnOut');

  function applyInState(action) {
    if (!timeInEl) return;
    if (action === 'checkin' || action === 'late') {
      timeInEl.disabled = false;
      if (!timeInEl.value) timeInEl.value = startDefault;
      timeInEl.placeholder = '';
    } else {
      timeInEl.disabled = true;
      timeInEl.value = '';
      timeInEl.placeholder = 'Opsional';
    }
  }

  function applyOutState(action) {
    if (!timeOutEl) return;
    if (action === 'checkout') {
      timeOutEl.disabled = false;
      if (!timeOutEl.value) timeOutEl.value = endDefault;
      timeOutEl.placeholder = '';
    } else if (action === 'bolos') {
      timeOutEl.disabled = false;
      if (!timeOutEl.value) timeOutEl.value = endDefault;
      timeOutEl.placeholder = 'Opsional (default: jam sekolah selesai)';
    } else if (action === 'clear_checkout') {
      timeOutEl.disabled = true;
      timeOutEl.value = '';
      timeOutEl.placeholder = 'Tidak perlu jam untuk Belum Pulang';
    } else {
      timeOutEl.disabled = true;
      timeOutEl.value = '';
      timeOutEl.placeholder = 'Opsional';
    }
  }

  function hydrateManualRow(row) {
    if (!row) return;
    if (actionInEl) {
      const statusIn = (row.masuk_status || '').toLowerCase();
      let inAction = 'checkin';
      if (statusIn === 'terlambat') inAction = 'late';
      else if (statusIn === 'tidak hadir') inAction = 'absent';
      actionInEl.value = inAction;
      applyInState(inAction);
      if (timeInEl && (inAction === 'checkin' || inAction === 'late') && row.first_time) {
        timeInEl.value = row.first_time;
      }
    }
    if (actionOutEl) {
      const statusOut = (row.pulang_status || '').toLowerCase();
      let outAction = 'checkout';
      if (statusOut === 'bolos') outAction = 'bolos';
      else if (statusOut === 'pulang') outAction = 'checkout';
      else outAction = 'clear_checkout';
      actionOutEl.value = outAction;
      applyOutState(outAction);
      if (timeOutEl && outAction === 'checkout' && row.last_time) {
        timeOutEl.value = row.last_time;
      }
    }
  }

  if (actionInEl && !actionInEl.value) actionInEl.value = 'checkin';
  if (actionOutEl && !actionOutEl.value) actionOutEl.value = 'checkout';
  applyInState(actionInEl ? (actionInEl.value || 'checkin') : 'checkin');
  applyOutState(actionOutEl ? (actionOutEl.value || 'checkout') : 'checkout');

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-correct]');
    if (!btn) return;
    ensureModal();
    const uid = btn.getAttribute('data-uid');
    const name = btn.getAttribute('data-name') || '';
    const dateAttr = (btn.getAttribute('data-date') || '').trim();
    const dateInput = document.getElementById('editDate');
    const recapDesktop = document.getElementById('recapDate');
    const recapMobile = document.getElementById('recapDateMobile');
    let date = dateAttr || (dateInput ? dateInput.value.trim() : '');
    if (!date) date = (recapDesktop ? recapDesktop.value : '') || (recapMobile ? recapMobile.value : '');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) date = new Date().toISOString().slice(0,10);
    document.getElementById('editUid').textContent = uid;
    document.getElementById('editName').textContent = name;
    if (dateInput) dateInput.value = date;
    if (actionInEl) actionInEl.value = 'checkin';
    applyInState('checkin');
    if (actionOutEl) actionOutEl.value = 'checkout';
    applyOutState('checkout');
    if (editModal) editModal.show();
    (async () => {
      try {
        const detailUrl = new URL(base + '/api/recap_row.php', window.location.origin);
        detailUrl.searchParams.set('uid', uid);
        detailUrl.searchParams.set('date', date);
        const detailResp = await fetch(detailUrl.toString(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        const detailJs = await detailResp.json();
        if (detailJs && detailJs.ok && detailJs.row) {
          hydrateManualRow(detailJs.row);
        }
      } catch (err) {
        /* ignore load errors */
      }
    })();
  });


  if (actionInEl) actionInEl.addEventListener('change', () => applyInState(actionInEl.value));
  if (actionOutEl) actionOutEl.addEventListener('change', () => applyOutState(actionOutEl.value));

  async function afterManualUpdate() {
    await updateList({});
    try {
      const uid_ = document.getElementById('editUid').textContent.trim();
      const date_ = (document.getElementById('editDate').value||'').trim();
      if (!uid_ || !date_) return;
      const appBase_ = '<?= e($appBase) ?>';
      const u_ = new URL(appBase_ + '/api/recap_row.php', window.location.origin);
      u_.searchParams.set('uid', uid_);
      u_.searchParams.set('date', date_);
      const r_ = await fetch(u_.toString(), { headers: { 'Accept':'application/json' }, cache: 'no-store' });
      const js_ = await r_.json();
      if (js_ && js_.ok && js_.row) {
        hydrateManualRow(js_.row);
        const root = document;
        const codeEl = root.querySelector('code[data-uid="' + uid_ + '"]');
        if (codeEl) {
          const tr = codeEl.closest('tr');
          const masukCell = tr ? tr.querySelector('td:nth-child(4) .badge') : null;
          const pulangCell = tr ? tr.querySelector('td:nth-child(5) .badge') : null;
          if (masukCell) {
            const ms = js_.row.masuk_status || '';
            masukCell.textContent = ms;
            masukCell.classList.remove('badge-present','badge-late','badge-absent');
            masukCell.classList.add(ms==='Hadir'?'badge-present':(ms==='Terlambat'?'badge-late':'badge-absent'));
          }
          if (pulangCell) {
            const ps = js_.row.pulang_status || '—';
            pulangCell.textContent = ps;
            pulangCell.classList.remove('badge-pulang','badge-bolos','badge-belum','badge-absent');
            pulangCell.classList.add(ps==='Pulang'?'badge-pulang':(ps==='Bolos'?'badge-bolos':(ps==='Belum Pulang'?'badge-belum':'badge-absent')));
          }
          if (tr && tr.animate) tr.animate([{backgroundColor:'#fff3cd'},{backgroundColor:'transparent'}], {duration:800, easing:'ease'});
        }
      }
    } catch (e) {}
    applyInState(actionInEl ? (actionInEl.value || 'checkin') : 'checkin');
    applyOutState(actionOutEl ? (actionOutEl.value || 'checkout') : 'checkout');
  }

  async function submitManual(action, timeValue, buttonEl) {
    const uid = document.getElementById('editUid').textContent.trim();
    const dateInput = document.getElementById('editDate');
    const recapDesktop = document.getElementById('recapDate');
    const recapMobile = document.getElementById('recapDateMobile');
    let date = (dateInput?.value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      date = ((recapDesktop?.value || recapMobile?.value) || '').trim();
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      date = new Date().toISOString().slice(0,10);
    }
    if (dateInput) dateInput.value = date;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { alert('Tanggal tidak valid'); return; }
    let sendTime = timeValue || '';
    if (action === 'checkin' || action === 'late' || action === 'checkout') {
      if (!/^\d{2}:\d{2}$/.test(sendTime)) { alert('Jam tidak valid'); return; }
    } else {
      sendTime = /^\d{2}:\d{2}$/.test(sendTime) ? sendTime : '00:00';
    }
    const originalLabel = buttonEl ? buttonEl.innerHTML : '';
    if (buttonEl) {
      buttonEl.disabled = true;
      buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses';
    }
    try {
      const resp = await fetch('<?= e($appBase) ?>/api/set_event.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        cache:'no-store',
        body: JSON.stringify({ uid_hex: uid, date, time: sendTime, action })
      });
      const js = await resp.json();
      if (!js.ok) { alert('Gagal menyimpan: ' + (js.error||'')); return; }
      await afterManualUpdate();
    } catch (err) {
      alert('Error jaringan');
    } finally {
      if (buttonEl) {
        buttonEl.disabled = false;
        buttonEl.innerHTML = originalLabel || '<i class="bi bi-save me-1"></i>Simpan';
      }
    }
  }

  if (saveEditBtnIn) {
    saveEditBtnIn.addEventListener('click', async () => {
      const action = actionInEl ? actionInEl.value : 'checkin';
      await submitManual(action, timeInEl ? timeInEl.value : '', saveEditBtnIn);
    });
  }
  if (saveEditBtnOut) {
    saveEditBtnOut.addEventListener('click', async () => {
      const action = actionOutEl ? actionOutEl.value : 'checkout';
      await submitManual(action, timeOutEl ? timeOutEl.value : '', saveEditBtnOut);
    });
  }
  // Sync mobile auto-refresh with desktop
  const autoRefreshDesktop = document.getElementById('autoRefresh');
  const autoRefreshMobile = document.getElementById('autoRefreshMobile');
  
  if (autoRefreshDesktop && autoRefreshMobile) {
    autoRefreshDesktop.addEventListener('change', () => {
      autoRefreshMobile.checked = autoRefreshDesktop.checked;
    });
    
    autoRefreshMobile.addEventListener('change', () => {
      autoRefreshDesktop.checked = autoRefreshMobile.checked;
    });
  }
  
  // Sync recap date between desktop and mobile
  const recapDateDesktop = document.getElementById('recapDate');
  const recapDateMobile = document.getElementById('recapDateMobile');
  
  if (recapDateDesktop && recapDateMobile) {
    recapDateDesktop.addEventListener('change', () => {
      recapDateMobile.value = recapDateDesktop.value;
    });
    
    recapDateMobile.addEventListener('change', () => {
      recapDateDesktop.value = recapDateMobile.value;
    });
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
  

