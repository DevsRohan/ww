<?php
/**
 * COMPLETE SYSTEM DIAGNOSTIC — Run this to identify ALL issues.
 * URL: https://test.devsarun.io/debug.php
 * DELETE THIS FILE AFTER DEBUGGING (contains sensitive info)
 */
require_once __DIR__ . '/config/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace;font-size:13px;line-height:1.6;padding:20px;'>";
echo "========================================\n";
echo " WHATSAPP CRM — FULL SYSTEM DIAGNOSTIC\n";
echo " Time: " . date('Y-m-d H:i:s T') . "\n";
echo " PHP: " . PHP_VERSION . " | SAPI: " . PHP_SAPI . "\n";
echo "========================================\n\n";

// ============================================================
// 1. DATABASE CONNECTION
// ============================================================
echo "--- 1. DATABASE ---\n";
try {
    $test = DB::fetch("SELECT 1 AS ok");
    echo "[OK] Database connected\n";
} catch (\Throwable $e) {
    echo "[FAIL] Database: " . $e->getMessage() . "\n";
}

// ============================================================
// 2. SETTINGS CHECK
// ============================================================
echo "\n--- 2. SETTINGS ---\n";
$nodeUrl = $GLOBALS['APP']['node']['api_url'] ?? '';
$nodeKey = $GLOBALS['APP']['node']['api_key'] ?? '';
$webhookSecret = $GLOBALS['APP']['webhook']['secret'] ?? '';
$groqKey = $GLOBALS['APP']['groq']['api_key'] ?? '';
$socketUrl = $GLOBALS['APP']['socket_url'] ?? '';
$activeStart = $GLOBALS['APP']['campaign']['active_start'] ?? 10;
$activeEnd = $GLOBALS['APP']['campaign']['active_end'] ?? 20;
$currentHour = (int)date('G');

echo "node_api_url: " . ($nodeUrl ? $nodeUrl : '[EMPTY!]') . "\n";
echo "node_api_key: " . ($nodeKey ? substr($nodeKey, 0, 10) . '...' : '[EMPTY!]') . "\n";
echo "webhook_secret: " . ($webhookSecret ? substr($webhookSecret, 0, 10) . '...' : '[EMPTY!]') . "\n";
echo "groq_api_key: " . ($groqKey ? substr($groqKey, 0, 10) . '...' : '[EMPTY!]') . "\n";
echo "socket_url: " . ($socketUrl ? $socketUrl : '[EMPTY!]') . "\n";
echo "active_hours: {$activeStart} - {$activeEnd} (current hour: {$currentHour})\n";
echo "campaign_within_hours: " . ($currentHour >= $activeStart && $currentHour < $activeEnd ? 'YES' : 'NO — CAMPAIGN WILL NOT RUN!') . "\n";

// ============================================================
// 3. HF ENGINE CONNECTIVITY
// ============================================================
echo "\n--- 3. HF ENGINE CONNECTION ---\n";
$health = NodeClient::health();
if (!empty($health['ok'])) {
    echo "[OK] HF Engine reachable\n";
    echo "  State: " . ($health['whatsapp']['state'] ?? '?') . "\n";
    echo "  Ready: " . (!empty($health['whatsapp']['ready']) ? 'YES' : 'NO') . "\n";
    echo "  WA Number: " . ($health['whatsapp']['info']['wid']['user'] ?? 'N/A') . "\n";
    echo "  Uptime: " . ($health['uptime_s'] ?? '?') . "s\n";
} else {
    echo "[FAIL] Cannot reach HF Engine!\n";
    echo "  Error: " . ($health['error'] ?? 'unknown') . "\n";
    echo "  Detail: " . ($health['detail'] ?? json_encode($health)) . "\n";
    echo "  >>> FIX: Check node_api_url and node_api_key in settings\n";
}

// Test API key auth
echo "\n--- 3b. HF API KEY AUTH TEST ---\n";
$status = NodeClient::status();
if (!empty($status['ok'])) {
    echo "[OK] API key accepted by HF engine\n";
} else {
    echo "[FAIL] API key rejected!\n";
    echo "  Response: " . json_encode($status) . "\n";
    echo "  >>> FIX: HF env API_KEY must match Hostinger node_api_key setting\n";
}

// ============================================================
// 4. WEBHOOK SECRET CONSISTENCY
// ============================================================
echo "\n--- 4. WEBHOOK SECRET ---\n";
$engineCache = SettingsRepository::get('engine_status_cache');
if (is_string($engineCache)) $engineCache = json_decode($engineCache, true);
$ownWid = null;
if (is_array($engineCache)) {
    $ownWid = $engineCache['info']['wid']['user'] ?? ($engineCache['info']['me']['user'] ?? null);
}
echo "Own WA number (from cache): " . ($ownWid ?? 'NOT CACHED — engine_status_cache might be wrong format') . "\n";
echo "engine_status_cache raw: " . json_encode($engineCache) . "\n";

// ============================================================
// 5. LEAD STATUS ANALYSIS
// ============================================================
echo "\n--- 5. LEAD STATUS ---\n";
$stats = LeadRepository::stats();
echo "Total leads: {$stats['total_leads']}\n";
echo "Valid: {$stats['valid_leads']}\n";
echo "Pending: {$stats['pending_leads']}\n";
echo "Sent: {$stats['sent_count']}\n";
echo "Replied: {$stats['replied_count']}\n";
echo "Queued: {$stats['queued_count']}\n";

// Check leads eligible for campaign
$eligible = DB::fetch(
    "SELECT COUNT(*) AS c FROM leads 
     WHERE whatsapp_status = 'valid' 
       AND outreach_status IN ('new','queued','failed') 
       AND last_outbound_at IS NULL"
);
echo "Campaign-eligible leads: " . ($eligible['c'] ?? 0) . "\n";

// Check leads stuck in 'sending'
$sending = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE outreach_status = 'sending'");
echo "Stuck in 'sending': " . ($sending['c'] ?? 0) . "\n";

// Sample 3 queued leads
echo "\nSample queued leads:\n";
$samples = DB::fetchAll(
    "SELECT id, business_name, phone_e164, outreach_status, last_outbound_at 
     FROM leads WHERE outreach_status IN ('queued','new') AND last_outbound_at IS NULL 
     LIMIT 3"
);
foreach ($samples as $s) {
    echo "  ID:{$s['id']} | {$s['business_name']} | {$s['phone_e164']} | status:{$s['outreach_status']} | last_out:{$s['last_outbound_at']}\n";
}

// Check if alreadyOutreached is blocking
echo "\nMessage check for sample leads:\n";
foreach ($samples as $s) {
    $msgs = DB::fetchAll("SELECT id, status, is_first_outreach, wa_message_id FROM messages WHERE lead_id = ? ORDER BY id DESC LIMIT 3", [$s['id']]);
    echo "  Lead {$s['id']} ({$s['business_name']}):\n";
    if (!$msgs) {
        echo "    No messages — GOOD (campaign should pick this)\n";
    } else {
        foreach ($msgs as $m) {
            echo "    msg_id:{$m['id']} status:{$m['status']} first_outreach:{$m['is_first_outreach']} wa_id:" . ($m['wa_message_id'] ?? 'null') . "\n";
        }
        // Check if our new logic would skip this
        $blocking = DB::fetch(
            "SELECT id, status FROM messages WHERE lead_id = ? AND is_first_outreach = 1 AND status IN ('sent','delivered','read') LIMIT 1",
            [$s['id']]
        );
        echo "    Would campaign skip? " . ($blocking ? "YES (msg {$blocking['id']} status:{$blocking['status']})" : "NO — will process") . "\n";
    }
}

// ============================================================
// 6. UNKNOWN LEADS ANALYSIS
// ============================================================
echo "\n--- 6. UNKNOWN/FAKE LEADS ---\n";
$unknowns = DB::fetchAll("SELECT id, business_name, phone_e164, source, created_at FROM leads WHERE source = 'inbound_unknown' ORDER BY id DESC LIMIT 5");
echo "Unknown leads count: " . count($unknowns) . "\n";
foreach ($unknowns as $u) {
    echo "  ID:{$u['id']} | {$u['business_name']} | phone:{$u['phone_e164']} | source:{$u['source']} | created:{$u['created_at']}\n";
    // Check if this phone matches own WA number
    if ($ownWid && $u['phone_e164'] === $ownWid) {
        echo "    >>> THIS IS YOUR OWN NUMBER! Fix: webhook should filter this out\n";
    }
}

// ============================================================
// 7. CAMPAIGN CRON SIMULATION
// ============================================================
echo "\n--- 7. CAMPAIGN CRON SIMULATION ---\n";
echo "Current hour: {$currentHour}\n";
echo "Active start: {$activeStart}\n";
echo "Active end: {$activeEnd}\n";
if ($currentHour < $activeStart || $currentHour >= $activeEnd) {
    echo "[BLOCKED] Campaign cron will SKIP — outside active hours!\n";
    echo ">>> FIX: Change campaign_active_hours_end to 23 in settings\n";
} else {
    echo "[OK] Within active hours\n";
}

$sentToday = LeadRepository::dailyOutboundCount();
$dailyLimit = $GLOBALS['APP']['campaign']['daily_limit'] ?? 60;
echo "Sent today: {$sentToday} / {$dailyLimit} daily limit\n";
if ($sentToday >= $dailyLimit) {
    echo "[BLOCKED] Daily limit reached!\n";
} else {
    echo "[OK] Under daily limit\n";
}

// ============================================================
// 8. GROQ AI TEST
// ============================================================
echo "\n--- 8. GROQ AI ---\n";
if (!$groqKey) {
    echo "[FAIL] Groq API key not configured — messages will use FALLBACK template\n";
} else {
    echo "Groq key configured: " . substr($groqKey, 0, 10) . "...\n";
    echo "Model: " . ($GLOBALS['APP']['groq']['model'] ?? 'not set') . "\n";
    // Quick test with a sample lead
    if (!empty($samples)) {
        $testLead = DB::fetch("SELECT * FROM leads WHERE id = ?", [$samples[0]['id']]);
        if ($testLead) {
            echo "Testing AI generation for: {$testLead['business_name']}...\n";
            $gen = Groq::generateOutreach($testLead);
            echo "  Used fallback: " . ($gen['used_fallback'] ? 'YES (Groq failed!)' : 'NO (AI worked)') . "\n";
            echo "  Language: {$gen['language']}\n";
            echo "  Preview: " . substr($gen['message'], 0, 150) . "...\n";
        }
    }
}

// ============================================================
// 9. SEND TEST (dry run — doesn't actually send)
// ============================================================
echo "\n--- 9. SEND CAPABILITY TEST ---\n";
if (!empty($samples) && !empty($health['whatsapp']['ready'])) {
    $testPhone = $samples[0]['phone_e164'];
    echo "Testing check-number for: {$testPhone}\n";
    $checkResp = NodeClient::checkNumber($testPhone);
    echo "  Response: " . json_encode($checkResp) . "\n";
    if (!empty($checkResp['ok'])) {
        echo "  [OK] Engine can check numbers — send should work too\n";
    } else {
        echo "  [FAIL] Engine cannot check number: " . ($checkResp['error'] ?? 'unknown') . "\n";
    }
} else {
    echo "[SKIP] No samples or engine not ready\n";
}

// ============================================================
// 10. WHATSAPP.SERVICE.JS VERSION CHECK
// ============================================================
echo "\n--- 10. HF CODE VERSION CHECK ---\n";
echo "Testing if msg.fromMe filter is active on HF...\n";
echo "(If Unknown leads still appear after HF redeploy, the filter is not deployed)\n";
$recentUnknowns = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE source = 'inbound_unknown' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
echo "Unknown leads created in last 10 min: " . ($recentUnknowns['c'] ?? 0) . "\n";
if (($recentUnknowns['c'] ?? 0) > 0) {
    echo "[PROBLEM] HF is STILL sending own messages as inbound!\n";
    echo ">>> FIX: HF Space MUST be redeployed with updated whatsapp.service.js\n";
    echo ">>> The line 'if (msg.fromMe) return;' must be in the 'message' event handler\n";
} else {
    echo "[OK] No recent unknowns\n";
}

// ============================================================
// 11. FAILED TO LOAD CHAT — DB PERFORMANCE
// ============================================================
echo "\n--- 11. CHAT LOAD TEST ---\n";
$start = microtime(true);
$testLeadId = $samples[0]['id'] ?? 4;
$lead = LeadRepository::findById($testLeadId);
$msgs = MessageRepository::listForLead($testLeadId, 200, 0);
$elapsed = round((microtime(true) - $start) * 1000);
echo "Load lead #{$testLeadId} + messages: {$elapsed}ms\n";
echo "Messages count: " . count($msgs) . "\n";
if ($elapsed > 2000) {
    echo "[SLOW] Query took >2s — may cause timeout on shared hosting\n";
} else {
    echo "[OK] Fast enough\n";
}

// ============================================================
// 12. RECENT LOGS
// ============================================================
echo "\n--- 12. RECENT CAMPAIGN LOGS ---\n";
$logs = DB::fetchAll("SELECT level, source, message, context, created_at FROM logs WHERE source IN ('campaign','node','groq') ORDER BY id DESC LIMIT 10");
foreach ($logs as $l) {
    echo "  [{$l['created_at']}] [{$l['level']}] [{$l['source']}] {$l['message']}";
    if ($l['context']) echo " " . substr($l['context'], 0, 200);
    echo "\n";
}

// ============================================================
// 13. WEBHOOK EVENTS (recent)
// ============================================================
echo "\n--- 13. RECENT WEBHOOK EVENTS ---\n";
$events = DB::fetchAll("SELECT event_type, processed, last_error, created_at FROM webhook_events ORDER BY id DESC LIMIT 10");
foreach ($events as $ev) {
    echo "  [{$ev['created_at']}] type:{$ev['event_type']} processed:{$ev['processed']}";
    if ($ev['last_error']) echo " ERROR:" . substr($ev['last_error'], 0, 100);
    echo "\n";
}

// ============================================================
// SUMMARY
// ============================================================
echo "\n========================================\n";
echo " DIAGNOSIS SUMMARY\n";
echo "========================================\n";

$issues = [];
if (empty($health['ok'])) $issues[] = "HF Engine NOT reachable — check node_api_url";
if (empty($status['ok'])) $issues[] = "API key rejected by HF — check API_KEY matches node_api_key";
if (empty($health['whatsapp']['ready'])) $issues[] = "WhatsApp NOT connected — scan QR";
if ($currentHour < $activeStart || $currentHour >= $activeEnd) $issues[] = "Outside active hours ({$activeStart}-{$activeEnd}) — campaign cron won't send";
if (!$groqKey) $issues[] = "Groq API key empty — AI messages will use fallback";
if (($eligible['c'] ?? 0) == 0) $issues[] = "No eligible leads for campaign (all already sent or no valid leads)";
if (($sending['c'] ?? 0) > 0) $issues[] = ($sending['c']) . " leads stuck in 'sending' status — need reset";
if (($recentUnknowns['c'] ?? 0) > 0) $issues[] = "HF still creating Unknown leads — REDEPLOY HF Space";

if (!$issues) {
    echo "\n [ALL CLEAR] No issues found! System should be working.\n";
} else {
    echo "\n ISSUES FOUND (" . count($issues) . "):\n";
    foreach ($issues as $i => $issue) {
        echo "  " . ($i + 1) . ". {$issue}\n";
    }
}

echo "\n========================================\n";
echo " END OF DIAGNOSTIC\n";
echo "========================================\n";
echo "</pre>";
