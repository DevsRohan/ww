<?php
/**
 * Groq AI Engine — message personalization for cold outreach.
 *
 * Responsibilities:
 *  - Build context-aware prompts based on lead segmentation
 *  - Branch by website status (type_a vs type_b)
 *  - Adapt language by region
 *  - Pick relevant services (never list all)
 *  - Call Groq API with safe error fallbacks
 *  - Provide a human-quality fallback when AI is unavailable
 */

class Groq
{
    public static function generateOutreach(array $lead): array
    {
        $cfg = $GLOBALS['APP']['groq'];
        $owner = $GLOBALS['APP']['owner'];

        $apiKey = (string)($cfg['api_key'] ?? '');
        $usedFallback = false;
        $message = null;

        $prompt = self::buildPrompt($lead, $owner);

        if ($apiKey !== '') {
            try {
                $message = self::callApi($apiKey, $cfg, $prompt);
            } catch (\Throwable $e) {
                AppLogger::warn('groq_api_failed', ['err' => $e->getMessage()], 'groq');
                $message = null;
            }
        }

        if ($message === null || trim($message) === '') {
            $message = self::fallbackMessage($lead, $owner);
            $usedFallback = true;
        }

        $message = self::sanitizeOutput($message);

        return [
            'message' => $message,
            'language' => $lead['language_preference'] ?? 'hinglish',
            'pitch_type' => $lead['pitch_type'] ?? 'unknown',
            'used_fallback' => $usedFallback,
        ];
    }

    private static function buildPrompt(array $lead, array $owner): array
    {
        $business   = $lead['business_name'] ?? 'this business';
        $locality   = $lead['locality'] ?? '';
        $city       = $lead['city'] ?? '';
        $state      = $lead['state'] ?? '';
        $rating     = $lead['rating'] ?? null;
        $reviews    = $lead['review_count'] ?? null;
        $website    = $lead['website_status'] ?? 'unknown';
        $pitchType  = $lead['pitch_type'] ?? 'unknown';
        $language   = $lead['language_preference'] ?? 'hinglish';
        $signature  = $owner['signature'] ?? 'Rohan from Rohan Digital';
        $brand      = $owner['brand_name'] ?? 'Rohan Digital';

        $services = self::pickServices($pitchType, $business);
        $servicesLine = implode(', ', $services);

        $languageInstr = self::languageInstruction($language);
        $pitchInstr    = self::pitchInstruction($pitchType);

        $location = trim(implode(', ', array_filter([$locality, $city, $state])));
        $trustSnippet = '';
        if ($rating !== null && (float)$rating > 0) {
            $trustSnippet = "Rating: $rating" . ($reviews ? " ($reviews reviews)" : "");
        }

        $system = <<<SYS
You are an expert cold outreach copywriter for a digital agency named "$brand".
You write the FIRST WhatsApp message to a local business owner.
Hard rules:
1. Output ONLY the final message text. No preface, no labels, no markdown, no quotes.
2. 4-5 short paragraphs, total 80-130 words.
3. NO emojis except a single optional 👋 in the opener (keep it tasteful, can omit).
4. NO pricing. NO urgency. NO ALL CAPS. NO sales clichés.
5. Mention business name and city/locality naturally.
6. Mention rating/reviews ONLY if it adds genuine credibility (skip if missing).
7. End with a soft, low-friction CTA inviting a short reply (not a phone call).
8. Sign off with: "— $signature".
9. Sound like a real human messaging on WhatsApp, not a marketer.
$languageInstr
SYS;

        $user = <<<USR
LEAD CONTEXT
- Business: $business
- Location: $location
- Website status: $website
- Trust signal: $trustSnippet
- Pitch type: $pitchType
$pitchInstr

PICK FROM THESE RELEVANT SERVICES (use 1-2 maximum, mention naturally, never list all):
$servicesLine

Now write the message.
USR;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    private static function pitchInstruction(string $pitchType): string
    {
        if ($pitchType === 'type_a') {
            return "- They already have a website. Pitch ANGLE = optimization, automation, AI, CRM, growth. Do NOT offer to build a website.";
        }
        if ($pitchType === 'type_b') {
            return "- They have NO website. Pitch ANGLE = digital presence, professional website / landing page, online discoverability. Do NOT offer optimization of an existing site.";
        }
        return "- Pitch angle is general digital growth.";
    }

    private static function languageInstruction(string $lang): string
    {
        switch ($lang) {
            case 'hinglish':
                return "10. LANGUAGE: MUST write in Hinglish (Roman script Hindi mixed with English). Example: 'Aapki shop ka rating kaafi accha hai. Hum aapke liye ek landing page bana sakte hain.' — DO NOT write in pure English.";
            case 'gujarati_english':
                return "Write in clean business English with a couple of warm Gujarati-flavored phrases (in English script) only if natural. Default to English.";
            case 'marathi_english':
                return "Write in clean business English with a couple of warm Marathi-flavored phrases (in English script) only if natural. Default to English.";
            case 'punjabi_hinglish':
                return "Write in friendly Hinglish with a hint of Punjabi warmth (Roman script). Default to Hinglish.";
            case 'bengali_english':
                return "Write in polite business English with optional warm Bengali touch (in English script). Default to English.";
            case 'business_english':
            default:
                return "Write in clean, professional yet friendly business English.";
        }
    }

    private static function pickServices(string $pitchType, string $businessName): array
    {
        $a = ['CRM Automation', 'AI Agent', 'WhatsApp Automation', 'Funnel Optimization', 'Website Speed & Conversion Audit'];
        $b = ['Business Website', 'Landing Page', 'Google My Business Optimization', 'Mobile-first Website', 'Enquiry / Lead Form System'];
        $pool = $pitchType === 'type_a' ? $a : ($pitchType === 'type_b' ? $b : array_merge(array_slice($a, 0, 2), array_slice($b, 0, 2)));
        // Stable but lightly varied selection
        $seed = crc32($businessName);
        shuffle_with_seed($pool, $seed);
        return array_slice($pool, 0, 3);
    }

    private static function callApi(string $apiKey, array $cfg, array $messages): ?string
    {
        $body = [
            'model'       => $cfg['model'] ?? 'llama-3.3-70b-versatile',
            'messages'    => $messages,
            'temperature' => (float)($cfg['temperature'] ?? 0.7),
            'max_tokens'  => (int)($cfg['max_tokens'] ?? 800),
            'top_p'       => 0.9,
        ];

        $ch = curl_init($cfg['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)($cfg['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('curl: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            throw new RuntimeException('http_' . $code . ': ' . substr((string)$raw, 0, 300));
        }
        $j = json_decode((string)$raw, true);
        if (!is_array($j)) throw new RuntimeException('invalid_json');
        $content = $j['choices'][0]['message']['content'] ?? null;
        return is_string($content) ? $content : null;
    }

    private static function sanitizeOutput(string $msg): string
    {
        // Strip surrounding quotes if AI added any
        $m = trim($msg);
        $m = trim($m, "\"' \t\n\r");
        // Collapse 3+ newlines
        $m = preg_replace("/\n{3,}/", "\n\n", $m);
        return $m;
    }

    private static function fallbackMessage(array $lead, array $owner): string
    {
        $name      = $lead['business_name'] ?? 'aapki business';
        $city      = $lead['city'] ?? '';
        $rating    = $lead['rating'] ?? null;
        $reviews   = $lead['review_count'] ?? null;
        $website   = $lead['website_status'] ?? 'unknown';
        $pitchType = $lead['pitch_type'] ?? 'unknown';
        $signature = $owner['signature'] ?? 'Rohan from Rohan Digital';
        $lang      = $lead['language_preference'] ?? 'hinglish';

        $trust = '';
        if ($rating && (float)$rating >= 4.0) {
            $trust = $lang === 'business_english'
                ? "Your $rating rating" . ($reviews ? " across $reviews reviews" : '') . " is impressive."
                : ($city ? "{$city} mein " : '') . "{$name} ka {$rating} rating" . ($reviews ? " aur {$reviews} reviews" : '') . " genuinely impressive hai.";
        }

        if ($pitchType === 'type_a') {
            $body = $lang === 'business_english'
                ? "I noticed your website is live, which is great. Most established local businesses still leave a lot of growth on the table because their site isn't tuned for conversions or doesn't have basic automations like WhatsApp follow-ups or a lead-capture flow."
                : "Maine notice kiya aapki website live hai — that's already ahead of most local businesses. Bas ek baat: aksar website hone ke baad bhi conversions aur lead capture properly setup nahi hota, aur WhatsApp follow-up jaise simple automations miss ho jaate hain.";
            $offer = $lang === 'business_english'
                ? "We help businesses like yours with conversion-focused website tweaks and a simple WhatsApp + CRM automation that captures and nurtures every enquiry."
                : "Hum businesses ke liye conversion-friendly website improvements aur simple WhatsApp + CRM automation setup karte hain — taaki har enquiry properly capture ho.";
        } else {
            $body = $lang === 'business_english'
                ? "I noticed your business doesn't seem to have a dedicated website yet. In your category, a clean mobile-first site (or a single landing page with WhatsApp enquiry) usually doubles incoming leads within a few weeks."
                : "Maine dekha aapki business ka dedicated website abhi nahi hai. Aapki category mein ek clean mobile-friendly website ya simple landing page (with WhatsApp enquiry) usually 2-3 weeks mein leads kaafi badha deti hai.";
            $offer = $lang === 'business_english'
                ? "We build fast, mobile-first business websites and landing pages designed specifically for local lead generation."
                : "Hum specifically local lead generation ke liye fast, mobile-first websites aur landing pages design karte hain.";
        }

        $cta = $lang === 'business_english'
            ? "If this sounds useful, just reply 'yes' and I'll share a quick 2-minute overview tailored to {$name}."
            : "Agar useful lagta hai toh ek short 'haan' reply kar dijiye, main {$name} ke liye ek quick 2-minute overview share kar dunga.";

        return implode("\n\n", array_filter([
            ($lang === 'business_english' ? "Hi! 👋 Reaching out about {$name}." : "Namaste 👋 {$name} ke baare mein ek quick baat."),
            $trust,
            $body,
            $offer,
            $cta,
            "— {$signature}",
        ]));
    }
}

/**
 * Seeded shuffle (deterministic per-business but varied across leads).
 */
if (!function_exists('shuffle_with_seed')) {
    function shuffle_with_seed(array &$arr, int $seed): void {
        mt_srand($seed);
        for ($i = count($arr) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
        mt_srand();
    }
}
