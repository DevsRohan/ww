<?php
/**
 * Settings Repository — DB-backed key-value store.
 */

class SettingsRepository
{
    /** Cache for current request */
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        try {
            $rows = DB::fetchAll('SELECT setting_key, setting_value, setting_type FROM settings');
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = self::cast($r['setting_value'], $r['setting_type']);
        }
        self::$cache = $out;
        return $out;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, $value, string $type = 'string', bool $isPublic = false, ?string $description = null): void
    {
        $store = self::stringify($value, $type);
        DB::execute(
            'INSERT INTO settings (setting_key, setting_value, setting_type, is_public, description)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                     setting_type  = VALUES(setting_type),
                                     is_public     = VALUES(is_public),
                                     description   = COALESCE(VALUES(description), description)',
            [$key, $store, $type, $isPublic ? 1 : 0, $description]
        );
        if (self::$cache !== null) self::$cache[$key] = self::cast($store, $type);
    }

    public static function setMany(array $kv): void
    {
        foreach ($kv as $k => $row) {
            $value = is_array($row) ? ($row['value'] ?? '') : $row;
            $type  = is_array($row) ? ($row['type']  ?? 'string') : 'string';
            $pub   = is_array($row) ? !empty($row['is_public']) : false;
            self::set($k, $value, $type, $pub);
        }
    }

    public static function publicAll(): array
    {
        $rows = DB::fetchAll('SELECT setting_key, setting_value, setting_type FROM settings WHERE is_public = 1');
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = self::cast($r['setting_value'], $r['setting_type']);
        }
        return $out;
    }

    public static function adminAll(): array
    {
        $rows = DB::fetchAll('SELECT setting_key, setting_value, setting_type, is_public, description FROM settings ORDER BY setting_key');
        // Mask secrets in display, also expose length so the operator can
        // verify the secret matches what's set on the HF Space side.
        foreach ($rows as &$r) {
            $r['is_secret'] = $r['setting_type'] === 'secret';
            if ($r['setting_type'] === 'secret') {
                $rawLen = strlen((string)($r['setting_value'] ?? ''));
                $r['secret_length'] = $rawLen;
                if ($rawLen > 0) {
                    $r['setting_value'] = self::maskSecret((string)$r['setting_value']);
                }
            }
        }
        return $rows;
    }

    private static function maskSecret(string $v): string
    {
        $len = strlen($v);
        if ($len <= 6) return str_repeat('•', $len);
        return substr($v, 0, 3) . str_repeat('•', max(4, $len - 6)) . substr($v, -3);
    }

    private static function cast($value, string $type)
    {
        if ($value === null) return null;
        switch ($type) {
            case 'int':    return (int)$value;
            case 'bool':   return (bool)((int)$value);
            case 'json':   $j = json_decode((string)$value, true); return is_array($j) ? $j : null;
            case 'secret':
            case 'string':
            default:       return (string)$value;
        }
    }

    private static function stringify($value, string $type): string
    {
        switch ($type) {
            case 'int':    return (string)(int)$value;
            case 'bool':   return $value ? '1' : '0';
            case 'json':   return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            default:       return (string)$value;
        }
    }
}
