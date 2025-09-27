<?php
require_once __DIR__ . '/../bootstrap.php';

function sanitize_redirect(string $target): string {
    $target = trim($target);
    if ($target === '') {
        return 'index.php';
    }
    if (str_contains($target, "\n") || str_contains($target, "\r")) {
        return 'index.php';
    }
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $target)) {
        return 'index.php';
    }
    return $target;
}

$redirect = sanitize_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php');
$message = '';
$error = '';

if (isset($_GET['logged_out'])) {
    $message = 'Anda sudah keluar. Silakan login kembali.';
}

if (is_logged_in()) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $redirect = sanitize_redirect($_POST['redirect'] ?? 'index.php');
    if ($username === '' || $password === '') {
        $error = 'Mohon isi username dan password.';
    } elseif (attempt_login($username, $password)) {
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Username atau password tidak sesuai.';
    }
}

$school = env('SCHOOL_NAME', 'Attendance');
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk • <?= htmlspecialchars($school, ENT_QUOTES, 'UTF-8') ?></title>
  <script>try{var t=localStorage.getItem('theme');if(t){document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-contrast',t==='dark'?'high':'normal');}else{document.documentElement.setAttribute('data-contrast','normal');}}catch(e){}</script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="./assets/style.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); }
    .login-card { max-width: 420px; width: 100%; }
    .login-title { color: var(--brand); }
  </style>
</head>
<body>
  <div class="card shadow-sm login-card">
    <div class="card-body p-4">
      <div class="d-flex justify-content-end">
        <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button"><i class="bi bi-moon-stars me-1"></i><span>Gelap</span></button>
      </div>
      <div class="text-center mb-4">
        <h5 class="mb-0 login-title fw-semibold">Sistem Kehadiran</h5>
        <div class="text-muted"><?= htmlspecialchars($school, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <?php if ($message): ?>
        <div class="alert alert-info" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input type="text" class="form-control" id="username" name="username" placeholder="admin" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</button>
        </div>
      </form>
    </div>
  </div>

<script>
  (function(){
    const root = document.documentElement;
    const btn = document.getElementById('themeToggle');
    const ico = btn ? btn.querySelector('i') : null;
    const txt = btn ? btn.querySelector('span') : null;
    const applyTheme = (theme) => {
      root.setAttribute('data-theme', theme);
      const contrast = theme === 'dark' ? 'high' : 'normal';
      root.setAttribute('data-contrast', contrast);
      try { localStorage.setItem('theme', theme); localStorage.setItem('contrast', contrast); } catch (e) {}
      if (ico && txt) {
        if (theme === 'dark') { ico.className = 'bi bi-sun me-1'; txt.textContent = 'Terang'; }
        else { ico.className = 'bi bi-moon-stars me-1'; txt.textContent = 'Gelap'; }
      }
    };
    let theme = root.getAttribute('data-theme') || 'light';
    try { const saved = localStorage.getItem('theme'); if (saved) theme = saved; } catch (e) {}
    applyTheme(theme);
    if (btn) {
      btn.addEventListener('click', () => {
        const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
      });
    }
    const pwdBtn = document.getElementById('togglePwd');
    const pwd = document.getElementById('password');
    if (pwdBtn && pwd) {
      pwdBtn.addEventListener('click', () => {
        const isText = pwd.type === 'text';
        pwd.type = isText ? 'password' : 'text';
        const icon = pwdBtn.querySelector('i');
        if (icon) icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        pwd.focus();
      });
    }
  })();
</script>
</body>
</html>
