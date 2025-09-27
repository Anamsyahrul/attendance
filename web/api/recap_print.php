<?php
require_once __DIR__ . '/../bootstrap.php';

if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$pdo = pdo();
$tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));

$date = $_GET['date'] ?? (new DateTime('today',$tz))->format('Y-m-d');
$room = trim((string)($_GET['room'] ?? ''));
$q    = trim((string)($_GET['q'] ?? ''));

$start = new DateTime($date, $tz);
$end   = (clone $start)->modify('+1 day');

$schoolStart = env('SCHOOL_START','07:15');
$schoolEnd   = env('SCHOOL_END','15:00');
$requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);

$lateAt = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d').' '.$schoolStart, $tz);
$endAt  = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d').' '.$schoolEnd, $tz);

if ($room !== '') {
  $sql = 'SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
          FROM users u LEFT JOIN attendance a ON a.uid_hex=u.uid_hex AND a.ts>=? AND a.ts<?
          WHERE u.room=? GROUP BY u.id,u.name,u.uid_hex,u.room ORDER BY u.name';
  $st = $pdo->prepare($sql);
  $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $room]);
} else {
  $sql = 'SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
          FROM users u LEFT JOIN attendance a ON a.uid_hex=u.uid_hex AND a.ts>=? AND a.ts<?
          GROUP BY u.id,u.name,u.uid_hex,u.room ORDER BY u.room,u.name';
  $st = $pdo->prepare($sql);
  $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
}
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Overrides map
$overrideMap = build_override_map($pdo, $start, $end);
$today = new DateTime('today', $tz);
$isPastDay = ($start < $today);

$out = [];
foreach ($rows as $r) {
  [$statusMasuk, $statusPulang] = resolve_daily_status($r, $tz, $start, $lateAt, $endAt, $isPastDay, $requireCheckout, $overrideMap);

  if ($q !== '') {
    $qLower = mb_strtolower($q,'UTF-8');
    $nm = mb_strtolower($r['name'] ?? '','UTF-8');
    $uid = mb_strtolower($r['uid_hex'] ?? '','UTF-8');
    if (strpos($nm,$qLower) === false && strpos($uid,$qLower) === false) continue;
  }
  $out[] = [ 'name'=>$r['name'], 'uid_hex'=>$r['uid_hex'], 'room'=>$r['room'], 'masuk'=>$statusMasuk, 'pulang'=>$statusPulang ];
}

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
  <title>Ekspor PDF - Rekap Kehadiran</title>
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
    .b { font-weight:700; }
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
    <h1>Rekap Kehadiran</h1>
    <h2>Tanggal: <?= e($start->format('Y-m-d')) ?><?= $room? ' • Kelas: '.e($room):'' ?><?= $q? ' • Cari: '.e($q):'' ?></h2>
    <table>
      <thead><tr><th>Nama</th><th>UID</th><th>Kelas</th><th>Status Hadir</th><th>Status Pulang</th></tr></thead>
      <tbody>
        <?php if (!$out): ?>
          <tr><td colspan="5" class="muted">Tidak ada data</td></tr>
        <?php else: foreach ($out as $r): ?>
          <tr>
            <td class="b"><?= e($r['name']) ?></td>
            <td><code><?= e($r['uid_hex']) ?></code></td>
            <td><?= e($r['room']) ?></td>
            <td><?= e($r['masuk']) ?></td>
            <td><?= e($r['pulang'] ?: '—') ?></td>
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

