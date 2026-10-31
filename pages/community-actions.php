<?php
$pageTitle = 'Community Action Board — NeighbourNet';
$activePage = 'actions';
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
$pdo = getDBConnection();
$user = getCurrentUser();
$incidentId = max(0, (int)($_GET['incident_id'] ?? 0));
$incidents = $pdo->prepare("SELECT id,title,location_text FROM incidents WHERE (society_id=? OR user_id=?) ORDER BY created_at DESC LIMIT 100");
$incidents->execute([(int)($user['society_id'] ?? 0),(int)$user['id']]);
$incidentOptions = $incidents->fetchAll();
if (in_array($user['role'], ['admin','moderator'], true)) $incidentOptions = $pdo->query("SELECT id,title,location_text FROM incidents ORDER BY created_at DESC LIMIT 100")->fetchAll();
$csrf = getCsrfToken();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main class="container py-4 community-board" data-csrf="<?= e($csrf) ?>" data-incident="<?= $incidentId ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><span class="text-primary fw-semibold small text-uppercase">NeighbourNet · Community</span><h1 class="h2 fw-bold mb-1">Community Action Board</h1><p class="text-secondary mb-0">Small, safe, voluntary actions that help neighbours coordinate.</p></div>
    <div class="d-flex gap-2"><span class="badge text-bg-primary align-self-center" id="unreadCount">Notifications</span><button class="btn btn-outline-primary" id="refreshTasks"><i class="fa-solid fa-rotate me-1"></i>Refresh</button></div>
  </div>
  <div class="alert alert-danger d-flex gap-3 align-items-start" role="alert"><i class="fa-solid fa-triangle-exclamation fs-4"></i><div><strong>Immediate danger or emergency?</strong><br>Do not use this board for emergency response. Contact official emergency services or the relevant authority immediately. Never confront suspects, enter unsafe/flooded areas, repair electrical infrastructure, or handle hazardous materials.</div></div>
  <div class="row g-4">
    <section class="col-lg-8"><div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h4 fw-bold mb-0">Volunteer opportunities</h2><select class="form-select form-select-sm w-auto" id="taskFilter"><option value="all">All tasks</option><option value="open">Open</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></div><div id="taskList" class="d-grid gap-3"><div class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading community tasks…</div></div></section>
    <aside class="col-lg-4"><div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-4"><h2 class="h5 fw-bold mb-3"><i class="fa-solid fa-hand-holding-heart text-primary me-2"></i>Create a task</h2><form id="createTaskForm"><div class="mb-3"><label class="form-label" for="incidentSelect">Linked incident</label><select class="form-select" id="incidentSelect" required><option value="">Choose an incident</option><?php foreach($incidentOptions as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $incidentId===(int)$item['id']?'selected':'' ?>>#<?= (int)$item['id'] ?> · <?= e($item['title']) ?> — <?= e($item['location_text']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="taskTitle">Task title</label><input class="form-control" id="taskTitle" maxlength="160" minlength="5" required placeholder="e.g. Share verified maintenance update"></div><div class="mb-3"><label class="form-label" for="taskDescription">Description</label><textarea class="form-control" id="taskDescription" rows="4" minlength="12" maxlength="3000" required placeholder="Describe a practical, safe action and how to contribute…"></textarea></div><div class="row g-2"><div class="col-6"><label class="form-label" for="taskPriority">Priority</label><select class="form-select" id="taskPriority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option></select></div><div class="col-6"><label class="form-label" for="taskLimit">Volunteer limit</label><input class="form-control" id="taskLimit" type="number" min="1" max="500" placeholder="No limit"></div></div><div class="form-text mt-2">Only the incident reporter, a resident in the same neighbourhood, or an administrator can create a linked task.</div><button class="btn btn-primary w-100 mt-3" type="submit"><i class="fa-solid fa-plus me-1"></i>Publish task</button><div class="small mt-2" id="createFeedback" aria-live="polite"></div></form></div></div>
    <div class="card border-0 bg-light rounded-4"><div class="card-body p-4"><h3 class="h6 fw-bold"><i class="fa-solid fa-shield-heart text-success me-2"></i>Safe participation</h3><ul class="small text-secondary mb-0 ps-3"><li>Share verified information, not rumours.</li><li>Contact authorized maintenance teams or public agencies.</li><li>Do not collect or publish private contact details.</li><li>Volunteering is optional; withdraw whenever needed.</li></ul></div></div>
    <div class="card border-0 shadow-sm rounded-4 mt-3"><div class="card-body p-4"><div class="d-flex justify-content-between"><h3 class="h6 fw-bold mb-2">Your notifications</h3><button class="btn btn-link btn-sm p-0" id="markRead">Mark read</button></div><div id="notificationHint" class="small text-secondary mb-2">Task updates and volunteer activity will appear here.</div><div id="notificationList" class="d-grid gap-2"></div></div></div></aside>
  </div>
</main>
<link rel="stylesheet" href="/neighbournet/assets/css/community-actions.css"><script src="/neighbournet/assets/js/community-actions.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
