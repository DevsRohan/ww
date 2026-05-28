<?php
/**
 * Start Campaign — marks leads as queued AND immediately sends first batch.
 * No longer depends on cron for sending — sends directly from this endpoint.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
set_time_limit(300);

$body = read_json_body();
$name = trim((string)($body['name'] ?? ('Campaign ' . date('d M H:i'))));

$cfg = $GLOBALS['APP']['campaign'];
$min = (int)($cfg['min_delay'] ?? 120);
$max = (int)($cfg['max_delay'] ?? 300);

// Sync delays to engine + resume queue
NodeClient::setQueueDelays($min * 1000, $max * 1000);
NodeClient::resumeQueue();

// Mark all eligible leads as queued
DB::execute(
    'UPDATE leads SET outreach_status = "queued", updated_at = NOW()
     WHERE whatsapp_status = "valid"
       AND outreach_status IN ("new","failed","queued")
       AND last_outbound_at IS NULL'
);

// Count queued leads
$countRow = DB::fetch(
    'SELECT COUNT(*) AS c FROM leads
     WHERE whatsapp_status = "valid"
       AND outreach_status = "queued"
       AND last_outbound_at IS NULL'
);
$queued = (int)($countRow['c'] ?? 0);

// Create campaign record
$campaignId = DB::insert(
    'INSERT INTO campaigns (name, status, total_leads, daily_limit, min_delay_seconds, max_delay_seconds, started_at)
     VALUES (?, "running", ?, ?, ?, ?, NOW())',
    [$name, $queued, (int)($cfg['daily_limit'] ?? 60), $min, $max]
);

// === ENQUEUE ALL LEADS TO NODE QUEUE (Node handles delays internally) ===
// Node queue sends one-by-one with min_delay to max_delay gap between each.
// No cron needed — Node queue is the scheduler.
$picked = 0; $errors = 0; $sent = 0;

if ($queued > 0) {
    // Pick ALL queued leads (daily limit capped)
    $dailySent = LeadRepository::dailyOutboundCount();
    $dailyRemaining = max(0, (int)($cfg['daily_limit'] ?? 60) - $dailySent);
    $batchLimit = min($queued, $dailyRemaining);

    $rows = DB::fetchAll(
        "SELECT id FROM leads
         WHERE whatsapp_status = 'valid'
           AND outreach_status = 'queued'
           AND last_outbound_at IS NULL
         ORDER BY id ASC LIMIT " . (int)$batchLimit
    );

    foreach ($rows as $r) {
        DB::execute("UPDATE leads SET outreach_status = 'sending', updated_at = NOW() WHERE id = ?", [$r['id']]);
    }
    $picked = count($rows);

    // Generate messages and enqueue each to Node
    $leads = DB::fetchAll("SELECT * FROM leads WHERE outreach_status = 'sending' ORDER BY id ASC LIMIT " . (int)$batchLimit);
    foreach ($leads as $lead) {
        try {
            // Skip if already sent
            $existingMsg = DB::fetch(
                "SELECT id FROM messages WHERE lead_id = ? AND is_first_outreach = 1 AND status IN ('sent','delivered','read') LIMIT 1",
                [$lead['id']]
            );
            if ($existingMsg) {
                LeadRepository::setOutreachStatus((int)$lead['id'], 'sent');
                LeadRepository::markOutbound((int)$lead['id']);
                continue;
            }

            $gen = Groq::generateOutreach($lead);
            $message = $gen['message'];
            $jobId = uuid_v4();

            // Record message in DB as 'queued'
            $msgId = MessageRepository::recordOutbound((int)$lead['id'], $message, null, 'queued', true, 'campaign', [
                'jobId' => $jobId, 'used_fallback' => $gen['used_fallback']
            ]);

            // Enqueue to Node (immediate=false → goes to queue with delay)
            $resp = NodeClient::sendMessage($lead['phone_e164'], $message, false, [
                'lead_id' => (int)$lead['id'],
                'message_id' => $msgId,
                'jobId' => $jobId,
                'mode' => 'campaign',
            ]);

            if (empty($resp['ok'])) {
                $errors++;
                LeadRepository::setOutreachStatus((int)$lead['id'], 'failed');
                AppLogger::warn('campaign_send_failed', ['lead_id' => $lead['id'], 'resp' => $resp], 'campaign');
            } else {
                $sent++;
                // Keep as 'queued' — Node webhook will update to 'sent' when actually sent
                LeadRepository::setOutreachStatus((int)$lead['id'], 'queued');
            }
        } catch (\Throwable $e) {
            $errors++;
            LeadRepository::setOutreachStatus((int)$lead['id'], 'failed');
            AppLogger::error('campaign_send_error', ['lead_id' => $lead['id'], 'err' => $e->getMessage()], 'campaign');
        }
    }
}

AppLogger::info('campaign_started', ['campaign_id' => $campaignId, 'queued' => $queued, 'sent_now' => $sent, 'errors' => $errors], 'campaign');
json_ok(['campaign_id' => $campaignId, 'queued' => $queued, 'sent_now' => $sent, 'errors' => $errors]);
