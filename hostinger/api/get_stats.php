<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$stats = LeadRepository::stats();

// Engine status (best-effort cache; live status comes via socket)
$cached = SettingsRepository::get('engine_status_cache');
if (is_string($cached)) {
    $cached = json_decode($cached, true) ?: null;
}
$stats['engine'] = $cached ?: null;

json_ok($stats);
