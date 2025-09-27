<?php
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $tz = new DateTimeZone(env('APP_TZ', 'Asia/Jakarta'));
    $start = new DateTime('today', $tz);
    $end = (clone $start)->modify('+1 day');

    $q1 = $pdo->prepare('SELECT COUNT(*) AS c FROM attendance WHERE ts >= ? AND ts < ?');
    $q1->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    $total = (int) $q1->fetchColumn();

    $q2 = $pdo->prepare('SELECT COUNT(DISTINCT uid_hex) FROM attendance WHERE ts >= ? AND ts < ?');
    $q2->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    $unique = (int) $q2->fetchColumn();

    $q3 = $pdo->query('SELECT COUNT(*) FROM devices WHERE is_active = 1');
    $activeDevices = (int) $q3->fetchColumn();

    echo json_encode([
        'ok' => true,
        'today' => $start->format('Y-m-d'),
        'total_scans' => $total,
        'unique_users' => $unique,
        'active_devices' => $activeDevices,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}

