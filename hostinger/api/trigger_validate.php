<?php
/**
 * Trigger validation of all pending leads.
 * Can be called from dashboard or via cron URL.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$rows = LeadRepository::pickPendingValidation(50);
if (!$rows) {
    json_ok(['message' => 'no_pending_leads', 'validated' => 0, 'deleted' => 0]);
}

$phones = array_column($rows, 'phone_e164');
$resp = NodeClient::checkBatch($phones);

if (empty($resp['ok'])) {
    json_fail($resp['error'] ?? 'engine_check_failed', 502, ['detail' => $resp]);
}

$results = $resp['results'] ?? [];
$valid = 0; $deleted = 0; $failed = 0;

foreach ($results as $r) {
    $phone = $r['phone'] ?? null;
    $status = $r['status'] ?? null;
    if (!$phone || !$status) { $failed++; continue; }
    $lead = LeadRepository::findByPhone($phone);
    if (!$lead) { $failed++; continue; }

    if ($status === 'valid') {
        LeadRepository::setWhatsappStatus((int)$lead['id'], 'valid');
        $valid++;
    } else {
        // Not on WhatsApp — delete the lead
        DB::execute('DELETE FROM messages WHERE lead_id = ?', [$lead['id']]);
        DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$lead['id']]);
        DB::execute('DELETE FROM leads WHERE id = ?', [$lead['id']]);
        $deleted++;
    }
}

AppLogger::info('trigger_validate', ['valid' => $valid, 'deleted' => $deleted, 'failed' => $failed], 'validate');

json_ok([
    'validated' => $valid,
    'deleted_invalid' => $deleted,
    'failed' => $failed,
    'total_checked' => count($results),
]);
