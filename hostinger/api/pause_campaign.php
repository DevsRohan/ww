<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

NodeClient::pauseQueue();
DB::execute('UPDATE campaigns SET status = "paused", paused_at = NOW() WHERE status = "running"');
AppLogger::info('campaign_paused', ['by' => Auth::user()['email'] ?? null], 'campaign');
json_ok([]);
