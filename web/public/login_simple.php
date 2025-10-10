<?php
require_once __DIR__ . '/../bootstrap.php';

// Simple login system that works with existing system
$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        // Check if it's the old admin login
        if ($username === 'admin' && $password === 'admin') {
            // Set session for admin
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'admin';
            $_SESSION['role'] = 'admin';
            $_SESSION['name'] = 'Administrator';
            $_SESSION['room'] = 'Admin';
            $_SESSION['login_time'] = time();
            
            header('Location: admin.php');
            exit;
        }
        
        // Check database for other users
        $sql = "SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['room'] = $user['room'];
            $_SESSION['login_time'] = time();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin.php');
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
            $error = 'Username, password, atau role tidak valid';
        }
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .role-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .role-card:hover {
            transform: translateY(-5px);
            border-color: #007bff;
        }
        .role-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                        <h2 class="mt-3">Sistem Kehadiran</h2>
                        <p class="text-muted"><?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></p>
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
                            <label class="form-label">Pilih Role</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="admin">
                                        <i class="bi bi-shield-check text-danger fs-1"></i>
                                        <h6 class="mt-2">Admin</h6>
                                        <small class="text-muted">Full Access</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="teacher">
                                        <i class="bi bi-person-check text-primary fs-1"></i>
                                        <h6 class="mt-2">Teacher</h6>
                                        <small class="text-muted">Class Management</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="parent">
                                        <i class="bi bi-person-heart text-success fs-1"></i>
                                        <h6 class="mt-2">Parent</h6>
                                        <small class="text-muted">View Child</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="role-card card text-center p-3" data-role="student">
                                        <i class="bi bi-person text-info fs-1"></i>
                                        <h6 class="mt-2">Student</h6>
                                        <small class="text-muted">View Own</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="role" id="selectedRole" value="admin">
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Masukkan username" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Masukkan password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Default Admin: admin / admin<br>
                            Teacher: teacher1 / password<br>
                            Parent: parent1 / password
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role selection
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                // Add selected class to clicked card
                this.classList.add('selected');
                // Update hidden input
                document.getElementById('selectedRole').value = this.dataset.role;
            });
        });

        // Set default selection
        document.querySelector('[data-role="admin"]').classList.add('selected');

        // Toggle password visibility
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

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const role = document.getElementById('selectedRole').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Username dan password harus diisi');
                return;
            }

            if (!role) {
                e.preventDefault();
                alert('Pilih role terlebih dahulu');
                return;
            }
        });
    </script>
</body>
</html>

