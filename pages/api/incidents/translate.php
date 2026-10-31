<?php
/**
 * NeighbourNet - Translation Endpoint
 * Accepts recognized speech transcript and returns translated text
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../config/translation.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to use voice translation.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?? $_POST;

$csrfToken  = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$text       = trim($data['text'] ?? '');
$sourceLang = trim($data['source_lang'] ?? 'hi');
$targetLang = trim($data['target_lang'] ?? 'en');

if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid (CSRF).']);
    exit;
}

if (empty($text) || mb_strlen($text) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Speech transcript must contain 1–5,000 characters.']);
    exit;
}

if (!in_array($sourceLang, ['hi','en','hi-IN','en-IN'], true) || !in_array($targetLang, ['hi','en','hi-IN','en-IN'], true) || $sourceLang === $targetLang) {
    http_response_code(422); echo json_encode(['success'=>false,'message'=>'Choose Hindi or English and select a different target language.']); exit;
}
$res = translateText($text, $sourceLang, $targetLang);

if ($res['success']) {
    echo json_encode([
        'success'         => true,
        'original_text'   => $text,
        'source_lang'     => $sourceLang,
        'target_lang'     => $targetLang,
        'translated_text' => $res['translated_text'],
        'provider'        => $res['provider']
    ]);
} else {
    // Report actual error; do NOT pretend translation succeeded
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => $res['error'] ?? 'Translation provider unavailable.'
    ]);
}
