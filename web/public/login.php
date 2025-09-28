<?php
require_once __DIR__ . '/../bootstrap.php';

// Inisialisasi PDO
$pdo = pdo();

// Sistem login sederhana yang bekerja dengan sistem yang ada
$error = '';
$success = '';

// Tangani login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        // Rate limiting - cek percobaan login
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $loginAttempts = $_SESSION['login_attempts'][$ip] ?? 0;
        $lastAttempt = $_SESSION['last_login_attempt'][$ip] ?? 0;
        
        // Reset attempts setelah 15 menit
        if (time() - $lastAttempt > 900) {
            $loginAttempts = 0;
        }
        
        // Blokir jika terlalu banyak percobaan
        if ($loginAttempts >= 5) {
            $error = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.';
        } else {
            // Periksa apakah ini login admin lama
            if ($username === 'admin' && $password === 'admin') {
                // Setel session untuk admin
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['role'] = 'admin';
                $_SESSION['name'] = 'Administrator';
                $_SESSION['room'] = 'Admin';
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Set remember me cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 hari
                    
                    // Simpan token ke database
                    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([1, $token, date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60))]);
                }
                
                // Reset login attempts
                unset($_SESSION['login_attempts'][$ip]);
                unset($_SESSION['last_login_attempt'][$ip]);
                
                // Log login
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $stmt->execute([1, 'login', 'Admin login successful', $ip]);
                
                header('Location: admin_simple.php');
                exit;
            }
            
            // Periksa database untuk pengguna lain
            $sql = "SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Setel session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['room'] = $user['room'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Set remember me cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 hari
                    
                    // Simpan token ke database
                    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60))]);
                }
                
                // Reset login attempts
                unset($_SESSION['login_attempts'][$ip]);
                unset($_SESSION['last_login_attempt'][$ip]);
                
                // Log login
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], 'login', 'User login successful', $ip]);
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Alihkan berdasarkan role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin_simple.php');
                        break;
                    case 'teacher':
                        header('Location: teacher.php');
                        break;
                    case 'parent':
                        header('Location: parent.php');
                        break;
                    case 'student':
                        header('Location: student.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit;
            } else {
                // Increment login attempts
                $_SESSION['login_attempts'][$ip] = $loginAttempts + 1;
                $_SESSION['last_login_attempt'][$ip] = time();
                
                // Log failed login
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $stmt->execute([0, 'login_failed', "Failed login attempt for username: $username, role: $role", $ip]);
                
                $error = 'Username, password, atau role tidak valid';
            }
        }
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-bg-dark: rgba(30, 30, 30, 0.95);
            --text-color: #333;
            --text-color-dark: #fff;
            --border-color: #dee2e6;
            --border-color-dark: #495057;
        }

        [data-bs-theme="dark"] {
            --card-bg: var(--card-bg-dark);
            --text-color: var(--text-color-dark);
            --border-color: var(--border-color-dark);
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        [data-bs-theme="dark"] body {
            background: var(--dark-gradient);
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        [data-bs-theme="dark"] .login-card {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .role-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            background: var(--card-bg);
            color: var(--text-color);
        }

        .role-card:hover {
            transform: translateY(-5px);
            border-color: #007bff;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.2);
        }

        .role-card.selected {
            border-color: #007bff;
            background-color: rgba(0, 123, 255, 0.1);
        }

        [data-bs-theme="dark"] .role-card.selected {
            background-color: rgba(0, 123, 255, 0.2);
        }

        .form-control {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .form-control:focus {
            background-color: var(--card-bg);
            border-color: #007bff;
            color: var(--text-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .input-group-text {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        [data-bs-theme="dark"] .theme-toggle {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .theme-toggle:hover {
            transform: scale(1.05);
        }

        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .admin-icon { color: #dc3545; }
        .teacher-icon { color: #0d6efd; }
        .parent-icon { color: #198754; }
        .student-icon { color: #6f42c1; }

        [data-bs-theme="dark"] .admin-icon { color: #ff6b6b; }
        [data-bs-theme="dark"] .teacher-icon { color: #4dabf7; }
        [data-bs-theme="dark"] .parent-icon { color: #51cf66; }
        [data-bs-theme="dark"] .student-icon { color: #9775fa; }

        .login-title {
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .login-subtitle {
            color: var(--text-color);
            opacity: 0.7;
            transition: color 0.3s ease;
        }

        .credential-info {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }

        .btn-outline-secondary {
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .btn-outline-secondary:hover {
            background-color: var(--border-color);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .alert {
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .navbar-brand, .nav-link {
            color: var(--text-color) !important;
        }

        .navbar {
            background: var(--card-bg) !important;
            border-bottom: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
        <i class="bi bi-sun-fill" id="themeIcon"></i>
    </button>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                        <h2 class="mt-3 login-title">Sistem Kehadiran</h2>
                        <p class="login-subtitle"><?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= e($error) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?= e($success) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <div class="mb-4">
                            <label class="form-label">Pilih Peran</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="admin">
                                        <i class="bi bi-shield-check role-icon admin-icon"></i>
                                        <h6 class="mt-2">Admin</h6>
                                        <small class="text-muted">Akses Penuh</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="teacher">
                                        <i class="bi bi-person-check role-icon teacher-icon"></i>
                                        <h6 class="mt-2">Guru</h6>
                                        <small class="text-muted">Kelola Kelas</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="parent">
                                        <i class="bi bi-person-heart role-icon parent-icon"></i>
                                        <h6 class="mt-2">Orang Tua</h6>
                                        <small class="text-muted">Lihat Anak</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="student">
                                        <i class="bi bi-person role-icon student-icon"></i>
                                        <h6 class="mt-2">Siswa</h6>
                                        <small class="text-muted">Lihat Sendiri</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="role" id="selectedRole" value="admin">
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Nama Pengguna</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Masukkan nama pengguna" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Masukkan kata sandi" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Ingat saya selama 30 hari
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Masuk
                            </button>
                        </div>
                    </form>

                    <div class="credential-info">
                        <h6><i class="bi bi-info-circle"></i> Kredensial Masuk:</h6>
                        <div class="row">
                            <div class="col-6">
                                <small><strong>Admin:</strong> admin / admin</small><br>
                                <small><strong>Guru:</strong> teacher1 / password</small>
                            </div>
                            <div class="col-6">
                                <small><strong>Orang Tua:</strong> parent1 / password</small><br>
                                <small><strong>Siswa:</strong> (akan dibuat otomatis)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi Toggle Tema
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        // Muat tema tersimpan atau default ke terang
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.className = 'bi bi-moon-fill';
                themeToggle.title = 'Beralih ke Mode Terang';
            } else {
                themeIcon.className = 'bi bi-sun-fill';
                themeToggle.title = 'Beralih ke Mode Gelap';
            }
        }

        // Pemilihan peran
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Hapus kelas terpilih dari semua kartu
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                // Tambahkan kelas terpilih ke kartu yang diklik
                this.classList.add('selected');
                // Perbarui input tersembunyi
                document.getElementById('selectedRole').value = this.dataset.role;
            });
        });

        // Setel pilihan default
        document.querySelector('[data-role="admin"]').classList.add('selected');

        // Toggle visibilitas kata sandi
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        // Validasi form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const role = document.getElementById('selectedRole').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Nama pengguna dan kata sandi harus diisi');
                return;
            }

            if (!role) {
                e.preventDefault();
                alert('Pilih peran terlebih dahulu');
                return;
            }
        });

        // Tambahkan transisi halus
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>