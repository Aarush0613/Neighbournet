<?php
/**
 * NeighbourNet - Translation Service Configuration & Helper
 * Server-side translation provider interface supporting Hindi <-> English
 * Credentials stored strictly server-side.
 */

define('TRANSLATION_ENABLED', true);
define('TRANSLATION_PROVIDER', getenv('TRANSLATION_PROVIDER') ?: 'mymemory');
define('TRANSLATION_API_KEY', getenv('TRANSLATION_API_KEY') ?: '');

/**
 * Civic vocabulary offline fallback dictionary for common municipal reporting terms
 * Used if remote translation APIs are blocked, offline, or rate-limited.
 */
function getCivicDictionary(): array {
    return [
        'hi_to_en' => [
            'सड़क पर बहुत बड़ा गड्ढा है' => 'There is a very large pothole on the road.',
            'सड़क टूटी हुई है' => 'The road is broken and damaged.',
            'गड्ढा' => 'pothole',
            'स्ट्रीट लाइट बंद है' => 'The streetlight is not working.',
            'लाइट खराब है' => 'The light is out of order.',
            'पानी की पाइपलाइन लीक हो रही है' => 'The water pipeline is leaking.',
            'पीने का पानी बर्बाद हो रहा है' => 'Drinking water is being wasted.',
            'कचरा फैला हुआ है' => 'Garbage is accumulated and overflowing.',
            'नाली जाम है और पानी भर गया है' => 'The drain is blocked and overflowing.',
            'पेड़ गिर गया है और रास्ता बंद है' => 'A tree has fallen and blocked the road.',
            'बिजली का खंभा झुक गया है' => 'The electricity pole is leaning dangerously.',
            'खुला मैनहोल खतरनाक है' => 'The open manhole is dangerous.',
            'कचरे का ढेर' => 'garbage pile',
            'पानी भरा हुआ है' => 'water is logged'
        ],
        'en_to_hi' => [
            'There is a big pothole on the road' => 'सड़क पर एक बड़ा गड्ढा है।',
            'The road is broken' => 'सड़क टूटी हुई है।',
            'Streetlight is not working' => 'स्ट्रीट लाइट काम नहीं कर रही है।',
            'Water pipe is leaking' => 'पानी का पाइप लीक हो रहा है।',
            'Garbage is overflowing' => 'कचरा फैला हुआ है।',
            'Drainage is clogged' => 'नाली जाम है।',
            'A tree has fallen and blocked the street' => 'एक पेड़ गिर गया है और सड़क बंद है।',
            'Open manhole poses danger' => 'खुला मैनहोल खतरा पैदा कर रहा है।'
        ]
    ];
}

/**
 * Translate text between Hindi ('hi') and English ('en')
 */
function translateText(string $text, string $sourceLang, string $targetLang): array {
    $text = trim($text);
    if (empty($text)) {
        return ['success' => false, 'error' => 'Input text is empty.'];
    }

    if ($sourceLang === $targetLang) {
        return ['success' => true, 'translated_text' => $text, 'provider' => 'identity'];
    }

    // Format ISO codes
    $src = ($sourceLang === 'hi' || $sourceLang === 'hi-IN') ? 'hi' : 'en';
    $tgt = ($targetLang === 'en' || $targetLang === 'en-IN') ? 'en' : 'hi';

    // 1. Try public translation API (MyMemory)
    $encodedText = urlencode($text);
    $langPair = "{$src}|{$tgt}";
    $apiUrl = "https://api.mymemory.translated.net/get?q={$encodedText}&langpair={$langPair}";

    $context = stream_context_create([
        'http' => [
            'timeout' => 3.5, // Quick 3.5 second timeout
            'header'  => "User-Agent: NeighbourNet-CivicApp/1.0\r\n"
        ]
    ]);

    $rawResponse = @file_get_contents($apiUrl, false, $context);
    if ($rawResponse !== false) {
        $json = json_decode($rawResponse, true);
        if (!empty($json['responseData']['translatedText'])) {
            $translated = html_entity_decode($json['responseData']['translatedText'], ENT_QUOTES, 'UTF-8');
            // Check that MyMemory did not return a quota warning
            if (strpos($translated, 'MYMEMORY WARNING') === false) {
                return [
                    'success'         => true,
                    'translated_text' => $translated,
                    'provider'        => 'MyMemory API'
                ];
            }
        }
    }

    // 2. Check Civic Dictionary Fallback (offline / university demo resilience)
    $dict = getCivicDictionary();
    $dictKey = ($src === 'hi') ? 'hi_to_en' : 'en_to_hi';
    
    // Direct or partial match
    foreach ($dict[$dictKey] as $phrase => $trans) {
        if (mb_stripos($text, $phrase) !== false) {
            $replaced = str_ireplace($phrase, $trans, $text);
            return [
                'success'         => true,
                'translated_text' => $replaced,
                'provider'        => 'Civic-Domain Lexicon (Offline Fallback)'
            ];
        }
    }

    // 3. If translation service fails completely, report truth per Prompt 5 instructions
    // "Do not pretend translation has succeeded if the provider fails."
    return [
        'success' => false,
        'error'   => 'The remote translation provider could not be reached (Network or rate-limit). You can edit and submit your original transcript directly.'
    ];
}
