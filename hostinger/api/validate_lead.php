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
    // Node backend unreachable or returned error — don't mark as failed in DB,
    // just tell user why so they can retry
    $reason = $res['error'] ?? 'unknown';
    $detail = $res['detail'] ?? ($res['raw'] ?? '');
    $httpCode = $res['http'] ?? 0;
    $backendMsg = $res['message'] ?? '';

    $userMessage = match($reason) {
        'curl_error'              => 'WhatsApp engine not reachable. Check if backend is running.',
        'node_url_not_configured' => 'Backend URL not configured in settings.',
        'invalid_response'        => 'Backend returned invalid response (HTTP ' . $httpCode . ').',
        'engine_not_ready'        => 'WhatsApp not connected! Scan QR code first, then retry.',
        'server_error'            => $backendMsg ?: 'WhatsApp engine error. Wait 30s and retry.',
        'puppeteer_error'         => $backendMsg ?: 'WhatsApp Web page crashed. Restart engine from Settings.',
        'invalid_phone'           => 'Invalid phone number format for this lead.',
        default                   => $backendMsg ?: ('Validation failed: ' . $reason),
    };

    AppLogger::warn('validate_lead_failed', [
        'lead_id' => $leadId, 'phone' => $lead['phone_e164'],
        'error' => $reason, 'http' => $httpCode
    ], 'validate');

    json_fail($userMessage, 502, ['error_code' => $reason, 'http' => $httpCode]);
}

$status = $res['result']['status'] ?? 'failed';

// Update lead status
LeadRepository::setWhatsappStatus($leadId, $status);
if ($status === 'not_on_whatsapp' || $status === 'invalid') {
    LeadRepository::setOutreachStatus($leadId, 'skipped');
}

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "system", "number_validated", ?)',
    [$leadId, $status]
);

json_ok(['lead_id' => $leadId, 'status' => $status]);
