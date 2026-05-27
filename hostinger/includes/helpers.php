<?php
/**
 * Generic helpers — sanitize, JSON I/O, phone normalize, parsing,
 * timestamp formatting, etc.
 */

// -------- Sanitize -----------------------------------------------------

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_text($value): string {
    if ($value === null) return '';
    $value = (string)$value;
    $value = trim($value);
    $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return $result !== null ? $result : $value;
}

function sanitize_int($value, int $default = 0): int {
    if (is_numeric($value)) return (int)$value;
    return $default;
}

function sanitize_float($value, float $default = 0.0): float {
    if (is_numeric($value)) return (float)$value;
    return $default;
}

function sanitize_email($value): ?string {
    $v = filter_var(trim((string)$value), FILTER_VALIDATE_EMAIL);
    return $v ?: null;
}

// -------- JSON I/O ----------------------------------------------------

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok($data = []): void {
    json_response(['ok' => true] + (is_array($data) ? $data : ['data' => $data]));
}

function json_fail(string $error, int $status = 400, array $extra = []): void {
    json_response(['ok' => false, 'error' => $error] + $extra, $status);
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// -------- Phone normalization ----------------------------------------

function normalize_phone(?string $raw, string $defaultCountry = '91'): ?string {
    if ($raw === null) return null;
    $digits = preg_replace('/\D+/', '', $raw);
    if (!$digits) return null;
    if (str_starts_with($digits, '0')) {
        $digits = $defaultCountry . ltrim($digits, '0');
    } elseif (strlen($digits) === 10) {
        $digits = $defaultCountry . $digits;
    }
    $len = strlen($digits);
    if ($len < 11 || $len > 15) return null;
    return $digits;
}

function format_phone_display(?string $e164): string {
    if (!$e164) return '';
    if (strlen($e164) === 12 && str_starts_with($e164, '91')) {
        $cc = substr($e164, 0, 2);
        $rest = substr($e164, 2);
        return '+' . $cc . ' ' . substr($rest, 0, 5) . ' ' . substr($rest, 5);
    }
    return '+' . $e164;
}

// -------- Address parsing --------------------------------------------

/**
 * Parse free-text address into locality, city, state.
 * Best-effort — Indian Google-Maps style addresses.
 */
function parse_address(string $address): array {
    $address = trim($address);
    if ($address === '') {
        return ['locality' => null, 'city' => null, 'state' => null];
    }
    // Strip pincode/country at tail
    $clean = preg_replace('/,?\s*\d{6}.*$/u', '', $address);
    $clean = preg_replace('/,?\s*India\s*$/iu', '', $clean);

    $parts = array_map('trim', explode(',', $clean));
    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
    $n = count($parts);
    $state = $n >= 1 ? $parts[$n - 1] : null;
    $city  = $n >= 2 ? $parts[$n - 2] : null;
    $locality = $n >= 3 ? $parts[$n - 3] : ($n >= 1 && $n <= 2 ? $parts[0] : null);
    return [
        'locality' => $locality ? mb_substr($locality, 0, 120) : null,
        'city'     => $city     ? mb_substr($city, 0, 120)     : null,
        'state'    => $state    ? mb_substr($state, 0, 120)    : null,
    ];
}

// -------- Website detection ------------------------------------------

function detect_website(?string $url): string {
    if (!$url) return 'no_website';
    $url = trim($url);
    if ($url === '' || strtolower($url) === 'n/a' || strtolower($url) === 'na' || $url === '-') {
        return 'no_website';
    }
    if (preg_match('#^https?://#i', $url) || preg_match('#^[a-z0-9-]+\.[a-z]{2,}#i', $url)) {
        return 'has_website';
    }
    return 'unknown';
}

// -------- Pitch type --------------------------------------------------

function pitch_type_from_website(string $websiteStatus): string {
    return $websiteStatus === 'has_website' ? 'type_a' : ($websiteStatus === 'no_website' ? 'type_b' : 'unknown');
}

// -------- Language preference (state -> language) --------------------

function language_for_state(?string $state): string {
    if (!$state) return 'hinglish';
    $s = mb_strtolower($state);
    $hinglishStates = ['bihar','jharkhand','uttar pradesh','up','delhi','rajasthan','madhya pradesh','mp','chhattisgarh','uttarakhand','himachal pradesh'];
    foreach ($hinglishStates as $k) if (str_contains($s, $k)) return 'hinglish';
    if (str_contains($s, 'gujarat')) return 'gujarati_english';
    if (str_contains($s, 'maharashtra')) return 'marathi_english';
    if (str_contains($s, 'punjab') || str_contains($s, 'haryana')) return 'punjabi_hinglish';
    if (str_contains($s, 'tamil')) return 'business_english';
    if (str_contains($s, 'karnataka')) return 'business_english';
    if (str_contains($s, 'kerala')) return 'business_english';
    if (str_contains($s, 'andhra') || str_contains($s, 'telangana')) return 'business_english';
    if (str_contains($s, 'bengal')) return 'bengali_english';
    if (str_contains($s, 'odisha')) return 'business_english';
    if (str_contains($s, 'assam')) return 'business_english';
    return 'hinglish';
}

// -------- Time helpers -----------------------------------------------

function time_ago(?string $datetime): string {
    if (!$datetime) return '';
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 2592000) return floor($diff/86400) . 'd ago';
    return date('d M Y', $ts);
}

function now_mysql(): string { return date('Y-m-d H:i:s'); }

// -------- Misc --------------------------------------------------------

function random_token(int $bytes = 24): string {
    return bin2hex(random_bytes($bytes));
}

function array_pluck(array $rows, string $key): array {
    $out = [];
    foreach ($rows as $r) if (isset($r[$key])) $out[] = $r[$key];
    return $out;
}

function uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ensure_dir(string $path): void {
    if (!is_dir($path)) @mkdir($path, 0755, true);
}

function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}
