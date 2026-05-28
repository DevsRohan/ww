<?php
/**
 * Refresh/Sync message statuses from Node engine.
 *
 * Logic: Node queue is FIFO. If we sent 30 messages to queue and queue
 * currently has 20 items, then first 10 are already sent.
 * We mark messages as 'sent' in ORDER (oldest first) until we've marked
 * (total_queued - current_queue_size) messages.
 *
 * Called by frontend every 30s during active campaign.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

// Get all outbound messages still marked 'queued' (ordered oldest first)
$queuedMessages = DB::fetchAll(
    "SELECT m.id, m.lead_id, m.created_at
     FROM messages m
     WHERE m.status = 'queued'
       AND m.direction = 'outbound'
     ORDER BY m.created_at ASC"
);

$totalQueued = count($queuedMessages);
$updated = 0;
$queueSize = 0;
$nodeOk = false;

// Ask Node how many items are STILL in its queue
$nodeStatus = NodeClient::status();
if (!empty($nodeStatus['ok']) && isset($nodeStatus['status'])) {
    $nodeOk = true;
    $queueSnap = $nodeStatus['status']['queue'] ?? null;
    if (is_array($queueSnap)) {
        $queueSize = (int)($queueSnap['size'] ?? 0);
    }
}

if ($nodeOk && $totalQueued > 0) {
    // How many have been processed = total we queued - what's still in Node queue
    // Node queue is FIFO: last N are still pending, everything before = already sent
    $alreadySent = max(0, $totalQueued - $queueSize);

    if ($alreadySent > 0) {
        // Mark the first $alreadySent messages as 'sent' (they're oldest = processed first)
        $toMark = array_slice($queuedMessages, 0, $alreadySent);
        foreach ($toMark as $msg) {
            DB::execute("UPDATE messages SET status = 'sent', updated_at = NOW() WHERE id = ? AND status = 'queued'", [$msg['id']]);
            $leadId = (int)$msg['lead_id'];
            LeadRepository::setOutreachStatus($leadId, 'sent');
            LeadRepository::markOutbound($leadId);
            $updated++;
        }
    }
} elseif (!$nodeOk && $totalQueued > 0) {
    // Can't reach Node — check by time: if message is older than 5 min, likely sent
    $oldMessages = DB::fetchAll(
        "SELECT m.id, m.lead_id
         FROM messages m
         WHERE m.status = 'queued'
           AND m.direction = 'outbound'
           AND m.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY m.created_at ASC
         LIMIT 50"
    );
    foreach ($oldMessages as $msg) {
        DB::execute("UPDATE messages SET status = 'sent', updated_at = NOW() WHERE id = ? AND status = 'queued'", [$msg['id']]);
        $leadId = (int)$msg['lead_id'];
        LeadRepository::setOutreachStatus($leadId, 'sent');
        LeadRepository::markOutbound($leadId);
        $updated++;
    }
}

if ($updated > 0) {
    AppLogger::info('refresh_sync_fixed', ['updated' => $updated, 'queue_size' => $queueSize], 'sync');
}

json_ok([
    'total_queued'    => $totalQueued,
    'updated_to_sent' => $updated,
    'queue_size'      => $queueSize,
    'node_reachable'  => $nodeOk,
]);
