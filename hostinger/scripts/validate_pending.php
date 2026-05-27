<?php
/**
 * Cron: Validate pending phone numbers in batches.
 * Invalid numbers are auto-deleted.
 * Crontab: *\/10 * * * * /usr/bin/php /path/scripts/validate_pending.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
set_time_limit(300);

$rows = LeadRepository::pickPendingValidation(10);
if (!$rows) {
    echo "[" . date('c') . "] no pending validations\n";
    return;
}

echo "[" . date('c') . "] checking " . count($rows) . " leads...\n";

$valid = 0; $deleted = 0; $failed = 0;

// Validate one by one to avoid timeout issues
foreach ($rows as $row) {
    $phone = $row['phone_e164'];
    $leadId = (int)$row['id'];

    $resp = NodeClient::checkNumber($phone);

    if (empty($resp['ok'])) {
        echo "  FAIL: $phone — " . ($resp['error'] ?? 'unknown') . "\n";
        AppLogger::warn('validate_single_failed', ['phone' => $phone, 'resp' => $resp], 'validate');
        $failed++;
        continue;
    }

    $status = $resp['result']['status'] ?? 'failed';

    if ($status === 'valid') {
        LeadRepository::setWhatsappStatus($leadId, 'valid');
        echo "  VALID: $phone\n";
        $valid++;
    } else {
        // Not on WhatsApp — delete the lead completely
        DB::execute('DELETE FROM messages WHERE lead_id = ?', [$leadId]);
        DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$leadId]);
        DB::execute('DELETE FROM leads WHERE id = ?', [$leadId]);
        echo "  DELETED: $phone (not on whatsapp)\n";
        $deleted++;
    }

    // Small delay between checks to be safe
    usleep(500000); // 0.5 sec
}

AppLogger::info('validate_tick', ['valid' => $valid, 'deleted' => $deleted, 'failed' => $failed], 'validate');
echo "[" . date('c') . "] validate tick — valid=$valid deleted=$deleted failed=$failed\n";
