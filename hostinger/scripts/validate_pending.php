<?php
/**
 * Cron: Validate pending phone numbers in batches.
 * Crontab: *\/10 * * * * /usr/bin/php /path/scripts/validate_pending.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
set_time_limit(0);

$rows = LeadRepository::pickPendingValidation(20);
if (!$rows) {
    echo "[" . date('c') . "] no pending validations\n";
    return;
}
$phones = array_column($rows, 'phone_e164');
$resp = NodeClient::checkBatch($phones);

if (empty($resp['ok'])) {
    AppLogger::warn('validate_batch_failed', ['resp' => $resp], 'validate');
    echo "[" . date('c') . "] batch failed\n";
    return;
}

$results = $resp['results'] ?? [];
$valid = 0; $invalid = 0; $failed = 0;
foreach ($results as $r) {
    $phone = $r['phone'] ?? null;
    $status = $r['status'] ?? null;
    if (!$phone || !$status) { $failed++; continue; }
    $lead = LeadRepository::findByPhone($phone);
    if (!$lead) { $failed++; continue; }
    LeadRepository::setWhatsappStatus((int)$lead['id'], $status);
    if ($status === 'valid') $valid++;
    else if ($status === 'not_on_whatsapp') {
        LeadRepository::setOutreachStatus((int)$lead['id'], 'skipped');
        $invalid++;
    } else {
        $failed++;
    }
}
AppLogger::info('validate_tick', ['valid' => $valid, 'invalid' => $invalid, 'failed' => $failed], 'validate');
echo "[" . date('c') . "] validate tick — valid=$valid invalid=$invalid failed=$failed\n";
