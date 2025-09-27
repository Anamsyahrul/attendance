<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$uid  = isset($data['uid_hex']) ? strtolower(preg_replace('/[^0-9a-f]/i','', (string)$data['uid_hex'])) : '';
$date = trim((string)($data['date'] ?? ''));
$time = trim((string)($data['time'] ?? ''));
$action = trim((string)($data['action'] ?? ''));

// validate basic
if ($uid === '' || $date === '' || !in_array($action, ['checkin','checkout','late','absent','bolos'], true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing or invalid uid/date/action']);
    exit;
}

try {
    $tz = new DateTimeZone(env('APP_TZ','Asia/Jakarta'));
    // Build datetime depending on action
    if (in_array($action, ['checkin','checkout'], true)) {
        if ($time === '') throw new Exception('Time required');
        $dt = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$time, $tz);
        if (!$dt) throw new Exception('Invalid date/time');
    } else {
        // override actions: default times
        $def = '12:00';
        if ($action === 'late') {
            $start = env('SCHOOL_START','07:15');
            // add 30 minutes to ensure late
            $def = $start;
            try {
                $sdt = DateTime::createFromFormat('H:i', $start, $tz) ?: new DateTime('07:15', $tz);
                $sdt->modify('+30 minutes');
                $def = $sdt->format('H:i');
            } catch (Throwable $e) {}
        } elseif ($action === 'bolos') {
            $def = env('SCHOOL_END','15:00');
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$def, $tz);
    }
    $dayStart = DateTime::createFromFormat('Y-m-d', $date, $tz);
    if (!$dayStart) throw new Exception('Invalid date');
    $dayStart->setTime(0, 0, 0);
    $dayEnd = (clone $dayStart)->modify('+1 day');

    $tsDb = $dt->format('Y-m-d H:i:s');

    $pdo = pdo();
    // Find user
    $su = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
    $su->execute([$uid]);
    $user = $su->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'User not found for uid']);
        exit;
    }

    if ($action === 'clear_checkout') {
        $cleanup = $pdo->prepare('DELETE FROM attendance WHERE uid_hex = ? AND ts >= ? AND ts < ? AND raw_json IS NOT NULL AND raw_json LIKE ?');
        $cleanup->execute([$uid, $dayStart->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s'), '%"type":"override"%']);
        $delManualCheckout = $pdo->prepare('DELETE FROM attendance WHERE uid_hex = ? AND ts >= ? AND ts < ? AND device_id = ? AND raw_json IS NOT NULL AND raw_json LIKE ?');
        $delManualCheckout->execute([$uid, $dayStart->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s'), 'manual', '%"type":"checkout"%']);
        echo json_encode(['ok'=>true, 'cleared'=>true]);
        return;
    }

    if (in_array($action, ['checkin','checkout','late','absent','bolos'], true)) {
        $cleanup = $pdo->prepare('DELETE FROM attendance WHERE uid_hex = ? AND ts >= ? AND ts < ? AND raw_json IS NOT NULL AND raw_json LIKE ?');
        $cleanup->execute([$uid, $dayStart->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s'), '%"type":"override"%']);
    }

    // Ensure a device id for manual entries exists
    $devId = 'manual';
    $sd = $pdo->prepare('SELECT id FROM devices WHERE id = ?');
    $sd->execute([$devId]);
    if (!$sd->fetchColumn()) {
        $insd = $pdo->prepare('INSERT INTO devices (id, name, device_secret, is_active) VALUES (?, ?, ?, 1)');
        $insd->execute([$devId, 'Manual Entry', 'n/a']);
    }

    $payload = [ 'uid'=>$uid, 'ts'=>$dt->format(DateTime::ATOM), 'manual'=>true ];
    if (in_array($action, ['checkin','checkout'], true)) {
        $payload['type'] = $action;
    } else {
        $payload['type'] = 'override';
        $payload['status'] = $action; // late/absent/bolos
    }
    $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $ins = $pdo->prepare('INSERT INTO attendance (user_id, device_id, ts, uid_hex, raw_json) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([(int)$user['id'], $devId, $tsDb, $uid, $rawJson]);

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
