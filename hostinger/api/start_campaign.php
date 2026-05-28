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

// === SEND ONLY THE FIRST LEAD IMMEDIATELY (rest go via queue one-by-one) ===
$picked = 0; $errors = 0; $sent = 0;

if ($queued > 0) {
    // Pick only 1 lead for immediate send
    $row = DB::fetch(
        "SELECT id FROM leads
         WHERE whatsapp_status = 'valid'
           AND outreach_status = 'queued'
           AND last_outbound_at IS NULL
         ORDER BY id ASC LIMIT 1"
    );
    if ($row) {
        DB::execute("UPDATE leads SET outreach_status = 'sending', updated_at = NOW() WHERE id = ?", [$row['id']]);
        $picked = 1;

        $lead = LeadRepository::findById((int)$row['id']);
        if ($lead) {
            try {
                $existingMsg = DB::fetch(
                    "SELECT id FROM messages WHERE lead_id = ? AND is_first_outreach = 1 AND status IN ('sent','delivered','read') LIMIT 1",
                    [$lead['id']]
                );
                if ($existingMsg) {
                    LeadRepository::setOutreachStatus((int)$lead['id'], 'sent');
                    LeadRepository::markOutbound((int)$lead['id']);
                } else {
                    $gen = Groq::generateOutreach($lead);
                    $message = $gen['message'];
                    $jobId = uuid_v4();

                    $msgId = MessageRepository::recordOutbound((int)$lead['id'], $message, null, 'queued', true, 'campaign', [
                        'jobId' => $jobId, 'used_fallback' => $gen['used_fallback']
                    ]);

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
                        LeadRepository::setOutreachStatus((int)$lead['id'], 'queued');
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                LeadRepository::setOutreachStatus((int)$lead['id'], 'failed');
                AppLogger::error('campaign_send_error', ['lead_id' => $lead['id'], 'err' => $e->getMessage()], 'campaign');
            }
        }
    }
}

AppLogger::info('campaign_started', ['campaign_id' => $campaignId, 'queued' => $queued, 'sent_now' => $sent, 'errors' => $errors], 'campaign');
json_ok(['campaign_id' => $campaignId, 'queued' => $queued, 'sent_now' => $sent, 'errors' => $errors]);
