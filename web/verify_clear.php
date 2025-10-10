<?php
// verify_clear.php - Verify that database has been cleared
require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = pdo();

    $tables = [
        'users',
        'attendance',
        'devices',
        'settings',
        'remember_tokens',
        'audit_logs',
        'notifications',
        'email_queue',
        'sms_queue',
        'backup_logs',
        'restore_logs',
        'system_settings',
        'attendance_rules',
        'holiday_calendar',
        'device_logs',
        'user_sessions'
    ];

    echo "Database verification - Record counts:\n";
    echo str_repeat("=", 40) . "\n";

    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $stmt->fetch()['count'];
        echo sprintf("%-20s: %d records\n", $table, $count);
    }

    echo str_repeat("=", 40) . "\n";
    echo "Database clearing verification complete!\n";

} catch (Exception $e) {
    echo "Error verifying database: " . $e->getMessage() . "\n";
}
?>
