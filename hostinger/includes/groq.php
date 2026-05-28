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
        $business     = $lead['business_name'] ?? 'this business';
        $businessType = $lead['business_type'] ?? '';
        $locality     = $lead['locality'] ?? '';
        $city         = $lead['city'] ?? '';
        $state        = $lead['state'] ?? '';
        $rating       = $lead['rating'] ?? null;
        $reviews      = $lead['review_count'] ?? null;
        $website      = $lead['website_status'] ?? 'unknown';
        $pitchType    = $lead['pitch_type'] ?? 'unknown';
        $language     = $lead['language_preference'] ?? 'hinglish';
        $signature    = $owner['signature'] ?? 'Rohan from Rohan Digital';
        $brand        = $owner['brand_name'] ?? 'Rohan Digital';

        $services = self::pickServices($pitchType, $businessType, $business);
        $servicesLine = implode(', ', $services);

        // Language: professional businesses get English, local businesses get Hinglish
        if ($businessType && self::isProfessionalBusiness($businessType)) {
            $language = 'business_english';
        }
        $languageInstr = self::languageInstruction($language);
        $pitchInstr    = self::pitchInstruction($pitchType);
        $bizTypeInstr  = self::businessTypeInstruction($businessType);

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
10. Acknowledge their SPECIFIC industry/business type naturally in the opener or body.
11. The message should feel SO relevant that the reader thinks "this person actually understands my business".
$languageInstr
SYS;

        $user = <<<USR
LEAD CONTEXT
- Business: $business
- Business Type: $businessType
- Location: $location
- Website status: $website
- Trust signal: $trustSnippet
- Pitch type: $pitchType
$bizTypeInstr
$pitchInstr

PICK FROM THESE RELEVANT SERVICES (use 1-2 maximum, mention naturally, never list all):
$servicesLine

IMPORTANT: Write the message as if you deeply understand the challenges of running a "$businessType" business. Reference industry-specific pain points. Make it feel personal, not generic.

Now write the message.
USR;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    private static function isProfessionalBusiness(string $type): bool
    {
        $professional = ['digital marketing agency','software company','it company','consulting firm','law firm','ca firm','chartered accountant','architect','hospital','clinic','hotel','resort','real estate','finance','bank','insurance'];
        $t = mb_strtolower($type);
        foreach ($professional as $p) {
            if (str_contains($t, $p)) return true;
        }
        return false;
    }

    private static function businessTypeInstruction(string $businessType): string
    {
        if (!$businessType) return '';
        $t = mb_strtolower($businessType);

        if (str_contains($t, 'digital marketing') || str_contains($t, 'seo') || str_contains($t, 'web development')) {
            return "- INDUSTRY CONTEXT: They are a digital agency themselves. Pitch collaboration, white-label services, AI automation tools they can offer their own clients, or overflow work partnership. Do NOT pitch basic digital marketing to them.";
        }
        if (str_contains($t, 'restaurant') || str_contains($t, 'cafe') || str_contains($t, 'food')) {
            return "- INDUSTRY CONTEXT: Food business. Pitch online ordering system, Google Maps visibility, WhatsApp menu/ordering bot, social media content for food photography.";
        }
        if (str_contains($t, 'salon') || str_contains($t, 'spa') || str_contains($t, 'beauty')) {
            return "- INDUSTRY CONTEXT: Beauty/wellness business. Pitch appointment booking system, Instagram/social media marketing, Google reviews automation, before-after portfolio website.";
        }
        if (str_contains($t, 'doctor') || str_contains($t, 'hospital') || str_contains($t, 'clinic') || str_contains($t, 'dental')) {
            return "- INDUSTRY CONTEXT: Healthcare. Pitch patient appointment booking, Google My Business optimization, reputation management, telemedicine website features.";
        }
        if (str_contains($t, 'gym') || str_contains($t, 'fitness') || str_contains($t, 'yoga')) {
            return "- INDUSTRY CONTEXT: Fitness business. Pitch membership management, class booking system, transformation showcase website, lead generation through social proof.";
        }
        if (str_contains($t, 'school') || str_contains($t, 'coaching') || str_contains($t, 'education') || str_contains($t, 'tuition')) {
            return "- INDUSTRY CONTEXT: Education/coaching. Pitch student enrollment system, online course platform, parent communication automation, Google Ads for local student acquisition.";
        }
        if (str_contains($t, 'real estate') || str_contains($t, 'property') || str_contains($t, 'builder')) {
            return "- INDUSTRY CONTEXT: Real estate. Pitch property listing website, lead capture landing pages, virtual tour integration, CRM for buyer follow-up automation.";
        }
        if (str_contains($t, 'shop') || str_contains($t, 'store') || str_contains($t, 'retail') || str_contains($t, 'boutique')) {
            return "- INDUSTRY CONTEXT: Retail shop. Pitch e-commerce/online store, WhatsApp catalog, Google Shopping integration, local SEO for foot traffic.";
        }
        if (str_contains($t, 'hotel') || str_contains($t, 'resort') || str_contains($t, 'travel') || str_contains($t, 'tourism')) {
            return "- INDUSTRY CONTEXT: Hospitality/travel. Pitch direct booking website (reduce OTA commission), Google Hotel integration, review management, retargeting campaigns.";
        }
        if (str_contains($t, 'lawyer') || str_contains($t, 'advocate') || str_contains($t, 'legal') || str_contains($t, 'law firm')) {
            return "- INDUSTRY CONTEXT: Legal services. Pitch professional authority website, content marketing for legal expertise, client intake automation, Google Ads for case-specific keywords.";
        }
        return "- INDUSTRY CONTEXT: Business type is '$businessType'. Tailor your message to address specific challenges and growth opportunities in this industry.";
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
                return "Write in friendly Hinglish (Roman script Hindi mixed with simple English). Warm and respectful tone.";
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

    private static function pickServices(string $pitchType, string $businessType, string $businessName): array
    {
        // If business type is digital marketing — offer collaboration/tools
        if ($businessType && str_contains(mb_strtolower($businessType), 'digital marketing')) {
            $pool = ['White-label AI Chatbot', 'Client CRM Automation', 'Overflow Project Partnership', 'AI Content Generation Tool', 'Automated Reporting Dashboard'];
        } else if ($pitchType === 'type_a') {
            $pool = ['CRM Automation', 'AI Agent', 'WhatsApp Automation', 'Funnel Optimization', 'Website Speed & Conversion Audit'];
        } else if ($pitchType === 'type_b') {
            $pool = ['Business Website', 'Landing Page', 'Google My Business Optimization', 'Mobile-first Website', 'Enquiry / Lead Form System'];
        } else {
            $pool = ['CRM Automation', 'AI Agent', 'Business Website', 'Landing Page'];
        }
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
