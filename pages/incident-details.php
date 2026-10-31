<?php
$pageTitle = "Incident Details — NeighbourNet";
$activePage = 'feed';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/clustering_engine.php';

// Authentication required to view details
requireLogin();

$currentUser = getCurrentUser();
$incidentId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incidentId <= 0) {
    $_SESSION['flash_error'] = 'Invalid incident identifier.';
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$incident = null;
$updates = [];
$userVote = null;
$voterComments = [];
$trueVotes = 0;
$falseVotes = 0;
$maybeVotes = 0;
$totalVotes = 0;
$communityTasks = [];
$communityTaskCount = 0;

try {
    $pdo = getDBConnection();

    // 1. Query Incident with Category and Reporter
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            c.name AS category_name, c.color_code, c.icon_class, c.description AS category_desc,
            u.full_name AS reporter_name, u.role AS reporter_role, u.reputation_score AS reporter_reputation,
            s.name AS society_name, s.ward_sector
        FROM incidents i
        JOIN categories c ON i.category_id = c.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN societies s ON i.society_id = s.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch();

    if (!$incident) {
        $_SESSION['flash_error'] = 'Incident record not found.';
        header('Location: /neighbournet/pages/dashboard.php');
        exit;
    }

    // Prompt 10: incident-linked community action tasks (optional until migration is installed).
    try {
        $taskStmt = $pdo->prepare("SELECT t.id,t.title,t.description,t.status,t.priority,t.max_volunteers,t.created_at,
            COALESCE(creator.full_name,'Community moderator') AS creator_name,
            COUNT(DISTINCT v.id) AS volunteer_count
            FROM community_tasks t LEFT JOIN users creator ON creator.id=t.created_by
            LEFT JOIN task_volunteers v ON v.task_id=t.id WHERE t.incident_id=?
            GROUP BY t.id ORDER BY FIELD(t.status,'open','in_progress','completed','cancelled'),t.created_at DESC LIMIT 4");
        $taskStmt->execute([$incidentId]);
        $communityTasks = $taskStmt->fetchAll();
        $communityTaskCount = count($communityTasks);
    } catch (Throwable $ignored) { $communityTasks = []; $communityTaskCount = 0; }

    // 2. Query Official Audit Timeline updates
    $updatesStmt = $pdo->prepare("
        SELECT u.old_status, u.new_status, u.note, u.created_at, usr.full_name AS updated_by
        FROM incident_updates u
        JOIN users usr ON u.user_id = usr.id
        WHERE u.incident_id = ?
        ORDER BY u.created_at ASC
    ");
    $updatesStmt->execute([$incidentId]);
    $updates = $updatesStmt->fetchAll();

    // 3. Query Community Voting Statistics (Prompt 7)
    $voteStatsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN vote_type = 'TRUE' THEN 1 ELSE 0 END) AS true_count,
            SUM(CASE WHEN vote_type = 'FALSE' THEN 1 ELSE 0 END) AS false_count,
            SUM(CASE WHEN vote_type = 'MAYBE' THEN 1 ELSE 0 END) AS maybe_count,
            COUNT(*) AS total_votes
        FROM community_votes
        WHERE incident_id = ?
    ");
    $voteStatsStmt->execute([$incidentId]);
    $voteStats = $voteStatsStmt->fetch();

    $trueVotes  = (int)($voteStats['true_count'] ?? 0);
    $falseVotes = (int)($voteStats['false_count'] ?? 0);
    $maybeVotes = (int)($voteStats['maybe_count'] ?? 0);
    $totalVotes = (int)($voteStats['total_votes'] ?? 0);

    // 4. Check if Current User has already voted
    $userVoteStmt = $pdo->prepare("
        SELECT vote_type, optional_comment, updated_at 
        FROM community_votes 
        WHERE incident_id = ? AND user_id = ?
    ");
    $userVoteStmt->execute([$incidentId, $currentUser['id']]);
    $userVote = $userVoteStmt->fetch();

    // 5. Query Recent Feedback Comments with Votes
    $voterCommentsStmt = $pdo->prepare("
        SELECT v.vote_type, v.optional_comment, v.created_at, u.full_name AS voter_name
        FROM community_votes v
        JOIN users u ON v.user_id = u.id
        WHERE v.incident_id = ? AND v.optional_comment IS NOT NULL AND v.optional_comment != '' AND COALESCE(v.is_hidden, 0) = 0
        ORDER BY v.updated_at DESC LIMIT 10
    ");
    $voterCommentsStmt->execute([$incidentId]);
    $voterComments = $voterCommentsStmt->fetchAll();

    // 6. Query Incident Cluster (Prompt 8)
    $cluster = ClusteringEngine::getClusterForIncident($pdo, $incidentId);

} catch (Exception $e) {
    error_log("Error loading incident: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error loading incident.';
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$isAuthor = ((int)$incident['user_id'] === (int)$currentUser['id']);
$pageTitle = e($incident['title']) . " — NeighbourNet";
$csrfToken = getCsrfToken();

// Compute visual representation percentages
$pctTrue  = $totalVotes > 0 ? round(($trueVotes / $totalVotes) * 100) : 0;
$pctFalse = $totalVotes > 0 ? round(($falseVotes / $totalVotes) * 100) : 0;
$pctMaybe = $totalVotes > 0 ? (100 - $pctTrue - $pctFalse) : 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/neighbournet/pages/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Incident #<?= (int)$incident['id'] ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Column: Incident Details & Community Voting -->
        <div class="col-lg-8">
            <!-- 1. Incident Card -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
                <!-- Status & Category Bar -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom pb-3 mb-3">
                    <span class="category-pill fs-6 px-3 py-1">
                        <i class="fa-solid <?= e($incident['icon_class']) ?>" style="color: <?= e($incident['color_code']) ?>"></i>
                        <?= e($incident['category_name']) ?>
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary small">Status:</span>
                        <span class="status-pill status-<?= e($incident['status']) ?> fs-6 px-3 py-1">
                            <?= str_replace('_', ' ', e($incident['status'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Incident Title -->
                <h2 class="fw-bold text-navy mb-3"><?= e($incident['title']) ?></h2>

                <!-- Incident Meta Information -->
                <div class="d-flex flex-wrap gap-3 small text-secondary mb-4 p-3 bg-light rounded-2 border">
                    <div>
                        <i class="fa-regular fa-clock text-primary me-1"></i>
                        <strong>Noticed:</strong> <?= date('M d, Y h:i A', strtotime($incident['incident_time'])) ?>
                    </div>
                    <div>&bull;</div>
                    <div>
                        <i class="fa-solid fa-calendar-check text-muted me-1"></i>
                        <strong>Reported:</strong> <?= date('M d, Y', strtotime($incident['created_at'])) ?>
                    </div>
                    <div>&bull;</div>
                    <div>
                        <i class="fa-solid fa-user text-muted me-1"></i>
                        <strong>Reporter:</strong> <?= e($incident['reporter_name']) ?>
                    </div>
                </div>

                <!-- Attached Photo Evidence (if present) -->
                <?php if (!empty($incident['image_path'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-navy mb-2"><i class="fa-solid fa-camera text-primary me-1"></i>Attached Photo Evidence</h6>
                        <div class="rounded-3 overflow-hidden border">
                            <img src="<?= e($incident['image_path']) ?>" alt="Incident photo" class="img-fluid w-100" style="max-height: 440px; object-fit: cover;">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Detailed Description -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold text-navy mb-0">Incident Description</h5>
                        <?php if (!empty($incident['voice_input_used'])): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                <i class="fa-solid fa-microphone me-1"></i> Voice Reported (<?= strtoupper(e($incident['original_language'] ?? 'Voice')) ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-light rounded-2 border text-secondary mb-3" style="white-space: pre-line; line-height: 1.6;">
                        <?= e($incident['description']) ?>
                    </div>

                    <!-- Voice Transcripts Record (if used) -->
                    <?php if (!empty($incident['voice_input_used']) && (!empty($incident['original_transcript']) || !empty($incident['translated_transcript']))): ?>
                        <div class="p-3 bg-white rounded-2 border border-primary-subtle">
                            <h6 class="fw-bold text-navy mb-2 small text-uppercase">
                                <i class="fa-solid fa-language text-primary me-1"></i> Voice Transcripts Record
                            </h6>
                            <div class="row g-3 small">
                                <?php if (!empty($incident['original_transcript'])): ?>
                                    <div class="col-md-6">
                                        <span class="text-muted fw-bold d-block mb-1">
                                            Original Speech (<?= strtoupper(e($incident['original_language'] ?? '')) ?>):
                                        </span>
                                        <div class="p-2 bg-light rounded border text-navy fst-italic">
                                            "<?= e($incident['original_transcript']) ?>"
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($incident['translated_transcript'])): ?>
                                    <div class="col-md-6">
                                        <span class="text-muted fw-bold d-block mb-1">
                                            Translated (<?= strtoupper(e($incident['translation_language'] ?? '')) ?>):
                                        </span>
                                        <div class="p-2 bg-light rounded border text-navy">
                                            <?= e($incident['translated_transcript']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Location & GPS Details -->
                <div class="mb-2">
                    <h5 class="fw-bold text-navy mb-2"><i class="fa-solid fa-location-dot text-danger me-1"></i>Location & Landmark</h5>
                    <p class="mb-2 text-secondary">
                        <strong><?= e($incident['location_text']) ?></strong>
                        <?php if (!empty($incident['society_name'])): ?>
                            <span class="text-muted">(<?= e($incident['society_name']) ?>)</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                        <div class="small text-muted mb-2">
                            GPS Coordinates: <code><?= e($incident['latitude']) ?>, <?= e($incident['longitude']) ?></code>
                        </div>
                        <div id="incidentMiniMap" style="height: 240px; border-radius: 8px; border: 1px solid #cbd5e1;"></div>
                    <?php else: ?>
                        <div class="alert alert-light border small text-muted mb-0">
                            <i class="fa-solid fa-circle-info me-1"></i> This incident was logged with a manual street landmark (no GPS coordinates provided).
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PROMPT 8: INCIDENT CLUSTER & RELATED REPORTS CARD -->
            <?php if (!empty($cluster)): ?>
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4 border-start border-4 <?= ($cluster['status'] === 'approved') ? 'border-success' : 'border-warning' ?>">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="brand-badge <?= ($cluster['status'] === 'approved') ? 'bg-success' : 'bg-warning text-dark' ?>" style="width: 36px; height: 36px; font-size: 1rem;">
                                <i class="fa-solid fa-object-group"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-navy mb-0">Connected Incident Cluster</h5>
                                <small class="text-secondary"><?= (int)$cluster['report_count'] ?> potentially related resident reports</small>
                            </div>
                        </div>
                        <span class="badge <?= ($cluster['status'] === 'approved') ? 'bg-success-subtle text-success border border-success' : 'bg-warning-subtle text-dark border border-warning' ?> py-2 px-3 small">
                            <?= ($cluster['status'] === 'approved') ? '<i class="fa-solid fa-circle-check me-1"></i> Confirmed Incident Group' : '<i class="fa-solid fa-lightbulb me-1"></i> Suggested Cluster (Under Review)' ?>
                        </span>
                    </div>

                    <!-- Non-Destructive Transparency Disclaimer -->
                    <div class="alert alert-light border small text-secondary mb-3">
                        <i class="fa-solid fa-circle-nodes text-primary me-2"></i>
                        <strong>Clustering Transparency:</strong> These reports are algorithmic suggestions based on category compatibility, geographic proximity, timing, neighbourhood and description similarity. A suggestion is not proof that reports describe the same event. Original reports and reporters remain separate. Clustered reports are related submissions, not independent confirmations.
                    </div>

                    <!-- Cluster Metadata Overview -->
                    <div class="row g-2 small text-secondary mb-3">
                        <div class="col-sm-6">
                            <strong>Approx. Affected Area:</strong> <?= e($cluster['approx_location_text'] ?? 'Central Vicinity') ?>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <strong>Report Window:</strong> <?= date('M d, h:i A', strtotime($cluster['first_report_time'])) ?> &ndash; <?= date('M d, h:i A', strtotime($cluster['latest_report_time'])) ?>
                        </div>
                    </div>

                    <!-- Linked Reports List -->
                    <h6 class="fw-bold text-navy mb-2 small text-uppercase">Reports in this Cluster</h6>
                    <div class="list-group list-group-flush border rounded-2 mb-3">
                        <?php foreach ($cluster['reports'] as $r): ?>
                            <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between align-items-sm-center py-2 px-3 gap-2 <?= ($r['id'] == $incident['id']) ? 'bg-light' : '' ?>">
                                <div>
                                    <span class="fw-bold text-navy small">
                                        <a href="/neighbournet/pages/incident-details.php?id=<?= (int)$r['id'] ?>" class="text-navy text-decoration-none">
                                            #<?= (int)$r['id'] ?>: <?= e($r['title']) ?>
                                        </a>
                                    </span>
                                    <?php if ($r['id'] == $incident['id']): ?>
                                        <span class="badge bg-primary-subtle text-primary border ms-1" style="font-size: 0.65rem;">Current Report</span>
                                    <?php endif; ?>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-location-dot me-1"></i><?= e($r['location_text']) ?> &bull; Reported by <?= e($r['reporter_name']) ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-pill status-<?= e($r['status']) ?>" style="font-size: 0.68rem;">
                                        <?= str_replace('_', ' ', e($r['status'])) ?>
                                    </span>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        <form action="/neighbournet/api/admin/clusters.php" method="POST" class="d-inline" onsubmit="return confirm('Separate this incident from the cluster?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="cluster_id" value="<?= (int)$cluster['id'] ?>">
                                            <input type="hidden" name="incident_id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="action" value="remove_report">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.7rem;" title="Separate incorrectly grouped report">
                                                Separate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Admin Cluster Actions -->
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <div class="p-2 bg-light rounded-2 border d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="small fw-bold text-navy"><i class="fa-solid fa-shield-halved text-danger me-1"></i> Admin Cluster Decision:</span>
                            <div class="d-flex gap-2">
                                <?php if ($cluster['status'] !== 'approved'): ?>
                                    <form action="/neighbournet/api/admin/clusters.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="cluster_id" value="<?= (int)$cluster['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-3">
                                            <i class="fa-solid fa-check me-1"></i> Approve Cluster
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($cluster['status'] !== 'rejected'): ?>
                                    <form action="/neighbournet/api/admin/clusters.php" method="POST" class="d-inline" onsubmit="return confirm('Reject this cluster?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="cluster_id" value="<?= (int)$cluster['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">
                                            <i class="fa-solid fa-xmark me-1"></i> Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ========================================================
                 PROMPT 7: COMMUNITY VOTING & VERIFICATION MODULE
                 ======================================================== -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="brand-badge bg-primary" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fa-solid fa-check-double text-white"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-navy mb-0">Community Verification & Feedback</h4>
                            <small class="text-secondary">Resident crowd-checking to confirm report accuracy</small>
                        </div>
                    </div>
                    <span class="badge bg-light text-secondary border small">Total Votes: <?= $totalVotes ?></span>
                </div>

                <!-- Mandatory Civic Explanation -->
                <div class="alert alert-light border small text-secondary mb-4">
                    <i class="fa-solid fa-circle-info text-primary me-2"></i>
                    <strong>Community Transparency Notice:</strong> Voting represents direct resident observations, not absolute legal or municipal proof. A high number of TRUE votes signals strong neighbourhood corroboration, but does not automatically change the official administrative resolution status.
                </div>

                <!-- Visual Representation of Results -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between small fw-bold mb-2">
                        <span>Community Consensus Breakdown</span>
                        <span class="text-secondary"><?= $totalVotes ?> Resident Response<?= $totalVotes === 1 ? '' : 's' ?></span>
                    </div>

                    <?php if ($totalVotes === 0): ?>
                        <div class="p-3 bg-light rounded text-center small text-muted border">
                            No community feedback recorded yet. Be the first local resident to verify!
                        </div>
                    <?php else: ?>
                        <!-- Multi-Segment Progress Bar -->
                        <div class="progress mb-3" style="height: 10px;">
                            <?php if ($pctTrue > 0): ?>
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pctTrue ?>%" title="TRUE: <?= $trueVotes ?> (<?= $pctTrue ?>%)"></div>
                            <?php endif; ?>
                            <?php if ($pctMaybe > 0): ?>
                                <div class="progress-bar bg-warning text-dark" role="progressbar" style="width: <?= $pctMaybe ?>%" title="MAYBE: <?= $maybeVotes ?> (<?= $pctMaybe ?>%)"></div>
                            <?php endif; ?>
                            <?php if ($pctFalse > 0): ?>
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pctFalse ?>%" title="FALSE: <?= $falseVotes ?> (<?= $pctFalse ?>%)"></div>
                            <?php endif; ?>
                        </div>

                        <!-- 3 Metric Cards Grid -->
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2 rounded bg-success-subtle border border-success-subtle">
                                    <div class="fs-4 fw-bold text-success mb-0"><?= $trueVotes ?></div>
                                    <span class="fw-bold text-success small d-block">TRUE</span>
                                    <small class="text-muted d-none d-sm-block" style="font-size: 0.7rem;">Can confirm</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-warning-subtle border border-warning-subtle">
                                    <div class="fs-4 fw-bold text-warning-emphasis mb-0"><?= $maybeVotes ?></div>
                                    <span class="fw-bold text-warning-emphasis small d-block">MAYBE</span>
                                    <small class="text-muted d-none d-sm-block" style="font-size: 0.7rem;">Unsure / Unverified</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-danger-subtle border border-danger-subtle">
                                    <div class="fs-4 fw-bold text-danger mb-0"><?= $falseVotes ?></div>
                                    <span class="fw-bold text-danger small d-block">FALSE</span>
                                    <small class="text-muted d-none d-sm-block" style="font-size: 0.7rem;">Inaccurate report</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Current User Voting Interaction -->
                <div class="p-3 bg-light rounded-3 border">
                    <?php if ($isAuthor): ?>
                        <!-- Author Exclusion Notice (Rule 3) -->
                        <div class="d-flex align-items-center gap-2 text-secondary small">
                            <i class="fa-solid fa-lock text-muted fs-5"></i>
                            <div>
                                <strong>Author Restriction:</strong> As the creator of this report, you cannot vote on your own issue. This ensures authentic community corroboration.
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User has voted banner & change option -->
                        <?php if ($userVote): ?>
                            <div class="alert alert-info py-2 px-3 small d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <i class="fa-solid fa-circle-check text-primary me-1"></i>
                                    You voted <strong><?= e($userVote['vote_type']) ?></strong>
                                    <?php if (!empty($userVote['optional_comment'])): ?>
                                        <em>("<?= e($userVote['optional_comment']) ?>")</em>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Vote Active</span>
                            </div>
                            <div class="small text-secondary mb-2">
                                Need to update your feedback? You can change your vote below at any time:
                            </div>
                        <?php else: ?>
                            <div class="small fw-bold text-navy mb-2">
                                Have you witnessed this issue? Cast your independent verification vote:
                            </div>
                        <?php endif; ?>

                        <!-- Voting Form -->
                        <form id="voteForm" action="/neighbournet/api/incidents/vote.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="incident_id" value="<?= (int)$incident['id'] ?>">

                            <div class="row g-2 mb-3">
                                <!-- TRUE -->
                                <div class="col-sm-4">
                                    <input type="radio" class="btn-check" name="vote_type" id="voteTrue" value="TRUE" 
                                           <?= ($userVote && $userVote['vote_type'] === 'TRUE') ? 'checked' : '' ?> required>
                                    <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" for="voteTrue">
                                        <i class="fa-solid fa-thumbs-up fs-5 mb-1"></i>
                                        <strong class="d-block">TRUE</strong>
                                        <small style="font-size: 0.72rem;">I can confirm this issue</small>
                                    </label>
                                </div>

                                <!-- MAYBE -->
                                <div class="col-sm-4">
                                    <input type="radio" class="btn-check" name="vote_type" id="voteMaybe" value="MAYBE" 
                                           <?= ($userVote && $userVote['vote_type'] === 'MAYBE') ? 'checked' : '' ?> required>
                                    <label class="btn btn-outline-warning text-dark w-100 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" for="voteMaybe">
                                        <i class="fa-solid fa-question fs-5 mb-1"></i>
                                        <strong class="d-block">MAYBE</strong>
                                        <small style="font-size: 0.72rem;">Unsure / Cannot confirm</small>
                                    </label>
                                </div>

                                <!-- FALSE -->
                                <div class="col-sm-4">
                                    <input type="radio" class="btn-check" name="vote_type" id="voteFalse" value="FALSE" 
                                           <?= ($userVote && $userVote['vote_type'] === 'FALSE') ? 'checked' : '' ?> required>
                                    <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" for="voteFalse">
                                        <i class="fa-solid fa-thumbs-down fs-5 mb-1"></i>
                                        <strong class="d-block">FALSE</strong>
                                        <small style="font-size: 0.72rem;">I believe it is inaccurate</small>
                                    </label>
                                </div>
                            </div>

                            <!-- Optional Comment Input -->
                            <div class="mb-3">
                                <label for="optional_comment" class="form-label small fw-semibold text-secondary mb-1">
                                    Optional Observation Comment (Max 255 chars)
                                </label>
                                <input type="text" class="form-control form-control-sm" id="optional_comment" name="optional_comment" 
                                       placeholder="e.g. Saw this on my way to work; water pressure dropped significantly" 
                                       maxlength="255" value="<?= e($userVote['optional_comment'] ?? '') ?>">
                            </div>

                            <!-- Submit Button with Spinner -->
                            <button type="submit" id="btnSubmitVote" class="btn btn-sm btn-civic-primary py-2 px-3">
                                <span id="voteBtnText">
                                    <i class="fa-solid fa-check me-1"></i> <?= $userVote ? 'Update My Vote' : 'Submit Verification Feedback' ?>
                                </span>
                                <span id="voteBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Recent Voter Comments List -->
                <?php if (!empty($voterComments)): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold text-navy mb-3 small text-uppercase">
                            <i class="fa-solid fa-comments text-primary me-1"></i> Community Observations & Notes
                        </h6>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($voterComments as $c): ?>
                                <div class="p-2 bg-light rounded-2 border small">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="fw-semibold text-navy">
                                            <?= e($c['voter_name']) ?>
                                            <span class="badge ms-1 <?= ($c['vote_type'] === 'TRUE') ? 'bg-success' : (($c['vote_type'] === 'FALSE') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                                <?= e($c['vote_type']) ?>
                                            </span>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.7rem;">
                                            <?= date('M d, h:i A', strtotime($c['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary mb-0">"<?= e($c['optional_comment']) ?>"</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Lifecycle Timeline / Audit Log -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <h5 class="fw-bold text-navy mb-3"><i class="fa-solid fa-timeline text-primary me-2"></i>Official Status Timeline</h5>
                <?php if (empty($updates)): ?>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fa-solid fa-clock me-1"></i> No status updates have been published yet. The incident is currently in its initial <strong><?= e($incident['status']) ?></strong> state.
                    </div>
                <?php else: ?>
                    <div class="timeline ps-2">
                        <?php foreach ($updates as $up): ?>
                            <div class="border-start border-2 border-primary ps-3 pb-3 position-relative">
                                <div class="small text-muted"><?= date('M d, Y h:i A', strtotime($up['created_at'])) ?> &bull; by <?= e($up['updated_by']) ?></div>
                                <div class="fw-bold text-navy">
                                    Transitioned to <span class="badge bg-primary text-capitalize"><?= str_replace('_', ' ', e($up['new_status'])) ?></span>
                                </div>
                                <p class="small text-secondary mb-0 mt-1"><?= e($up['note']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Administrative Controls & Reporter Profile -->
        <div class="col-lg-4">
            <!-- Administrator Official Status Management Panel -->
            <?php if ($currentUser['role'] === 'admin'): ?>
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4 border-start border-danger border-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-shield-halved text-danger fs-5"></i>
                        <h5 class="fw-bold text-navy mb-0">Admin Status Controls</h5>
                    </div>
                    <p class="small text-secondary mb-3">
                        As an authorized administrator, you can change the official lifecycle state of this incident. Community voting informs, but does not overwrite, administrative action.
                    </p>

                    <form action="/neighbournet/api/incidents/update_status.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="incident_id" value="<?= (int)$incident['id'] ?>">

                        <div class="mb-3">
                            <label for="status" class="form-label small fw-bold text-navy">Set New Status</label>
                            <select class="form-select form-select-sm" id="status" name="status" required>
                                <option value="pending" <?= ($incident['status'] === 'pending') ? 'selected' : '' ?>>Pending Review</option>
                                <option value="under_verification" <?= ($incident['status'] === 'under_verification') ? 'selected' : '' ?>>Under Verification</option>
                                <option value="verified" <?= ($incident['status'] === 'verified') ? 'selected' : '' ?>>Verified Hazard</option>
                                <option value="in_progress" <?= ($incident['status'] === 'in_progress') ? 'selected' : '' ?>>Action In Progress</option>
                                <option value="resolved" <?= ($incident['status'] === 'resolved') ? 'selected' : '' ?>>Resolved & Closed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label small fw-bold text-navy">Audit Note / Resolution Comment</label>
                            <textarea class="form-control form-control-sm" id="note" name="note" rows="2" placeholder="e.g. Dispatched maintenance contractor ticket #4928" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-sm btn-danger w-100">
                            <i class="fa-solid fa-check me-1"></i> Update Official Status
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Reporter Profile Widget -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
                <h6 class="fw-bold text-navy mb-3">Reported By</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-badge" style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-navy"><?= e($incident['reporter_name']) ?></div>
                        <div class="small text-muted"><?= ucfirst(e($incident['reporter_role'])) ?> &bull; <?= (int)$incident['reporter_reputation'] ?> reputation pts</div>
                    </div>
                </div>
            </div>

            <!-- Prompt 10: Community Action Board preview -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div><h5 class="fw-bold mb-1"><i class="fa-solid fa-people-group text-primary me-2"></i>Community Action Board</h5><p class="text-secondary small mb-0">Safe, voluntary tasks linked to this incident.</p></div>
                    <a class="btn btn-sm btn-outline-primary" href="/neighbournet/pages/community-actions.php?incident_id=<?= (int)$incident['id'] ?>">View all tasks <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <?php if (!$communityTasks): ?>
                    <p class="small text-secondary mb-0">No community tasks have been posted for this incident yet.</p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                    <?php foreach ($communityTasks as $task): ?>
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2"><strong><?= e($task['title']) ?></strong><span class="badge text-bg-light"><?= e(ucwords(str_replace('_',' ',$task['status']))) ?></span></div>
                            <p class="small text-secondary my-2"><?= e(mb_strimwidth($task['description'],0,180,'…')) ?></p>
                            <div class="small text-secondary"><i class="fa-solid fa-users me-1"></i><?= (int)$task['volunteer_count'] ?> volunteers<?= $task['max_volunteers'] ? ' / '.(int)$task['max_volunteers'] : '' ?> · Created by <?= e($task['creator_name']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="small text-danger mt-3"><i class="fa-solid fa-triangle-exclamation me-1"></i>For immediate threats, contact official emergency services. Do not enter unsafe areas or attempt dangerous repairs.</div>
            </div>

            <!-- Navigation Shortcuts -->
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <h6 class="fw-bold text-navy mb-3">Quick Navigation</h6>
                <div class="d-grid gap-2">
                    <a href="/neighbournet/pages/dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Return to Feed
                    </a>
                    <a href="/neighbournet/pages/report.php" class="btn btn-sm btn-civic-primary">
                        <i class="fa-solid fa-plus-circle me-1"></i> Report Another Issue
                    </a>
                    <a href="/neighbournet/pages/map-view.php" class="btn btn-sm btn-civic-secondary">
                        <i class="fa-solid fa-map me-1"></i> View on Community Map
                    </a>
                    <a href="/neighbournet/pages/community-actions.php?incident_id=<?= (int)$incident['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-people-group me-1"></i> Community Action Tasks
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lat = <?= (float)$incident['latitude'] ?>;
    const lng = <?= (float)$incident['longitude'] ?>;

    const miniMap = L.map('incidentMiniMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(miniMap);

    L.marker([lat, lng]).addTo(miniMap)
        .bindPopup("<b><?= e($incident['title']) ?></b><br><?= e($incident['location_text']) ?>")
        .openPopup();
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const voteForm = document.getElementById('voteForm');
    if (voteForm) {
        voteForm.addEventListener('submit', () => {
            const btn = document.getElementById('btnSubmitVote');
            const text = document.getElementById('voteBtnText');
            const spinner = document.getElementById('voteBtnSpinner');
            if (btn && text && spinner) {
                btn.disabled = true;
                text.classList.add('d-none');
                spinner.classList.remove('d-none');
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(window.location.search).get('submitted') === '1') {
        try { localStorage.removeItem('neighbournet_report_draft_v1'); } catch (error) { /* Browser storage may be disabled. */ }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
