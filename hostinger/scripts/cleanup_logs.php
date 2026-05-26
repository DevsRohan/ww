<?php
/**
 * Cron: Cleanup old logs (keep last 30 days).
 * Crontab: 0 3 * * * /usr/bin/php /path/scripts/cleanup_logs.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
set_time_limit(0);

$deletedLogs = DB::execute("DELETE FROM logs WHERE created_at < (NOW() - INTERVAL 30 DAY)");
$deletedHooks = DB::execute("DELETE FROM webhook_events WHERE created_at < (NOW() - INTERVAL 14 DAY) AND processed = 1");
echo "[" . date('c') . "] cleanup: logs=$deletedLogs hooks=$deletedHooks\n";

// Trim file logs > 5MB
$logsDir = $GLOBALS['APP']['paths']['logs'];
foreach (glob($logsDir . '/*.log') as $f) {
    if (@filesize($f) > 5 * 1024 * 1024) {
        @rename($f, $f . '.' . date('Ymd-His'));
        @file_put_contents($f, "");
    }
}
