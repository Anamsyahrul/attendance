<?php
// bootstrap.php - loads env, sets timezone, provides PDO + helpers

// Load env as PHP array from web/config.php
$envPath = __DIR__ . '/config.php';
if (!file_exists($envPath)) {
    // fallback to default values
    $ENV = [
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'attendance',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'APP_TZ' => 'Asia/Jakarta',
        'APP_NAME' => 'Sistem Kehadiran RFID',
        'SCHOOL_NAME' => 'Sekolah Anda',
        'REQUIRE_CHECKOUT' => false,
        'WEEKLY_OFF' => [6, 7],
        'ADMIN_USER' => 'admin',
        'ADMIN_PASS' => 'admin',
    ];
} else {
    $ENV = require $envPath;
}

function env($key, $default = null) {
    global $ENV;
    return $ENV[$key] ?? $default;
}

date_default_timezone_set(env('APP_TZ', 'Asia/Jakarta'));

// Start output buffering to prevent headers already sent error
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = env('DB_HOST', '127.0.0.1');
    $db   = env('DB_NAME', 'attendance');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opt);
    return $pdo;
}

function verify_hmac(string $secret, string $message, string $provided): bool {
    $calc = hash_hmac('sha256', $message, $secret);
    if (function_exists('hash_equals')) {
        return hash_equals($calc, strtolower($provided));
    }
    return strtolower($calc) === strtolower($provided);
}

/**
 * Persist selected env values back to web/.env.php
 * Only merges provided keys; keeps others intact.
 */
function auth_username(): string {
    return (string) env('ADMIN_USER', 'admin');
}

function auth_password(): string {
    return (string) env('ADMIN_PASS', 'kelompok2');
}

function is_logged_in(): bool {
    return !empty($_SESSION['auth_logged_in']);
}

function login_user(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['auth_logged_in'] = true;
    $_SESSION['auth_username'] = auth_username();
}

function logout_user(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
    }
    session_destroy();
}

function attempt_login(string $username, string $password): bool {
    $validUser = auth_username();
    $validPass = auth_password();
    if ($username === $validUser && (function_exists('hash_equals') ? hash_equals((string)$validPass, (string)$password) : (string)$validPass === (string)$password)) {
        login_user();
        return true;
    }
    return false;
}

function require_login(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (is_logged_in()) {
        return;
    }
    $redirect = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?redirect=' . urlencode($redirect));
    exit;
}

function save_env(array $overrides): bool {
    $path = __DIR__ . '/config.php';
    $cur = file_exists($path) ? (require $path) : [];
    if (!is_array($cur)) $cur = [];
    $new = array_merge($cur, $overrides);
    // Normalize keys we expect
    $allowed = [
        'DB_HOST','DB_NAME','DB_USER','DB_PASS','APP_TZ',
        'AUTO_CREATE_UNKNOWN','SCHOOL_MODE','SCHOOL_START','SCHOOL_END',
        'SCHOOL_NAME',
        'SCHOOL_ADDRESS','SCHOOL_PHONE','SCHOOL_EMAIL','SCHOOL_WEBSITE','SCHOOL_MOTTO',
        'REQUIRE_CHECKOUT','SCHOOL_SKIP_WEEKENDS','HOLIDAYS',
        'REGISTRATION_MODE','ADMIN_USER','ADMIN_PASS','WEEKLY_OFF_DAYS'
    ];
    $out = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $new)) $out[$k] = $new[$k];
        elseif (array_key_exists($k, $cur)) $out[$k] = $cur[$k];
    }
    $export = var_export($out, true);
    $php = "<?php\nreturn " . $export . ";\n";
    return (bool)file_put_contents($path, $php);
}

function weekly_off_days(): array {
    $raw = trim((string) env('WEEKLY_OFF_DAYS', ''));
    $days = [];
    if ($raw !== '') {
        foreach (explode(',', $raw) as $part) {
            $n = (int) trim($part);
            if ($n >= 1 && $n <= 7) {
                $days[$n] = $n;
            }
        }
    }
    if (!$days && (bool) env('SCHOOL_SKIP_WEEKENDS', false)) {
        $days[6] = 6;
        $days[7] = 7;
    }
    return array_values($days);
}

function is_holiday(DateTime $date): bool {
    $holidaysStr = (string) env('HOLIDAYS', '');
    $y = $date->format('Y-m-d');
    if ($holidaysStr !== '') {
        $list = array_filter(array_map('trim', explode(',', $holidaysStr)));
        if (in_array($y, $list, true)) return true;
    }
    $weeklyOff = weekly_off_days();
    if ($weeklyOff) {
        $w = (int) $date->format('N');
        if (in_array($w, $weeklyOff, true)) return true;
    }
    return false;
}

function build_override_map(PDO $pdo, DateTime $start, DateTime $end): array {
    $map = [];
    try {
        $stmt = $pdo->prepare('SELECT uid_hex, ts, raw_json FROM attendance WHERE ts >= ? AND ts < ? AND raw_json IS NOT NULL AND JSON_EXTRACT(raw_json, \'$.type\') = \'override\'');
        $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $js = @json_decode($row['raw_json'], true);
            if (!is_array($js) || ($js['type'] ?? '') !== 'override') {
                continue;
            }
            $status = strtolower((string)($js['status'] ?? ''));
            if (!in_array($status, ['late','absent','bolos'], true)) {
                continue;
            }
            $key = strtolower((string)($row['uid_hex'] ?? ''));
            if ($key === '') {
                continue;
            }
            $ts = (string)($row['ts'] ?? '');
            if (!isset($map[$key]) || $ts >= $map[$key]['ts']) {
                $map[$key] = ['status' => $status, 'ts' => $ts];
            }
        }
    } catch (Throwable $e) {
        // ignore override fetch issues
    }
    return $map;
}

function resolve_daily_status(array $row, DateTimeZone $tz, DateTime $startDay, DateTime $lateAt, DateTime $endAt, bool $isPastDay, bool $requireCheckout, array $overrideMap): array {
    $statusMasuk = 'Tidak Hadir';
    $statusPulang = '';
    if (!empty($row['first_ts'])) {
        try {
            $firstDt = new DateTime($row['first_ts'], $tz);
            $statusMasuk = ($firstDt > $lateAt) ? 'Terlambat' : 'Hadir';
        } catch (Throwable $e) {
            $statusMasuk = 'Hadir';
        }
        if (!empty($row['last_ts'])) {
            $statusPulang = 'Pulang';
        } else {
            if ($isPastDay) {
                $statusPulang = 'Bolos';
            } else {
                $now = new DateTime('now', $tz);
                $cut21 = DateTime::createFromFormat('Y-m-d H:i', $startDay->format('Y-m-d') . ' 21:00', $tz) ?: clone $endAt;
                if ($now > $cut21 && !is_holiday($startDay)) {
                    $statusPulang = 'Bolos';
                } elseif ($requireCheckout) {
                    if ($now > $endAt && !is_holiday($startDay)) {
                        $statusPulang = 'Bolos';
                    } else {
                        $statusPulang = 'Belum Pulang';
                    }
                } else {
                    $statusPulang = 'Belum Pulang';
                }
            }
        }
    }
    $key = strtolower((string)($row['uid_hex'] ?? ''));
    $override = $overrideMap[$key]['status'] ?? '';
    if ($override === 'absent') {
        $statusMasuk = 'Tidak Hadir';
        $statusPulang = '';
    } elseif ($override === 'bolos') {
        if ($statusMasuk === 'Tidak Hadir') {
            $statusMasuk = 'Hadir';
        }
        $statusPulang = 'Bolos';
    } elseif ($override === 'late') {
        $statusMasuk = 'Terlambat';
        if ($statusPulang === '') {
            $statusPulang = 'Belum Pulang';
        }
    }
    return [$statusMasuk, $statusPulang];
}

