<?php
/**
 * Cron: Retry failed sends with backoff.
 * Crontab: *\/5 * * * * /usr/bin/php /path/scripts/retry_failed.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
set_time_limit(0);

$retryCfg = $GLOBALS['APP']['retry'];
$backoffSeconds = (int)$retryCfg['backoff_seconds'];
$maxAttempts    = (int)$retryCfg['max_attempts'];

$rows = DB::fetchAll(
    "SELECT * FROM leads
     WHERE outreach_status = 'failed'
       AND whatsapp_status = 'valid'
       AND (last_outbound_at IS NULL OR TIMESTAMPDIFF(SECOND, last_outbound_at, NOW()) > ?)
     LIMIT 10",
    [$backoffSeconds]
);

$count = 0;
foreach ($rows as $lead) {
    // Count past attempts for this lead's first outreach
    $attempts = DB::fetch(
        "SELECT COUNT(*) AS c FROM messages WHERE lead_id = ? AND is_first_outreach = 1",
        [$lead['id']]
    );
    if ((int)$attempts['c'] >= $maxAttempts) {
        LeadRepository::setOutreachStatus((int)$lead['id'], 'blocked');
        AppLogger::warn('retry_blocked_max_attempts', ['lead_id' => $lead['id']], 'retry');
        continue;
    }

    if (MessageRepository::alreadyOutreached((int)$lead['id'])) {
        LeadRepository::setOutreachStatus((int)$lead['id'], 'sent');
        continue;
    }

    LeadRepository::setOutreachStatus((int)$lead['id'], 'new');
    $count++;
}
echo "[" . date('c') . "] retry_failed: rescheduled=$count\n";
AppLogger::info('retry_tick', ['rescheduled' => $count], 'retry');
