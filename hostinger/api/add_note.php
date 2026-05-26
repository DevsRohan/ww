<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
$note   = trim((string)($body['note'] ?? ''));
if ($leadId <= 0 || $note === '') json_fail('lead_id_and_note_required');

LeadRepository::appendNote($leadId, $note);
DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, ?, "note_added", ?)',
    [$leadId, Auth::user()['email'] ?? 'system', mb_substr($note, 0, 200)]
);
json_ok(['lead_id' => $leadId]);
