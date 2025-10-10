<?php
// Simple local tester for ingest endpoint
// php attendance/web/api/test_ingest.php

$deviceId = 'DEVICE-01';
$secret   = 'anamganteng123';
$nonce    = 'abc123';
$ts       = time();
$events   = [
  ['uid' => 'A1B2C3D4', 'ts' => date('c'), 'type' => 'checkin']
];

$eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES);
$message = $deviceId . '|' . $ts . '|' . $nonce . '|' . $eventsJson;
$hmac = hash_hmac('sha256', $message, $secret);

$payload = [
  'device_id' => $deviceId,
  'nonce' => $nonce,
  'ts' => $ts,
  'hmac' => $hmac,
  'events' => $events,
];

$url = 'http://127.0.0.1/attendance/web/api/ingest.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n";
echo $resp, "\n";


