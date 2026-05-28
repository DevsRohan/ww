<?php
/**
 * Refresh/Sync message statuses from Node engine.
 *
 * When webhook doesn't fire (secret missing, URL not set, etc),
 * this endpoint manually checks if queued messages have been sent
 * by querying the Node engine's queue status.
 *
 * Called by frontend every 30s during active campaign.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

// Find all messages stuck in 'queued' status for more than 60 seconds
$stuckMessages = DB::fetchAll(
    "SELECT m.id, m.lead_id, m.wa_message_id, m.meta, m.created_at
     FROM messages m
     WHERE m.status = 'queued'
       AND m.direction = 'outbound'
       AND m.created_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)
     ORDER BY m.created_at ASC
     LIMIT 50"
);

$updated = 0;
$nodeStatus = NodeClient::status();
$queueSize = 0;

if (!empty($nodeStatus['ok']) && isset($nodeStatus['status'])) {
    $queueSnap = $nodeStatus['status']['queue'] ?? null;
    if (is_array($queueSnap)) {
        $queueSize = $queueSnap['size'] ?? 0;
    }
}

// If Node queue is empty but we have queued messages, they were already sent
// (webhook just didn't fire). Mark them as 'sent'.
if ($queueSize === 0 && count($stuckMessages) > 0) {
    foreach ($stuckMessages as $msg) {
        DB::execute("UPDATE messages SET status = 'sent', updated_at = NOW() WHERE id = ? AND status = 'queued'", [$msg['id']]);
        $leadId = (int)$msg['lead_id'];
        LeadRepository::setOutreachStatus($leadId, 'sent');
        LeadRepository::markOutbound($leadId);
        $updated++;
    }
    if ($updated > 0) {
        AppLogger::info('refresh_sync_fixed', ['updated' => $updated], 'sync');
    }
}

// Also check: if queue has items, see which leads are still waiting
$pendingInQueue = $queueSize;

json_ok([
    'checked'         => count($stuckMessages),
    'updated_to_sent' => $updated,
    'queue_size'      => $queueSize,
    'pending_in_queue'=> $pendingInQueue,
]);
