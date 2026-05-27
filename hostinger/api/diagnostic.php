<?php
/**
 * Hostinger PHP-side diagnostic dump. Mirrors the HF /debug.json approach.
 *
 * GET /api/diagnostic.php
 *  - Auth required (logged-in admin) so secret prefixes/suffixes never leak.
 *  - Returns DB connectivity, settings (with length + masked prefix/suffix
 *    for secrets), live engine ping, recent logs, recent webhook events,
 *    lead/message counts.
 *
 * Use this side-by-side with HF /debug.json to verify the two halves agree.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

function maskWithLen($v) {
    $len = strlen((string)$v);
    if ($len === 0) return ['set' => false, 'length' => 0, 'preview' => null];
    $preview = $len <= 6
        ? str_repeat('•', $len)
        : substr($v, 0, 3) . '…' . substr($v, -3);
    return ['set' => true, 'length' => $len, 'preview' => $preview];
}

// ---- DB check ----------------------------------------------------------------
$db = ['ok' => false];
try {
    DB::pdo();
    $db['ok'] = true;
    $db['driver'] = DB::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    $db['server_version'] = DB::pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    $db['leads'] = (int) (DB::fetch('SELECT COUNT(*) AS c FROM leads')['c'] ?? 0);
    $db['messages'] = (int) (DB::fetch('SELECT COUNT(*) AS c FROM messages')['c'] ?? 0);
    $db['campaigns'] = (int) (DB::fetch('SELECT COUNT(*) AS c FROM campaigns')['c'] ?? 0);
    $db['webhook_events'] = (int) (DB::fetch('SELECT COUNT(*) AS c FROM webhook_events')['c'] ?? 0);
    $db['logs'] = (int) (DB::fetch('SELECT COUNT(*) AS c FROM logs')['c'] ?? 0);
} catch (\Throwable $e) {
    $db['error'] = $e->getMessage();
}

// ---- Lead breakdown ----------------------------------------------------------
$leadsByWa = [];
foreach (DB::fetchAll("SELECT whatsapp_status AS s, COUNT(*) AS c FROM leads GROUP BY whatsapp_status") as $r) {
    $leadsByWa[$r['s']] = (int)$r['c'];
}
$leadsByOut = [];
foreach (DB::fetchAll("SELECT outreach_status AS s, COUNT(*) AS c FROM leads GROUP BY outreach_status") as $r) {
    $leadsByOut[$r['s']] = (int)$r['c'];
}

// ---- Settings (with length + safe preview for secrets) -----------------------
$settings = [];
foreach (DB::fetchAll('SELECT setting_key, setting_value, setting_type FROM settings ORDER BY setting_key') as $r) {
    if ($r['setting_type'] === 'secret') {
        $settings[$r['setting_key']] = maskWithLen($r['setting_value']);
    } else {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}

// ---- Live engine ping (best-effort) ------------------------------------------
$engine = ['ok' => false];
try {
    $health = NodeClient::health();
    $status = NodeClient::status();
    $engine = [
        'ok' => !empty($health['ok']) || !empty($status['ok']),
        'http_health' => $health['http'] ?? null,
        'http_status' => $status['http'] ?? null,
        'whatsapp' => $status['status'] ?? ($health['whatsapp'] ?? null),
        'uptime_s' => $health['uptime_s'] ?? null,
        'rss_mb' => $health['rss_mb'] ?? null,
        'node' => $health['node'] ?? null,
        'qr_url' => rtrim((string)($GLOBALS['APP']['node']['api_url'] ?? ''), '/') . '/qr',
        'debug_url' => rtrim((string)($GLOBALS['APP']['node']['api_url'] ?? ''), '/') . '/debug',
    ];
    if (!empty($status['error']) && empty($status['ok'])) $engine['error'] = $status['error'];
    if (!empty($health['error']) && empty($health['ok'])) $engine['error'] = $health['error'];
} catch (\Throwable $e) {
    $engine = ['ok' => false, 'error' => $e->getMessage()];
}

// ---- Recent logs / webhook events --------------------------------------------
$recentLogs = DB::fetchAll(
    'SELECT id, level, source, message, context, created_at FROM logs ORDER BY id DESC LIMIT 30'
);
foreach ($recentLogs as &$L) {
    if ($L['context']) $L['context'] = json_decode($L['context'], true);
}
unset($L);

$recentHooks = DB::fetchAll(
    'SELECT id, event_id, event_type, processed, attempts, last_error, created_at, processed_at
     FROM webhook_events ORDER BY id DESC LIMIT 20'
);

// ---- Health checks summary (boolean roll-up) ---------------------------------
$checks = [
    'db_connected'              => !empty($db['ok']),
    'node_api_url_configured'   => !empty($GLOBALS['APP']['node']['api_url']),
    'node_api_key_configured'   => !empty($GLOBALS['APP']['node']['api_key']),
    'webhook_secret_configured' => !empty($GLOBALS['APP']['webhook']['secret']),
    'socket_url_configured'     => !empty($GLOBALS['APP']['socket_url']),
    'groq_api_key_configured'   => !empty($GLOBALS['APP']['groq']['api_key']),
    'engine_reachable'          => !empty($engine['ok']),
    'whatsapp_ready'            => ($engine['whatsapp']['ready'] ?? false) === true,
    'uploads_writable'          => is_writable($GLOBALS['APP']['paths']['uploads'] ?? ''),
    'logs_writable'             => is_writable($GLOBALS['APP']['paths']['logs'] ?? ''),
];

$overall = 'misconfigured';
if ($checks['db_connected'] && $checks['node_api_url_configured'] && $checks['node_api_key_configured'] && $checks['webhook_secret_configured']) {
    $overall = $checks['engine_reachable']
        ? ($checks['whatsapp_ready'] ? 'healthy' : 'engine_not_ready')
        : 'engine_unreachable';
}

json_ok([
    'overall' => $overall,
    'service' => 'hostinger-php-backend',
    'version' => $GLOBALS['APP']['app_version'] ?? '1.0.0',
    'iso' => date('c'),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'php_extensions' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'fileinfo' => extension_loaded('fileinfo'),
        'json' => extension_loaded('json'),
        'openssl' => extension_loaded('openssl'),
    ],
    'paths' => [
        'app_root' => APP_ROOT,
        'uploads' => $GLOBALS['APP']['paths']['uploads'] ?? null,
        'logs' => $GLOBALS['APP']['paths']['logs'] ?? null,
    ],
    'checks' => $checks,
    'db' => $db,
    'leads_by_whatsapp_status' => $leadsByWa,
    'leads_by_outreach_status' => $leadsByOut,
    'settings' => $settings,
    'engine' => $engine,
    'recent_logs' => $recentLogs,
    'recent_webhook_events' => $recentHooks,
]);
