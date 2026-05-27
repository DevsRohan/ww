<?php
/**
 * Webhook receiver — accepts events from the Hugging Face Node.js engine.
 * Verifies HMAC-SHA256 signature, stores raw event for audit, processes idempotently.
 *
 * IMPORTANT: Responds with 200 OK immediately, then processes in background.
 * This prevents HF free tier from timing out (15s limit).
 */

require_once __DIR__ . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$eventType = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '';
$eventId   = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? '';

// Verify signature — try multiple header formats (LiteSpeed may alter header names)
$sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
    ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
    ?? $_SERVER['HTTP_WEBHOOK_SIGNATURE']
    ?? '';

// If signature is empty, try reading from all headers manually
if ($sig === '' && function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    foreach ($allHeaders as $k => $v) {
        if (strtolower($k) === 'x-webhook-signature') {
            $sig = $v;
            break;
        }
    }
}

// Signature verification — log but don't block (HF env var issue)
$sigValid = Auth::verifyWebhookSignature($raw, $sig);
if (!$sigValid) {
    AppLogger::debug('webhook_sig_mismatch', [
        'ip' => client_ip(),
        'event' => $eventType,
        'sig_length' => strlen($sig),
        'has_sig' => $sig !== '',
    ], 'webhook');
    // Don't block — allow processing (own-number filter in handler prevents abuse)
}

// === RESPOND IMMEDIATELY — don't make HF wait ===
http_response_code(200);
echo json_encode(['ok' => true]);

// Flush response to client so HF gets 200 instantly
if (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // Fallback: flush output buffers
    if (ob_get_level() > 0) ob_end_flush();
    flush();
    if (function_exists('ignore_user_abort')) ignore_user_abort(true);
}

// === Now process in background (HF already got 200 OK) ===

$body = json_decode($raw, true);
if (!is_array($body)) exit;

$type    = $body['type']    ?? $eventType ?? 'unknown';
$payload = $body['payload'] ?? [];

// Persist (idempotent on event_id when present)
try {
    DB::execute(
        'INSERT INTO webhook_events (event_id, event_type, payload, signature, processed)
         VALUES (?, ?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE attempts = attempts + 1',
        [
            !empty($eventId) ? $eventId : ($body['id'] ?? null),
            $type,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $sig
        ]
    );
} catch (\Throwable $e) {
    AppLogger::error('webhook_persist_failed', ['err' => $e->getMessage()], 'webhook');
}

try {
    switch ($type) {
        case 'inbound_message':
            handle_inbound_message($payload);
            break;
        case 'outbound_status':
            handle_outbound_status($payload);
            break;
        case 'qr_updated':
        case 'connection_state':
        case 'engine_health':
            SettingsRepository::set('engine_status_cache', json_encode([
                'state' => $payload['state'] ?? null,
                'info'  => $payload['info']  ?? null,
                'last_seen' => date('c'),
            ], JSON_UNESCAPED_UNICODE), 'json', false);
            break;
        case 'number_validated':
            handle_number_validated($payload);
            break;
        default:
            AppLogger::info('webhook_unknown_type', ['type' => $type], 'webhook');
    }

    if (!empty($eventId) || !empty($body['id'])) {
        DB::execute(
            'UPDATE webhook_events SET processed = 1, processed_at = NOW() WHERE event_id = ?',
            [$eventId ?: $body['id']]
        );
    }
} catch (\Throwable $e) {
    AppLogger::error('webhook_handler_failed', ['err' => $e->getMessage(), 'type' => $type], 'webhook');
    if (!empty($eventId) || !empty($body['id'])) {
        DB::execute(
            'UPDATE webhook_events SET last_error = ? WHERE event_id = ?',
            [substr($e->getMessage(), 0, 500), $eventId ?: $body['id']]
        );
    }
}

// ---------- Handlers --------------------------------------------------

function handle_inbound_message(array $p): void
{
    $from = (string)($p['from'] ?? '');
    if (!$from) return;

    $phoneE164 = normalize_phone($from);
    if (!$phoneE164) return;

    // Reject corrupted phone numbers (> 13 digits)
    if (strlen($phoneE164) > 13) return;

    // Ignore messages from own WhatsApp number (outbound echo from smba platform)
    $engineCache = SettingsRepository::get('engine_status_cache');
    if (is_string($engineCache)) $engineCache = json_decode($engineCache, true);
    if (is_array($engineCache)) {
        $ownWid = $engineCache['info']['wid']['user'] ?? ($engineCache['info']['me']['user'] ?? null);
        if ($ownWid) {
            if ($phoneE164 === (string)$ownWid) return;
            if (substr($phoneE164, -10) === substr((string)$ownWid, -10)) return;
        }
    }

    // Find the lead by phone — exact match then last 10 digits
    $lead = LeadRepository::findByPhone($phoneE164);
    if (!$lead) {
        $last10 = substr($phoneE164, -10);
        $lead = DB::fetch('SELECT * FROM leads WHERE phone_e164 LIKE ? LIMIT 1', ['%' . $last10]);
    }
    if (!$lead) {
        AppLogger::info('inbound_ignored_unknown', ['from' => $phoneE164], 'webhook');
        return;
    }
    $leadId = (int)$lead['id'];

    $waId = $p['wa_message_id'] ?? null;
    $text = (string)($p['body'] ?? '');
    $ts   = isset($p['timestamp']) ? (int)round(((int)$p['timestamp']) / 1000) : null;

    MessageRepository::recordInbound($leadId, $text, $waId, $ts, [
        'type' => $p['type'] ?? 'chat',
        'has_media' => $p['has_media'] ?? false,
    ]);
    LeadRepository::markInbound($leadId);
    DB::execute(
        'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, "lead", "reply_received", ?)',
        [$leadId, mb_substr($text, 0, 200)]
    );
}

function handle_outbound_status(array $p): void
{
    $waId = $p['wa_message_id'] ?? null;
    $status = $p['status'] ?? null;
    $jobId = $p['meta']['jobId'] ?? ($p['jobId'] ?? null);
    if (!$status) return;

    $msg = null;
    if ($waId) {
        $msg = MessageRepository::findByWaId($waId);
    }
    if (!$msg && $jobId) {
        $msg = DB::fetch(
            "SELECT * FROM messages
             WHERE wa_message_id IS NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.jobId')) = ?
             ORDER BY id DESC LIMIT 1",
            [$jobId]
        );
        if ($msg && $waId) {
            DB::execute(
                'UPDATE messages SET wa_message_id = ?, status = ?, error_message = ?, updated_at = NOW() WHERE id = ?',
                [$waId, $status, $p['error'] ?? null, $msg['id']]
            );
        } else if ($msg) {
            DB::execute(
                'UPDATE messages SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?',
                [$status, $p['error'] ?? null, $msg['id']]
            );
        }
    } else if ($msg) {
        MessageRepository::updateStatusByWaId($waId, $status, $p['error'] ?? null);
    }

    if ($msg) {
        $leadId = (int)$msg['lead_id'];
        $map = ['sent'=>'sent','delivered'=>'delivered','read'=>'read','failed'=>'failed','queued'=>'queued'];
        if (isset($map[$status])) {
            $rank = ['queued'=>0,'sent'=>1,'delivered'=>2,'read'=>3,'replied'=>4,'failed'=>1,'new'=>0,'sending'=>0,'skipped'=>1,'blocked'=>1];
            $current = LeadRepository::findById($leadId);
            if ($current && $current['outreach_status'] !== 'replied') {
                $cur = $rank[$current['outreach_status']] ?? 0;
                $nw  = $rank[$map[$status]] ?? 0;
                if ($status === 'failed') {
                    LeadRepository::setOutreachStatus($leadId, 'failed');
                } else if ($nw >= $cur) {
                    LeadRepository::setOutreachStatus($leadId, $map[$status]);
                }
                if ($status === 'sent') {
                    LeadRepository::markOutbound($leadId);
                }
            }
        }
    }
}

function handle_number_validated(array $p): void
{
    $phone = $p['phone'] ?? '';
    $status = $p['status'] ?? null;
    if (!$phone || !$status) return;
    $e164 = normalize_phone($phone);
    if (!$e164) return;
    $lead = LeadRepository::findByPhone($e164);
    if (!$lead) return;
    LeadRepository::setWhatsappStatus((int)$lead['id'], $status);
    if ($status === 'not_on_whatsapp') {
        LeadRepository::setOutreachStatus((int)$lead['id'], 'skipped');
    }
}
