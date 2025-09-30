<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();

$msg = '';$err='';

// Handle success message dari redirect
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = trim($_POST['SCHOOL_START'] ?? '');
    $end   = trim($_POST['SCHOOL_END'] ?? '');
    $name  = trim($_POST['SCHOOL_NAME'] ?? '');
    $addr  = trim($_POST['SCHOOL_ADDRESS'] ?? '');
    $phone = trim($_POST['SCHOOL_PHONE'] ?? '');
    $email = trim($_POST['SCHOOL_EMAIL'] ?? '');
    $site  = trim($_POST['SCHOOL_WEBSITE'] ?? '');
    $motto = trim($_POST['SCHOOL_MOTTO'] ?? '');
    $reqCo = isset($_POST['REQUIRE_CHECKOUT']);
    $hol   = trim($_POST['HOLIDAYS'] ?? '');
    $adminUser = trim($_POST['ADMIN_USER'] ?? '');
    $adminPass = (string)($_POST['ADMIN_PASS'] ?? '');
    $adminPassConfirm = (string)($_POST['ADMIN_PASS_CONFIRM'] ?? '');
    $weeklyOffInput = $_POST['WEEKLY_OFF'] ?? [];
    $regMode = isset($_POST['REGISTRATION_MODE']);

    $weeklyOffDays = [];
    if (is_array($weeklyOffInput)) {
        foreach ($weeklyOffInput as $val) {
            $day = (int)$val;
            if ($day >= 1 && $day <= 7) {
                $weeklyOffDays[$day] = $day;
            }
        }
    }
    ksort($weeklyOffDays);
    $weeklyOffDays = array_values($weeklyOffDays);
    $weeklyOffStr = implode(',', $weeklyOffDays);

    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
        $err = 'Format jam harus HH:MM (contoh 07:15).';
    } elseif ($adminUser === '') {
        $err = 'Username admin tidak boleh kosong.';
    } elseif ($adminPass !== '' && strlen($adminPass) < 6) {
        $err = 'Password admin minimal 6 karakter.';
    } elseif ($adminPass !== '' && $adminPass !== $adminPassConfirm) {
        $err = 'Konfirmasi password baru tidak sama.';
    } else {
        $payload = [
            'SCHOOL_START'=>$start,
            'SCHOOL_END'=>$end,
            'SCHOOL_NAME'=>$name,
            'REQUIRE_CHECKOUT'=>$reqCo,
            'HOLIDAYS'=>$hol,
            'SCHOOL_ADDRESS'=>$addr,
            'SCHOOL_PHONE'=>$phone,
            'SCHOOL_EMAIL'=>$email,
            'SCHOOL_WEBSITE'=>$site,
            'SCHOOL_MOTTO'=>$motto,
            'ADMIN_USER'=>$adminUser,
            'WEEKLY_OFF_DAYS'=>$weeklyOffStr,
            'SCHOOL_SKIP_WEEKENDS'=> in_array(6, $weeklyOffDays, true) && in_array(7, $weeklyOffDays, true),
            'REGISTRATION_MODE'=>$regMode,
        ];
        if ($adminPass !== '') {
            $payload['ADMIN_PASS'] = $adminPass;
        }
        if (simpan_konfigurasi($payload)) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['auth_username'] = $adminUser;
            }
            $msg = 'Pengaturan tersimpan.' . ($adminPass !== '' ? ' Password admin diperbarui.' : '');
            
            // Redirect dengan pesan sukses untuk refresh otomatis
            header('Location: settings.php?success=1&msg=' . urlencode($msg));
            exit;
        } else {
            $err = 'Gagal menyimpan .env.php. Cek izin file.';
        }
    }
}

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$school = env('SCHOOL_NAME','Attendance');
$start = env('SCHOOL_START','07:15');
$end   = env('SCHOOL_END','15:00');
$addr = env('SCHOOL_ADDRESS','');
$phone = env('SCHOOL_PHONE','');
$email = env('SCHOOL_EMAIL','');
$site = env('SCHOOL_WEBSITE','');
$motto = env('SCHOOL_MOTTO','');
$weeklyOffRaw = trim((string) env('WEEKLY_OFF_DAYS',''));
$weeklyOffCurrent = [];
if ($weeklyOffRaw !== '') {
    foreach (explode(',', $weeklyOffRaw) as $part) {
        $n = (int) trim($part);
        if ($n >= 1 && $n <= 7) {
            $weeklyOffCurrent[$n] = $n;
        }
    }
    ksort($weeklyOffCurrent);
    $weeklyOffCurrent = array_values($weeklyOffCurrent);
} elseif ((bool) env('SCHOOL_SKIP_WEEKENDS', false)) {
    $weeklyOffCurrent = [6,7];
}
$adminUserCurrent = env('ADMIN_USER','admin');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($adminUser) && $adminUser !== '') {
    $adminUserCurrent = $adminUser;
}
if (isset($weeklyOffDays)) {
    $weeklyOffCurrent = $weeklyOffDays;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pengaturan</title>
  <script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="./assets/style.css" rel="stylesheet">
  <style>
    .alert {
      transition: opacity 0.5s ease-out;
    }
    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
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
        <li class="nav-item"><a class="nav-link active" data-nav="settings" href="./settings.php"><i class="bi bi-gear me-1"></i><span class="btn-text">Pengaturan</span></a></li>

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

<?php $appBase = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/'); ?>

<div class="container">
  <h3 class="mb-3">Pengaturan</h3>
  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">Jam Hadir & Pulang</div>
    <div class="card-body">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Jam Hadir (HH:MM)</label>
            <input class="form-control" name="SCHOOL_START" value="<?= e($start) ?>" placeholder="07:15" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Jam Pulang (HH:MM)</label>
            <input class="form-control" name="SCHOOL_END" value="<?= e($end) ?>" placeholder="15:00" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Sekolah</label>
            <input class="form-control" name="SCHOOL_NAME" value="<?= e($school) ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Alamat Sekolah</label>
            <input class="form-control" name="SCHOOL_ADDRESS" value="<?= e($addr) ?>" placeholder="Jl. ...">
          </div>
          <div class="col-md-4">
            <label class="form-label">Telepon</label>
            <input class="form-control" name="SCHOOL_PHONE" value="<?= e($phone) ?>" placeholder="(0xx)xxxxxxx">
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="SCHOOL_EMAIL" value="<?= e($email) ?>" placeholder="admin@sekolah.sch.id">
          </div>
          <div class="col-md-4">
            <label class="form-label">Website</label>
            <input class="form-control" type="url" name="SCHOOL_WEBSITE" value="<?= e($site) ?>" placeholder="https://...">
          </div>
          <div class="col-md-12">
            <label class="form-label">Motto/Slogan</label>
            <input class="form-control" name="SCHOOL_MOTTO" value="<?= e($motto) ?>" placeholder="Unggul, Berkarakter, ...">
          </div>
          <div class="col-md-4">
            <label class="form-label">Username Admin</label>
            <input class="form-control" name="ADMIN_USER" value="<?= e($adminUserCurrent) ?>" placeholder="admin" required>
            <div class="form-text">Akun ini digunakan untuk login dashboard.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Password Admin (baru)</label>
            <div class="input-group">
              <input class="form-control" type="password" name="ADMIN_PASS" id="adminPassInput" placeholder="Biarkan kosong jika tidak diganti" autocomplete="new-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-target="adminPassInput"><i class="bi bi-eye"></i></button>
            </div>
            <div class="form-text">Minimal 6 karakter. Kosongkan untuk mempertahankan password lama.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Konfirmasi Password Baru</label>
            <div class="input-group">
              <input class="form-control" type="password" name="ADMIN_PASS_CONFIRM" id="adminPassConfirmInput" placeholder="Ulangi password baru" autocomplete="new-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-target="adminPassConfirmInput"><i class="bi bi-eye"></i></button>
            </div>
            <div class="form-text">Harus sama dengan password baru.</div>
          </div>
          <div class="col-md-12">
            <div class="form-check form-switch">
              <?php $req = env('REQUIRE_CHECKOUT', false); ?>
              <input class="form-check-input" type="checkbox" id="reqco" name="REQUIRE_CHECKOUT" <?= $req?'checked':'' ?>>
              <label class="form-check-label" for="reqco">Wajib scan pulang (tanpa scan pulang setelah jam pulang = Bolos)</label>
            </div>
          </div>
          <div class="col-md-12">
            <label class="form-label">Hari Libur Mingguan</label>
            <div class="d-flex flex-wrap gap-3">
              <?php $offLabels = [5=>'Jumat',6=>'Sabtu',7=>'Minggu']; ?>
              <?php foreach ($offLabels as $dayVal => $dayLabel): $checked = in_array($dayVal, $weeklyOffCurrent, true); ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="off<?= $dayVal ?>" name="WEEKLY_OFF[]" value="<?= $dayVal ?>" <?= $checked ? 'checked' : '' ?>>
                  <label class="form-check-label" for="off<?= $dayVal ?>"><?= $dayLabel ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Gunakan pilihan ini untuk menandai hari yang selalu libur (mis. Jumat saja, Sabtu-Minggu, dll). Anda tetap dapat menambah tanggal libur khusus di bawah.</div>
          </div>
          <div class="col-md-12">
            <div class="form-check form-switch">
              <?php $regm = env('REGISTRATION_MODE', false); ?>
              <input class="form-check-input" type="checkbox" id="regmode" name="REGISTRATION_MODE" <?= $regm?'checked':'' ?>>
              <label class="form-check-label" for="regmode">Mode Pendaftaran (izinkan kartu unknown dengan feedback sukses di perangkat)</label>
            </div>
          </div>
          <div class="col-md-12">
            <label class="form-label">Tanggal Libur (YYYY-MM-DD pisah koma)</label>
            <?php $hol = env('HOLIDAYS',''); ?>
            <textarea class="form-control" name="HOLIDAYS" rows="2" placeholder="2025-06-01,2025-06-17"><?= e($hol) ?></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit" id="saveBtn"><i class="bi bi-save me-1"></i>Simpan</button>
          </div>
        </div>
      </form>
      <div class="mt-3 text-muted small">Catatan: perubahan tersimpan ke file <code>web/.env.php</code>.</div>
    </div>
  </div>


  <div class="card mb-4">
    <div class="card-header">Panduan Instalasi Lengkap</div>
    <div class="card-body">
      <p class="mb-3">Pilih panduan yang sesuai dengan tingkat pengalaman Anda:</p>

      <div class="alert alert-info">
        <strong>🆕 Panduan Pemula:</strong> Jika Anda baru pertama kali menggunakan sistem ini, mulai dari sini!
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <a class="btn btn-success" target="_blank" href="<?= e($appBase) ?>/PANDUAN_INSTALASI_PEMULA.md"><i class="bi bi-star me-1"></i>Panduan Pemula (Rekomendasi)</a>
        <a class="btn btn-outline-danger" href="<?= e($appBase) ?>/api/install_guide.php"><i class="bi bi-file-earmark-pdf me-1"></i>Unduh PDF Lengkap</a>
        <a class="btn btn-outline-primary" target="_blank" href="<?= e($appBase) ?>/PANDUAN_LENGKAP_SISTEM_KEHADIRAN_RFID.html"><i class="bi bi-file-earmark-text me-1"></i>Lihat HTML Lengkap</a>
      </div>

      <div class="row">
        <div class="col-md-6">
          <h6>📖 Panduan Pemula</h6>
          <ul class="small">
            <li>Langkah sederhana untuk pemula</li>
            <li>Checklist yang mudah diikuti</li>
            <li>Troubleshooting dasar</li>
            <li>Cocok untuk instalasi pertama kali</li>
          </ul>
        </div>
        <div class="col-md-6">
          <h6>📋 Panduan Lengkap</h6>
          <ul class="small">
            <li>Penjelasan detail semua fitur</li>
            <li>Konfigurasi advanced</li>
            <li>Troubleshooting mendalam</li>
            <li>Untuk pengguna mahir</li>
          </ul>
        </div>
      </div>

      <span class="text-muted small">Tip: simpan panduan ini sebagai acuan dan bagikan ke operator piket.</span>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Penjelasan Istilah</div>
    <div class="card-body">
      <ul class="mb-3">
        <li><strong>Dashboard</strong>: tampilan utama berisi ringkasan dan tabel kehadiran.</li>
        <li><strong>Rekap Sekolah</strong>: daftar siswa per tanggal/kelas dengan dua kolom inti: <em>Status Hadir</em> dan <em>Status Pulang</em>.</li>
        <li><strong>Log Mentah</strong>: daftar semua pemindaian kartu apa adanya (setiap scan) — untuk penelusuran/diagnostik.</li>
        <li><strong>Status Hadir</strong>: <em>Hadir</em> (tepat waktu) atau <em>Terlambat</em> (scan pertama setelah <em>Jam Hadir</em>); <em>Tidak Hadir</em> bila tidak ada scan hari itu.</li>
        <li><strong>Status Pulang</strong>: <em>Pulang</em> (ada scan pulang), <em>Belum Pulang</em> (hari berjalan belum scan), atau <em>Bolos</em> (hingga hari berikutnya belum ada scan pulang).</li>
      </ul>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small muted me-2">Legenda:</span>
        <span class="badge badge-status badge-present">Hadir</span>
        <span class="badge badge-status badge-late">Terlambat</span>
        <span class="badge badge-status badge-absent">Tidak Hadir</span>
        <span class="badge badge-status badge-pulang">Pulang</span>
        <span class="badge badge-status badge-belum">Belum Pulang</span>
        <span class="badge badge-status badge-bolos">Bolos</span>
      </div>
    </div>
  </div>

</div>
<script type="module">
  import { initTheme, initThemeToggle, initContrast, setActiveNav } from './assets/app.js';
  initTheme(); initThemeToggle();
  initContrast();
  setActiveNav('settings');

  document.querySelectorAll('[data-toggle-target]').forEach(btn => {
    const targetId = btn.getAttribute('data-toggle-target');
    const input = document.getElementById(targetId);
    if (!input) return;
    btn.addEventListener('click', () => {
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      const icon = btn.querySelector('i');
      if (icon) icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
      input.focus();
    });
  });

  // Auto-hide success message setelah 5 detik
  const successAlert = document.querySelector('.alert-success');
  if (successAlert) {
    setTimeout(() => {
      successAlert.style.transition = 'opacity 0.5s ease-out';
      successAlert.style.opacity = '0';
      setTimeout(() => {
        successAlert.remove();
      }, 500);
    }, 5000);
  }

  // Scroll ke atas saat ada success message
  if (successAlert) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Loading state untuk submit button
  const form = document.querySelector('form');
  const saveBtn = document.getElementById('saveBtn');
  
  if (form && saveBtn) {
    form.addEventListener('submit', function() {
      // Disable button dan tampilkan loading
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
      
      // Tambahkan spinner
      saveBtn.classList.add('spinner-border', 'spinner-border-sm');
    });
  }

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

