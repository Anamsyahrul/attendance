<?php
require_once __DIR__ . '/../bootstrap.php';
wajib_masuk();
$pdo = pdo();

// Hapus semua data siswa
$stmt = $pdo->prepare('DELETE FROM users');
$stmt->execute();

// Hapus semua log kehadiran
$stmt2 = $pdo->prepare('DELETE FROM attendance');
$stmt2->execute();

echo "Semua data siswa dan log kehadiran telah dihapus.";
?>
