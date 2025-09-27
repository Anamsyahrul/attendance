<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$deviceId = $data['device_id'] ?? null;
$nonce    = $data['nonce'] ?? null;
$ts       = $data['ts'] ?? null;
$hmac     = $data['hmac'] ?? null;
$events   = $data['events'] ?? null;

if (!$deviceId || !$nonce || !$ts || !$hmac || !is_array($events)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$pdo = null;
try {
    $pdo = pdo();

    // Fetch device
    $stmt = $pdo->prepare('SELECT id, device_secret, is_active FROM devices WHERE id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$device || (int)$device['is_active'] !== 1) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Device not authorized']);
        exit;
    }

    // Verify HMAC
    $eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES);
    $message = $deviceId . '|' . $ts . '|' . $nonce . '|' . $eventsJson;
    if (!verify_hmac($device['device_secret'], $message, $hmac)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid HMAC']);
        exit;
    }

    $autoCreate = (bool) env('AUTO_CREATE_UNKNOWN', false);

    $pdo->beginTransaction();

    $insert = $pdo->prepare('INSERT INTO attendance (user_id, device_id, ts, uid_hex, raw_json) VALUES (?, ?, ?, ?, ?)');
    $findUser = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
    $createUser = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');

    $saved = 0;
    $errors = [];

    foreach ($events as $i => $e) {
        $uidHex = isset($e['uid']) ? strtolower(preg_replace('/[^0-9a-f]/i', '', $e['uid'])) : null;
        $tsStr  = $e['ts'] ?? null;
        if (!$uidHex || !$tsStr) {
            $errors[] = ['index' => $i, 'error' => 'Missing uid/ts'];
            continue;
        }

        try {
            $dt = new DateTime($tsStr, new DateTimeZone(env('APP_TZ', 'Asia/Jakarta')));
        } catch (Exception $ex) {
            $errors[] = ['index' => $i, 'error' => 'Invalid ts'];
            continue;
        }
        $tsDb = $dt->format('Y-m-d H:i:s');

        // Resolve user id
        $userId = null;
        $findUser->execute([$uidHex]);
        $u = $findUser->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $userId = (int)$u['id'];
        } elseif ($autoCreate) {
            $name = 'Unknown ' . strtoupper($uidHex);
            $createUser->execute([$name, $uidHex, '']);
            $userId = (int)$pdo->lastInsertId();
        }

        $rawJson = json_encode($e, JSON_UNESCAPED_SLASHES);
        $insert->execute([$userId, $deviceId, $tsDb, $uidHex, $rawJson]);
        $saved++;
    }

    $pdo->commit();

    echo json_encode(['ok' => true, 'saved' => $saved, 'errors' => $errors]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}

