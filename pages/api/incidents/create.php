<?php
/**
 * NeighbourNet - Submit Incident Report Endpoint
 * Validates text inputs, processes photo upload, and inserts incident with status 'pending'
 */

require_once __DIR__ . '/../../includes/auth_guard.php';

// Must be authenticated
if (!isLoggedIn()) {
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please log in to report an issue.']);
        exit;
    }
    $_SESSION['flash_error'] = 'Please log in to report an issue.';
    header('Location: /neighbournet/pages/login.php');
    exit;
}

$currentUser = getCurrentUser();
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); if ($isAjax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); } else { header('Location: /neighbournet/pages/report.php'); } exit; }

// 1. CSRF Validation
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $errorMsg = 'Security validation failed (CSRF). Please refresh and resubmit.';
    if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/report.php');
    exit;
}

// 2. Collect & Trim Inputs
$title          = trim($_POST['title'] ?? '');
$categoryId     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$description    = trim($_POST['description'] ?? '');
$locationText   = trim($_POST['location_text'] ?? '');
$societyId      = !empty($_POST['society_id']) ? (int)$_POST['society_id'] : ($currentUser['society_id'] ?? null);
$incidentTime   = trim($_POST['incident_time'] ?? '');
$additionalInfo = trim($_POST['additional_info'] ?? '');
$latitude       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

// Voice Reporting Fields
$voiceInputUsed          = !empty($_POST['voice_input_used']) ? 1 : 0;
$originalTranscript      = !empty($_POST['original_transcript']) ? trim($_POST['original_transcript']) : null;
$originalLanguage        = !empty($_POST['original_language']) ? trim($_POST['original_language']) : null;
$translatedTranscript    = !empty($_POST['translated_transcript']) ? trim($_POST['translated_transcript']) : null;
$translationLanguage     = !empty($_POST['translation_language']) ? trim($_POST['translation_language']) : null;
$selectedDescriptionType = !empty($_POST['selected_description_type']) ? trim($_POST['selected_description_type']) : 'manual';

$invalidIncidentTime = false;
// Default incident time to now only when the field is omitted.
if (empty($incidentTime)) {
    $incidentTime = date('Y-m-d H:i:s');
} else {
    $timestamp = strtotime($incidentTime);
    $incidentTime = $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
    if ($incidentTime === '') $invalidIncidentTime = true;
}

if (!empty($additionalInfo)) {
    $description .= "\n\n[Additional Information: " . $additionalInfo . "]";
}

// 3. Server-Side Validation
$errors = [];
if ($invalidIncidentTime) $errors[] = 'Please provide a valid incident date and time.';
if (strlen($additionalInfo) > 2000) $errors[] = 'Additional information must be 2,000 characters or fewer.';

if (strlen($title) < 5 || strlen($title) > 160) {
    $errors[] = 'Incident title must be between 5 and 160 characters.';
}

if ($categoryId <= 0) {
    $errors[] = 'Please select a valid incident category.';
}

if (strlen($description) < 10 || strlen($description) > 12000) {
    $errors[] = 'Description must be between 10 and 12,000 characters.';
}

if (empty($locationText)) { $errors[] = 'Please enter a location or street landmark.'; }
if (strlen($locationText) > 255) { $errors[] = 'Location must be 255 characters or fewer.'; }
if (($latitude !== null && ($latitude < -90 || $latitude > 90)) || ($longitude !== null && ($longitude < -180 || $longitude > 180)) || (($latitude === null) !== ($longitude === null))) { $errors[] = 'Provide both valid latitude and longitude values, or leave both blank.'; }
if (!in_array($selectedDescriptionType, ['manual','original','translated','combined'], true)) { $errors[] = 'Invalid description source selection.'; }
if (!in_array($originalLanguage, [null,'hi','en'], true) || !in_array($translationLanguage, [null,'hi','en'], true)) { $errors[] = 'Unsupported voice language.'; }
if (strlen((string)$originalTranscript) > 6000 || strlen((string)$translatedTranscript) > 6000) { $errors[] = 'Voice transcript is too long (maximum 6,000 characters).'; }

// 4. File Upload Validation & Processing
$imagePath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error during photo upload. Please try a different image.';
    } else {
        // Size validation (Max 5MB)
        if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Uploaded image is invalid or exceeds the 5MB maximum size limit.';
        }

        // MIME Type validation using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mime, $allowedMimes) || @getimagesize($file['tmp_name']) === false) {
            $errors[] = 'Invalid file format. Only JPG, PNG, and WEBP images are supported.';
        } else {
            $ext = $allowedMimes[$mime];
            $fileName = 'inc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/incidents/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (empty($errors)) {
                $destination = $uploadDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $destination)) { $imagePath = '/neighbournet/uploads/incidents/' . $fileName; }
                else { $errors[] = 'Failed to save uploaded photo to storage directory.'; }
            }
        }
    }
}

// If validation errors exist, abort
if (!empty($errors)) {
    $errorMsg = implode(' ', $errors);
    if ($isAjax) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $errorMsg, 'errors' => $errors]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    $_SESSION['old_incident'] = [
        'title'         => $title,
        'category_id'   => $categoryId,
        'description'   => $description,
        'location_text' => $locationText,
        'society_id'    => $societyId
    ];
    header('Location: /neighbournet/pages/report.php');
    exit;
}

// 5. Save to MySQL with Status 'pending'
try {
    $pdo = getDBConnection();

    if ($societyId !== null) { $societyCheck = $pdo->prepare('SELECT id FROM societies WHERE id=?'); $societyCheck->execute([$societyId]); if (!$societyCheck->fetchColumn()) throw new InvalidArgumentException('Selected neighbourhood does not exist.'); }

    // Verify category exists
    $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $catCheck->execute([$categoryId]);
    if (!$catCheck->fetch()) {
        throw new Exception("Selected category does not exist.");
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO incidents (
            user_id, category_id, society_id, title, description,
            voice_input_used, original_transcript, original_language,
            translated_transcript, translation_language, selected_description_type,
            image_path, latitude, longitude, location_text,
            incident_time, status
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, 'pending'
        )
    ");

    $insertStmt->execute([
        $currentUser['id'],
        $categoryId,
        $societyId,
        $title,
        $description,
        $voiceInputUsed,
        $originalTranscript,
        $originalLanguage,
        $translatedTranscript,
        $translationLanguage,
        $selectedDescriptionType,
        $imagePath,
        $latitude,
        $longitude,
        $locationText,
        $incidentTime
    ]);

    $newIncidentId = (int)$pdo->lastInsertId();

    // Multi-factor incident similarity & clustering analysis (Prompt 8)
    require_once __DIR__ . '/../../includes/clustering_engine.php';
    ClusteringEngine::processIncidentClustering($pdo, $newIncidentId);

    // Award 5 reputation points to resident for active reporting
    $pdo->prepare("UPDATE users SET reputation_score = reputation_score + 5 WHERE id = ?")
        ->execute([$currentUser['id']]);

    $successMsg = "Incident #{$newIncidentId} submitted successfully! It is now pending verification.";

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'     => true,
            'message'     => $successMsg,
            'incident_id' => $newIncidentId,
            'redirect'    => "/neighbournet/pages/incident-details.php?id={$newIncidentId}&submitted=1"
        ]);
        exit;
    }

    $_SESSION['flash_success'] = $successMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$newIncidentId}&submitted=1");
    exit;

} catch (Exception $e) {
    error_log("Incident insert error: " . $e->getMessage());
    if (!empty($imagePath)) { $stored = __DIR__ . '/../../uploads/incidents/' . basename($imagePath); if (is_file($stored)) @unlink($stored); }
    $errorMsg = ($e instanceof InvalidArgumentException) ? $e->getMessage() : 'Unable to save the incident. Please retry.';
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/report.php');
    exit;
}
