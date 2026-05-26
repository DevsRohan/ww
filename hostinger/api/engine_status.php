<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$status = NodeClient::status();
$health = NodeClient::health();

if (!empty($status['ok'])) {
    SettingsRepository::set('engine_status_cache', json_encode([
        'state' => $status['status']['state'] ?? null,
        'info'  => $status['status']['info']  ?? null,
        'last_seen' => date('c'),
    ], JSON_UNESCAPED_UNICODE), 'json', false);
}

json_ok([
    'status' => $status,
    'health' => $health,
    'qr_url' => rtrim((string)($GLOBALS['APP']['node']['api_url'] ?? ''), '/') . '/qr',
]);
