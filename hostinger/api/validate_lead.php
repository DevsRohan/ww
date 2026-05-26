<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

$res = NodeClient::checkNumber($lead['phone_e164']);
if (empty($res['ok'])) {
    json_fail($res['error'] ?? 'check_failed', 502, ['detail' => $res]);
}
$status = $res['result']['status'] ?? 'failed';
LeadRepository::setWhatsappStatus($leadId, $status);
if ($status === 'not_on_whatsapp') {
    LeadRepository::setOutreachStatus($leadId, 'skipped');
}

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "number_validated", ?)',
    [$leadId, $status]
);

json_ok(['lead_id' => $leadId, 'status' => $status]);
