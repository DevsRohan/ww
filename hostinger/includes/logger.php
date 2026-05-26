<?php
/**
 * Logger — writes to file + DB.
 * File logs are quick and durable; DB logs are queryable from dashboard.
 */

class AppLogger
{
    private static function write(string $level, string $source, string $message, array $context = []): void
    {
        $line = sprintf("[%s] [%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $source,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        $logDir = $GLOBALS['APP']['paths']['logs'] ?? (APP_ROOT . '/logs');
        ensure_dir($logDir);
        $file = $logDir . '/app.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        try {
            DB::execute(
                'INSERT INTO logs (level, source, message, context) VALUES (?, ?, ?, ?)',
                [$level, $source, $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null]
            );
        } catch (\Throwable $e) {
            // DB unavailable — file log is fallback
            @file_put_contents($file, "[LOGGER-DB-FAIL] " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    public static function debug(string $msg, array $ctx = [], string $src = 'app'): void { self::write('debug', $src, $msg, $ctx); }
    public static function info (string $msg, array $ctx = [], string $src = 'app'): void { self::write('info',  $src, $msg, $ctx); }
    public static function warn (string $msg, array $ctx = [], string $src = 'app'): void { self::write('warn',  $src, $msg, $ctx); }
    public static function error(string $msg, array $ctx = [], string $src = 'app'): void { self::write('error', $src, $msg, $ctx); }
    public static function critical(string $msg, array $ctx = [], string $src = 'app'): void { self::write('critical', $src, $msg, $ctx); }
}
