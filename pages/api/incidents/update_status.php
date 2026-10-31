<?php
/**
 * NeighbourNet - Admin Status Update Endpoint
 * Restrictive: Only administrators can officially change incident lifecycle status
 */

require_once __DIR__ . '/../../includes/auth_guard.php';

// Server-side admin check
requireRole(['admin']);

$currentUser = getCurrentUser();
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$csrfToken  = $_POST['csrf_token'] ?? '';
$incidentId = !empty($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$newStatus  = trim($_POST['status'] ?? '');
$note       = trim($_POST['note'] ?? 'Official status update by administrator.');

if (!validateCsrfToken($csrfToken)) {
    $errorMsg = 'Security validation failed (CSRF).';
    if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}

$allowedStatuses = ['pending', 'under_verification', 'verified', 'in_progress', 'resolved'];
if (strlen($note) > 3000) {
    http_response_code(400);
    $errorMsg = 'Status note must be 3,000 characters or fewer.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $errorMsg]); exit; }
    $_SESSION['flash_error'] = $errorMsg; header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}"); exit;
}
if (!in_array($newStatus, $allowedStatuses, true)) {
    $errorMsg = 'Invalid status transition selected.';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = ? FOR UPDATE");
    $stmt->execute([$incidentId]);
    $currentStatus = $stmt->fetchColumn();
    if ($currentStatus === false) throw new Exception("Incident not found.");

    if ($currentStatus !== $newStatus) {
        $updateStmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $incidentId]);
        $auditStmt = $pdo->prepare("INSERT INTO incident_updates (incident_id, user_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)");
        $historyNote = $note !== '' ? $note : 'Official status update by administrator.';
        $auditStmt->execute([$incidentId, $currentUser['id'], $currentStatus, $newStatus, $historyNote]);
        $historyStmt = $pdo->prepare('INSERT INTO incident_history (incident_id, actor_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)');
        $historyStmt->execute([$incidentId, $currentUser['id'], $currentStatus, $newStatus, $historyNote]);
        // Prompt 11 global administrative audit trail.
        $log = $pdo->prepare("INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, details) VALUES (?, 'status_changed', 'incident', ?, ?)");
        $log->execute([$currentUser['id'], $incidentId, $currentStatus . ' -> ' . $newStatus . '. ' . $note]);
    }
    $pdo->commit();
    $successMsg = "Incident #{$incidentId} status " . ($currentStatus === $newStatus ? 'was already ' : 'updated to ') . "'" . ucwords(str_replace('_', ' ', $newStatus)) . "'.";

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $successMsg]);
        exit;
    }
    $_SESSION['flash_success'] = $successMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin incident status update: ' . $e->getMessage());
    $errorMsg = "Failed to update status. Please retry.";
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header("Location: /neighbournet/pages/incident-details.php?id={$incidentId}");
    exit;
}
