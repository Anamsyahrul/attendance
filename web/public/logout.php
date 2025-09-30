<?php
require_once __DIR__ . '/../bootstrap.php';

// Pastikan session dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logout pengguna
keluar_pengguna();

// Redirect ke login
header('Location: login.php?logged_out=1');
exit;

