<?php
/** NeighbourNet Community Action Board API. All writes require login + CSRF. */
require_once __DIR__ . '/../../includes/auth_guard.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function taskJson(int $status, array $data): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function taskCanManage(array $task, array $user): bool { return (int)($task['created_by'] ?? 0) === (int)$user['id'] || in_array($user['role'], ['admin','moderator'], true); }
function taskSafeText(string $text): bool {
    $unsafe = [
        '/\b(confront|chase|detain|attack)\s+(a\s+)?(suspect|person|intruder)\b/i',
        '/\b(enter|go into|walk through)\s+(a\s+)?(flooded|unsafe|restricted|dangerous)\s+(area|building|site|location)\b/i',
        '/\b(repair|fix|touch|handle)\s+(live\s+)?(electrical|electricity|power line|transformer|hazardous material|chemical)\b/i',
        '/\binvestigate\s+(a\s+)?(dangerous|unsafe)\s+(location|site|area)\b/i'
    ];
    foreach ($unsafe as $pattern) if (preg_match($pattern, $text)) return false;
    return true;
}

$pdo = getDBConnection();
$user = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $incidentId = max(0, (int)($_GET['incident_id'] ?? 0));
    $taskId = max(0, (int)($_GET['task_id'] ?? 0));
    $where = ['1=1']; $params = [(int)$user['id']];
    if (!in_array($user['role'], ['admin','moderator'], true)) { $where[] = '(i.society_id = ? OR i.user_id = ?)'; $params[] = (int)($user['society_id'] ?? 0); $params[] = (int)$user['id']; }
    if ($incidentId) { $where[] = 't.incident_id = ?'; $params[] = $incidentId; }
    if ($taskId) { $where[] = 't.id = ?'; $params[] = $taskId; }
    try {
        $sql = "SELECT t.*, i.title AS incident_title, i.location_text, i.status AS incident_status,
                       creator.full_name AS creator_name,
                       COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN v.id END) AS volunteer_count,
                       MAX(CASE WHEN v.user_id = ? THEN 1 ELSE 0 END) AS is_volunteered
                FROM community_tasks t JOIN incidents i ON i.id=t.incident_id
                LEFT JOIN users creator ON creator.id=t.created_by
                LEFT JOIN task_volunteers v ON v.task_id=t.id
                WHERE " . implode(' AND ', $where) . " GROUP BY t.id ORDER BY FIELD(t.status,'open','in_progress','completed','cancelled'), FIELD(t.priority,'high','normal','low'), t.created_at DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($params); $tasks = $stmt->fetchAll();
        foreach ($tasks as &$task) {
            $u = $pdo->prepare("SELECT tu.update_text, tu.created_at, COALESCE(us.full_name,'Former member') AS author_name FROM task_updates tu LEFT JOIN users us ON us.id=tu.author_id WHERE tu.task_id=? ORDER BY tu.created_at DESC LIMIT 8");
            $u->execute([(int)$task['id']]); $task['updates'] = $u->fetchAll();
        }
        unset($task);
        $n = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $n->execute([(int)$user['id']]);
        $notificationsStmt = $pdo->prepare('SELECT id,message,notification_type,is_read,created_at,task_id,incident_id FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
        $notificationsStmt->execute([(int)$user['id']]);
        foreach ($tasks as &$task) $task['can_manage'] = taskCanManage($task, $user);
        unset($task);
        taskJson(200, ['success'=>true,'tasks'=>$tasks,'unread_notifications'=>(int)$n->fetchColumn(),'notifications'=>$notificationsStmt->fetchAll()]);
    } catch (PDOException $e) { taskJson(500, ['success'=>false,'message'=>'Could not load community tasks. Run the Prompt 10 database migration first.']); }
}

if ($method !== 'POST') taskJson(405, ['success'=>false,'message'=>'Method not allowed.']);
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!validateCsrfToken($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) taskJson(403, ['success'=>false,'message'=>'Security token expired. Refresh the page and try again.']);
$action = (string)($input['action'] ?? '');
$userId = (int)$user['id'];

try {
    if ($action === 'create') {
        $incidentId = (int)($input['incident_id'] ?? 0); $title = trim((string)($input['title'] ?? '')); $description = trim((string)($input['description'] ?? ''));
        $priority = (string)($input['priority'] ?? 'normal'); $max = ($input['max_volunteers'] ?? '') === '' ? null : (int)$input['max_volunteers'];
        if ($incidentId < 1 || mb_strlen($title) < 5 || mb_strlen($title) > 160 || mb_strlen($description) < 12 || mb_strlen($description) > 3000) taskJson(422,['success'=>false,'message'=>'Provide a valid incident, a 5–160 character title and a 12–3000 character description.']);
        if (!in_array($priority,['low','normal','high'],true) || ($max !== null && ($max < 1 || $max > 500))) taskJson(422,['success'=>false,'message'=>'Invalid priority or volunteer limit.']);
        if (!taskSafeText($title.' '.$description)) taskJson(422,['success'=>false,'message'=>'This task appears to encourage unsafe activity. Please rephrase it around contacting authorized services or sharing verified information.']);
        $check = $pdo->prepare('SELECT id,user_id,society_id FROM incidents WHERE id=?'); $check->execute([$incidentId]); $incident=$check->fetch();
        if (!$incident) taskJson(404,['success'=>false,'message'=>'Linked incident not found.']);
        if (!in_array($user['role'],['admin','moderator'],true) && (int)$incident['user_id'] !== $userId && (empty($user['society_id']) || (int)$incident['society_id'] !== (int)$user['society_id'])) taskJson(403,['success'=>false,'message'=>'Only the incident reporter, a resident of the same neighbourhood, or an administrator can create a linked task.']);
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("INSERT INTO community_tasks (incident_id,title,description,created_by,priority,max_volunteers) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$incidentId,$title,$description,$userId,$priority,$max]); $taskId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO task_updates(task_id,author_id,update_text) VALUES(?,?,?)")->execute([$taskId,$userId,'Task created. Residents can volunteer if they can contribute safely.']);
        $pdo->commit();
        taskJson(201,['success'=>true,'message'=>'Community task created.','task_id'=>$taskId]);
    }
    if ($action === 'join') {
        $taskId=(int)($input['task_id']??0); $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT * FROM community_tasks WHERE id=? FOR UPDATE'); $q->execute([$taskId]); $task=$q->fetch();
        if (!$task || $task['status']!=='open') { $pdo->rollBack(); taskJson(409,['success'=>false,'message'=>'This task is no longer accepting volunteers.']); }
        $count=$pdo->prepare('SELECT COUNT(*) FROM task_volunteers WHERE task_id=?'); $count->execute([$taskId]); $current=(int)$count->fetchColumn();
        if ($task['max_volunteers'] !== null && $current >= (int)$task['max_volunteers']) { $pdo->rollBack(); taskJson(409,['success'=>false,'message'=>'This task has reached its volunteer limit.']); }
        $ins=$pdo->prepare('INSERT IGNORE INTO task_volunteers(task_id,user_id) VALUES(?,?)'); $ins->execute([$taskId,$userId]);
        if ($ins->rowCount()===0) { $pdo->rollBack(); taskJson(409,['success'=>false,'message'=>'You have already volunteered for this task.']); }
        if (!empty($task['created_by'])) $pdo->prepare("INSERT INTO notifications(user_id,task_id,incident_id,notification_type,message) VALUES(?,?,?,'volunteer_joined',?)")->execute([(int)$task['created_by'],$taskId,(int)$task['incident_id'],$user['name'].' volunteered for "'.$task['title'].'".']);
        $pdo->commit(); taskJson(200,['success'=>true,'message'=>'You are now volunteering for this task.']);
    }
    if ($action === 'withdraw') {
        $taskId=(int)($input['task_id']??0); $q=$pdo->prepare('DELETE FROM task_volunteers WHERE task_id=? AND user_id=?'); $q->execute([$taskId,$userId]);
        if (!$q->rowCount()) taskJson(404,['success'=>false,'message'=>'You are not listed as a volunteer for this task.']);
        taskJson(200,['success'=>true,'message'=>'You have withdrawn from this task.']);
    }
    if ($action === 'update') {
        $taskId=(int)($input['task_id']??0); $message=trim((string)($input['update_text']??'')); $newStatus=(string)($input['status']??'');
        if (mb_strlen($message)<4 || mb_strlen($message)>1500 || !taskSafeText($message)) taskJson(422,['success'=>false,'message'=>'Enter a safe progress update between 4 and 1500 characters.']);
        $q=$pdo->prepare('SELECT * FROM community_tasks WHERE id=?'); $q->execute([$taskId]); $task=$q->fetch();
        if (!$task || !taskCanManage($task,$user)) taskJson(403,['success'=>false,'message'=>'Only the task creator or an administrator can post updates.']);
        $allowed=['open','in_progress','completed','cancelled'];
        if ($newStatus!=='' && !in_array($newStatus,$allowed,true)) taskJson(422,['success'=>false,'message'=>'Invalid task status.']);
        $pdo->beginTransaction();
        if ($newStatus!=='') $pdo->prepare('UPDATE community_tasks SET status=? WHERE id=?')->execute([$newStatus,$taskId]);
        $pdo->prepare('INSERT INTO task_updates(task_id,author_id,update_text) VALUES(?,?,?)')->execute([$taskId,$userId,$message]);
        $recipients=$pdo->prepare('SELECT DISTINCT user_id FROM task_volunteers WHERE task_id=? AND user_id<>?'); $recipients->execute([$taskId,$userId]);
        $notify=$pdo->prepare("INSERT INTO notifications(user_id,task_id,incident_id,notification_type,message) VALUES(?,?,?,'task_update',?)");
        foreach($recipients->fetchAll(PDO::FETCH_COLUMN) as $recipient) $notify->execute([(int)$recipient,$taskId,(int)$task['incident_id'],'Update on "'.$task['title'].'": '.mb_substr($message,0,175)]);
        $pdo->commit(); taskJson(200,['success'=>true,'message'=>'Progress update posted and volunteers notified.']);
    }
    if ($action === 'mark_notifications_read') {
        $pdo->prepare('UPDATE notifications SET is_read=1,read_at=NOW() WHERE user_id=? AND is_read=0')->execute([$userId]);
        taskJson(200,['success'=>true,'message'=>'Notifications marked as read.']);
    }
    taskJson(400,['success'=>false,'message'=>'Unknown action.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode()==='23000') taskJson(409,['success'=>false,'message'=>'This action conflicts with an existing record. You may already be volunteering.']);
    taskJson(500,['success'=>false,'message'=>'The action could not be completed. Please try again.']);
}
