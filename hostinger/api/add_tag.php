<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
$tag = trim((string)($body['tag'] ?? ''));
$action = $body['action'] ?? 'add';
if ($leadId <= 0 || $tag === '') json_fail('lead_id_and_tag_required');
$tag = mb_substr($tag, 0, 40);

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

if ($action === 'remove') LeadRepository::removeTag($leadId, $tag);
else                       LeadRepository::addTag($leadId, $tag);

json_ok(['lead_id' => $leadId, 'tag' => $tag, 'action' => $action]);
