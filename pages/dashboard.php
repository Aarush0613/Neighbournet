<?php
$pageTitle = "Resident Dashboard — NeighbourNet";
$activePage = 'feed';
require_once __DIR__ . '/../includes/auth_guard.php';

// Enforce authentication
requireLogin();

$currentUser = getCurrentUser();
$filterMy = isset($_GET['my']) && $_GET['my'] == 1;
$filterStatus = trim($_GET['status'] ?? '');

// Initialize statistics
$totalIncidents    = 0;
$pendingCount      = 0;
$verifiedCount     = 0;
$inProgressCount   = 0;
$resolvedCount     = 0;
$myReportsCount    = 0;

$incidents = [];
$nearbyIncidents = [];

try {
    $pdo = getDBConnection();

    // Query 1: Real-time statistics directly from database
    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
    $totalIncidents = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'pending'");
    $pendingCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'verified'");
    $verifiedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'in_progress'");
    $inProgressCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'resolved'");
    $resolvedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $myReportsCount = (int)$stmt->fetchColumn();

    // Query 2: Feed Incidents List
    $sql = "
        SELECT i.*, c.name AS category_name, c.color_code, c.icon_class,
               u.full_name AS reporter_name, s.name AS society_name
        FROM incidents i
        JOIN categories c ON i.category_id = c.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN societies s ON i.society_id = s.id
        WHERE 1=1
    ";

    $params = [];
    if ($filterMy) {
        $sql .= " AND i.user_id = ?";
        $params[] = $currentUser['id'];
    }

    if (!empty($filterStatus)) {
        $sql .= " AND i.status = ?";
        $params[] = $filterStatus;
    }

    $sql .= " ORDER BY i.created_at DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();

    // Query 3: Nearby preview (Incidents in user's society or recent active)
    if (!empty($currentUser['society_id'])) {
        $nearbyStmt = $pdo->prepare("
            SELECT i.id, i.title, i.status, i.location_text, c.name AS category_name, c.color_code, c.icon_class
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            WHERE i.society_id = ? AND i.status != 'resolved'
            ORDER BY i.created_at DESC LIMIT 4
        ");
        $nearbyStmt->execute([$currentUser['society_id']]);
        $nearbyIncidents = $nearbyStmt->fetchAll();
    }

    // Fallback if no society-specific incidents
    if (empty($nearbyIncidents)) {
        $nearbyStmt = $pdo->query("
            SELECT i.id, i.title, i.status, i.location_text, c.name AS category_name, c.color_code, c.icon_class
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            WHERE i.status != 'resolved'
            ORDER BY i.created_at DESC LIMIT 4
        ");
        $nearbyIncidents = $nearbyStmt->fetchAll();
    }

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Top Welcome & Profile Identity Bar -->
    <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-badge" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    <i class="fa-solid fa-house-user"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-navy">Welcome, <?= e($currentUser['name']) ?></h3>
                        <span class="badge <?= ($currentUser['role'] === 'admin') ? 'bg-danger' : 'bg-primary' ?> text-uppercase small">
                            <?= e($currentUser['role']) ?>
                        </span>
                    </div>
                    <div class="small text-secondary d-flex flex-wrap align-items-center gap-3">
                        <span><i class="fa-solid fa-building me-1 text-primary"></i><?= e($currentUser['society_name']) ?></span>
                        <span>&bull;</span>
                        <span class="text-primary fw-semibold"><i class="fa-solid fa-award me-1 text-warning"></i>Reputation Score: <?= (int)$currentUser['reputation'] ?> pts</span>
                    </div>
                </div>
            </div>

            <!-- Quick Action Shortcuts -->
            <div class="d-flex flex-wrap gap-2">
                <a href="/neighbournet/pages/report.php" class="btn btn-civic-primary">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Quick Report Issue</span>
                </a>
                <a href="/neighbournet/pages/dashboard.php?my=1" class="btn btn-civic-secondary <?= $filterMy ? 'active border-primary' : '' ?>">
                    <i class="fa-solid fa-bullhorn text-warning"></i>
                    <span>My Reports (<?= $myReportsCount ?>)</span>
                </a>
                <a href="/neighbournet/pages/map-view.php" class="btn btn-civic-secondary">
                    <i class="fa-solid fa-map-location-dot text-primary"></i>
                    <span>Community Map</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Live Database Metric Counters (All queried from DB) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-auto flex-lg-fill">
            <div class="card border-0 shadow-sm p-3 rounded-3 bg-white text-center">
                <span class="text-secondary small fw-bold text-uppercase">Total Issues</span>
                <h2 class="fw-bold text-navy mb-0 mt-1"><?= $totalIncidents ?></h2>
            </div>
        </div>
        <div class="col-6 col-lg-auto flex-lg-fill">
            <a href="/neighbournet/pages/dashboard.php?status=pending" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white text-center">
                    <span class="text-secondary small fw-bold text-uppercase">Pending</span>
                    <h2 class="fw-bold text-warning mb-0 mt-1"><?= $pendingCount ?></h2>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-auto flex-lg-fill">
            <a href="/neighbournet/pages/dashboard.php?status=verified" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white text-center">
                    <span class="text-secondary small fw-bold text-uppercase">Verified</span>
                    <h2 class="fw-bold text-primary mb-0 mt-1"><?= $verifiedCount ?></h2>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-auto flex-lg-fill">
            <a href="/neighbournet/pages/dashboard.php?status=in_progress" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white text-center">
                    <span class="text-secondary small fw-bold text-uppercase">In Progress</span>
                    <h2 class="fw-bold text-info mb-0 mt-1"><?= $inProgressCount ?></h2>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-auto flex-lg-fill">
            <a href="/neighbournet/pages/dashboard.php?status=resolved" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white text-center">
                    <span class="text-secondary small fw-bold text-uppercase">Resolved</span>
                    <h2 class="fw-bold text-success mb-0 mt-1"><?= $resolvedCount ?></h2>
                </div>
            </a>
        </div>
    </div>

    <!-- Main Content Area: Feed and Nearby Preview Grid -->
    <div class="row g-4">
        <!-- Col Left: Feed of Recent Neighbourhood Activity -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold text-navy mb-1">
                            <?= $filterMy ? 'My Submitted Reports' : (!empty($filterStatus) ? ucfirst(str_replace('_', ' ', $filterStatus)) . ' Incidents' : 'Recent Neighbourhood Activity') ?>
                        </h4>
                        <p class="small text-secondary mb-0">Browse recent issues reported across your community</p>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <a href="/neighbournet/pages/dashboard.php" class="btn <?= (!$filterMy && empty($filterStatus)) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            All
                        </a>
                        <a href="/neighbournet/pages/dashboard.php?my=1" class="btn <?= $filterMy ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            My Reports
                        </a>
                    </div>
                </div>

                <?php if (empty($incidents)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-clipboard-check fs-1 mb-3 text-secondary"></i>
                        <h5>No incidents match this filter</h5>
                        <p class="small">There are currently no reports recorded in this scope.</p>
                        <a href="/neighbournet/pages/report.php" class="btn btn-sm btn-civic-primary mt-2">
                            <i class="fa-solid fa-plus-circle me-1"></i> Report an Incident Now
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($incidents as $inc): ?>
                            <div class="incident-card-clean p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="category-pill">
                                        <i class="fa-solid <?= e($inc['icon_class']) ?>" style="color: <?= e($inc['color_code']) ?>"></i>
                                        <?= e($inc['category_name']) ?>
                                    </span>
                                    <span class="status-pill status-<?= e($inc['status']) ?>">
                                        <?= str_replace('_', ' ', e($inc['status'])) ?>
                                    </span>
                                </div>

                                <h5 class="fw-bold mb-2">
                                    <a href="/neighbournet/pages/incident-details.php?id=<?= (int)$inc['id'] ?>" class="text-navy text-decoration-none">
                                        <?= e($inc['title']) ?>
                                    </a>
                                </h5>

                                <p class="small text-secondary mb-2">
                                    <?= e(substr($inc['description'], 0, 150)) ?><?= (strlen($inc['description']) > 150) ? '...' : '' ?>
                                </p>

                                <div class="small text-muted mb-3 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-danger"></i>
                                    <span><?= e($inc['location_text']) ?></span>
                                </div>

                                <div class="d-flex align-items-center justify-content-between pt-2 border-top small">
                                    <div class="text-secondary">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <?= date('M d, Y h:i A', strtotime($inc['incident_time'])) ?>
                                        &bull; Reported by <?= e($inc['reporter_name']) ?>
                                    </div>
                                    <a href="/neighbournet/pages/incident-details.php?id=<?= (int)$inc['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        View Details &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Col Right: Nearby Incidents Preview & Info Sidebar -->
        <div class="col-lg-4">
            <!-- Nearby Incidents Widget -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-navy mb-0">
                        <i class="fa-solid fa-street-view text-primary me-2"></i>Nearby Active Issues
                    </h5>
                    <span class="badge bg-light text-secondary border small">Local</span>
                </div>

                <?php if (empty($nearbyIncidents)): ?>
                    <p class="small text-muted mb-0">No active nearby incidents reported.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($nearbyIncidents as $nearby): ?>
                            <div class="p-2 bg-light rounded-2 border">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="category-pill" style="font-size: 0.7rem;">
                                        <i class="fa-solid <?= e($nearby['icon_class']) ?>"></i> <?= e($nearby['category_name']) ?>
                                    </span>
                                    <span class="status-pill status-<?= e($nearby['status']) ?>" style="font-size: 0.7rem;">
                                        <?= str_replace('_', ' ', e($nearby['status'])) ?>
                                    </span>
                                </div>
                                <a href="/neighbournet/pages/incident-details.php?id=<?= (int)$nearby['id'] ?>" class="fw-bold text-navy small text-decoration-none d-block mb-1">
                                    <?= e($nearby['title']) ?>
                                </a>
                                <small class="text-muted d-block text-truncate">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($nearby['location_text']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <a href="/neighbournet/pages/map-view.php" class="btn btn-sm btn-outline-primary w-100 mt-3">
                    <i class="fa-solid fa-map me-1"></i> Open Live Map
                </a>
            </div>

            <!-- Incident Lifecycle Guide Widget -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <h6 class="fw-bold text-navy mb-3">Incident Status Lifecycle</h6>
                <div class="d-flex flex-column gap-2 small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pill status-pending">Pending</span>
                        <span class="text-muted">Awaiting initial review</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pill status-verified">Verified</span>
                        <span class="text-muted">Corroborated by community</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pill status-in_progress">In Progress</span>
                        <span class="text-muted">Civic fix underway</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pill status-resolved">Resolved</span>
                        <span class="text-muted">Issue inspected and closed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
