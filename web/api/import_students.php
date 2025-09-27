<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
    exit;
}

$tmp = $_FILES['file']['tmp_name'];
$pdo = pdo();

$inserted = 0; $updated = 0; $errors = 0; $lineNo = 0;
if (($fh = fopen($tmp, 'r')) !== false) {
    while (($row = fgetcsv($fh)) !== false) {
        $lineNo++;
        if (count($row) < 2) { $errors++; continue; }
        $name = trim((string)$row[0]);
        $uid  = strtolower(preg_replace('/[^0-9a-f]/i','', (string)$row[1]));
        $room = isset($row[2]) ? trim((string)$row[2]) : '';
        if ($name === '' || $uid === '') { $errors++; continue; }
        try {
            $sel = $pdo->prepare('SELECT id FROM users WHERE uid_hex = ?');
            $sel->execute([$uid]);
            $u = $sel->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $upd = $pdo->prepare('UPDATE users SET name = ?, room = ? WHERE id = ?');
                $upd->execute([$name, $room, (int)$u['id']]);
                $updated++;
            } else {
                $ins = $pdo->prepare('INSERT INTO users (name, uid_hex, room) VALUES (?, ?, ?)');
                $ins->execute([$name, $uid, $room]);
                $inserted++;
            }
        } catch (Throwable $e) { $errors++; }
    }
    fclose($fh);
}

echo json_encode(['ok'=> true, 'inserted'=>$inserted, 'updated'=>$updated, 'errors'=>$errors]);

