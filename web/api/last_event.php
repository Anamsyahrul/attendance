<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');
try {
  $pdo = pdo();
  $row = $pdo->query('SELECT MAX(id) AS last_id, MAX(ts) AS last_ts FROM attendance')->fetch(PDO::FETCH_ASSOC) ?: ['last_id'=>0,'last_ts'=>null];
  echo json_encode(['ok'=>true,'last_id'=>(int)($row['last_id'] ?? 0), 'last_ts'=>$row['last_ts']]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

