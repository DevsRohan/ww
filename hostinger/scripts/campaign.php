<?php
/**
 * Cron: Campaign runner.
 * Picks up queueable leads in small batches, generates personalized
 * messages with Groq, and dispatches to the HF engine queue.
 *
 * Crontab (Hostinger):
 *   *\/2 * * * * /usr/bin/php /home/USER/public_html/scripts/campaign.php >> /home/USER/public_html/logs/campaign.log 2>&1
 *
 * Designed to be re-entrant safe: a row-level lock (outreach_status = 'sending')
 * prevents two cron invocations from picking the same lead.
 */

if (PHP_SAPI !== 'cli') {
    // Allow http call but require API key for safety
    require_once __DIR__ . '/../config/bootstrap.php';
    Auth::requireApi();
} else {
    require_once __DIR__ . '/../config/bootstrap.php';
}

set_time_limit(300);

$cfg = $GLOBALS['APP']['campaign'];
$now = (int)date('G');
// Active hours check removed — campaigns run 24/7 now (controlled by start/pause button)
// If you want hour restrictions, set campaign_active_hours_start and campaign_active_hours_end in settings

// Daily cap
$sentToday = LeadRepository::dailyOutboundCount();
if ($sentToday >= $cfg['daily_limit']) {
    echo "[" . date('c') . "] daily_limit reached ({$sentToday}/{$cfg['daily_limit']}), skipping.\n";
    if (PHP_SAPI !== 'cli') json_ok(['skipped' => 'daily_limit_reached']);
    return;
}
$remaining = $cfg['daily_limit'] - $sentToday;
$batch = (int) min($cfg['batch_size'], $remaining);

if ($batch <= 0) {
    if (PHP_SAPI !== 'cli') json_ok(['picked' => 0]);
    return;
}

// Sync engine delays each run (in case settings changed)
NodeClient::setQueueDelays((int)$cfg['min_delay'] * 1000, (int)$cfg['max_delay'] * 1000);
NodeClient::resumeQueue();

$picked = 0; $errors = 0;
DB::transaction(function() use (&$picked, $batch) {
    $rows = DB::fetchAll(
        "SELECT id FROM leads
         WHERE whatsapp_status = 'valid'
           AND outreach_status IN ('new','queued','failed')
           AND last_outbound_at IS NULL
         ORDER BY id ASC
         LIMIT " . (int)$batch . "
         FOR UPDATE"
    );
    foreach ($rows as $r) {
        DB::execute("UPDATE leads SET outreach_status = 'sending', updated_at = NOW() WHERE id = ?", [$r['id']]);
        $picked++;
    }
});

$leads = $picked > 0 ? DB::fetchAll(
    "SELECT * FROM leads WHERE outreach_status = 'sending' ORDER BY updated_at DESC LIMIT " . (int)$batch
) : [];

foreach ($leads as $lead) {
    try {
        // Skip only if message was already SENT (not just queued)
        $existingMsg = DB::fetch(
            "SELECT id, status FROM messages WHERE lead_id = ? AND is_first_outreach = 1 AND status IN ('sent','delivered','read') LIMIT 1",
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
            AppLogger::warn('campaign_enqueue_failed', ['lead_id' => $lead['id'], 'resp' => $resp], 'campaign');
            continue;
        }
        LeadRepository::setOutreachStatus((int)$lead['id'], 'queued');
        DB::execute(
            'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "campaign_queued", ?)',
            [$lead['id'], 'cron picked, enqueued at engine']
        );
    } catch (\Throwable $e) {
        $errors++;
        LeadRepository::setOutreachStatus((int)$lead['id'], 'failed');
        AppLogger::error('campaign_loop_error', ['lead_id' => $lead['id'], 'err' => $e->getMessage()], 'campaign');
    }
}

AppLogger::info('campaign_tick', ['picked' => $picked, 'errors' => $errors, 'sent_today' => $sentToday], 'campaign');
echo "[" . date('c') . "] campaign tick — picked=$picked errors=$errors sent_today=$sentToday\n";
if (PHP_SAPI !== 'cli') json_ok(['picked' => $picked, 'errors' => $errors, 'sent_today' => $sentToday]);
