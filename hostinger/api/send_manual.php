<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
$text   = trim((string)($body['message'] ?? ''));
if ($leadId <= 0 || $text === '') json_fail('lead_id_and_message_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);
if ($lead['whatsapp_status'] !== 'valid') json_fail('lead_not_valid_on_whatsapp', 422);

$resp = NodeClient::sendMessage($lead['phone_e164'], $text, true, [
    'lead_id' => $leadId,
    'mode'    => 'manual',
    'sender'  => Auth::user()['email'] ?? 'operator',
]);
if (empty($resp['ok'])) {
    AppLogger::warn('manual_send_failed', ['lead_id' => $leadId, 'resp' => $resp], 'send');
    json_fail($resp['error'] ?? 'send_failed', 502, ['detail' => $resp]);
}

$waId = $resp['result']['wa_message_id'] ?? null;
$mid = MessageRepository::recordOutbound(
    $leadId, $text, $waId, 'sent', false,
    Auth::user()['email'] ?? 'operator',
    ['mode' => 'manual']
);
LeadRepository::markOutbound($leadId);

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, ?, "manual_message_sent", ?)',
    [$leadId, Auth::user()['email'] ?? 'operator', mb_substr($text, 0, 200)]
);

json_ok([
    'message_id' => $mid,
    'wa_message_id' => $waId,
    'status' => 'sent',
]);
