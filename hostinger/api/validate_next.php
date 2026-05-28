<?php
/**
 * Validate the NEXT pending lead (one at a time).
 * Frontend calls this in a loop to show progress.
 * Returns: validated lead info + remaining count.
 *
 * Special action: POST { "action": "check_engine" } — pre-flight check
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ─── Pre-flight: check if WhatsApp engine is connected ────────────────────
if (($input['action'] ?? '') === 'check_engine') {
    $health = NodeClient::status();
    if (empty($health['ok'])) {
        $reason = $health['error'] ?? 'unknown';
        $userMsg = match($reason) {
            'curl_error'              => 'WhatsApp backend is not reachable. Make sure the Hugging Face server is running.',
            'node_url_not_configured' => 'Backend URL is not configured. Go to Settings → Node URL.',
            'invalid_response'        => 'Backend returned an unexpected response.',
            default                   => 'Cannot connect to backend: ' . $reason,
        };
        json_fail($userMsg, 503, ['error_code' => $reason, 'fixable' => true]);
    }
    $status = $health['status'] ?? $health;
    $state = $status['state'] ?? 'unknown';
    $ready = !empty($status['ready']);
    if (!$ready) {
        $stateMsg = match($state) {
            'qr'              => 'WhatsApp needs QR scan! Open the QR page and scan with your phone first.',
            'initializing'    => 'WhatsApp engine is still starting up. Wait 30-60 seconds and try again.',
            'auth_failure'    => 'WhatsApp auth failed. Try restarting the engine from Settings.',
            'disconnected'    => 'WhatsApp is disconnected. Check your phone internet or restart engine.',
            default           => "WhatsApp engine state: $state. Not ready for validation yet.",
        };
        json_fail($stateMsg, 503, ['error_code' => 'engine_not_ready', 'state' => $state, 'fixable' => true]);
    }
    // Engine is good
    $countRow = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'");
    json_ok(['engine_ready' => true, 'state' => $state, 'pending_count' => (int)($countRow['c'] ?? 0)]);
}

// ─── Main: validate next pending lead ─────────────────────────────────────

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
    $detail = $resp['detail'] ?? ($resp['raw'] ?? '');
    $httpCode = $resp['http'] ?? 0;
    $backendMsg = $resp['message'] ?? ''; // Node sends "message" field with details

    $userMessage = match($reason) {
        'curl_error'              => 'WhatsApp backend not reachable. Is the server running?',
        'node_url_not_configured' => 'Backend URL not configured in Settings.',
        'invalid_response'        => 'Backend returned invalid response (HTTP ' . $httpCode . ').',
        'engine_not_ready'        => 'WhatsApp not connected! Scan QR code first, then retry.',
        'server_error'            => $backendMsg ?: 'WhatsApp engine error. Wait 30s and retry.',
        'invalid_phone'           => 'Invalid phone number format for this lead.',
        default                   => $backendMsg ?: ('Validation error: ' . $reason),
    };

    // Don't mark as failed — leave as pending so user can retry later
    json_fail($userMessage, 502, [
        'remaining'  => $remaining,
        'lead_id'    => (int)$lead['id'],
        'lead_name'  => $lead['business_name'],
        'error_code' => $reason,
        'http'       => $httpCode,
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
