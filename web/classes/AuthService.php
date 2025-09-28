<?php

class AuthService {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    /**
     * Login user with role-based access
     */
    public function login($username, $password, $role = 'admin') {
        $sql = "SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $this->setSession($user);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Register new user with role
     */
    public function register($data) {
        $requiredFields = ['username', 'password', 'email', 'role'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            throw new Exception("Username already exists");
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $sql = "INSERT INTO users (username, password, email, role, name, room, parent_email, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['username'],
            $hashedPassword,
            $data['email'],
            $data['role'],
            $data['name'] ?? '',
            $data['room'] ?? '',
            $data['parent_email'] ?? '',
        ]);
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission, $userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        if (!$userId) return false;
        
        $user = $this->getUserById($userId);
        if (!$user) return false;
        
        $permissions = $this->getRolePermissions($user['role']);
        return in_array($permission, $permissions);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get role permissions
     */
    private function getRolePermissions($role) {
        $permissions = [
            'admin' => [
                'view_dashboard',
                'view_reports',
                'edit_attendance',
                'manage_users',
                'manage_settings',
                'view_all_attendance',
                'export_data',
                'send_notifications'
            ],
            'teacher' => [
                'view_dashboard',
                'view_reports',
                'edit_attendance',
                'view_class_attendance',
                'send_notifications'
            ],
            'parent' => [
                'view_child_attendance',
                'view_reports'
            ],
            'student' => [
                'view_own_attendance'
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Set user session
     */
    private function setSession($user) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['room'] = $user['room'];
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_start();
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'name' => $_SESSION['name'],
            'room' => $_SESSION['room']
        ];
    }
    
    /**
     * Require login
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($roles) {
        $this->requireLogin();
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], $roles)) {
            header('HTTP/1.1 403 Forbidden');
            echo "Access denied. Required role: " . implode(', ', $roles);
            exit;
        }
    }
    
    /**
     * Require specific permission
     */
    public function requirePermission($permission) {
        $this->requireLogin();
        
        if (!$this->hasPermission($permission)) {
            header('HTTP/1.1 403 Forbidden');
            echo "Access denied. Required permission: {$permission}";
            exit;
        }
    }
    
    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $sql = "SELECT * FROM users WHERE role = ? AND is_active = 1 ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update user role
     */
    public function updateUserRole($userId, $role) {
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$role, $userId]);
    }
    
    /**
     * Deactivate user
     */
    public function deactivateUser($userId) {
        $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    /**
     * Activate user
     */
    public function activateUser($userId) {
        $sql = "UPDATE users SET is_active = 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
}
?>
