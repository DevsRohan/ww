<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

// Bring engine status
$status = NodeClient::status();
if (!empty($status['ok'])) {
    SettingsRepository::set('engine_status_cache', json_encode([
        'state' => $status['status']['state'] ?? null,
        'info'  => $status['status']['info']  ?? null,
        'last_seen' => date('c'),
    ], JSON_UNESCAPED_UNICODE), 'json', false);
}

$stats = LeadRepository::stats();
$cached = SettingsRepository::get('engine_status_cache');
if (is_string($cached)) $cached = json_decode($cached, true);
$stats['engine'] = $cached ?: null;

json_ok(['stats' => $stats, 'engine_live' => $status]);
