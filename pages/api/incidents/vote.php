<?php
/**
 * NeighbourNet - Community Voting & Verification Endpoint
 * Prompt 7: Transparent feedback system (TRUE, FALSE, MAYBE)
 * Enforces: Authenticated only, no self-voting, unique vote per resident, vote updates
 */

require_once __DIR__ . '/../../includes/auth_guard.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

// 1. Check Authentication
if (!isLoggedIn()) {
    $msg = 'Please log in to submit your verification vote.';
    if ($isAjax) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: /neighbournet/pages/login.php');
    exit;
}

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);

$csrfToken       = $_POST['csrf_token'] ?? $jsonInput['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$incidentId      = !empty($_POST['incident_id'] ?? $jsonInput['incident_id']) ? (int)($_POST['incident_id'] ?? $jsonInput['incident_id']) : 0;
$voteType        = trim($_POST['vote_type'] ?? $jsonInput['vote_type'] ?? '');
$optionalComment = trim($_POST['optional_comment'] ?? $jsonInput['optional_comment'] ?? '');

// 2. Validate CSRF Token
if (!validateCsrfToken($csrfToken)) {
    $msg = 'Security validation failed (CSRF). Please refresh and try again.';
    if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}

// 3. Validate Inputs
if ($incidentId <= 0) {
    $msg = 'Invalid incident specified.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$allowedVotes = ['TRUE', 'FALSE', 'MAYBE'];
if (!in_array($voteType, $allowedVotes, true)) {
    $msg = 'Invalid vote option. Please select TRUE, FALSE, or MAYBE.';
    if ($isAjax) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}

// Truncate comment if overly long
if (mb_strlen($optionalComment) > 255) {
    $optionalComment = mb_substr($optionalComment, 0, 255);
}
if (empty($optionalComment)) {
    $optionalComment = null;
}

try {
    $pdo = getDBConnection();

    // 4. Verify Incident Exists & Check Reporter ID (Prevent self-voting)
    $stmt = $pdo->prepare("SELECT id, user_id, title FROM incidents WHERE id = ? LIMIT 1");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch();

    if (!$incident) {
        $msg = 'The incident report could not be found.';
        if ($isAjax) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        $_SESSION['flash_error'] = $msg;
        header('Location: /neighbournet/pages/dashboard.php');
        exit;
    }

    // Rule 3: Prevent the original reporter from voting on their own incident
    if ((int)$incident['user_id'] === (int)$currentUser['id']) {
        $msg = 'As the author of this report, you cannot vote on your own report to preserve consensus integrity.';
        if ($isAjax) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        $_SESSION['flash_error'] = $msg;
        header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
        exit;
    }

    // 5. Check whether this is a new vote or an update to an existing vote
    $existingStmt = $pdo->prepare("SELECT id, vote_type FROM community_votes WHERE incident_id = ? AND user_id = ?");
    $existingStmt->execute([$incidentId, $currentUser['id']]);
    $existingVote = $existingStmt->fetch();

    $isVoteChange = (bool)$existingVote;

    // 6. UPSERT Vote into community_votes (Enforces UNIQUE constraint & updates existing)
    $upsertStmt = $pdo->prepare("
        INSERT INTO community_votes (incident_id, user_id, vote_type, optional_comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            vote_type = VALUES(vote_type),
            optional_comment = VALUES(optional_comment),
            updated_at = CURRENT_TIMESTAMP
    ");
    $upsertStmt->execute([$incidentId, $currentUser['id'], $voteType, $optionalComment]);

    // Award +2 reputation points if first-time voter
    if (!$isVoteChange) {
        $pdo->prepare("UPDATE users SET reputation_score = reputation_score + 2 WHERE id = ?")
            ->execute([$currentUser['id']]);
    }

    // 7. Calculate Updated Vote Statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN vote_type = 'TRUE' THEN 1 ELSE 0 END) AS true_count,
            SUM(CASE WHEN vote_type = 'FALSE' THEN 1 ELSE 0 END) AS false_count,
            SUM(CASE WHEN vote_type = 'MAYBE' THEN 1 ELSE 0 END) AS maybe_count,
            COUNT(*) AS total_votes
        FROM community_votes
        WHERE incident_id = ?
    ");
    $statsStmt->execute([$incidentId]);
    $stats = $statsStmt->fetch();

    $trueCount  = (int)($stats['true_count'] ?? 0);
    $falseCount = (int)($stats['false_count'] ?? 0);
    $maybeCount = (int)($stats['maybe_count'] ?? 0);
    $totalVotes = (int)($stats['total_votes'] ?? 0);

    $actionText = $isVoteChange ? "Vote updated to {$voteType}." : "Feedback recorded as {$voteType}.";
    $successMsg = "Thank you! {$actionText}";

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'     => true,
            'message'     => $successMsg,
            'is_change'   => $isVoteChange,
            'user_vote'   => $voteType,
            'statistics'  => [
                'true_count'  => $trueCount,
                'false_count' => $falseCount,
                'maybe_count' => $maybeCount,
                'total_votes' => $totalVotes
            ]
        ]);
        exit;
    }

    $_SESSION['flash_success'] = $successMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;

} catch (Exception $e) {
    error_log("Voting Error: " . $e->getMessage());
    $msg = 'A database error occurred while registering your vote.';
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}
