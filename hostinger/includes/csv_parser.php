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
            'business_type' => ['business type','type','category','business category','industry'],
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
        $businessType = sanitize_text($get('business_type'));
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

        // Override language based on business_type (professional vs local)
        if ($businessType !== '') {
            $language = self::languageForBusinessType($businessType, $language);
        }

        $rating  = $get('rating');
        $reviews = $get('reviews');

        return [
            'business_name'       => mb_substr($businessName, 0, 255),
            'business_type'       => $businessType !== '' ? mb_substr($businessType, 0, 120) : null,
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

    /**
     * Determine language based on business type:
     * Professional businesses → English
     * Local/service businesses → Hinglish
     */
    private static function languageForBusinessType(string $businessType, string $fallback): string
    {
        $type = strtolower(trim($businessType));

        // Professional businesses → English
        $professional = [
            'digital marketing agency', 'digital marketing', 'it company', 'it services',
            'software company', 'law firm', 'lawyer', 'advocate', 'ca', 'chartered accountant',
            'consulting', 'consultancy', 'hotel', 'resort', 'corporate', 'architect',
            'interior designer', 'real estate', 'export', 'import', 'travel agency',
            'event management', 'advertising agency', 'media company', 'startup',
            'coworking', 'fintech', 'edtech', 'clinic chain', 'hospital',
        ];

        // Local businesses → Hinglish
        $local = [
            'shop', 'kirana', 'grocery', 'restaurant', 'dhaba', 'cafe',
            'salon', 'parlour', 'parlor', 'beauty salon', 'barber',
            'gym', 'fitness', 'yoga', 'coaching', 'tuition', 'classes',
            'doctor', 'clinic', 'dentist', 'pharmacy', 'medical store',
            'tailor', 'boutique', 'jeweller', 'jewellery', 'optician',
            'garage', 'mechanic', 'electrician', 'plumber', 'carpenter',
            'sweet shop', 'bakery', 'caterer', 'florist', 'laundry',
            'pet shop', 'stationery', 'mobile shop', 'electronics shop',
        ];

        foreach ($professional as $keyword) {
            if (str_contains($type, $keyword)) {
                return 'business_english';
            }
        }

        foreach ($local as $keyword) {
            if (str_contains($type, $keyword)) {
                return 'hinglish';
            }
        }

        return $fallback;
    }
}
