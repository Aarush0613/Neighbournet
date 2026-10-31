<?php
$pageTitle = 'My Profile & Settings — NeighbourNet';
$activePage = 'profile';
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

function profileRedirect(): void { header('Location: /neighbournet/pages/profile.php'); exit; }
function profileFlash(string $type, string $message): void { $_SESSION['profile_'.$type] = $message; }

// Complete a verified email change only after the emailed, expiring token is presented.
if (isset($_GET['verify_email'], $_GET['token'])) {
    $requestId = (int)$_GET['verify_email'];
    $token = (string)$_GET['token'];
    $stmt = $pdo->prepare('SELECT * FROM email_change_requests WHERE id=? AND user_id=? AND expires_at>NOW()');
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch();
    if ($request && hash_equals($request['token_hash'], hash('sha256', $token))) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$request['new_email'], $userId]);
            $pdo->prepare('DELETE FROM email_change_requests WHERE user_id=?')->execute([$userId]);
            $pdo->commit();
            $_SESSION['user_email'] = $request['new_email'];
            profileFlash('success', 'Your email address has been verified and updated.');
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); profileFlash('error', 'That email address may already be in use.'); }
    } else { profileFlash('error', 'This email verification link is invalid or expired.'); }
    profileRedirect();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security token expired. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'profile') {
                $name = trim($_POST['full_name'] ?? '');
                if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Enter a name between 1 and 120 characters.');
                $pdo->prepare('UPDATE users SET full_name=? WHERE id=?')->execute([$name, $userId]);
                $_SESSION['user_name'] = $name;
                if (!empty($_FILES['profile_image']['name'])) {
                    $file = $_FILES['profile_image'];
                    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Profile image must be a valid upload no larger than 2 MB.');
                    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
                    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) throw new RuntimeException('Upload a valid JPG, PNG, or WebP image.');
                    $dir = __DIR__ . '/../uploads/profiles';
                    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Could not create profile image storage.');
                    $filename = 'user_'.$userId.'_'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
                    if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$filename)) throw new RuntimeException('Could not save the profile image.');
                    $relative = '/neighbournet/uploads/profiles/'.$filename;
                    $pdo->prepare('UPDATE users SET profile_image=? WHERE id=?')->execute([$relative,$userId]);
                }
                profileFlash('success', 'Profile updated successfully.'); profileRedirect();
            }
            if ($action === 'email') {
                $email = strtolower(trim($_POST['new_email'] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>150) throw new RuntimeException('Enter a valid email address.');
                $check=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?'); $check->execute([$email,$userId]);
                if ($check->fetch()) throw new RuntimeException('That email address is already registered.');
                if ($email === strtolower($_SESSION['user_email'] ?? '')) throw new RuntimeException('This is already your current email address.');
                $token=bin2hex(random_bytes(32));
                $pdo->prepare('DELETE FROM email_change_requests WHERE user_id=? OR new_email=?')->execute([$userId,$email]);
                $pdo->prepare('INSERT INTO email_change_requests (user_id,new_email,token_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([$userId,$email,hash('sha256',$token)]);
                $verifyUrl = (isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'/neighbournet/pages/profile.php?verify_email='.$pdo->lastInsertId().'&token='.$token;
                $sent = @mail($email, 'Verify your NeighbourNet email change', "Use this link within 30 minutes to verify your new email address:\n\n$verifyUrl\n\nIf you did not request this, ignore this message.");
                profileFlash($sent?'success':'error', $sent?'Verification email sent. Your current email remains unchanged until verification.':'Verification email could not be sent by this server. Configure PHP mail/SMTP and request the change again.'); profileRedirect();
            }
            if ($action === 'preferences') {
                $language = in_array($_POST['language']??'', ['en','hi'], true) ? $_POST['language'] : 'en';
                $mode = in_array($_POST['theme_mode']??'', ['light','dark','system'], true) ? $_POST['theme_mode'] : 'system';
                $colour = in_array($_POST['colour_theme']??'', ['ocean-blue','forest-green','midnight-navy','purple'], true) ? $_POST['colour_theme'] : 'ocean-blue';
                $pdo->prepare('INSERT INTO user_preferences (user_id,language,theme_mode,colour_theme) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language),theme_mode=VALUES(theme_mode),colour_theme=VALUES(colour_theme)')->execute([$userId,$language,$mode,$colour]);
                profileFlash('success', 'Your language and appearance preferences have been saved.'); profileRedirect();
            }
            if ($action === 'password') {
                $stmt=$pdo->prepare('SELECT password_hash FROM users WHERE id=?'); $stmt->execute([$userId]); $hash=$stmt->fetchColumn();
                $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
                if (!$hash || !password_verify($current,$hash)) throw new RuntimeException('Your current password is incorrect.');
                if (strlen($new)<10 || strlen($new)>72 || !preg_match('/[A-Za-z]/',$new) || !preg_match('/[0-9]/',$new)) throw new RuntimeException('New password must be at least 10 characters and include letters and numbers.');
                if ($new !== $confirm) throw new RuntimeException('New password and confirmation do not match.');
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$userId]);
                profileFlash('success','Password changed successfully.'); profileRedirect();
            }
            if ($action === 'society') {
                $societyId=(int)($_POST['society_id']??0);
                $check=$pdo->prepare('SELECT id FROM societies WHERE id=?'); $check->execute([$societyId]);
                if (!$check->fetch()) throw new RuntimeException('Select a valid neighbourhood.');
                $pending=$pdo->prepare("SELECT id FROM society_change_requests WHERE user_id=? AND status='pending'"); $pending->execute([$userId]);
                if ($pending->fetch()) throw new RuntimeException('You already have a neighbourhood change awaiting review.');
                $pdo->prepare('INSERT INTO society_change_requests (user_id,requested_society_id) VALUES (?,?)')->execute([$userId,$societyId]);
                profileFlash('success','Neighbourhood change request submitted for administrator approval.'); profileRedirect();
            }
            if ($action === 'delete_account') {
                $deleteHashStmt=$pdo->prepare('SELECT password_hash FROM users WHERE id=?'); $deleteHashStmt->execute([$userId]); $deleteHash=(string)$deleteHashStmt->fetchColumn();
                if (($_POST['confirm_delete']??'') !== 'DELETE' || !password_verify($_POST['delete_password']??'', $deleteHash)) throw new RuntimeException('Enter DELETE and your current password to confirm account closure.');
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM email_change_requests WHERE user_id=?')->execute([$userId]);
                $pdo->prepare('DELETE FROM society_change_requests WHERE user_id=?')->execute([$userId]);
                // Retain the user row and incident foreign-key history; anonymize instead of cascading away reports/votes/comments.
                $pdo->prepare("UPDATE users SET full_name='Deleted Resident', email=?, password_hash=?, society_id=NULL, profile_image=NULL, account_status='suspended', reputation_score=0 WHERE id=?")
                    ->execute(['deleted-'.$userId.'-'.bin2hex(random_bytes(5)).'@account.invalid', password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$userId]);
                $pdo->commit();
                $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy();
                header('Location: /neighbournet/index.php?account=closed'); exit;
            }
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = $e instanceof PDOException ? 'The requested change could not be saved. Please check the values and try again.' : $e->getMessage(); }
    }
}
if (!empty($_SESSION['profile_success'])) { $success=$_SESSION['profile_success']; unset($_SESSION['profile_success']); }
if (!empty($_SESSION['profile_error'])) { $errors[]=$_SESSION['profile_error']; unset($_SESSION['profile_error']); }

$stmt=$pdo->prepare("SELECT u.*,s.name AS society_name,s.ward_sector,s.city FROM users u LEFT JOIN societies s ON s.id=u.society_id WHERE u.id=?"); $stmt->execute([$userId]); $user=$stmt->fetch();
if (!$user) { header('Location: /neighbournet/pages/login.php'); exit; }
$prefStmt=$pdo->prepare('SELECT * FROM user_preferences WHERE user_id=?'); $prefStmt->execute([$userId]); $prefs=$prefStmt->fetch() ?: ['language'=>'en','theme_mode'=>'system','colour_theme'=>'ocean-blue'];
$statsStmt=$pdo->prepare("SELECT COUNT(*) total, SUM(status='resolved') resolved, SUM(status='pending') pending, SUM(status='in_progress') in_progress FROM incidents WHERE user_id=?"); $statsStmt->execute([$userId]); $stats=$statsStmt->fetch();
$page=(max(1,(int)($_GET['page']??1))); $perPage=8; $filter=$_GET['status']??'all'; $validFilters=['all','pending','under_verification','verified','in_progress','resolved']; if(!in_array($filter,$validFilters,true))$filter='all';
$where='user_id=?'; $params=[$userId]; if($filter!=='all'){$where.=' AND status=?';$params[]=$filter;}
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM incidents WHERE $where");$countStmt->execute($params);$totalReports=(int)$countStmt->fetchColumn();$pages=max(1,(int)ceil($totalReports/$perPage));$page=min($page,$pages);
$reportsStmt=$pdo->prepare("SELECT i.id,i.title,i.location_text,i.status,i.created_at,c.name category_name FROM incidents i JOIN categories c ON c.id=i.category_id WHERE $where ORDER BY i.created_at DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));$reportsStmt->execute($params);$reports=$reportsStmt->fetchAll();
$societies=$pdo->query('SELECT id,name,ward_sector,city FROM societies ORDER BY name')->fetchAll();
$pendingReq=$pdo->prepare("SELECT scr.*,s.name society_name FROM society_change_requests scr JOIN societies s ON s.id=scr.requested_society_id WHERE scr.user_id=? AND scr.status='pending' ORDER BY scr.created_at DESC LIMIT 1");$pendingReq->execute([$userId]);$pendingSociety=$pendingReq->fetch();
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>
<main class="container py-4 profile-page">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2"><div><div class="text-uppercase small fw-bold text-primary">Account centre</div><h1 class="h2 fw-bold mb-1">Profile & Settings</h1><p class="text-secondary mb-0">Manage your identity, reports, preferences and account security.</p></div><a class="btn btn-outline-primary" href="/neighbournet/pages/dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a></div>
 <?php foreach($errors as $err): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?=e($err)?></div><?php endforeach; ?>
 <?php if($success): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?=e($success)?></div><?php endif; ?>
 <section class="profile-hero card border-0 shadow-sm mb-4"><div class="card-body p-4"><div class="d-flex flex-wrap align-items-center gap-3"><img class="profile-avatar" src="<?=e($user['profile_image'] ?: '/neighbournet/assets/images/default-avatar.svg')?>" alt="Profile picture" onerror="this.src='/neighbournet/assets/images/default-avatar.svg'"><div class="flex-grow-1"><h2 class="h4 mb-1"><?=e($user['full_name'])?></h2><div class="text-secondary"><?=e($user['email'])?></div><div class="small text-secondary mt-1"><i class="fa-solid fa-location-dot me-1"></i><?=e($user['society_name'] ?: 'Neighbourhood not set')?><?= $user['ward_sector'] ? ' · '.e($user['ward_sector']) : '' ?></div><span class="badge text-bg-light mt-2 text-capitalize"><?=e($user['role'])?></span></div><div class="small text-secondary">Member since<br><strong><?=date('d M Y',strtotime($user['created_at']))?></strong></div></div></div></section>
 <div class="row g-3 mb-4"><div class="col-6 col-lg-3"><div class="profile-stat"><span>Total reports</span><strong><?= (int)$stats['total']?></strong><i class="fa-solid fa-file-lines"></i></div></div><div class="col-6 col-lg-3"><div class="profile-stat"><span>Resolved</span><strong><?= (int)$stats['resolved']?></strong><i class="fa-solid fa-circle-check"></i></div></div><div class="col-6 col-lg-3"><div class="profile-stat"><span>Pending</span><strong><?= (int)$stats['pending']?></strong><i class="fa-solid fa-hourglass-half"></i></div></div><div class="col-6 col-lg-3"><div class="profile-stat"><span>In progress</span><strong><?= (int)$stats['in_progress']?></strong><i class="fa-solid fa-person-digging"></i></div></div></div>
 <div class="row g-4"><div class="col-lg-8">
  <section class="card profile-card mb-4"><div class="card-header"><h3 class="h5 mb-0">My Reports</h3></div><div class="card-body"><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach(['all'=>'All','pending'=>'Pending','under_verification'=>'Under Verification','verified'=>'Verified','in_progress'=>'In Progress','resolved'=>'Resolved'] as $key=>$label): ?><a class="btn btn-sm <?= $filter===$key?'btn-primary':'btn-outline-secondary'?>" href="?status=<?=$key?>"><?=e($label)?></a><?php endforeach; ?></div>
  <?php if(!$reports): ?><div class="text-center text-secondary py-4"><i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>No reports found for this filter.</div><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Report</th><th>Location</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($reports as $r): ?><tr><td><strong><?=e($r['title'])?></strong><div class="small text-secondary"><?=e($r['category_name'])?></div></td><td class="small"><?=e($r['location_text'])?></td><td class="small text-nowrap"><?=date('d M Y',strtotime($r['created_at']))?></td><td><span class="badge status-<?=e($r['status'])?>"><?=e(ucwords(str_replace('_',' ',$r['status'])))?></span></td><td><a class="btn btn-sm btn-light" href="/neighbournet/pages/incident-details.php?id=<?=(int)$r['id']?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <?php if($pages>1): ?><nav class="d-flex justify-content-between align-items-center"><span class="small text-secondary">Page <?=$page?> of <?=$pages?></span><div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary <?=$page<=1?'disabled':''?>" href="?status=<?=e($filter)?>&page=<?=$page-1?>">Previous</a><a class="btn btn-outline-secondary <?=$page>=$pages?'disabled':''?>" href="?status=<?=e($filter)?>&page=<?=$page+1?>">Next</a></div></nav><?php endif; ?></div></section>
  <section class="card profile-card mb-4"><div class="card-header"><h3 class="h5 mb-0">Edit Profile</h3></div><div class="card-body"><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="profile"><div class="col-md-7"><label class="form-label">Full name</label><input class="form-control" name="full_name" maxlength="120" required value="<?=e($user['full_name'])?>"></div><div class="col-md-5"><label class="form-label">Profile picture (JPG, PNG, WebP; max 2 MB)</label><input class="form-control" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"></div><div class="col-12"><button class="btn btn-primary">Save profile</button></div></form><hr><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="email"><div class="col-md-8"><label class="form-label">Change email address</label><input class="form-control" type="email" name="new_email" required placeholder="New email address"><div class="form-text">Your existing email stays active until you verify the link sent to the new address.</div></div><div class="col-md-4 align-self-end"><button class="btn btn-outline-primary">Send verification</button></div></form><hr><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="society"><div class="col-md-8"><label class="form-label">Request neighbourhood change</label><select class="form-select" name="society_id" required><option value="">Choose neighbourhood</option><?php foreach($societies as $s): ?><option value="<?=$s['id']?>" <?=$user['society_id']==$s['id']?'selected':''?>><?=e($s['name'].' — '.$s['ward_sector'].', '.$s['city'])?></option><?php endforeach; ?></select><?php if($pendingSociety): ?><div class="form-text text-warning">Pending approval: <?=e($pendingSociety['society_name'])?></div><?php else: ?><div class="form-text">Changes require administrator approval.</div><?php endif; ?></div><div class="col-md-4 align-self-end"><button class="btn btn-outline-primary" <?=$pendingSociety?'disabled':''?>>Submit request</button></div></form></div></section>
 </div><div class="col-lg-4">
  <section class="card profile-card mb-4"><div class="card-header"><h3 class="h5 mb-0">General Settings</h3></div><div class="card-body"><form method="post" class="vstack gap-3"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="preferences"><div><label class="form-label">Language</label><select class="form-select" name="language"><option value="en" <?=$prefs['language']==='en'?'selected':''?>>English</option><option value="hi" <?=$prefs['language']==='hi'?'selected':''?>>Hindi</option></select></div><div><label class="form-label">Appearance</label><select class="form-select" name="theme_mode"><?php foreach(['light'=>'Light','dark'=>'Dark','system'=>'System default'] as $k=>$v): ?><option value="<?=$k?>" <?=$prefs['theme_mode']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div><div><label class="form-label">Colour theme</label><select class="form-select" name="colour_theme"><?php foreach(['ocean-blue'=>'Ocean Blue','forest-green'=>'Forest Green','midnight-navy'=>'Midnight Navy','purple'=>'Purple'] as $k=>$v): ?><option value="<?=$k?>" <?=$prefs['colour_theme']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div><button class="btn btn-primary">Save preferences</button></form><p class="small text-secondary mt-3 mb-0">Preferences are stored with your account and restored after login. Language preference is saved; translated page content is applied where translations are available.</p></div></section>
  <section class="card profile-card mb-4"><div class="card-header"><h3 class="h5 mb-0">Account Security</h3></div><div class="card-body"><form method="post" class="vstack gap-2"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="password"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" minlength="10" required autocomplete="new-password"><div class="form-text">At least 10 characters, including letters and numbers.</div><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="confirm_password" minlength="10" required autocomplete="new-password"><button class="btn btn-outline-primary mt-2">Change password</button></form><hr><form method="post" action="/neighbournet/api/auth/logout.php"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><button class="btn btn-outline-secondary w-100" type="submit"><i class="fa-solid fa-right-from-bracket me-2"></i>Log out</button></form></div></section>
  <section class="card border-danger profile-card"><div class="card-header bg-danger-subtle"><h3 class="h5 text-danger mb-0">Delete Account</h3></div><div class="card-body"><p class="small">Your profile will be anonymized and login disabled. Existing incident reports and their civic history will be retained with the reporter shown as <strong>Deleted Resident</strong>. This action cannot be undone.</p><form method="post" onsubmit="return confirm('Permanently close and anonymize your account? Existing reports will remain in the public incident history.');" class="vstack gap-2"><input type="hidden" name="csrf_token" value="<?=e(getCsrfToken())?>"><input type="hidden" name="action" value="delete_account"><label class="form-label">Type DELETE to confirm</label><input class="form-control" name="confirm_delete" required pattern="DELETE"><label class="form-label">Current password</label><input class="form-control" type="password" name="delete_password" required autocomplete="current-password"><button class="btn btn-danger mt-2">Close my account</button></form></div></section>
 </div></div>
</main>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
