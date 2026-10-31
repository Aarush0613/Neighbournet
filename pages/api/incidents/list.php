<?php
/**
 * NeighbourNet - Incidents Query & Filter Endpoint (JSON)
 * Provides sanitized public incident data for interactive map and feeds
 * Strict Privacy: Reporter personal data (email, phone) is NOT exposed.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

try {
    $pdo = getDBConnection();

    // Query parameters
    $status      = trim($_GET['status'] ?? '');
    $categoryId  = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $societyId   = !empty($_GET['society_id']) ? (int)$_GET['society_id'] : 0;
    $dateRange   = trim($_GET['date_range'] ?? ''); // 'today', 'week', 'month'
    $search      = trim($_GET['search'] ?? '');
    $hasCoords   = isset($_GET['has_coords']) && $_GET['has_coords'] == 1;
    $filterMy    = !empty($_GET['my']) && $_GET['my'] == 1;
    $limit       = min(150, max(1, (int)($_GET['limit'] ?? 100)));

    $query = "
        SELECT 
            i.id, i.title, i.description, i.image_path,
            i.latitude, i.longitude, i.location_text,
            i.incident_time, i.status, i.created_at,
            i.voice_input_used,
            c.id AS category_id, c.name AS category_name, c.color_code, c.icon_class,
            s.id AS society_id, s.name AS society_name, s.ward_sector,
            ic.cluster_id, ic.cluster_report_count, ic.cluster_status
        FROM incidents i
        JOIN categories c ON i.category_id = c.id
        LEFT JOIN societies s ON i.society_id = s.id
        LEFT JOIN (
            SELECT cr.incident_id, c.id AS cluster_id, c.status AS cluster_status, COUNT(*) AS cluster_report_count
            FROM cluster_reports cr JOIN incident_clusters c ON c.id = cr.cluster_id
            WHERE c.status IN ('suggested','approved') GROUP BY cr.incident_id, c.id, c.status
        ) ic ON ic.incident_id = i.id
        WHERE 1=1
    ";

    $params = [];

    // Filter by Status
    if (!empty($status) && $status !== 'all') {
        $query .= " AND i.status = ?";
        $params[] = $status;
    }

    // Filter by Category
    if ($categoryId > 0) {
        $query .= " AND i.category_id = ?";
        $params[] = $categoryId;
    }

    // Filter by Society / Neighbourhood
    if ($societyId > 0) {
        $query .= " AND i.society_id = ?";
        $params[] = $societyId;
    }

    // Filter by Date Period
    if ($dateRange === 'today') {
        $query .= " AND DATE(i.incident_time) = CURDATE()";
    } elseif ($dateRange === 'week') {
        $query .= " AND i.incident_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateRange === 'month') {
        $query .= " AND i.incident_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    // Search Keyword Filter (Title or Location text)
    if (!empty($search)) {
        $query .= " AND (i.title LIKE ? OR i.location_text LIKE ? OR i.description LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    // Filter by Coordinates Availability
    if ($hasCoords) {
        $query .= " AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL AND i.latitude != 0 AND i.longitude != 0";
    }

    // Filter by Logged-in User's Reports
    if ($filterMy) {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required for My Reports filter.']);
            exit;
        }
        $query .= " AND i.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    $query .= " ORDER BY i.created_at DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();

    // Format coordinates to float for clean GeoJSON / Leaflet consumption
    $formatted = array_map(function($inc) {
        return [
            'id'                => (int)$inc['id'],
            'title'             => $inc['title'],
            'description'       => mb_substr($inc['description'], 0, 140) . (mb_strlen($inc['description']) > 140 ? '...' : ''),
            'image_path'        => $inc['image_path'],
            'latitude'          => $inc['latitude'] !== null ? (float)$inc['latitude'] : null,
            'longitude'         => $inc['longitude'] !== null ? (float)$inc['longitude'] : null,
            'location_text'     => $inc['location_text'],
            'incident_time'     => $inc['incident_time'],
            'status'            => $inc['status'],
            'created_at'        => $inc['created_at'],
            'voice_input_used'  => (bool)$inc['voice_input_used'],
            'category_id'       => (int)$inc['category_id'],
            'category_name'     => $inc['category_name'],
            'color_code'        => $inc['color_code'],
            'icon_class'        => $inc['icon_class'],
            'society_name'      => $inc['society_name'] ?? 'Local Ward',
            'ward_sector'       => $inc['ward_sector'] ?? '',
            'cluster_id'        => isset($inc['cluster_id']) ? (int)$inc['cluster_id'] : null,
            'cluster_report_count' => isset($inc['cluster_report_count']) ? (int)$inc['cluster_report_count'] : 0,
            'cluster_status'    => $inc['cluster_status'] ?? null
        ];
    }, $incidents);

    echo json_encode([
        'success'   => true,
        'count'     => count($formatted),
        'incidents' => $formatted
    ]);

} catch (Exception $e) {
    error_log('Incident list API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to query incidents. Please retry.'
    ]);
}
