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
    // Also support form-encoded fallback
    $data = $_POST;
}

$name = trim($data['name'] ?? '');
$uid  = strtolower(trim($data['uid_hex'] ?? ''));
$room = trim($data['room'] ?? '');
$backfill = !isset($data['backfill']) || filter_var($data['backfill'], FILTER_VALIDATE_BOOLEAN);

// Sanitize uid to hex only
$uid = preg_replace('/[^0-9a-f]/i', '', $uid);

if ($name === '' || $uid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name and uid_hex are required']);
    exit;
}

try {
    $pdo = pdo();
    $pdo->beginTransaction();

    // Check existing user for UID
    $chk = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
    $chk->execute([$uid]);
    $u = $chk->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        // Update name/room if provided
        $upd = $pdo->prepare('UPDATE users SET name = ?, room = ? WHERE id = ?');
        $upd->execute([$name, $room, (int)$u['id']]);
        $userId = (int)$u['id'];
    } else {
        $ins = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');
        $ins->execute([$name, $uid, $room]);
        $userId = (int)$pdo->lastInsertId();
    }

    if ($backfill) {
        $bf = $pdo->prepare('UPDATE attendance a SET a.user_id = ? WHERE a.uid_hex = ? AND (a.user_id IS NULL OR a.user_id <> ?)');
        $bf->execute([$userId, $uid, $userId]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'user_id' => $userId]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}

