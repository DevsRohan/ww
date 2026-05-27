<?php
/**
 * Send first outreach message to a single lead immediately (queued via engine
 * with random delay). Used by dashboard "Send first outreach" action.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

// Auto-validate if the lead is still 'pending' so the user does not have to
// wait for the validation cron. If invalid, surface a clear error.
if ($lead['whatsapp_status'] === 'pending') {
    $check = NodeClient::checkNumber($lead['phone_e164']);
    if (empty($check['ok'])) {
        json_fail('validation_failed', 502, ['detail' => $check]);
    }
    $newStatus = $check['result']['status'] ?? 'failed';
    LeadRepository::setWhatsappStatus($leadId, $newStatus);
    if ($newStatus !== 'valid') {
        if ($newStatus === 'not_on_whatsapp') {
            LeadRepository::setOutreachStatus($leadId, 'skipped');
        }
        json_fail('not_on_whatsapp', 422, ['validated' => $newStatus]);
    }
    $lead['whatsapp_status'] = 'valid';
}

if ($lead['whatsapp_status'] === 'not_on_whatsapp') json_fail('not_on_whatsapp', 422);
if ($lead['whatsapp_status'] !== 'valid') json_fail('lead_not_valid', 422, ['current_status' => $lead['whatsapp_status']]);
if (MessageRepository::alreadyOutreached((int)$lead['id'])) json_fail('already_outreached', 409);
if (in_array($lead['outreach_status'], ['replied'], true)) json_fail('lead_replied_already', 409);

$gen = Groq::generateOutreach($lead);
$message = $gen['message'];

// Pre-record the outbound row in 'queued' state, link by jobId
$jobId = uuid_v4();
$msgId = MessageRepository::recordOutbound((int)$lead['id'], $message, null, 'queued', true, 'system', [
    'jobId' => $jobId, 'used_fallback' => $gen['used_fallback'], 'pitch_type' => $gen['pitch_type'],
]);
LeadRepository::setOutreachStatus((int)$lead['id'], 'queued');

$resp = NodeClient::sendMessage($lead['phone_e164'], $message, false, [
    'lead_id'    => (int)$lead['id'],
    'message_id' => $msgId,
    'jobId'      => $jobId,
    'mode'       => 'first_outreach',
]);
if (empty($resp['ok'])) {
    LeadRepository::setOutreachStatus((int)$lead['id'], 'failed');
    json_fail($resp['error'] ?? 'engine_failed', 502, ['detail' => $resp]);
}

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "first_outreach_queued", ?)',
    [$lead['id'], 'queued via engine']
);

json_ok([
    'message_id' => $msgId,
    'job_id'     => $jobId,
    'queued'     => true,
    'preview'    => mb_substr($message, 0, 120),
]);
