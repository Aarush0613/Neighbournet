<?php
/**
 * NeighbourNet - Authentication, Authorization & Security Guard
 * Implements secure session cookies, CSRF protection, rate limiting, and RBAC
 */

// 1. Secure Session Cookie Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    
    session_set_cookie_params([
        'lifetime' => 0, // Session ends when browser closes
        'path'     => '/neighbournet/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true, // Mitigate XSS cookie theft
        'samesite' => 'Lax' // Mitigate CSRF
    ]);
    
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Output escaping helper to prevent XSS
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Protection: Generate or retrieve token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Protection: Validate submitted token
 */
function validateCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate Limiting: Check if IP or email exceeded failed login attempts
 * Max 5 attempts within 15 minutes
 */
function isRateLimited(PDO $pdo, string $email, string $ip): bool {
    $timeWindow = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts 
        WHERE (email = ? OR ip_address = ?) AND attempted_at > ?
    ");
    $stmt->execute([$email, $ip, $timeWindow]);
    $attempts = (int)$stmt->fetchColumn();

    return $attempts >= 5;
}

/**
 * Rate Limiting: Record a failed login attempt
 */
function recordLoginFailure(PDO $pdo, string $email, string $ip): void {
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address) 
        VALUES (?, ?)
    ");
    $stmt->execute([$email, $ip]);
}

/**
 * Rate Limiting: Clear failed attempts upon successful login
 */
function resetLoginFailures(PDO $pdo, string $email, string $ip): void {
    $stmt = $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE email = ? OR ip_address = ?
    ");
    $stmt->execute([$email, $ip]);
}

/**
 * Check if the current user is logged in
 */
function isLoggedIn(): bool {
    static $activeAccount = null;
    if (empty($_SESSION['user_id'])) return false;
    if ($activeAccount !== null) return $activeAccount;
    try {
        $stmt = getDBConnection()->prepare('SELECT role, account_status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || ($account['account_status'] ?? '') !== 'active') {
            $_SESSION['flash_error'] = 'Your account is not active. Please contact the community administrator.';
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['society_id'], $_SESSION['society_name']);
            $activeAccount = false;
            return false;
        }
        $_SESSION['user_role'] = $account['role'];
        $activeAccount = true;
        return true;
    } catch (Throwable $e) {
        error_log('Active account verification failed: ' . $e->getMessage());
        return false; // Fail closed if account status cannot be verified.
    }
}

/**
 * Check if the current user is an admin
 */
function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'admin');
}

/**
 * Get current authenticated user details from session
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'            => (int)$_SESSION['user_id'],
        'name'          => $_SESSION['user_name'] ?? 'Resident',
        'email'         => $_SESSION['user_email'] ?? '',
        'role'          => $_SESSION['user_role'] ?? 'resident',
        'society_id'    => $_SESSION['society_id'] ?? null,
        'society_name'  => $_SESSION['society_name'] ?? 'Central Ward',
        'reputation'    => (int)($_SESSION['reputation_score'] ?? 15)
    ];
}

/**
 * Enforce login for a protected page
 */
function requireLogin(string $redirectTo = '/neighbournet/pages/login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/neighbournet/pages/dashboard.php';
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Enforce specific role access (checked server-side, not just in UI)
 */
function requireRole(array $allowedRoles, string $redirectTo = '/neighbournet/pages/dashboard.php'): void {
    requireLogin();
    // Re-check role and account state against the database on every protected request.
    // Session role values alone can become stale after an administrator changes access.
    try {
        $stmt = getDBConnection()->prepare('SELECT role, account_status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        $role = $account['role'] ?? 'resident';
        $active = ($account['account_status'] ?? '') === 'active';
        if (!$active || !in_array($role, $allowedRoles, true)) {
            $_SESSION['user_role'] = $role;
            $_SESSION['flash_error'] = !$active
                ? 'Your account is not active. Please contact the community administrator.'
                : 'Access Denied: You do not have permission to view that administrative section.';
            $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            if ($isApi) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Administrator authorization required.']);
                exit;
            }
            header("Location: $redirectTo");
            exit;
        }
        $_SESSION['user_role'] = $role;
    } catch (Throwable $e) {
        error_log('Role authorization check failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Authorization could not be verified. Please retry shortly.');
    }
}
