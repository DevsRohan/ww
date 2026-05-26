<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$leadId = sanitize_int($_GET['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

$limit  = max(1, min(500, (int)($_GET['limit'] ?? 200)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$messages = MessageRepository::listForLead($leadId, $limit, $offset);

json_ok([
    'lead' => [
        'id' => (int)$lead['id'],
        'business_name' => $lead['business_name'],
        'phone_e164'    => $lead['phone_e164'],
        'phone_display' => format_phone_display($lead['phone_e164']),
        'whatsapp_status' => $lead['whatsapp_status'],
        'outreach_status' => $lead['outreach_status'],
        'is_pinned'     => (int)$lead['is_pinned'],
    ],
    'messages' => $messages,
]);
