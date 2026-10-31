<?php
$pageTitle = "Community Incident Map — NeighbourNet";
$activePage = 'map';
require_once __DIR__ . '/../includes/auth_guard.php';

// Fetch categories and societies for filter dropdowns
$categories = [];
$societies = [];
try {
    $pdo = getDBConnection();
    $categories = $pdo->query("SELECT id, name, icon_class, color_code FROM categories ORDER BY id ASC")->fetchAll();
    $societies = $pdo->query("SELECT id, name, ward_sector FROM societies ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Map view dropdown load error: " . $e->getMessage());
}

$extraCss = '<link rel="stylesheet" href="/neighbournet/assets/css/map.css">';
$extraJs = '<script src="/neighbournet/assets/js/map.js"></script>';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="map-page-wrapper">
    <!-- Left Sidebar: Filters & Active Incidents List -->
    <aside class="map-sidebar">
        <!-- Sidebar Header -->
        <div class="map-sidebar-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bold text-navy mb-0">Community Map</h5>
                <small class="text-secondary" id="visibleIncidentCount">Loading reports...</small>
            </div>
            <a href="/neighbournet/pages/report.php" class="btn btn-sm btn-civic-primary py-1 px-2" style="font-size: 0.8rem;">
                <i class="fa-solid fa-plus-circle me-1"></i> Report
            </a>
        </div>

        <!-- Filter Controls Container -->
        <div class="map-sidebar-filters">
            <!-- Search Keyword Input -->
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="mapSearchInput" placeholder="Search by street or title...">
            </div>

            <!-- Category & Status Filters -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select class="form-select form-select-sm" id="filterCategory">
                        <option value="0" selected>All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="all" selected>All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="under_verification">Under Verification</option>
                        <option value="verified">Verified Hazard</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
            </div>

            <!-- Date Range & Society Filters -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select class="form-select form-select-sm" id="filterDate">
                        <option value="all" selected>All Time</option>
                        <option value="today">Reported Today</option>
                        <option value="week">Past 7 Days</option>
                        <option value="month">Past 30 Days</option>
                    </select>
                </div>
                <div class="col-6">
                    <select class="form-select form-select-sm" id="filterSociety">
                        <option value="0" selected>All Sectors</option>
                        <?php foreach ($societies as $soc): ?>
                            <option value="<?= (int)$soc['id'] ?>"><?= e($soc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Filter Reset & Refresh Buttons -->
            <div class="d-flex justify-content-between align-items-center pt-1">
                <button type="button" class="btn btn-link btn-sm text-secondary p-0 text-decoration-none small" id="btnResetFilters">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset Filters
                </button>
                <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none small" id="btnRefreshMap">
                    <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh Data
                </button>
            </div>
        </div>

        <!-- Incidents Scrollable Feed -->
        <div class="map-sidebar-list" id="mapIncidentList">
            <!-- Dynamic incident items injected here by map.js -->
        </div>

        <!-- Empty State Notice (Shown when 0 incidents match) -->
        <div id="mapEmptyNotice" class="d-none p-3 text-center bg-light border-top">
            <span class="small text-secondary d-block mb-1">No incidents found with current filters.</span>
            <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('btnResetFilters').click()">
                Clear Filters
            </button>
        </div>
    </aside>

    <!-- Right Canvas: Fullscreen Interactive Map -->
    <main class="map-canvas-container">
        <!-- Floating Controls Over Map -->
        <div class="map-floating-controls">
            <button type="button" class="btn-map-control" id="btnLocateMe" title="Find my location">
                <i class="fa-solid fa-location-crosshairs text-primary"></i>
                <span>My Location</span>
            </button>
            <button type="button" class="btn-map-control" id="btnFitBounds" title="Zoom to show all markers">
                <i class="fa-solid fa-expand text-secondary"></i>
                <span class="d-none d-sm-inline">Fit All</span>
            </button>
            <button type="button" class="btn-map-control" data-bs-toggle="modal" data-bs-target="#mapLegendModal" title="Show category & status legend">
                <i class="fa-solid fa-circle-question text-info"></i>
                <span class="d-none d-sm-inline">Legend</span>
            </button>
        </div>

        <!-- Loading Overlay -->
        <div id="mapLoading" class="position-absolute top-50 start-50 translate-middle bg-white p-3 rounded-3 shadow-sm border d-flex align-items-center gap-2" style="z-index: 1000;">
            <span class="spinner-border spinner-border-sm text-primary"></span>
            <span class="small fw-semibold text-navy">Loading incident markers...</span>
        </div>

        <!-- The Leaflet Map Canvas -->
        <div id="communityMap"></div>
    </main>
</div>

<!-- Map Legend Modal -->
<div class="modal fade" id="mapLegendModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy">Map Legend & Indicators</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small text-secondary">
                <h6 class="fw-bold text-navy mb-2">Pin Border (Status Indicator)</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge border border-warning text-dark bg-warning-subtle">Yellow: Pending Review</span>
                    <span class="badge border border-info text-info bg-info-subtle">Light Blue: Under Verification</span>
                    <span class="badge border border-primary text-primary bg-primary-subtle">Blue: Verified Hazard</span>
                    <span class="badge border border-purple text-dark bg-purple-subtle" style="border-color: #a855f7 !important;">Purple: In Progress</span>
                    <span class="badge border border-success text-success bg-success-subtle">Green: Resolved</span>
                </div>

                <h6 class="fw-bold text-navy mb-2">Category Colors</h6>
                <div class="row g-2">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-6 d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background-color: <?= e($cat['color_code']) ?>;"></span>
                            <span class="text-truncate"><?= e($cat['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
