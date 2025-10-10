<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

$action = strtolower(trim((string)($_GET['action'] ?? '')));
$ttl    = max(10, min(300, (int)($_GET['ttl'] ?? 60))); // default 60s, max 5min

$pathDir = __DIR__ . '/../tmp';
$path = $pathDir . '/registration_mode.json';
if (!is_dir($pathDir)) @mkdir($pathDir, 0777, true);

if ($action === 'on') {
  $data = ['until' => time() + $ttl, 'by' => 'web-register'];
  file_put_contents($path, json_encode($data));
  echo json_encode(['ok'=>true,'reg_mode'=>true,'until'=>$data['until']]);
} elseif ($action === 'off') {
  if (file_exists($path)) @unlink($path);
  echo json_encode(['ok'=>true,'reg_mode'=>false]);
} else {
  // status
  $on = false; $until=0;
  if (file_exists($path)) {
    $js = @json_decode(@file_get_contents($path), true);
    if (is_array($js)) { $until = (int)($js['until'] ?? 0); $on = $until > time(); }
  }
  echo json_encode(['ok'=>true,'reg_mode'=>$on,'until'=>$until]);
}

