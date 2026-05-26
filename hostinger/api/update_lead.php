<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$body = read_json_body();
$leadId = sanitize_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_fail('lead_id_required');

$lead = LeadRepository::findById($leadId);
if (!$lead) json_fail('lead_not_found', 404);

$allowed = ['business_name','locality','city','state','address','website_url','website_status','pitch_type','language_preference','rating','review_count','outreach_status','notes'];
$updated = 0;
foreach ($body as $k => $v) {
    if ($k === 'lead_id') continue;
    if (in_array($k, $allowed, true)) {
        LeadRepository::updateField($leadId, $k, $v);
        $updated++;
    }
}

if (isset($body['is_pinned'])) { LeadRepository::togglePin($leadId, !empty($body['is_pinned'])); $updated++; }

DB::execute(
    'INSERT INTO activity_log (lead_id, actor, action, description) VALUES (?, ?, "lead_updated", ?)',
    [$leadId, Auth::user()['email'] ?? 'system', "{$updated} field(s) updated"]
);

json_ok(['lead_id' => $leadId, 'updated' => $updated]);
