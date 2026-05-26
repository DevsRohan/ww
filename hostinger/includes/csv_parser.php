<?php
/**
 * CSV Parser — robustly parse Google-Maps style business CSVs.
 *
 * Accepts headers (case-insensitive, flexible variants):
 *  - Business Name | Name
 *  - Address | Full Address
 *  - Phone | Phone Number | Mobile
 *  - Website | URL | Site
 *  - Rating
 *  - Reviews | Review Count | Reviews Count
 *  - Status
 *  - City, State, Locality (optional, will auto-derive from Address otherwise)
 */

class CsvParser
{
    /** @return array{rows: array, errors: array, total: int} */
    public static function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException('csv_unreadable');
        }
        $f = fopen($path, 'r');
        if (!$f) throw new RuntimeException('csv_open_failed');

        // BOM strip
        $first = fread($f, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($f);
        }

        $header = fgetcsv($f);
        if (!$header) {
            fclose($f);
            return ['rows' => [], 'errors' => ['empty_csv'], 'total' => 0];
        }
        $map = self::buildHeaderMap($header);

        $rows = [];
        $errors = [];
        $total = 0;
        while (($r = fgetcsv($f)) !== false) {
            $total++;
            try {
                $row = self::mapRow($r, $map);
                if (!$row) continue;
                $rows[] = $row;
            } catch (\Throwable $e) {
                $errors[] = "row $total: " . $e->getMessage();
            }
        }
        fclose($f);
        return ['rows' => $rows, 'errors' => $errors, 'total' => $total];
    }

    private static function buildHeaderMap(array $header): array
    {
        $aliases = [
            'business_name' => ['business name','name','title','business','company'],
            'address'       => ['address','full address','location'],
            'phone'         => ['phone','phone number','mobile','contact','contact number','telephone','phone1'],
            'website'       => ['website','url','site','web'],
            'rating'        => ['rating','stars'],
            'reviews'       => ['reviews','review count','reviews count','total reviews'],
            'status'        => ['status','business status'],
            'city'          => ['city'],
            'state'         => ['state','region'],
            'locality'      => ['locality','area','neighborhood'],
        ];
        $map = [];
        foreach ($header as $i => $h) {
            $key = strtolower(trim((string)$h));
            foreach ($aliases as $field => $alts) {
                if (in_array($key, $alts, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    private static function mapRow(array $r, array $map): ?array
    {
        $get = fn(string $k) => isset($map[$k]) && isset($r[$map[$k]]) ? trim((string)$r[$map[$k]]) : '';

        $businessName = sanitize_text($get('business_name'));
        $phoneRaw     = $get('phone');
        if ($businessName === '' || $phoneRaw === '') {
            return null; // skip silently
        }

        $phoneE164 = normalize_phone($phoneRaw);
        if (!$phoneE164) {
            throw new RuntimeException("invalid_phone:$phoneRaw");
        }

        $address = sanitize_text($get('address'));
        $city    = sanitize_text($get('city'));
        $state   = sanitize_text($get('state'));
        $locality= sanitize_text($get('locality'));
        if ((!$city || !$state || !$locality) && $address) {
            $parsed = parse_address($address);
            if (!$city)     $city     = (string)($parsed['city'] ?? '');
            if (!$state)    $state    = (string)($parsed['state'] ?? '');
            if (!$locality) $locality = (string)($parsed['locality'] ?? '');
        }

        $websiteRaw   = $get('website');
        $websiteStat  = detect_website($websiteRaw);
        $pitchType    = pitch_type_from_website($websiteStat);
        $language     = language_for_state($state);

        $rating  = $get('rating');
        $reviews = $get('reviews');

        return [
            'business_name'       => mb_substr($businessName, 0, 255),
            'address'             => $address,
            'locality'            => $locality !== '' ? mb_substr($locality, 0, 120) : null,
            'city'                => $city     !== '' ? mb_substr($city, 0, 120)     : null,
            'state'               => $state    !== '' ? mb_substr($state, 0, 120)    : null,
            'phone_number'        => $phoneRaw,
            'phone_e164'          => $phoneE164,
            'website_url'         => $websiteRaw !== '' ? mb_substr($websiteRaw, 0, 500) : null,
            'website_status'      => $websiteStat,
            'rating'              => $rating !== '' ? (float)$rating : null,
            'review_count'        => $reviews !== '' ? (int)preg_replace('/\D+/', '', $reviews) : 0,
            'pitch_type'          => $pitchType,
            'language_preference' => $language,
            'whatsapp_status'     => 'pending',
            'outreach_status'     => 'new',
            'source'              => 'csv_import',
        ];
    }
}
