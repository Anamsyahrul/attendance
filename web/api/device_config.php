<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

// Optional: you can validate device_id here if needed
$deviceId = $_GET['device_id'] ?? '';

// Runtime registration flag (auto-enabled by register.php heartbeat)
$runtime = __DIR__ . '/../tmp/registration_mode.json';
$runtimeOn = false;
if (file_exists($runtime)) {
  $js = @json_decode(@file_get_contents($runtime), true);
  if (is_array($js)) {
    $until = (int)($js['until'] ?? 0);
    if ($until > time()) $runtimeOn = true;
  }
}

$reg = $runtimeOn || (bool) env('REGISTRATION_MODE', false);

echo json_encode([
  'ok' => true,
  'device_id' => $deviceId,
  'reg_mode' => $reg,
  'school_start' => (string) env('SCHOOL_START','07:15'),
  'school_end' => (string) env('SCHOOL_END','15:00'),
]);
