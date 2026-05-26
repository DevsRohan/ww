<?php
/**
 * Message Repository — all DB ops on `messages` table.
 */

class MessageRepository
{
    public static function listForLead(int $leadId, int $limit = 200, int $offset = 0): array
    {
        return DB::fetchAll(
            'SELECT id, lead_id, direction, sender, message_text, wa_message_id, status, is_read, is_first_outreach, timestamp
             FROM messages
             WHERE lead_id = ?
             ORDER BY timestamp ASC
             LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            [$leadId]
        );
    }

    public static function findByWaId(string $waId): ?array
    {
        return DB::fetch('SELECT * FROM messages WHERE wa_message_id = ? LIMIT 1', [$waId]);
    }

    public static function recordOutbound(int $leadId, string $text, ?string $waId, string $status = 'queued', bool $isFirstOutreach = false, ?string $sender = 'system', ?array $meta = null): int
    {
        // Idempotency
        if ($waId) {
            $existing = self::findByWaId($waId);
            if ($existing) return (int)$existing['id'];
        }
        return DB::insert(
            'INSERT INTO messages (lead_id, direction, sender, message_text, wa_message_id, status, is_first_outreach, meta, timestamp)
             VALUES (?, "outbound", ?, ?, ?, ?, ?, ?, NOW())',
            [$leadId, $sender, $text, $waId, $status, $isFirstOutreach ? 1 : 0, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]
        );
    }

    public static function recordInbound(int $leadId, string $text, ?string $waId, ?int $unixTs = null, ?array $meta = null): int
    {
        if ($waId) {
            $existing = self::findByWaId($waId);
            if ($existing) return (int)$existing['id'];
        }
        $ts = $unixTs ? date('Y-m-d H:i:s', (int)$unixTs) : now_mysql();
        return DB::insert(
            'INSERT INTO messages (lead_id, direction, sender, message_text, wa_message_id, status, is_read, meta, timestamp)
             VALUES (?, "inbound", "lead", ?, ?, "received", 0, ?, ?)',
            [$leadId, $text, $waId, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, $ts]
        );
    }

    public static function updateStatusByWaId(string $waId, string $status, ?string $error = null): void
    {
        DB::execute(
            'UPDATE messages SET status = ?, error_message = ?, updated_at = NOW() WHERE wa_message_id = ?',
            [$status, $error, $waId]
        );
    }

    public static function markLeadRead(int $leadId): void
    {
        DB::execute('UPDATE messages SET is_read = 1 WHERE lead_id = ? AND direction = "inbound" AND is_read = 0', [$leadId]);
    }

    public static function alreadyOutreached(int $leadId): bool
    {
        $row = DB::fetch('SELECT id FROM messages WHERE lead_id = ? AND is_first_outreach = 1 LIMIT 1', [$leadId]);
        return $row !== null;
    }

    public static function lastForLead(int $leadId): ?array
    {
        return DB::fetch('SELECT * FROM messages WHERE lead_id = ? ORDER BY timestamp DESC LIMIT 1', [$leadId]);
    }

    public static function recentActivity(int $limit = 20): array
    {
        return DB::fetchAll(
            "SELECT m.*, l.business_name, l.phone_e164
             FROM messages m
             JOIN leads l ON l.id = m.lead_id
             ORDER BY m.timestamp DESC
             LIMIT " . (int)$limit
        );
    }
}
