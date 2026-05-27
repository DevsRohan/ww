<?php
/**
 * POST /api/validate_all.php
 * Validates ALL leads currently in `whatsapp_status = pending` against the
 * HF engine in batches. Useful right after CSV import when the cron hasn't
 * run yet. Returns a summary.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

set_time_limit(180);

$totalPending = (int) (DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'")['c'] ?? 0);
if ($totalPending === 0) {
    json_ok(['validated' => 0, 'invalidated' => 0, 'failed' => 0, 'remaining' => 0, 'total' => 0]);
}

$validated = 0; $invalidated = 0; $failed = 0;
$batchSize = 20; $batches = 0; $maxBatches = 10; // up to 200 in one shot

while ($batches < $maxBatches) {
    $rows = DB::fetchAll(
        "SELECT id, phone_e164 FROM leads WHERE whatsapp_status = 'pending' LIMIT " . $batchSize
    );
    if (!$rows) break;
    $batches++;
    $phones = array_column($rows, 'phone_e164');
    $resp = NodeClient::checkBatch($phones);
    if (empty($resp['ok'])) {
        AppLogger::warn('validate_all_batch_failed', ['resp' => $resp], 'validate');
        json_fail($resp['error'] ?? 'engine_unreachable', 502, ['detail' => $resp]);
    }
    $results = $resp['results'] ?? [];
    foreach ($results as $r) {
        $phone = $r['phone'] ?? null;
        $status = $r['status'] ?? null;
        if (!$phone || !$status) { $failed++; continue; }
        $lead = LeadRepository::findByPhone($phone);
        if (!$lead) { $failed++; continue; }
        LeadRepository::setWhatsappStatus((int)$lead['id'], $status);
        if ($status === 'valid') {
            $validated++;
        } else if ($status === 'not_on_whatsapp') {
            LeadRepository::setOutreachStatus((int)$lead['id'], 'skipped');
            $invalidated++;
        } else {
            $failed++;
        }
    }
}

$remaining = (int) (DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'")['c'] ?? 0);
AppLogger::info('validate_all', [
    'validated' => $validated, 'invalidated' => $invalidated, 'failed' => $failed, 'remaining' => $remaining,
], 'validate');

json_ok([
    'validated' => $validated,
    'invalidated' => $invalidated,
    'failed' => $failed,
    'remaining' => $remaining,
    'total' => $totalPending,
]);
