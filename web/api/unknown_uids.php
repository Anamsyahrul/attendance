<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');
try {
    $pdo = pdo();
    $rows = $pdo->query('SELECT a.uid_hex, COUNT(*) AS cnt, MAX(a.ts) AS last_ts
                         FROM attendance a LEFT JOIN users u ON u.uid_hex = a.uid_hex
                         WHERE u.id IS NULL GROUP BY a.uid_hex ORDER BY last_ts DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'items' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

