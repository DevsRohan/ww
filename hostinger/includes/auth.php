<?php
/**
 * Auth — dashboard session login + webhook HMAC verification + API guards.
 */

class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = $GLOBALS['APP']['auth']['session_name'] ?? 'wcrm_sess';
            session_name($name);
            $cookieParams = [
                'lifetime' => $GLOBALS['APP']['auth']['session_lifetime'] ?? 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            session_set_cookie_params($cookieParams);
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::startSession();
        if (empty($_SESSION['user_id'])) return null;
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . ($GLOBALS['APP']['public_path'] ?? '/') . 'login.php');
            exit;
        }
    }

    public static function requireApi(): void
    {
        if (!self::check()) {
            json_fail('unauthorized', 401);
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::startSession();

        // Throttle
        $ip = client_ip();
        $bucketKey = 'login_throttle_' . md5($email . '|' . $ip);
        $bucket = $_SESSION[$bucketKey] ?? ['count' => 0, 'until' => 0];
        if ($bucket['until'] > time()) {
            return false;
        }

        $row = DB::fetch('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1', [$email]);
        if (!$row || !$row['is_active'] || !password_verify($password, $row['password_hash'])) {
            $bucket['count']++;
            $maxAttempts = $GLOBALS['APP']['auth']['login_throttle'] ?? 5;
            $lockout = $GLOBALS['APP']['auth']['login_lockout'] ?? 600;
            if ($bucket['count'] >= $maxAttempts) {
                $bucket['until'] = time() + $lockout;
                $bucket['count'] = 0;
            }
            $_SESSION[$bucketKey] = $bucket;
            AppLogger::warn('login_failed', ['email' => $email, 'ip' => $ip], 'auth');
            return false;
        }

        unset($_SESSION[$bucketKey]);
        // Regenerate session id to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['user'] = [
            'id'    => (int)$row['id'],
            'name'  => $row['name'],
            'email' => $row['email'],
            'role'  => $row['role'],
        ];
        DB::execute('UPDATE users SET last_login_at = ? WHERE id = ?', [now_mysql(), $row['id']]);
        AppLogger::info('login_success', ['user_id' => (int)$row['id']], 'auth');
        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Verify HMAC-SHA256 signature on webhook request.
     * Header: X-Webhook-Signature = hex-hmac(secret, raw body)
     */
    public static function verifyWebhookSignature(string $rawBody, string $providedSignature): bool
    {
        $secret = $GLOBALS['APP']['webhook']['secret'] ?? '';
        if ($secret === '' || $providedSignature === '') return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $providedSignature);
    }
}
