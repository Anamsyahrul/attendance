<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

$tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$uid  = isset($_GET['uid']) ? preg_replace('/[^0-9a-f]/i','', strtolower((string)$_GET['uid'])) : '';
if ($date === '' || $uid === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'date and uid required']);
  exit;
}

try {
  $pdo = pdo();
  $start = new DateTime($date, $tz);
  $end   = (clone $start)->modify('+1 day');
  $schoolStart = env('SCHOOL_START','07:15');
  $schoolEnd   = env('SCHOOL_END','15:00');
  $lateAt = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolStart, $tz);
  $endAt  = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolEnd, $tz);

  $st = $pdo->prepare('SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
                       FROM users u LEFT JOIN attendance a
                       ON a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ?
                       WHERE u.uid_hex = ?
                       GROUP BY u.id, u.name, u.uid_hex, u.room');
  $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $uid]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) { echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

  $today = new DateTime('today', $tz);
  $isPastDay = ($start < $today);
  $requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);

  $overrideMap = build_override_map($pdo, $start, $end);

  [$statusMasuk, $statusPulang] = resolve_daily_status($r, $tz, $start, $lateAt, $endAt, $isPastDay, $requireCheckout, $overrideMap);

  echo json_encode(['ok'=>true,'row'=>[
    'name'=>$r['name'], 'uid_hex'=>$r['uid_hex'], 'room'=>$r['room'],
    'masuk_status'=>$statusMasuk, 'pulang_status'=>$statusPulang,
  ]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}


