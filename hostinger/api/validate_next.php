<?php
/**
 * Validate the NEXT pending lead (one at a time).
 * Frontend calls this in a loop to show progress.
 * Returns: validated lead info + remaining count.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

// Get count of remaining pending
$countRow = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'");
$remaining = (int)($countRow['c'] ?? 0);

if ($remaining === 0) {
    json_ok(['done' => true, 'remaining' => 0, 'message' => 'All leads validated']);
}

// Pick the next one
$lead = DB::fetch("SELECT id, phone_e164, business_name FROM leads WHERE whatsapp_status = 'pending' ORDER BY created_at ASC LIMIT 1");
if (!$lead) {
    json_ok(['done' => true, 'remaining' => 0, 'message' => 'All leads validated']);
}

$resp = NodeClient::checkNumber($lead['phone_e164']);

if (empty($resp['ok'])) {
    $reason = $resp['error'] ?? 'unknown';
    $detail = $resp['detail'] ?? '';

    $userMessage = match($reason) {
        'curl_error'              => 'WhatsApp engine not reachable. Is the backend running?',
        'node_url_not_configured' => 'Backend URL not configured.',
        'invalid_response'        => 'Backend returned invalid response.',
        default                   => 'Check failed: ' . $reason,
    };

    // Don't mark as failed — leave as pending so user can retry later
    json_fail($userMessage, 502, [
        'remaining'  => $remaining,
        'lead_id'    => (int)$lead['id'],
        'lead_name'  => $lead['business_name'],
        'error_code' => $reason,
    ]);
}

$status = $resp['result']['status'] ?? 'failed';
$result = [
    'done'      => false,
    'remaining' => $remaining - 1,
    'lead_id'   => (int)$lead['id'],
    'lead_name' => $lead['business_name'],
    'status'    => $status,
];

if ($status === 'valid') {
    LeadRepository::setWhatsappStatus((int)$lead['id'], 'valid');
    $result['action'] = 'kept';
} else {
    // Not on WhatsApp — delete the lead
    DB::execute('DELETE FROM messages WHERE lead_id = ?', [$lead['id']]);
    DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$lead['id']]);
    DB::execute('DELETE FROM leads WHERE id = ?', [$lead['id']]);
    $result['action'] = 'deleted';
    // Recount after deletion
    $newCount = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'");
    $result['remaining'] = (int)($newCount['c'] ?? 0);
}

json_ok($result);
