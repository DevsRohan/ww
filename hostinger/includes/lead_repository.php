<?php
/**
 * Lead Repository — all DB ops on `leads` table.
 */

class LeadRepository
{
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM leads WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findByPhone(string $phoneE164): ?array
    {
        return DB::fetch('SELECT * FROM leads WHERE phone_e164 = ? LIMIT 1', [$phoneE164]);
    }

    public static function upsert(array $data): array
    {
        $now = now_mysql();
        $existing = self::findByPhone($data['phone_e164']);
        if ($existing) {
            // Update only enriching fields (do not overwrite phone)
            $updatable = ['business_name','address','locality','city','state','website_url','website_status','rating','review_count','pitch_type','language_preference','tags','notes','source'];
            $sets = [];
            $params = [];
            foreach ($updatable as $f) {
                if (array_key_exists($f, $data) && $data[$f] !== null && $data[$f] !== '') {
                    $sets[] = "`$f` = ?";
                    $params[] = is_array($data[$f]) ? json_encode($data[$f], JSON_UNESCAPED_UNICODE) : $data[$f];
                }
            }
            if ($sets) {
                $params[] = $existing['id'];
                DB::execute('UPDATE leads SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?', $params);
            }
            return ['id' => (int)$existing['id'], 'inserted' => false];
        }

        $id = DB::insert(
            'INSERT INTO leads (business_name, address, locality, city, state, phone_number, phone_e164,
              website_url, website_status, rating, review_count, whatsapp_status, outreach_status,
              pitch_type, language_preference, tags, notes, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['business_name'] ?? '',
                $data['address'] ?? null,
                $data['locality'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['phone_number'] ?? '',
                $data['phone_e164'],
                $data['website_url'] ?? null,
                $data['website_status'] ?? 'unknown',
                isset($data['rating']) ? (float)$data['rating'] : null,
                isset($data['review_count']) ? (int)$data['review_count'] : 0,
                $data['whatsapp_status'] ?? 'pending',
                $data['outreach_status'] ?? 'new',
                $data['pitch_type'] ?? 'unknown',
                $data['language_preference'] ?? 'hinglish',
                isset($data['tags']) ? (is_array($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : $data['tags']) : null,
                $data['notes'] ?? null,
                $data['source'] ?? 'csv_import',
                $now, $now,
            ]
        );
        return ['id' => $id, 'inserted' => true];
    }

    public static function updateField(int $id, string $field, $value): void
    {
        $allowed = ['whatsapp_status','outreach_status','pitch_type','language_preference','notes','website_url','website_status','last_outbound_at','last_inbound_at','last_contacted_at','unread_count','is_pinned','tags','rating','review_count','business_name','locality','city','state','address'];
        if (!in_array($field, $allowed, true)) return;
        if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        DB::execute("UPDATE leads SET `$field` = ?, updated_at = NOW() WHERE id = ?", [$value, $id]);
    }

    public static function setOutreachStatus(int $id, string $status): void
    {
        DB::execute('UPDATE leads SET outreach_status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
    }

    public static function setWhatsappStatus(int $id, string $status): void
    {
        DB::execute('UPDATE leads SET whatsapp_status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
    }

    public static function markOutbound(int $id): void
    {
        DB::execute('UPDATE leads SET last_outbound_at = NOW(), last_contacted_at = NOW(), updated_at = NOW() WHERE id = ?', [$id]);
    }

    public static function markInbound(int $id): void
    {
        DB::execute('UPDATE leads SET last_inbound_at = NOW(), outreach_status = "replied", unread_count = unread_count + 1, updated_at = NOW() WHERE id = ?', [$id]);
    }

    public static function markRead(int $id): void
    {
        DB::execute('UPDATE leads SET unread_count = 0, updated_at = NOW() WHERE id = ?', [$id]);
    }

    public static function togglePin(int $id, bool $on): void
    {
        DB::execute('UPDATE leads SET is_pinned = ?, updated_at = NOW() WHERE id = ?', [$on ? 1 : 0, $id]);
    }

    public static function addTag(int $id, string $tag): void
    {
        $row = self::findById($id);
        if (!$row) return;
        $tags = $row['tags'] ? json_decode($row['tags'], true) : [];
        if (!is_array($tags)) $tags = [];
        if (!in_array($tag, $tags, true)) $tags[] = $tag;
        DB::execute('UPDATE leads SET tags = ?, updated_at = NOW() WHERE id = ?', [json_encode($tags, JSON_UNESCAPED_UNICODE), $id]);
    }

    public static function removeTag(int $id, string $tag): void
    {
        $row = self::findById($id);
        if (!$row) return;
        $tags = $row['tags'] ? json_decode($row['tags'], true) : [];
        if (!is_array($tags)) return;
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        DB::execute('UPDATE leads SET tags = ?, updated_at = NOW() WHERE id = ?', [json_encode($tags, JSON_UNESCAPED_UNICODE), $id]);
    }

    public static function appendNote(int $id, string $note): void
    {
        $row = self::findById($id);
        if (!$row) return;
        $existing = (string)($row['notes'] ?? '');
        $stamp = date('Y-m-d H:i');
        $appended = trim($existing . "\n[$stamp] " . $note);
        DB::execute('UPDATE leads SET notes = ?, updated_at = NOW() WHERE id = ?', [$appended, $id]);
    }

    /**
     * Filtered list with pagination.
     */
    public static function search(array $filters, int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(business_name LIKE ? OR phone_number LIKE ? OR phone_e164 LIKE ? OR city LIKE ? OR locality LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q, $q);
        }
        if (!empty($filters['whatsapp_status'])) {
            $where[] = 'whatsapp_status = ?';
            $params[] = $filters['whatsapp_status'];
        }
        if (!empty($filters['outreach_status'])) {
            $where[] = 'outreach_status = ?';
            $params[] = $filters['outreach_status'];
        }
        if (!empty($filters['pitch_type'])) {
            $where[] = 'pitch_type = ?';
            $params[] = $filters['pitch_type'];
        }
        if (!empty($filters['city'])) {
            $where[] = 'city = ?';
            $params[] = $filters['city'];
        }
        if (!empty($filters['state'])) {
            $where[] = 'state = ?';
            $params[] = $filters['state'];
        }
        if (isset($filters['has_unread']) && $filters['has_unread']) {
            $where[] = 'unread_count > 0';
        }
        if (isset($filters['pinned']) && $filters['pinned']) {
            $where[] = 'is_pinned = 1';
        }

        $orderBy = 'is_pinned DESC, COALESCE(last_inbound_at, last_outbound_at, created_at) DESC';
        $whereSql = implode(' AND ', $where);

        $countRow = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE $whereSql", $params);
        $total = (int)($countRow['c'] ?? 0);

        $sql = "SELECT id, business_name, phone_number, phone_e164, city, state, locality,
                       website_status, whatsapp_status, outreach_status, pitch_type,
                       language_preference, rating, review_count, is_pinned, unread_count,
                       last_outbound_at, last_inbound_at, last_contacted_at, tags, created_at
                FROM leads WHERE $whereSql
                ORDER BY $orderBy
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $r['tags'] = $r['tags'] ? json_decode($r['tags'], true) : [];
        }
        return ['total' => $total, 'rows' => $rows];
    }

    public static function stats(): array
    {
        $totalRow = DB::fetch('SELECT COUNT(*) AS c FROM leads');
        $valid    = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'valid'");
        $invalid  = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status IN ('not_on_whatsapp','invalid','failed')");
        $pending  = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'");
        $sent     = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE outreach_status IN ('sent','delivered','read')");
        $replied  = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE outreach_status = 'replied'");
        $queued   = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE outreach_status = 'queued'");
        $today    = DB::fetch("SELECT COUNT(*) AS c FROM messages WHERE direction = 'outbound' AND DATE(timestamp) = CURDATE()");
        $unread   = DB::fetch('SELECT COALESCE(SUM(unread_count),0) AS c FROM leads');

        return [
            'total_leads'     => (int)$totalRow['c'],
            'valid_leads'     => (int)$valid['c'],
            'invalid_leads'   => (int)$invalid['c'],
            'pending_leads'   => (int)$pending['c'],
            'sent_count'      => (int)$sent['c'],
            'replied_count'   => (int)$replied['c'],
            'queued_count'    => (int)$queued['c'],
            'sent_today'      => (int)$today['c'],
            'unread_total'    => (int)$unread['c'],
        ];
    }

    public static function pickPendingValidation(int $limit = 20): array
    {
        return DB::fetchAll(
            "SELECT id, phone_e164 FROM leads WHERE whatsapp_status = 'pending' ORDER BY created_at ASC LIMIT " . (int)$limit
        );
    }

    public static function pickQueueable(int $limit = 5): array
    {
        return DB::fetchAll(
            "SELECT * FROM leads
             WHERE whatsapp_status = 'valid'
               AND outreach_status IN ('new','queued','failed')
               AND (last_outbound_at IS NULL)
             ORDER BY created_at ASC
             LIMIT " . (int)$limit
        );
    }

    public static function dailyOutboundCount(): int
    {
        $row = DB::fetch("SELECT COUNT(*) AS c FROM messages WHERE direction='outbound' AND DATE(timestamp) = CURDATE()");
        return (int)($row['c'] ?? 0);
    }
}
