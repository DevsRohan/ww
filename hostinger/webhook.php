<?php
/**
 * Webhook receiver — SIMPLE VERSION.
 * No signature check, no background processing.
 * Just receive, parse, process, done.
 */

require_once __DIR__ . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

// Respond 200 immediately
http_response_code(200);
echo json_encode(['ok' => true]);

$type    = $body['type'] ?? 'unknown';
$payload = $body['payload'] ?? $body;

// If payload is empty but body has 'from' directly, treat body as payload
if (empty($payload) || !is_array($payload)) {
    $payload = $body;
}
if (isset($body['from']) && !isset($payload['from'])) {
    $payload = $body;
}

AppLogger::info('webhook_received', ['type' => $type, 'keys' => array_keys($body)], 'webhook');

try {
    switch ($type) {
        case 'inbound_message':
            handle_inbound($payload);
            break;
        case 'outbound_status':
            handle_outbound($payload);
            break;
        case 'connection_state':
        case 'qr_updated':
        case 'engine_health':
            SettingsRepository::set('engine_status_cache', json_encode([
                'state' => $payload['state'] ?? null,
                'info'  => $payload['info'] ?? null,
                'last_seen' => date('c'),
            ], JSON_UNESCAPED_UNICODE), 'json', false);
            break;
        case 'number_validated':
            handle_validated($payload);
            break;
        default:
            // Maybe type is missing but payload has 'from' — treat as inbound
            if (isset($payload['from']) && isset($payload['body'])) {
                handle_inbound($payload);
            }
            break;
    }
} catch (\Throwable $e) {
    AppLogger::error('webhook_error', ['err' => $e->getMessage(), 'type' => $type], 'webhook');
}

// ---------- Handlers --------------------------------------------------

function handle_inbound(array $p): void
{
    $from = (string)($p['from'] ?? '');
    if (!$from) return;

    $phoneE164 = normalize_phone($from);
    if (!$phoneE164) return;
    if (strlen($phoneE164) > 13) return;

    // Skip own number
    $engineCache = SettingsRepository::get('engine_status_cache');
    if (is_string($engineCache)) $engineCache = json_decode($engineCache, true);
    if (is_array($engineCache)) {
        $ownWid = $engineCache['info']['wid']['user'] ?? ($engineCache['info']['me']['user'] ?? null);
        if ($ownWid) {
            if ($phoneE164 === (string)$ownWid) return;
            if (substr($phoneE164, -10) === substr((string)$ownWid, -10)) return;
        }
    }

    $lead = LeadRepository::findByPhone($phoneE164);
    if (!$lead) {
        $last10 = substr($phoneE164, -10);
        $lead = DB::fetch('SELECT * FROM leads WHERE phone_e164 LIKE ? LIMIT 1', ['%' . $last10]);
    }
    if (!$lead) {
        AppLogger::info('inbound_no_lead', ['from' => $phoneE164], 'webhook');
        return;
    }

    $waId = $p['wa_message_id'] ?? null;
    $text = (string)($p['body'] ?? '');
    $ts = isset($p['timestamp']) ? (int)round(((int)$p['timestamp']) / 1000) : null;

    MessageRepository::recordInbound((int)$lead['id'], $text, $waId, $ts);
    LeadRepository::markInbound((int)$lead['id']);
    AppLogger::info('inbound_saved', ['lead_id' => $lead['id'], 'from' => $phoneE164, 'text' => mb_substr($text, 0, 50)], 'webhook');
}

function handle_outbound(array $p): void
{
    $waId = $p['wa_message_id'] ?? null;
    $status = $p['status'] ?? null;
    $jobId = $p['meta']['jobId'] ?? ($p['jobId'] ?? null);
    if (!$status) return;

    $msg = null;
    if ($waId) $msg = MessageRepository::findByWaId($waId);
    if (!$msg && $jobId) {
        $msg = DB::fetch("SELECT * FROM messages WHERE JSON_UNQUOTE(JSON_EXTRACT(meta, '$.jobId')) = ? ORDER BY id DESC LIMIT 1", [$jobId]);
    }
    if ($msg) {
        if ($waId) {
            DB::execute('UPDATE messages SET wa_message_id = ?, status = ?, updated_at = NOW() WHERE id = ?', [$waId, $status, $msg['id']]);
        } else {
            DB::execute('UPDATE messages SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $msg['id']]);
        }
        $leadId = (int)$msg['lead_id'];
        if ($status === 'sent') LeadRepository::markOutbound($leadId);
        if (in_array($status, ['sent','delivered','read'])) {
            LeadRepository::setOutreachStatus($leadId, $status);
        }
    }
}

function handle_validated(array $p): void
{
    $phone = $p['phone'] ?? '';
    $status = $p['status'] ?? null;
    if (!$phone || !$status) return;
    $e164 = normalize_phone($phone);
    if (!$e164) return;
    $lead = LeadRepository::findByPhone($e164);
    if (!$lead) return;
    LeadRepository::setWhatsappStatus((int)$lead['id'], $status);
    if ($status === 'not_on_whatsapp') LeadRepository::setOutreachStatus((int)$lead['id'], 'skipped');
}
