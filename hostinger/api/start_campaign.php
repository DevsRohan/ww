<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$name = trim((string)($body['name'] ?? ('Campaign ' . date('d M H:i'))));

// Sync delays to engine
$min = (int)($GLOBALS['APP']['campaign']['min_delay'] ?? 120);
$max = (int)($GLOBALS['APP']['campaign']['max_delay'] ?? 300);
NodeClient::setQueueDelays($min * 1000, $max * 1000);
NodeClient::resumeQueue();

// Mark all eligible leads as queued
DB::execute(
    'UPDATE leads SET outreach_status = "queued", updated_at = NOW()
     WHERE whatsapp_status = "valid"
       AND outreach_status IN ("new","failed","queued")
       AND last_outbound_at IS NULL'
);

// Count actual queued leads (don't rely on rowCount which returns 0 when value unchanged)
$countRow = DB::fetch(
    'SELECT COUNT(*) AS c FROM leads
     WHERE whatsapp_status = "valid"
       AND outreach_status = "queued"
       AND last_outbound_at IS NULL'
);
$queued = (int)($countRow['c'] ?? 0);

$campaignId = DB::insert(
    'INSERT INTO campaigns (name, status, total_leads, daily_limit, min_delay_seconds, max_delay_seconds, started_at)
     VALUES (?, "running", ?, ?, ?, ?, NOW())',
    [
        $name,
        $queued,
        (int)($GLOBALS['APP']['campaign']['daily_limit'] ?? 60),
        $min, $max
    ]
);

AppLogger::info('campaign_started', ['campaign_id' => $campaignId, 'queued' => $queued], 'campaign');
json_ok(['campaign_id' => $campaignId, 'queued' => $queued]);
