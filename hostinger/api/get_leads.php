<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$filters = [
    'q'                => trim((string)($_GET['q'] ?? '')),
    'whatsapp_status'  => $_GET['whatsapp_status'] ?? null,
    'outreach_status'  => $_GET['outreach_status'] ?? null,
    'pitch_type'       => $_GET['pitch_type'] ?? null,
    'city'             => $_GET['city'] ?? null,
    'state'            => $_GET['state'] ?? null,
    'has_unread'       => !empty($_GET['has_unread']),
    'pinned'           => !empty($_GET['pinned']),
];

$preset = $_GET['filter'] ?? '';
switch ($preset) {
    case 'unread':  $filters['has_unread'] = true; break;
    case 'replied': $filters['outreach_status'] = 'replied'; break;
    case 'queued':  $filters['outreach_status'] = 'queued'; break;
    case 'sent':    $filters['outreach_status'] = 'sent'; break;
    case 'valid':   $filters['whatsapp_status'] = 'valid'; break;
    case 'invalid': $filters['whatsapp_status'] = 'not_on_whatsapp'; break;
    case 'pending': $filters['whatsapp_status'] = 'pending'; break;
    case 'type_a':  $filters['pitch_type'] = 'type_a'; break;
    case 'type_b':  $filters['pitch_type'] = 'type_b'; break;
}

$limit  = max(1, min(200, (int)($_GET['limit'] ?? 60)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$result = LeadRepository::search($filters, $limit, $offset);
foreach ($result['rows'] as &$row) {
    $row['phone_display'] = format_phone_display($row['phone_e164']);
}
json_ok($result);
