<?php
/** Prompt 11: central admin-only management endpoint. */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['admin']);
$admin = getCurrentUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!validateCsrfToken($data['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid. Refresh and retry.']); exit; }
$action = trim((string)($data['action'] ?? ''));
$pdo = getDBConnection();
function adminAudit(PDO $pdo, int $adminId, string $action, string $type, ?int $id, string $details=''): void {
    $s=$pdo->prepare('INSERT INTO admin_audit_logs(admin_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)');
    $s->execute([$adminId,$action,$type,$id,$details]);
}
try {
 $pdo->beginTransaction();
 switch ($action) {
  case 'status':
   $id=(int)($data['incident_id']??0); $status=(string)($data['status']??''); $note=trim((string)($data['note']??''));
   $allowed=['pending','under_verification','verified','in_progress','resolved'];
   if($id<1 || !in_array($status,$allowed,true) || mb_strlen($note)>3000) throw new InvalidArgumentException('Check the incident, status and note.');
   $q=$pdo->prepare('SELECT status FROM incidents WHERE id=? FOR UPDATE'); $q->execute([$id]); $old=$q->fetchColumn(); if($old===false) throw new InvalidArgumentException('Incident not found.');
   if($old!==$status){ $pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$status,$id]); $historyNote=$note!==''?$note:'Official status update by administrator.'; $pdo->prepare('INSERT INTO incident_updates(incident_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)')->execute([$id,$admin['id'],$old,$status,$historyNote]); $pdo->prepare('INSERT INTO incident_history(incident_id,actor_id,old_status,new_status,note) VALUES(?,?,?,?,?)')->execute([$id,$admin['id'],$old,$status,$historyNote]); adminAudit($pdo,$admin['id'],'status_changed','incident',$id,"{$old} -> {$status}. {$note}"); }
   echo json_encode(['success'=>true,'message'=>$old===$status?'Status unchanged.':'Official incident status updated.']); break;
  case 'note':
   $id=(int)($data['incident_id']??0); $note=trim((string)($data['note']??'')); if($id<1||$note===''||mb_strlen($note)>5000) throw new InvalidArgumentException('A valid note (up to 5,000 characters) is required.');
   $q=$pdo->prepare('SELECT id FROM incidents WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Incident not found.');
   $pdo->prepare('INSERT INTO incident_admin_notes(incident_id,admin_id,note) VALUES(?,?,?)')->execute([$id,$admin['id'],$note]);adminAudit($pdo,$admin['id'],'note_added','incident',$id,'Administrative note added.');echo json_encode(['success'=>true,'message'=>'Administrative note saved.']);break;
  case 'flag_incident':
   $id=(int)($data['incident_id']??0);$flag=(string)($data['flag']??'none');$note=trim((string)($data['note']??''));if($id<1||!in_array($flag,['none','suspicious','potentially_false','abusive'],true)||mb_strlen($note)>500)throw new InvalidArgumentException('Invalid review flag or note.');
   $q=$pdo->prepare('UPDATE incidents SET review_flag=?,review_note=? WHERE id=?');$q->execute([$flag,$note?:null,$id]);if(!$q->rowCount()){ $c=$pdo->prepare('SELECT id FROM incidents WHERE id=?');$c->execute([$id]);if(!$c->fetchColumn())throw new InvalidArgumentException('Incident not found.'); }
   adminAudit($pdo,$admin['id'],'incident_review_flagged','incident',$id,"Flag: {$flag}. {$note}");echo json_encode(['success'=>true,'message'=>'Incident review flag updated.']);break;
  case 'comment':
   $id=(int)($data['comment_id']??0);$hidden=(int)($data['hidden']??1);$note=trim((string)($data['note']??''));$source=(string)($data['source_type']??'comment');if($id<1||!in_array($hidden,[0,1],true)||!in_array($source,['comment','vote'],true))throw new InvalidArgumentException('Invalid comment action.');
   if($source==='vote'){$q=$pdo->prepare('UPDATE community_votes SET is_hidden=?,moderation_note=? WHERE id=? AND optional_comment IS NOT NULL');$q->execute([$hidden,$note?:null,$id]);$table='community_observation';}else{$q=$pdo->prepare('UPDATE comments SET is_hidden=?,moderation_note=? WHERE id=?');$q->execute([$hidden,$note?:null,$id]);$table='comment';}
   if(!$q->rowCount()){$c=$pdo->prepare($source==='vote'?'SELECT id FROM community_votes WHERE id=? AND optional_comment IS NOT NULL':'SELECT id FROM comments WHERE id=?');$c->execute([$id]);if(!$c->fetchColumn())throw new InvalidArgumentException('Comment not found.');}
   adminAudit($pdo,$admin['id'],$hidden?'comment_hidden':'comment_restored',$table,$id,$note);echo json_encode(['success'=>true,'message'=>$hidden?'Comment hidden from residents.':'Comment restored.']);break;
  case 'user':
   $id=(int)($data['user_id']??0);$status=(string)($data['status']??'');if($id<1||$id===(int)$admin['id']||!in_array($status,['active','suspended'],true))throw new InvalidArgumentException('Invalid account moderation request.');
   $q=$pdo->prepare("UPDATE users SET account_status=? WHERE id=? AND role<>'admin'");$q->execute([$status,$id]);if(!$q->rowCount())throw new InvalidArgumentException('Resident account not found or unchanged.');adminAudit($pdo,$admin['id'],'account_'.$status,'user',$id,'Resident account status changed.');echo json_encode(['success'=>true,'message'=>'Resident account updated.']);break;
  case 'task':
   $id=(int)($data['task_id']??0);$status=(string)($data['status']??'');if($id<1||!in_array($status,['open','in_progress','completed','cancelled'],true))throw new InvalidArgumentException('Invalid task moderation request.');
   $q=$pdo->prepare('UPDATE community_tasks SET status=? WHERE id=?');$q->execute([$status,$id]);if(!$q->rowCount())throw new InvalidArgumentException('Task not found or unchanged.');adminAudit($pdo,$admin['id'],'task_'.$status,'community_task',$id,'Task moderated by administrator.');echo json_encode(['success'=>true,'message'=>'Community task updated.']);break;
  default: throw new InvalidArgumentException('Unsupported administrative action.');
 }
 $pdo->commit();
} catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Admin management error: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Administrative action failed. Please retry.']);}
