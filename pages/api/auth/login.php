<?php
/**
 * NeighbourNet - User Login Endpoint
 * Secure authentication with password_verify(), rate limiting, and session regeneration
 */

require_once __DIR__ . '/../../includes/auth_guard.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }
    header('Location: /neighbournet/pages/login.php');
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);

$csrfToken = $_POST['csrf_token'] ?? $jsonInput['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$email     = trim($_POST['email'] ?? $jsonInput['email'] ?? '');
$password  = $_POST['password'] ?? $jsonInput['password'] ?? '';
$clientIp  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// 1. CSRF Validation
if (!validateCsrfToken($csrfToken)) {
    $errorMsg = 'Security validation failed (CSRF). Please refresh and try again.';
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/login.php');
    exit;
}

// 2. Basic Input Presence Check
if (empty($email) || empty($password)) {
    $errorMsg = 'Please enter both your email address and password.';
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    $_SESSION['old_email'] = $email;
    header('Location: /neighbournet/pages/login.php');
    exit;
}

try {
    $pdo = getDBConnection();

    // 3. Rate Limiting Protection (Max 5 failed attempts per 15 minutes)
    if (isRateLimited($pdo, $email, $clientIp)) {
        $errorMsg = 'Too many failed login attempts. For security reasons, please wait 15 minutes before trying again.';
        if ($isAjax) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        $_SESSION['flash_error'] = $errorMsg;
        header('Location: /neighbournet/pages/login.php');
        exit;
    }

    // 4. Retrieve User Record with Society Info
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.password_hash, u.role, u.account_status, u.reputation_score,
               u.society_id, s.name AS society_name
        FROM users u
        LEFT JOIN societies s ON u.society_id = s.id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 5. Verify Password and Account Status
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Record failure for brute-force protection
        recordLoginFailure($pdo, $email, $clientIp);

        $errorMsg = 'Invalid email address or password.';
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        $_SESSION['flash_error'] = $errorMsg;
        $_SESSION['old_email'] = $email;
        header('Location: /neighbournet/pages/login.php');
        exit;
    }

    if ($user['account_status'] !== 'active') {
        $errorMsg = 'Your account is currently ' . htmlspecialchars($user['account_status']) . '. Please contact support.';
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        $_SESSION['flash_error'] = $errorMsg;
        header('Location: /neighbournet/pages/login.php');
        exit;
    }

    // 6. Login Success: Reset rate limit counter
    resetLoginFailures($pdo, $email, $clientIp);

    // 7. Security: Regenerate Session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // 8. Populate Authenticated Session State
    $_SESSION['user_id']          = (int)$user['id'];
    $_SESSION['user_name']        = $user['full_name'];
    $_SESSION['user_email']       = $user['email'];
    $_SESSION['user_role']        = $user['role'];
    $_SESSION['society_id']       = $user['society_id'];
    $_SESSION['society_name']     = $user['society_name'] ?? 'General Resident';
    $_SESSION['reputation_score'] = (int)$user['reputation_score'];

    // Determine target redirect
    $redirectUrl = $_SESSION['redirect_after_login'] ?? '/neighbournet/pages/dashboard.php';
    unset($_SESSION['redirect_after_login']);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'message'  => 'Login successful! Redirecting...',
            'redirect' => $redirectUrl,
            'user'     => [
                'id'       => (int)$user['id'],
                'name'     => $user['full_name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'society'  => $user['society_name'] ?? 'General Resident'
            ]
        ]);
        exit;
    }

    $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!';
    header("Location: $redirectUrl");
    exit;

} catch (PDOException $e) {
    error_log('Login Error: ' . $e->getMessage());
    $errorMsg = 'A database error occurred. Please check your connection and try again.';
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/login.php');
    exit;
}
