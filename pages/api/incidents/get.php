<?php
/**
 * NeighbourNet - Single Incident Details Endpoint (JSON)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid incident ID parameter.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT 
            i.id, i.title, i.description, i.image_path, i.latitude, i.longitude,
            i.location_text, i.incident_time, i.status, i.created_at, i.updated_at,
            c.id AS category_id, c.name AS category_name, c.color_code, c.icon_class,
            u.id AS reporter_id, u.full_name AS reporter_name, u.role AS reporter_role,
            s.id AS society_id, s.name AS society_name, s.ward_sector
        FROM incidents i
        JOIN categories c ON i.category_id = c.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN societies s ON i.society_id = s.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $incident = $stmt->fetch();

    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident record not found.']);
        exit;
    }

    // Fetch official audit updates
    $updatesStmt = $pdo->prepare("
        SELECT u.old_status, u.new_status, u.note, u.created_at, usr.full_name AS updated_by
        FROM incident_updates u
        JOIN users usr ON u.user_id = usr.id
        WHERE u.incident_id = ?
        ORDER BY u.created_at ASC
    ");
    $updatesStmt->execute([$id]);
    $updates = $updatesStmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'incident' => $incident,
        'updates'  => $updates
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('Incident details API: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load incident details. Please retry.']);
}
