<?php
require_once __DIR__ . '/../bootstrap.php';

$tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
$date = $_GET['date'] ?? (new DateTime('today', $tz))->format('Y-m-d');
$room = $_GET['room'] ?? '';

try {
    $pdo = pdo();

    $start = new DateTime($date, $tz);
    $end = (clone $start)->modify('+1 day');
    $schoolStart = env('SCHOOL_START', '07:15');
    $schoolEnd = env('SCHOOL_END', '15:00');
    $lateThreshold = (int) env('LATE_THRESHOLD', 15); // menit
    $lateAt = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolStart, $tz);
    $lateAt->modify('+' . $lateThreshold . ' minutes');
    $endAt  = DateTime::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $schoolEnd, $tz);
    $requireCheckout = (bool) env('REQUIRE_CHECKOUT', false);

    if ($room !== '') {
        $stmt = $pdo->prepare('SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
                               FROM users u
                               LEFT JOIN attendance a
                               ON a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ?
                               WHERE u.room = ?
                               GROUP BY u.id, u.name, u.uid_hex, u.room
                               ORDER BY u.name');
        $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $room]);
    } else {
        $stmt = $pdo->prepare('SELECT u.id, u.name, u.uid_hex, u.room, MIN(a.ts) AS first_ts, MAX(a.ts) AS last_ts
                               FROM users u
                               LEFT JOIN attendance a
                               ON a.uid_hex = u.uid_hex AND a.ts >= ? AND a.ts < ?
                               GROUP BY u.id, u.name, u.uid_hex, u.room
                               ORDER BY u.room, u.name');
        $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    }

    header('Content-Type: text/csv');
    $fname = 'recap_' . $start->format('Y-m-d') . ($room !== '' ? ('_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $room)) : '') . '.csv';
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['date', 'name', 'uid_hex', 'room', 'status_masuk', 'status_pulang']);
    $overrideMap = buat_peta_override($pdo, $start, $end);
    $today = new DateTime('today', $tz);
    $isPastDay = ($start < $today);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        [$statusMasuk, $statusPulang] = selesaikan_status_harian($r, $tz, $start, $lateAt, $endAt, $isPastDay, $requireCheckout, $overrideMap);
        fputcsv($out, [$start->format('Y-m-d'), $r['name'], $r['uid_hex'], $r['room'], $statusMasuk, $statusPulang]);
    }
    fclose($out);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
