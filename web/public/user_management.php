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

$message = '';
$error = '';

// Tangani aksi CRUD user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? 'student';
            $room = trim($_POST['room'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($username) || empty($password) || empty($email) || empty($name)) {
                $error = 'Username, password, email, dan nama harus diisi';
            } else {
                // Cek apakah username sudah ada
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username sudah digunakan';
                } else {
                    // Generate UID hex unik
                    $uidHex = strtolower(substr(md5($username . time() . rand()), 0, 16));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, email, role, name, room, uid_hex, phone, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    if ($stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $role, $name, $room, $uidHex, $phone])) {
                        $message = 'User berhasil dibuat dengan UID: ' . $uidHex;
                    } else {
                        $error = 'Gagal membuat user';
                    }
                }
            }
            break;
            
        case 'update_user':
            $userId = $_POST['user_id'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? 'student';
            $room = trim($_POST['room'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($userId) || empty($username) || empty($email) || empty($name)) {
                $error = 'Semua field wajib harus diisi';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, role = ?, name = ?, room = ?, phone = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$username, $email, $role, $name, $room, $phone, $isActive, $userId])) {
                    $message = 'User berhasil diperbarui';
                } else {
                    $error = 'Gagal memperbarui user';
                }
            }
            break;
            
        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';
            
            if (empty($userId)) {
                $error = 'User ID tidak valid';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $message = 'User berhasil dihapus';
                } else {
                    $error = 'Gagal menghapus user';
                }
            }
            break;
            
        case 'reset_password':
            $userId = $_POST['user_id'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            
            if (empty($userId) || empty($newPassword)) {
                $error = 'User ID dan password baru harus diisi';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId])) {
                    $message = 'Password berhasil direset';
                } else {
                    $error = 'Gagal reset password';
                }
            }
            break;
    }
}

// Ambil daftar users
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role)) {
    $whereConditions[] = "role = ?";
    $params[] = $role;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Hitung total users
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalUsers / $limit);

// Ambil users
$sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil statistik
$statsSql = "
    SELECT 
        role,
        COUNT(*) as count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
    FROM users 
    GROUP BY role
";
$stmt = $pdo->prepare($statsSql);
$stmt->execute();
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Pengguna - <?= e(env('SCHOOL_NAME', 'SMA Bustanul Arifin')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="user_management.php">
                <i class="bi bi-people"></i> Manajemen Pengguna
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
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <?php foreach ($stats as $stat): ?>
            <div class="col-md-3">
                <div class="card text-white bg-<?= $stat['role'] === 'admin' ? 'danger' : ($stat['role'] === 'teacher' ? 'success' : ($stat['role'] === 'parent' ? 'info' : 'primary')) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $stat['count'] ?></h4>
                                <p class="card-text"><?= ucfirst($stat['role']) ?></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-<?= $stat['role'] === 'admin' ? 'shield-check' : ($stat['role'] === 'teacher' ? 'person-check' : ($stat['role'] === 'parent' ? 'person-heart' : 'person')) ?> display-4"></i>
                            </div>
                        </div>
                        <small>Aktif: <?= $stat['active_count'] ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <!-- Search and Filter -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-search me-2"></i> Pencarian & Filter</h5>
                    </div>
                    <div class="card-body">
                        <form method="get">
                            <div class="mb-3">
                                <label for="search" class="form-label">Cari Pengguna</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Nama, username, atau email">
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">Semua Role</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Guru</option>
                                    <option value="parent" <?= $role === 'parent' ? 'selected' : '' ?>>Orang Tua</option>
                                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Siswa</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Cari
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Create User Form -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i> Tambah Pengguna</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_user">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student">Siswa</option>
                                    <option value="teacher">Guru</option>
                                    <option value="parent">Orang Tua</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="room" class="form-label">Kelas/Ruangan</label>
                                <input type="text" class="form-control" id="room" name="room">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telepon</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-person-plus"></i> Tambah Pengguna
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Users List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list me-2"></i> Daftar Pengguna</h5>
                        <span class="badge bg-primary"><?= $totalUsers ?> pengguna</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people display-1"></i>
                            <p class="mt-3">Tidak ada pengguna ditemukan</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Kelas</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'success' : ($user['role'] === 'parent' ? 'info' : 'primary')) ?> text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <?= e($user['name']) ?>
                                            </div>
                                        </td>
                                        <td><?= e($user['username']) ?></td>
                                        <td><?= e($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'success' : ($user['role'] === 'parent' ? 'info' : 'primary')) ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= e($user['room']) ?></td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Tidak Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="resetPassword(<?= $user['id'] ?>)">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= e($user['name']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= e($search) ?>&role=<?= e($role) ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editName" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" id="editRole" name="role" required>
                                <option value="student">Siswa</option>
                                <option value="teacher">Guru</option>
                                <option value="parent">Orang Tua</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editRoom" class="form-label">Kelas/Ruangan</label>
                            <input type="text" class="form-control" id="editRoom" name="room">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editPhone" class="form-label">Telepon</label>
                            <input type="text" class="form-control" id="editPhone" name="phone">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">
                                Aktif
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="resetPasswordForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="resetUserId">
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="confirmPassword" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editName').value = user.name;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editRoom').value = user.room || '';
            document.getElementById('editPhone').value = user.phone || '';
            document.getElementById('editIsActive').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function resetPassword(userId) {
            document.getElementById('resetUserId').value = userId;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }

        function deleteUser(userId, userName) {
            if (confirm('Yakin ingin menghapus pengguna "' + userName + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="' + userId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Validasi konfirmasi password
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
