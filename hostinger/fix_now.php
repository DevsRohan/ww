<?php
/**
 * ONE-TIME FIX: Update campaign active hours + delete fake Unknown leads.
 * URL: https://test.devsarun.io/fix_now.php
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/config/bootstrap.php';
Auth::startSession();
if (!Auth::check()) { die('Login first.'); }

header('Content-Type: text/plain; charset=utf-8');
echo "=== APPLYING FIXES ===\n\n";

// 1. Fix campaign active hours
SettingsRepository::set('campaign_active_hours_start', '6', 'int', false, 'Active sending start hour (24h)');
SettingsRepository::set('campaign_active_hours_end', '23', 'int', false, 'Active sending end hour (24h)');
echo "[DONE] Campaign hours: 6 AM - 11 PM\n";

// 2. Delete fake Unknown leads
$unknowns = DB::fetchAll("SELECT id, phone_e164 FROM leads WHERE source = 'inbound_unknown'");
$deleted = 0;
foreach ($unknowns as $u) {
    DB::execute('DELETE FROM messages WHERE lead_id = ?', [$u['id']]);
    DB::execute('DELETE FROM activity_log WHERE lead_id = ?', [$u['id']]);
    DB::execute('DELETE FROM leads WHERE id = ?', [$u['id']]);
    $deleted++;
}
echo "[DONE] Deleted $deleted fake Unknown leads\n";

// 3. Reset stuck 'sending' leads
$stuck = DB::execute("UPDATE leads SET outreach_status = 'queued', updated_at = NOW() WHERE outreach_status = 'sending'");
echo "[DONE] Reset $stuck stuck leads\n";

// 4. Summary
$eligible = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'valid' AND outreach_status IN ('new','queued','failed') AND last_outbound_at IS NULL");
echo "\n=== RESULT ===\n";
echo "Campaign-eligible leads: " . ($eligible['c'] ?? 0) . "\n";
echo "Campaign will run: 6 AM - 11 PM\n";
echo "Next cron tick will pick 5 leads and send.\n";
echo "\n>>> DELETE THIS FILE NOW <<<\n";
