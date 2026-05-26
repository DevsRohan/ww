<?php
/**
 * Node.js (Hugging Face) HTTP client wrapper.
 * Uses cURL — safer than file_get_contents on shared hosting.
 */

class NodeClient
{
    public static function sendMessage(string $phone, string $message, bool $immediate = false, array $meta = []): array
    {
        return self::post('/send-message', [
            'phone'     => $phone,
            'message'   => $message,
            'immediate' => $immediate,
            'meta'      => $meta,
            'jobId'     => $meta['jobId'] ?? null,
        ]);
    }

    public static function checkNumber(string $phone): array
    {
        return self::post('/check-number', ['phone' => $phone]);
    }

    public static function checkBatch(array $phones): array
    {
        return self::post('/check-number', ['phones' => $phones]);
    }

    public static function status(): array
    {
        return self::get('/status');
    }

    public static function health(): array
    {
        return self::get('/health');
    }

    public static function setQueueDelays(int $minMs, int $maxMs): array
    {
        return self::post('/queue/delays', ['minMs' => $minMs, 'maxMs' => $maxMs]);
    }

    public static function clearQueue(): array
    {
        return self::post('/queue/clear', []);
    }

    public static function pauseQueue(): array
    {
        return self::post('/queue/pause', []);
    }

    public static function resumeQueue(): array
    {
        return self::post('/queue/resume', []);
    }

    public static function restart(): array
    {
        return self::post('/restart', []);
    }

    // -------- HTTP --------------------------------------------------

    private static function baseUrl(): string
    {
        $url = $GLOBALS['APP']['node']['api_url'] ?? '';
        if (!$url) {
            return '';
        }
        return rtrim($url, '/');
    }

    private static function apiKey(): string
    {
        return (string)($GLOBALS['APP']['node']['api_key'] ?? '');
    }

    private static function timeout(): int
    {
        return (int)($GLOBALS['APP']['node']['timeout'] ?? 20);
    }

    private static function get(string $path): array
    {
        return self::request('GET', $path, null);
    }

    private static function post(string $path, array $body): array
    {
        return self::request('POST', $path, $body);
    }

    private static function request(string $method, string $path, ?array $body): array
    {
        $base = self::baseUrl();
        if (!$base) {
            return ['ok' => false, 'error' => 'node_url_not_configured'];
        }
        $url = $base . $path;
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-key: ' . self::apiKey(),
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::timeout(),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? new stdClass(), JSON_UNESCAPED_UNICODE);
        } else {
            $opts[CURLOPT_HTTPGET] = true;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            AppLogger::error('node_client_curl_error', ['url' => $url, 'err' => $err], 'node');
            return ['ok' => false, 'error' => 'curl_error', 'detail' => $err];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'invalid_response', 'http' => $code, 'raw' => substr((string)$raw, 0, 500)];
        }
        $decoded['http'] = $code;
        return $decoded;
    }
}
