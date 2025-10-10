<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Simple guard
$confirm = strtolower((string)($_GET['confirm'] ?? ''));
if (!in_array($confirm, ['yes','y','1'], true)) {
    echo json_encode([
        'ok' => false,
        'hint' => 'Tambahkan ?confirm=YES pada URL untuk menjalankan generator.',
        'example' => rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/').'/api/demo_seed.php?confirm=YES&users=100&days=7'
    ]);
    exit;
}

$usersTarget = max(1, min(1000, (int)($_GET['users'] ?? 100)));
$days = max(1, min(90, (int)($_GET['days'] ?? 14)));
$presentRate = min(1.0, max(0.0, (float)($_GET['present'] ?? 0.9)));
$lateRate = min(1.0, max(0.0, (float)($_GET['late'] ?? 0.15)));
$noCheckoutRate = min(1.0, max(0.0, (float)($_GET['no_checkout'] ?? 0.1)));

try {
    $pdo = pdo();
    $tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));

    // Ensure device exists
    $devId = 'esp32-01';
    $dev = $pdo->prepare('SELECT id FROM devices WHERE id = ?');
    $dev->execute([$devId]);
    if (!$dev->fetchColumn()) {
        $insd = $pdo->prepare('INSERT INTO devices (id, name, device_secret, is_active) VALUES (?, ?, ?, 1)');
        $insd->execute([$devId, 'Demo Device', 'changeme_device_secret']);
    }

    // Predefined rooms
    $rooms = [];
    foreach ([10,11,12] as $grade) {
        foreach (['A','B','C'] as $cls) $rooms[] = $grade.$cls;
    }

    // Random Indonesian-like names
    $firsts = ['Ahmad','Budi','Citra','Dewi','Eko','Fajar','Gita','Hadi','Intan','Joko','Karin','Lia','Maya','Nur','Oka','Putra','Putri','Rizki','Sari','Tono','Umar','Vina','Wahyu','Xenia','Yudha','Zahra'];
    $lasts  = ['Saputra','Sari','Pratama','Wibowo','Wijaya','Permata','Ramadhan','Utami','Hidayat','Aulia','Mahardika','Syahputra','Kurniawan','Ananda'];

    // Create users up to target (idempotent by uid)
    $createdUsers = 0; $updatedUsers = 0;
    $existing = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $toMake = max(0, $usersTarget - (int)$existing);

    $selU = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
    $insU = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');
    $updU = $pdo->prepare('UPDATE users SET name=?, room=? WHERE id=?');

    for ($i=0; $i<$toMake; $i++) {
        $name = $firsts[array_rand($firsts)] . ' ' . $lasts[array_rand($lasts)];
        $uid = '';
        do {
            $uid = strtolower(bin2hex(random_bytes(5))); // 10 hex chars
            $selU->execute([$uid]);
        } while ($selU->fetch());
        $room = $rooms[array_rand($rooms)];
        $insU->execute([$name, $uid, $room]);
        $createdUsers++;
    }

    // Build date range (past N days)
    $end = new DateTime('today', $tz);
    $start = (clone $end)->modify('-'.($days-1).' days');

    $findUsers = $pdo->query('SELECT id, uid_hex, room FROM users');
    $allUsers = $findUsers->fetchAll(PDO::FETCH_ASSOC);

    $insA = $pdo->prepare('INSERT INTO attendance (user_id, device_id, ts, uid_hex, raw_json) VALUES (?, ?, ?, ?, ?)');

    $events = 0;
    $pdo->beginTransaction();
    foreach (new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day')) as $day) {
        $dateStr = $day->format('Y-m-d');
        foreach ($allUsers as $u) {
            if (mt_rand() / mt_getrandmax() > $presentRate) continue; // absent
            // Generate check-in time between 06:30 and 08:30
            $inHour = 6 + random_int(0,2);
            $inMin  = random_int(0,59);
            if ($inHour == 6 && $inMin < 30) $inMin += 30;
            $late = (mt_rand() / mt_getrandmax() < $lateRate);
            if ($late) {
                $inHour = 7 + random_int(0,1);
                $inMin = random_int(15,59);
            }
            $inTs = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:%02d', $dateStr, $inHour, $inMin, random_int(0,59)), $tz);
            $rawIn = json_encode(['uid'=>$u['uid_hex'],'ts'=>$inTs->format('c'),'type'=>'in']);
            $insA->execute([(int)$u['id'], $devId, $inTs->format('Y-m-d H:i:s'), $u['uid_hex'], $rawIn]);
            $events++;
            // Checkout
            if (mt_rand() / mt_getrandmax() < $noCheckoutRate) continue;
            $outHour = 14 + random_int(0,2);
            $outMin  = random_int(0,59);
            $outTs = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:%02d', $dateStr, $outHour, $outMin, random_int(0,59)), $tz);
            if ($outTs <= $inTs) $outTs = (clone $inTs)->modify('+6 hours');
            $rawOut = json_encode(['uid'=>$u['uid_hex'],'ts'=>$outTs->format('c'),'type'=>'out']);
            $insA->execute([(int)$u['id'], $devId, $outTs->format('Y-m-d H:i:s'), $u['uid_hex'], $rawOut]);
            $events++;
        }
    }
    $pdo->commit();

    echo json_encode(['ok'=>true,'users_created'=>$createdUsers,'events_inserted'=>$events,'days'=>$days,'users_total'=>count($allUsers)]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

