<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
    $date = $_GET['date'] ?? (new DateTime('today', $tz))->format('Y-m-d');
    $room = trim((string)($_GET['room'] ?? ''));
    $q    = trim((string)($_GET['q'] ?? ''));
    $sf   = strtolower(trim((string)($_GET['sf'] ?? ''))); // hadir|terlambat|tidak_hadir

    $start = new DateTime($date, $tz);
    $end   = (clone $start)->modify('+1 day');
    $schoolStart = env('SCHOOL_START', '07:15');
    $schoolEnd   = env('SCHOOL_END', '15:00');
    $requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);

    $lateAt = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolStart, $tz);
    $endAt  = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolEnd, $tz);

    if ($room !== '') {
        $sql = 'SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
                FROM users u
                LEFT JOIN attendance a ON a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ?
                WHERE u.room = ?
                GROUP BY u.id, u.name, u.uid_hex, u.room
                ORDER BY u.name';
        $st = $pdo->prepare($sql);
        $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $room]);
    } else {
        $sql = 'SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
                FROM users u
                LEFT JOIN attendance a ON a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ?
                GROUP BY u.id, u.name, u.uid_hex, u.room
                ORDER BY u.room, u.name';
        $st = $pdo->prepare($sql);
        $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    }

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $overrideMap = buat_peta_override($pdo, $start, $end);
    $today = new DateTime('today', $tz);
    $isPastDay = ($start < $today);

    $outRows = [];
    foreach ($rows as $r) {
        [$statusMasuk, $statusPulang] = selesaikan_status_harian($r, $tz, $start, $lateAt, $endAt, $isPastDay, $requireCheckout, $overrideMap);

        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $nm = mb_strtolower($r['name'] ?? '', 'UTF-8');
            $uid = mb_strtolower($r['uid_hex'] ?? '', 'UTF-8');
            if (strpos($nm, $qLower) === false && strpos($uid, $qLower) === false) {
                continue;
            }
        }
        if ($sf === 'hadir' && $statusMasuk !== 'Hadir') {
            continue;
        }
        if ($sf === 'terlambat' && $statusMasuk !== 'Terlambat') {
            continue;
        }
        if ($sf === 'tidak_hadir' && $statusMasuk !== 'Tidak Hadir') {
            continue;
        }

        $outRows[] = [
            'name' => $r['name'],
            'uid_hex' => $r['uid_hex'],
            'room' => $r['room'],
            'masuk_status' => $statusMasuk,
            'pulang_status' => $statusPulang,
            'first_ts' => $r['first_ts'],
            'last_ts' => $r['last_ts'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'date' => $start->format('Y-m-d'),
        'room' => $room,
        'count' => count($outRows),
        'rows' => $outRows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

