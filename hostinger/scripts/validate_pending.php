<?php
/**
 * Cron: Validate pending phone numbers.
 * For WhatsApp Business (smba) accounts, isRegisteredUser() returns server_error.
 * So we validate one-by-one and treat server_error as valid (assume Google Maps numbers are on WA).
 * Invalid numbers (not_on_whatsapp) are deleted.
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

foreach ($rows as $row) {
    $phone = $row['phone_e164'];
    $leadId = (int)$row['id'];

    $resp = NodeClient::checkNumber($phone);

    if (!empty($resp['ok'])) {
        $status = $resp['result']['status'] ?? 'failed';
        if ($status === 'valid') {
            LeadRepository::setWhatsappStatus($leadId, 'valid');
            echo "  VALID: $phone\n";
            $valid++;
        } else if ($status === 'not_on_whatsapp') {
            DB::execute('DELETE FROM messages WHERE lead_id = ?', [$leadId]);
            DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$leadId]);
            DB::execute('DELETE FROM leads WHERE id = ?', [$leadId]);
            echo "  DELETED: $phone (not on whatsapp)\n";
            $deleted++;
        } else {
            // server_error or unknown — mark as valid (smba limitation)
            LeadRepository::setWhatsappStatus($leadId, 'valid');
            echo "  VALID (assumed): $phone (status: $status)\n";
            $valid++;
        }
    } else {
        // API call failed — mark as valid anyway (smba platform limitation)
        LeadRepository::setWhatsappStatus($leadId, 'valid');
        echo "  VALID (assumed): $phone (api error: " . ($resp['error'] ?? 'unknown') . ")\n";
        $valid++;
    }

    usleep(500000); // 0.5 sec delay
}

AppLogger::info('validate_tick', ['valid' => $valid, 'deleted' => $deleted, 'failed' => $failed], 'validate');
echo "[" . date('c') . "] validate tick — valid=$valid deleted=$deleted failed=$failed\n";
