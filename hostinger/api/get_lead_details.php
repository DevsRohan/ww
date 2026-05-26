<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$leadId = sanitize_int($_GET['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

$lead['tags'] = $lead['tags'] ? (json_decode($lead['tags'], true) ?: []) : [];
$lead['phone_display'] = format_phone_display($lead['phone_e164']);

// Activity timeline
$activity = DB::fetchAll(
    'SELECT id, actor, action, description, meta, created_at FROM activity_log WHERE lead_id = ? ORDER BY created_at DESC LIMIT 50',
    [$leadId]
);

// Counts
$counts = DB::fetch(
    'SELECT
        SUM(CASE WHEN direction="outbound" THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN direction="inbound"  THEN 1 ELSE 0 END) AS received,
        MAX(CASE WHEN direction="outbound" THEN timestamp END) AS last_out,
        MAX(CASE WHEN direction="inbound"  THEN timestamp END) AS last_in
     FROM messages WHERE lead_id = ?',
    [$leadId]
);

$last = MessageRepository::lastForLead($leadId);

json_ok([
    'lead'     => $lead,
    'activity' => $activity,
    'counts'   => [
        'sent'     => (int)($counts['sent'] ?? 0),
        'received' => (int)($counts['received'] ?? 0),
        'last_outbound_at' => $counts['last_out'] ?? null,
        'last_inbound_at'  => $counts['last_in'] ?? null,
    ],
    'last_message' => $last,
]);
