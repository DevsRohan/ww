<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

MessageRepository::markLeadRead($leadId);
LeadRepository::markRead($leadId);

json_ok(['lead_id' => $leadId]);
