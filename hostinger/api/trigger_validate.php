<?php
/**
 * Trigger validation of all pending leads.
 * Can be called from dashboard browser console.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$rows = LeadRepository::pickPendingValidation(50);
if (!$rows) {
    json_ok(['message' => 'no_pending_leads', 'validated' => 0, 'deleted' => 0]);
}

$valid = 0; $deleted = 0; $failed = 0;

foreach ($rows as $row) {
    $resp = NodeClient::checkNumber($row['phone_e164']);
    if (empty($resp['ok'])) { $failed++; continue; }

    $status = $resp['result']['status'] ?? 'failed';

    if ($status === 'valid') {
        LeadRepository::setWhatsappStatus((int)$row['id'], 'valid');
        $valid++;
    } else {
        // Not on WhatsApp — delete the lead
        DB::execute('DELETE FROM messages WHERE lead_id = ?', [$row['id']]);
        DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$row['id']]);
        DB::execute('DELETE FROM leads WHERE id = ?', [$row['id']]);
        $deleted++;
    }
}

AppLogger::info('trigger_validate', ['valid' => $valid, 'deleted' => $deleted, 'failed' => $failed], 'validate');

json_ok([
    'validated' => $valid,
    'deleted_invalid' => $deleted,
    'failed' => $failed,
    'total_checked' => count($rows),
]);
