<?php
require_once __DIR__ . '/../bootstrap.php';

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$q    = trim($_GET['q'] ?? '');
$room = trim($_GET['room'] ?? '');

try { $fromDt = new DateTime($from ?: 'today', $tz); } catch (Exception $e) { $fromDt = new DateTime('today', $tz); }
try { $toDt = new DateTime($to ?: 'tomorrow', $tz); } catch (Exception $e) { $toDt = (clone $fromDt)->modify('+1 day'); }

$params = [$fromDt->format('Y-m-d H:i:s'), $toDt->format('Y-m-d H:i:s')];
$sql = 'SELECT a.ts, u.name AS user_name, u.room AS room, a.uid_hex, a.device_id
        FROM attendance a LEFT JOIN users u ON a.user_id = u.id
        WHERE a.ts >= ? AND a.ts < ?';
if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR a.uid_hex LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($room !== '') { $sql .= ' AND u.room = ?'; $params[] = $room; }
$sql .= ' ORDER BY a.ts DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$school = env('SCHOOL_NAME','Attendance');
$addr   = env('SCHOOL_ADDRESS','');
$phone  = env('SCHOOL_PHONE','');
$email  = env('SCHOOL_EMAIL','');
$site   = env('SCHOOL_WEBSITE','');
$motto  = env('SCHOOL_MOTTO','');

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ekspor PDF - Log Mentah</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color:#111; }
    .wrap { max-width: 1024px; margin: 0 auto; }
    .head { display:flex; align-items:center; gap:16px; border-bottom:2px solid #000; padding-bottom:10px; }
    .head img { height: 56px; border-radius:6px; }
    .meta { font-size: 12px; color:#444; margin-top: 6px; }
    h1 { font-size: 20px; margin: 12px 0 4px; }
    h2 { font-size: 16px; margin: 0 0 12px; color:#333; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid #ddd; font-size: 12px; }
    thead th { background:#f1f3f5; border-bottom:2px solid #ccc; text-align:left; }
    .muted { color:#666; }
    @media print { .no-print { display:none; } body { color:#000; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="head">
      <div>
        <div style="font-weight:700; font-size:18px;"><?= e($school) ?></div>
        <?php if ($motto): ?><div class="muted"><?= e($motto) ?></div><?php endif; ?>
        <div class="meta"><?= e($addr) ?><?= $phone? ' • Telp: '.e($phone):'' ?><?= $email? ' • Email: '.e($email):'' ?><?= $site? ' • '.e($site):'' ?></div>
      </div>
    </div>
    <h1>Log Kehadiran (Mentah)</h1>
    <h2>Periode: <?= e($fromDt->format('Y-m-d')) ?> s/d <?= e($toDt->format('Y-m-d')) ?><?= $room? ' • Kelas: '.e($room):'' ?><?= $q? ' • Cari: '.e($q):'' ?></h2>
    <table>
      <thead><tr><th>Waktu</th><th>Nama</th><th>Kelas</th><th>UID</th><th>Perangkat</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="muted">Tidak ada data</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['ts']) ?></td>
            <td><?= e($r['user_name'] ?: '—') ?></td>
            <td><?= e($r['room'] ?: '') ?></td>
            <td><?= e($r['uid_hex']) ?></td>
            <td><?= e($r['device_id']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <div class="no-print" style="margin-top:12px;">
      <button onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
  </div>
</body>
</html>
