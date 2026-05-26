<?php
/**
 * PDO Database Connection (singleton)
 * Hostinger-friendly: lazy connect, persistent disabled (shared hosting safer),
 * UTF8MB4, throws on error, returns associative arrays.
 */

class DB
{
    /** @var PDO|null */
    private static $pdo = null;
    /** @var array */
    private static $config;

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (!self::$config) {
            $cfg = require __DIR__ . '/app.php';
            self::$config = $cfg['db'];
        }
        $cfg = self::$config;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}",
        ];
        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
            self::$pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            error_log('[DB] connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_connection_failed']);
            exit;
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function transaction(callable $cb)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $cb($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
