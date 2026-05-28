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

        // Override language based on business type (professional vs local)
        if ($businessType !== '') {
            $language = self::resolveLanguageByBusinessType($businessType, $language);
        }

        $services = self::pickServices($pitchType, $business, $businessType);
        $servicesLine = implode(', ', $services);

        $languageInstr = self::languageInstruction($language);
        $pitchInstr    = self::pitchInstruction($pitchType);
        $industryInstr = self::industryInstruction($businessType);

        $location = trim(implode(', ', array_filter([$locality, $city, $state])));
        $trustSnippet = '';
        if ($rating !== null && (float)$rating > 0) {
            $trustSnippet = "Rating: $rating" . ($reviews ? " ($reviews reviews)" : "");
        }

        $businessTypeContext = $businessType !== '' ? "- Business Type: $businessType" : '';

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
10. Message should feel SO relevant that the reader thinks you understand their specific industry.
$languageInstr
SYS;

        $user = <<<USR
LEAD CONTEXT
- Business: $business
$businessTypeContext
- Location: $location
- Website status: $website
- Trust signal: $trustSnippet
- Pitch type: $pitchType
$pitchInstr
$industryInstr

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

    private static function pickServices(string $pitchType, string $businessName, string $businessType = ''): array
    {
        // Industry-specific service pools based on business type
        $typeKey = strtolower(trim($businessType));
        $industryServices = self::industryServicePool($typeKey);

        if (!empty($industryServices)) {
            $pool = $industryServices;
        } else {
            // Fallback to website-status-based pools
            $a = ['CRM Automation', 'AI Agent', 'WhatsApp Automation', 'Funnel Optimization', 'Website Speed & Conversion Audit'];
            $b = ['Business Website', 'Landing Page', 'Google My Business Optimization', 'Mobile-first Website', 'Enquiry / Lead Form System'];
            $pool = $pitchType === 'type_a' ? $a : ($pitchType === 'type_b' ? $b : array_merge(array_slice($a, 0, 2), array_slice($b, 0, 2)));
        }

        // Stable but lightly varied selection
        $seed = crc32($businessName);
        shuffle_with_seed($pool, $seed);
        return array_slice($pool, 0, 3);
    }

    /**
     * Return industry-specific service offerings based on business type.
     */
    private static function industryServicePool(string $type): array
    {
        if (!$type) return [];

        // Digital / IT / Agency
        if (str_contains($type, 'digital marketing') || str_contains($type, 'it ') || str_contains($type, 'software') || str_contains($type, 'agency')) {
            return ['White-label AI Chatbot', 'Collaboration / Reseller Partnership', 'Lead Generation Automation', 'Client Reporting Dashboard', 'WhatsApp API Integration'];
        }
        // Restaurant / Cafe / Food
        if (str_contains($type, 'restaurant') || str_contains($type, 'cafe') || str_contains($type, 'dhaba') || str_contains($type, 'food')) {
            return ['Online Menu & Ordering Page', 'Google My Business Optimization', 'WhatsApp Order Automation', 'Table Reservation System', 'Customer Review Collection'];
        }
        // Salon / Beauty / Spa
        if (str_contains($type, 'salon') || str_contains($type, 'parlour') || str_contains($type, 'parlor') || str_contains($type, 'beauty') || str_contains($type, 'spa')) {
            return ['Online Booking System', 'WhatsApp Appointment Reminders', 'Instagram-linked Portfolio Website', 'Google Maps Listing Optimization', 'Customer Loyalty Automation'];
        }
        // Doctor / Clinic / Healthcare
        if (str_contains($type, 'doctor') || str_contains($type, 'clinic') || str_contains($type, 'dentist') || str_contains($type, 'hospital') || str_contains($type, 'healthcare')) {
            return ['Patient Appointment Booking', 'WhatsApp Prescription & Follow-up', 'Google My Business for Clinics', 'Professional Medical Website', 'Patient Review Management'];
        }
        // Gym / Fitness
        if (str_contains($type, 'gym') || str_contains($type, 'fitness') || str_contains($type, 'yoga')) {
            return ['Member Management System', 'WhatsApp Class Reminders', 'Lead Capture Landing Page', 'Online Class Booking', 'Referral Program Automation'];
        }
        // Coaching / Education
        if (str_contains($type, 'coaching') || str_contains($type, 'tuition') || str_contains($type, 'classes') || str_contains($type, 'education') || str_contains($type, 'institute')) {
            return ['Student Enquiry Funnel', 'WhatsApp Batch Updates', 'Course Landing Page', 'Online Fee Collection', 'Google Ads for Admissions'];
        }
        // Hotel / Resort / Travel
        if (str_contains($type, 'hotel') || str_contains($type, 'resort') || str_contains($type, 'travel')) {
            return ['Direct Booking Website', 'WhatsApp Concierge Bot', 'OTA-independent Lead Funnel', 'Google Hotel Listing Optimization', 'Guest Review Automation'];
        }
        // Law Firm / CA / Consulting
        if (str_contains($type, 'law') || str_contains($type, 'advocate') || str_contains($type, 'ca ') || str_contains($type, 'chartered') || str_contains($type, 'consult')) {
            return ['Professional Authority Website', 'Client Intake Automation', 'WhatsApp Document Collection', 'LinkedIn Thought Leadership Funnel', 'CRM for Client Management'];
        }
        // Real Estate
        if (str_contains($type, 'real estate') || str_contains($type, 'property') || str_contains($type, 'builder')) {
            return ['Property Listing Website', 'WhatsApp Lead Nurturing', 'Virtual Tour Pages', 'Facebook/Google Ads for Plots', 'CRM for Buyer Follow-up'];
        }
        // Retail / Shop
        if (str_contains($type, 'shop') || str_contains($type, 'store') || str_contains($type, 'kirana') || str_contains($type, 'retail')) {
            return ['Product Catalogue Website', 'WhatsApp Order System', 'Google My Business Setup', 'Local SEO', 'Customer Loyalty WhatsApp Bot'];
        }

        return [];
    }

    /**
     * Industry-specific prompt instructions based on business type.
     */
    private static function industryInstruction(string $businessType): string
    {
        if ($businessType === '') return '';

        $type = strtolower(trim($businessType));

        if (str_contains($type, 'digital marketing') || str_contains($type, 'agency')) {
            return "INDUSTRY NOTE: This is a digital agency — pitch COLLABORATION or white-label partnership, NOT basic marketing services they already provide. Think: reseller AI tools, mutual referrals, or white-label chatbots.";
        }
        if (str_contains($type, 'restaurant') || str_contains($type, 'cafe') || str_contains($type, 'food')) {
            return "INDUSTRY NOTE: Restaurant/food business — focus on online ordering, customer reviews, and table reservations. They care about footfall and repeat customers, not abstract 'digital growth'.";
        }
        if (str_contains($type, 'salon') || str_contains($type, 'parlour') || str_contains($type, 'beauty')) {
            return "INDUSTRY NOTE: Salon/beauty business — focus on online booking, appointment reminders, and showcasing their work visually. They value Instagram presence and word-of-mouth.";
        }
        if (str_contains($type, 'doctor') || str_contains($type, 'clinic') || str_contains($type, 'dentist')) {
            return "INDUSTRY NOTE: Medical/clinic — focus on patient convenience (online appointments, WhatsApp follow-ups). Tone must be professional and trustworthy. Never sound salesy.";
        }
        if (str_contains($type, 'gym') || str_contains($type, 'fitness')) {
            return "INDUSTRY NOTE: Gym/fitness — focus on member retention, class scheduling automation, and new member lead capture. They think in terms of memberships and batches.";
        }
        if (str_contains($type, 'coaching') || str_contains($type, 'tuition') || str_contains($type, 'classes')) {
            return "INDUSTRY NOTE: Coaching/education — focus on student enquiry capture, batch management, and parent communication. Admission season matters to them.";
        }
        if (str_contains($type, 'hotel') || str_contains($type, 'resort')) {
            return "INDUSTRY NOTE: Hotel/resort — focus on direct bookings (reducing OTA commissions), guest experience automation, and review management.";
        }
        if (str_contains($type, 'law') || str_contains($type, 'advocate') || str_contains($type, 'ca') || str_contains($type, 'chartered')) {
            return "INDUSTRY NOTE: Professional services (legal/CA) — focus on authority building, client intake automation, and professional credibility. Tone must be very polished.";
        }
        if (str_contains($type, 'real estate') || str_contains($type, 'property')) {
            return "INDUSTRY NOTE: Real estate — focus on lead generation for plots/flats, virtual tours, and CRM for buyer follow-up. They care about qualified leads, not just traffic.";
        }

        return "INDUSTRY NOTE: Business type is '$businessType' — tailor your pitch to what THIS specific industry actually needs. Don't be generic.";
    }

    /**
     * Language resolution based on business type:
     * Professional → English, Local → Hinglish
     */
    private static function resolveLanguageByBusinessType(string $businessType, string $fallback): string
    {
        $type = strtolower(trim($businessType));

        $professional = [
            'digital marketing agency', 'digital marketing', 'it company', 'it services',
            'software company', 'law firm', 'lawyer', 'advocate', 'ca', 'chartered accountant',
            'consulting', 'consultancy', 'hotel', 'resort', 'corporate', 'architect',
            'interior designer', 'real estate', 'export', 'import', 'travel agency',
            'event management', 'advertising agency', 'media company', 'startup',
            'coworking', 'fintech', 'edtech', 'clinic chain', 'hospital',
        ];

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
            if (str_contains($type, $keyword)) return 'business_english';
        }
        foreach ($local as $keyword) {
            if (str_contains($type, $keyword)) return 'hinglish';
        }

        return $fallback;
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
        $bizType   = $lead['business_type'] ?? '';
        $city      = $lead['city'] ?? '';
        $rating    = $lead['rating'] ?? null;
        $reviews   = $lead['review_count'] ?? null;
        $website   = $lead['website_status'] ?? 'unknown';
        $pitchType = $lead['pitch_type'] ?? 'unknown';
        $signature = $owner['signature'] ?? 'Rohan from Rohan Digital';
        $lang      = $lead['language_preference'] ?? 'hinglish';

        // Override language for fallback too
        if ($bizType !== '') {
            $lang = self::resolveLanguageByBusinessType($bizType, $lang);
        }

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
