<?php
/** Session logout requires POST + CSRF to prevent cross-site logout. */
require_once __DIR__ . '/../../includes/auth_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrfToken($token)) { http_response_code(403); exit('Security token invalid. Refresh and retry.'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$params['path'],'domain'=>$params['domain'],'secure'=>$params['secure'],'httponly'=>$params['httponly'],'samesite'=>$params['samesite'] ?? 'Lax']); }
session_destroy();
header('Location: /neighbournet/index.php'); exit;
