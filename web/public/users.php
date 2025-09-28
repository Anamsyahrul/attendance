<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();
$pdo = pdo();

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $uid  = strtolower(trim($_POST['uid_hex'] ?? ''));
    $room = trim($_POST['room'] ?? '');
    $uid  = preg_replace('/[^0-9a-f]/i', '', $uid);
    if ($name && $uid) {
        $stmt = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');
        try { $stmt->execute([$name, $uid, $room]); } catch (Throwable $e) {}
    }
    header('Location: users.php');
    exit;
}

// Delete user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: users.php');
    exit;
}

$usersSql = 'SELECT id, name, uid_hex, room FROM users';
$params = [];
$q = trim($_GET['q'] ?? '');
$roomFilter = trim($_GET['room'] ?? '');
if ($q !== '' || $roomFilter !== '') {
    $usersSql .= ' WHERE 1=1';
    if ($q !== '') { $usersSql .= ' AND (name LIKE ? OR uid_hex LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($roomFilter !== '') { $usersSql .= ' AND room = ?'; $params[] = $roomFilter; }
}
$usersSql .= ' ORDER BY name';
$stmtU = $pdo->prepare($usersSql); $stmtU->execute($params);
$users = $stmtU->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Siswa</title>
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
  <h3 class="mb-3">Siswa</h3>

  <div class="card mb-3">
    <div class="card-header">Import Siswa dari CSV</div>
    <div class="card-body">
      <form method="post" action="../api/import_students.php" enctype="multipart/form-data" class="d-flex align-items-end gap-2 flex-nowrap">
        <div>
          <label class="form-label">File CSV (name,uid_hex,room)</label>
          <input class="form-control" type="file" name="file" accept=".csv" required style="min-width:280px;">
        </div>
        <div class="nowrap">
          <button class="btn btn-outline-primary" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
        </div>
      </form>
      <div class="text-muted small mt-2">Format: baris per siswa. Contoh: <code>Ali,04a1b2c3d4,12A</code></div>
    </div>
  </div>

  <form method="get" class="d-flex align-items-end gap-2 flex-nowrap mb-3">
    <?php $roomsL = $pdo->query("SELECT DISTINCT room FROM users WHERE room<>'' ORDER BY room")->fetchAll(PDO::FETCH_COLUMN);
      $q = trim($_GET['q'] ?? ''); $roomFilter = trim($_GET['room'] ?? ''); ?>
    <input class="form-control" name="q" placeholder="Cari nama/UID" value="<?= e($q) ?>" style="min-width:220px;">
    <select class="form-select" name="room" style="min-width:160px;">
      <option value="">Semua Kelas</option>
      <?php foreach ($roomsL as $r): ?>
        <option value="<?= e($r) ?>" <?= $roomFilter===$r?'selected':'' ?>><?= e($r) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="btn-group nowrap" role="group">
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
      <a class="btn btn-outline-secondary" href="./users.php">Reset</a>
    </div>
  </form>

  <form method="post" class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Nama</label>
      <input class="form-control" name="name" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">UID (hex)</label>
      <input class="form-control" name="uid_hex" required placeholder="e.g. 1234abcd">
    </div>
    <div class="col-md-3">
      <label class="form-label">Kelas</label>
      <input class="form-control" list="classList" name="room" placeholder="mis. 12A">
      <?php $roomsL = $pdo->query("SELECT DISTINCT room FROM users WHERE room<>'' ORDER BY room")->fetchAll(PDO::FETCH_COLUMN); ?>
      <datalist id="classList">
        <?php foreach ($roomsL as $r): ?>
          <option value="<?= e($r) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <button class="btn btn-primary w-100" type="submit">Tambah</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nama</th>
          <th>UID</th>
          <th>Kelas</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><a href="./student.php?id=<?= (int)$u['id'] ?>"><?= e($u['name']) ?></a></td>
          <td><code><?= e($u['uid_hex']) ?></code></td>
          <td><?= e($u['room']) ?></td>
          <td>
            <a class="btn btn-sm btn-outline-danger" href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Hapus siswa ini?')">Hapus</a>
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
  setActiveNav('users');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
