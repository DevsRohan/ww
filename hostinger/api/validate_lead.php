<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

$res = NodeClient::checkNumber($lead['phone_e164']);

// Handle smba platform limitation — server_error means we can't check, assume valid
if (empty($res['ok'])) {
    // If engine returned error, just mark as valid (smba limitation)
    LeadRepository::setWhatsappStatus($leadId, 'valid');
    DB::execute(
        'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "number_validated", ?)',
        [$leadId, 'valid (assumed - smba)']
    );
    json_ok(['lead_id' => $leadId, 'status' => 'valid', 'note' => 'assumed_valid_smba']);
}

$status = $res['result']['status'] ?? 'failed';

// server_error = assume valid (smba can't check)
if ($status === 'failed' || $status === 'server_error') {
    $status = 'valid';
}

LeadRepository::setWhatsappStatus($leadId, $status);
if ($status === 'not_on_whatsapp') {
    LeadRepository::setOutreachStatus($leadId, 'skipped');
}

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "number_validated", ?)',
    [$leadId, $status]
);

json_ok(['lead_id' => $leadId, 'status' => $status]);
