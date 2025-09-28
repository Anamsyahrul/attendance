<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();
$pdo = pdo();

function e($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $uid  = strtolower(trim($_POST['uid_hex'] ?? ''));
    $room = trim($_POST['room'] ?? '');
    $backfill = isset($_POST['backfill']);
    $uid = preg_replace('/[^0-9a-f]/i', '', $uid);
    if ($name === '' || $uid === '') {
        $err = 'Nama dan UID wajib diisi.';
    } else {
        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
            $chk->execute([$uid]);
            $u = $chk->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $upd = $pdo->prepare('UPDATE users SET name = ?, room = ? WHERE id = ?');
                $upd->execute([$name, $room, (int)$u['id']]);
                $userId = (int)$u['id'];
                $msg = 'UID sudah terdaftar. Data diperbarui.';
            } else {
                $ins = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');
                $ins->execute([$name, $uid, $room]);
                $userId = (int)$pdo->lastInsertId();
                $msg = 'Siswa berhasil didaftarkan.';
            }
            if ($backfill) {
                $bf = $pdo->prepare('UPDATE attendance a SET a.user_id = ? WHERE a.uid_hex = ? AND (a.user_id IS NULL OR a.user_id <> ?)');
                $bf->execute([$userId, $uid, $userId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$prefillUid = strtolower(trim($_GET['uid'] ?? ''));
$prefillUid = preg_replace('/[^0-9a-f]/i', '', $prefillUid);

// Ambil daftar UID yang belum terdaftar (unknown)
$unknown = $pdo->query('SELECT a.uid_hex, COUNT(*) AS cnt, MAX(a.ts) AS last_ts
                        FROM attendance a
                        LEFT JOIN users u ON u.uid_hex = a.uid_hex
                        WHERE u.id IS NULL
                        GROUP BY a.uid_hex
                        ORDER BY last_ts DESC
                        LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Kartu</title>
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
        <li class="nav-item"><a class="nav-link active" data-nav="register" href="./register.php"><i class="bi bi-person-plus me-1"></i><span class="btn-text">Daftar Kartu</span></a></li>
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
  <h3 class="mb-3">Daftarkan Kartu MIFARE (UID → Siswa)</h3>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Form Pendaftaran</div>
        <div class="card-body">
          <form method="post">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="autoFillUid" checked>
              <label class="form-check-label" for="autoFillUid">Isi otomatis UID dari hasil scan</label>
            </div>
            <div class="mb-3">
              <label class="form-label">UID (hex)</label>
              <input class="form-control" id="uidInput" name="uid_hex" value="<?= e($prefillUid) ?>" placeholder="mis. 04a1b2c3d4" required>
              <div class="form-text">Saat aktif, kolom UID akan terisi otomatis dari scan terbaru.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Nama</label>
              <input class="form-control" name="name" placeholder="Nama siswa" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Room/Kelas</label>
              <input class="form-control" name="room" placeholder="mis. 12A">
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="backfill" id="bf" checked>
              <label class="form-check-label" for="bf">Hubungkan log yang sudah ada (backfill)</label>
            </div>
            <button class="btn btn-primary" type="submit">Simpan</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">UID Belum Terdaftar (scan terbaru)</div>
        <div class="card-body">
          <?php if (!$unknown): ?>
            <div class="text-muted">Belum ada UID tidak dikenal. Silakan scan kartu lalu refresh halaman.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr><th>UID</th><th>Terakhir Scan</th><th>Jumlah</th><th></th></tr>
                </thead>
                <tbody id="unknownTbody">
                <?php foreach ($unknown as $u): ?>
                  <tr>
                    <td><code><?= e($u['uid_hex']) ?></code></td>
                    <td><?= e($u['last_ts']) ?></td>
                    <td><?= (int)$u['cnt'] ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="?uid=<?= e($u['uid_hex']) ?>">Pakai UID</a></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          <div class="mt-2">
            <a class="btn btn-sm btn-outline-secondary" href="./index.php">Buka Log Mentah</a>
            <a class="btn btn-sm btn-outline-secondary" href="./register.php">Muat Ulang</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="alert alert-info">Mode pendaftaran perangkat otomatis aktif saat halaman ini terbuka.</div>
<?php $appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); ?>
</div>
<script type="module">
  import { initTheme, initThemeToggle, initContrast, setActiveNav, pollUnknownUids } from './assets/app.js';
  initTheme(); initThemeToggle();
  initContrast();
  setActiveNav('register');
  const apiUnknown = '<?= e($appBase) ?>/api/unknown_uids.php';
  const uidInput = document.querySelector('#uidInput');
  const autoChk = document.querySelector('#autoFillUid');
  const toggleReadonly = () => { if (autoChk?.checked) { uidInput.readOnly = true; } else { uidInput.readOnly = false; uidInput.dataset.autofilled = ''; } };
  autoChk?.addEventListener('change', toggleReadonly);
  toggleReadonly();
  pollUnknownUids(apiUnknown, '#unknownTbody', '#uidInput', { intervalMs: 3000, autoFillCheckbox: '#autoFillUid', focusSelector: 'input[name=name]' });
  // Heartbeat: keep device in registration mode while this page is open
  const hb = async ()=>{ try{ await fetch('<?= e($appBase) ?>/api/registration_mode.php?action=on&ttl=60'); }catch(e){} };
  hb(); const t = setInterval(hb, 25000);
  window.addEventListener('beforeunload', ()=>{ try{ navigator.sendBeacon('<?= e($appBase) ?>/api/registration_mode.php?action=off'); }catch(e){} clearInterval(t); });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
