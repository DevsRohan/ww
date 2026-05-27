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

// ---- Auto-validate pending leads first (so a fresh import isn't blocked) ----
$validated = 0; $invalidated = 0;
try {
    $pending = DB::fetchAll(
        "SELECT id, phone_e164 FROM leads WHERE whatsapp_status = 'pending' LIMIT 100"
    );
    if (!empty($pending)) {
        $phones = array_column($pending, 'phone_e164');
        $resp = NodeClient::checkBatch($phones);
        if (!empty($resp['ok']) && !empty($resp['results']) && is_array($resp['results'])) {
            foreach ($resp['results'] as $r) {
                $phone = $r['phone'] ?? null;
                $status = $r['status'] ?? null;
                if (!$phone || !$status) continue;
                $ld = LeadRepository::findByPhone($phone);
                if (!$ld) continue;
                LeadRepository::setWhatsappStatus((int)$ld['id'], $status);
                if ($status === 'valid') $validated++;
                else if ($status === 'not_on_whatsapp') {
                    LeadRepository::setOutreachStatus((int)$ld['id'], 'skipped');
                    $invalidated++;
                }
            }
        }
    }
} catch (\Throwable $e) {
    AppLogger::warn('campaign_pre_validate_failed', ['err' => $e->getMessage()], 'campaign');
}

// Mark all eligible leads as queued
$rows = DB::execute(
    'UPDATE leads SET outreach_status = "queued"
     WHERE whatsapp_status = "valid"
       AND outreach_status IN ("new","failed")'
);

$campaignId = DB::insert(
    'INSERT INTO campaigns (name, status, total_leads, daily_limit, min_delay_seconds, max_delay_seconds, started_at)
     VALUES (?, "running", ?, ?, ?, ?, NOW())',
    [
        $name,
        $rows,
        (int)($GLOBALS['APP']['campaign']['daily_limit'] ?? 60),
        $min, $max
    ]
);

AppLogger::info('campaign_started', [
    'campaign_id' => $campaignId, 'queued' => $rows,
    'validated_now' => $validated, 'invalidated_now' => $invalidated,
], 'campaign');
json_ok([
    'campaign_id' => $campaignId,
    'queued' => $rows,
    'validated_now' => $validated,
    'invalidated_now' => $invalidated,
]);
