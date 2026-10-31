<?php
/** Admin-only, audited cluster moderation endpoint. */
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest') || stripos($_SERVER['HTTP_ACCEPT']??'', 'application/json')!==false;
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['admin']);
$user=getCurrentUser();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required']); exit; }
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
if (!validateCsrfToken($data['csrf_token']??'')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit; }
$clusterId=(int)($data['cluster_id']??0); $incidentId=(int)($data['incident_id']??0); $action=trim($data['action']??''); $note=trim($data['note']??'Administrative review');
if ($clusterId<1) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Valid cluster ID required']); exit; }
try {
 $pdo=getDBConnection(); $pdo->beginTransaction();
 $lock=$pdo->prepare('SELECT id,status FROM incident_clusters WHERE id=? FOR UPDATE'); $lock->execute([$clusterId]); $cluster=$lock->fetch();
 if (!$cluster) { $pdo->rollBack(); http_response_code(404); echo json_encode(['success'=>false,'message'=>'Cluster not found']); exit; }
 $auditAction=$action; $details=$note;
 switch($action) {
  case 'approve': $pdo->prepare("UPDATE incident_clusters SET status='approved' WHERE id=?")->execute([$clusterId]); break;
  case 'reject': $pdo->prepare("UPDATE incident_clusters SET status='rejected' WHERE id=?")->execute([$clusterId]); break;
  case 'remove_report': case 'separate':
   if ($incidentId<1) throw new InvalidArgumentException('Incident ID required');
   $d=$pdo->prepare('DELETE FROM cluster_reports WHERE cluster_id=? AND incident_id=?'); $d->execute([$clusterId,$incidentId]);
   if (!$d->rowCount()) throw new InvalidArgumentException('That incident is not linked to this cluster');
   $n=$pdo->prepare('SELECT COUNT(*) FROM cluster_reports WHERE cluster_id=?'); $n->execute([$clusterId]);
   if ((int)$n->fetchColumn()<2) $pdo->prepare("UPDATE incident_clusters SET status='rejected' WHERE id=?")->execute([$clusterId]);
   $auditAction=($action==='separate')?'separate':'remove_report'; $details="Separated incident #{$incidentId}. {$note}"; break;
  case 'add_report':
   if ($incidentId<1) throw new InvalidArgumentException('Incident ID required');
   $exists=$pdo->prepare('SELECT id FROM incidents WHERE id=?'); $exists->execute([$incidentId]); if (!$exists->fetchColumn()) throw new InvalidArgumentException('Incident not found');
   $add=$pdo->prepare("INSERT IGNORE INTO cluster_reports(cluster_id,incident_id,similarity_score,added_by) VALUES(?,?,100,'admin')"); $add->execute([$clusterId,$incidentId]);
   if (!$add->rowCount()) throw new InvalidArgumentException('Incident is already linked to this cluster');
   $auditAction='add_report'; $details="Manually attached incident #{$incidentId}. {$note}"; break;
  default: throw new InvalidArgumentException('Unsupported cluster action');
 }
 $log=$pdo->prepare('INSERT INTO cluster_audit_logs(cluster_id,admin_id,action,details) VALUES(?,?,?,?)'); $log->execute([$clusterId,$user['id'],$auditAction,$details]);
 $globalLog=$pdo->prepare('INSERT INTO admin_audit_logs(admin_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)'); $globalLog->execute([$user['id'],'cluster_'.$auditAction,'incident_cluster',$clusterId,$details]);
 $pdo->commit();
 if (!$isAjax) { $_SESSION['flash_success']='Cluster #'.$clusterId.' updated successfully.'; header('Location: '.($_SERVER['HTTP_REFERER']??'/neighbournet/pages/admin.php')); exit; }
 echo json_encode(['success'=>true,'message'=>'Cluster updated successfully.','cluster_id'=>$clusterId]);
} catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
catch (Throwable $e) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); error_log('Cluster moderation: '.$e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to update cluster. Please try again.']); }
