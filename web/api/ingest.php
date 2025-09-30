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

    // Inisialisasi NotificationManager
    require_once __DIR__ . '/../classes/NotificationManager.php';
    $notificationManager = new NotificationManager($pdo, $ENV);

    $pdo->beginTransaction();

    $insert = $pdo->prepare('INSERT INTO attendance (user_id, device_id, ts, uid_hex, raw_json) VALUES (?, ?, ?, ?, ?)');
    $findUser = $pdo->prepare('SELECT id, name, room, role FROM users WHERE uid_hex = ?');
    $createUser = $pdo->prepare('INSERT INTO users (name, uid_hex, room, role) VALUES (?, ?, ?, ?)');

    $saved = 0;
    $errors = [];
    $notifications = [];

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

        // Cek apakah hari ini adalah hari libur
        $today = new DateTime('today', new DateTimeZone(env('APP_TZ', 'Asia/Jakarta')));
        $isHoliday = false;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM holiday_calendar WHERE holiday_date = ?");
        $stmt->execute([$today->format('Y-m-d')]);
        if ($stmt->fetchColumn() > 0) {
            $isHoliday = true;
        }
        
        // Cek apakah hari ini adalah weekend
        $isWeekend = in_array($today->format('N'), [6, 7]); // 6 = Sabtu, 7 = Minggu
        
        // Skip jika hari libur atau weekend (kecuali jika diaktifkan)
        if (($isHoliday || $isWeekend) && !env('ALLOW_WEEKEND_HOLIDAY_SCAN', false)) {
            $notifications[] = [
                'index' => $i,
                'message' => 'Hari libur - scan tidak dicatat',
                'is_holiday' => $isHoliday,
                'is_weekend' => $isWeekend
            ];
            continue;
        }

        // Resolve user id
        $userId = null;
        $userName = null;
        $userRoom = null;
        $userRole = null;
        
        $findUser->execute([$uidHex]);
        $u = $findUser->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $userId = (int)$u['id'];
            $userName = $u['name'];
            $userRoom = $u['room'];
            $userRole = $u['role'];
        } elseif ($autoCreate) {
            $name = 'Unknown ' . strtoupper($uidHex);
            $createUser->execute([$name, $uidHex, '', 'student']);
            $userId = (int)$pdo->lastInsertId();
            $userName = $name;
            $userRoom = '';
            $userRole = 'student';
        }

        if (!$userId) {
            // Izinkan penyimpanan log unknown saat mode pendaftaran aktif
            $regMode = (bool) env('REGISTRATION_MODE', false);
            if (!$regMode) {
                // Cek runtime flag dari register.php heartbeat
                try {
                    $runtimePath = __DIR__ . '/../tmp/registration_mode.json';
                    if (file_exists($runtimePath)) {
                        $js = @json_decode(@file_get_contents($runtimePath), true);
                        if (is_array($js)) {
                            $until = (int)($js['until'] ?? 0);
                            $regMode = $until > time();
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            if ($regMode) {
                // Simpan attendance dengan user_id NULL supaya muncul di register.php (unknown_uids)
                $rawExtras = [
                    'is_late' => false,
                    'late_minutes' => 0,
                    'is_holiday' => $isHoliday,
                    'is_weekend' => $isWeekend,
                    'user_name' => null,
                    'user_room' => null,
                ];
                $rawJson = json_encode(array_merge($e, $rawExtras), JSON_UNESCAPED_SLASHES);
                $insert->execute([null, $deviceId, $tsDb, $uidHex, $rawJson]);
                $saved++;
                // Jangan lanjut ke hitung terlambat; lompat ke iterasi berikutnya
                continue;
            } else {
                $errors[] = ['index' => $i, 'error' => 'User not found and auto-create disabled'];
                continue;
            }
        }

        // Cek keterlambatan
        $schoolStart = env('SCHOOL_START', '07:30');
        $lateThreshold = env('LATE_THRESHOLD', 15); // menit
        
        $schoolStartTime = new DateTime($today->format('Y-m-d') . ' ' . $schoolStart, new DateTimeZone(env('APP_TZ', 'Asia/Jakarta')));
        $lateTime = clone $schoolStartTime;
        $lateTime->modify("+{$lateThreshold} minutes");
        
        $isLate = $dt > $lateTime;
        $lateMinutes = 0;
        
        if ($isLate) {
            $lateMinutes = $dt->diff($schoolStartTime)->i;
            
            // Kirim notifikasi terlambat
            $notificationManager->notifyLateAttendance(
                $userId, 
                $userName, 
                $lateMinutes
            );
        }

        // Tambahkan informasi tambahan ke raw_json
        $e['is_late'] = $isLate;
        $e['late_minutes'] = $lateMinutes;
        $e['is_holiday'] = $isHoliday;
        $e['is_weekend'] = $isWeekend;
        $e['user_name'] = $userName;
        $e['user_room'] = $userRoom;

        $rawJson = json_encode($e, JSON_UNESCAPED_SLASHES);
        $insert->execute([$userId, $deviceId, $tsDb, $uidHex, $rawJson]);
        $saved++;

               // Log ke audit
               $stmt = $pdo->prepare("
                   INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW())
               ");
               $stmt->execute([
                   $userId, 
                   'attendance_scan', 
                   'attendance',
                   $userId,
                   json_encode(['message' => "RFID scan - " . ($isLate ? "Terlambat {$lateMinutes} menit" : "Tepat waktu")]), 
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);

        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    $pdo->commit();

    echo json_encode(['ok' => true, 'saved' => $saved, 'errors' => $errors, 'notifications' => $notifications]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}

