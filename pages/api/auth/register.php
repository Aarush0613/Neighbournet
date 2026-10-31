<?php
/**
 * NeighbourNet - User Registration Endpoint
 * Secure server-side validation, duplicate prevention, and bcrypt hashing
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
    header('Location: /neighbournet/pages/register.php');
    exit;
}

// Read input (Supports JSON payload or form POST)
$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);

$csrfToken       = $_POST['csrf_token'] ?? $jsonInput['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$fullName        = trim($_POST['full_name'] ?? $jsonInput['full_name'] ?? '');
$email           = trim($_POST['email'] ?? $jsonInput['email'] ?? '');
$password        = $_POST['password'] ?? $jsonInput['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? $jsonInput['confirm_password'] ?? '';
$societyId       = !empty($_POST['society_id'] ?? $jsonInput['society_id'] ?? '') ? (int)($_POST['society_id'] ?? $jsonInput['society_id']) : null;
$termsAgreed     = !empty($_POST['terms'] ?? $jsonInput['terms'] ?? false);

// 1. CSRF Validation
if (!validateCsrfToken($csrfToken)) {
    $errorMsg = 'Invalid security token (CSRF). Please refresh and submit again.';
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/register.php');
    exit;
}

// 2. Server-Side Input Validation
$errors = [];

if (strlen($fullName) < 3 || strlen($fullName) > 100) {
    $errors[] = 'Full name must be between 3 and 100 characters.';
}

if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}

if (strlen($password) < 8 || strlen($password) > 72) {
    $errors[] = 'Password must be between 8 and 72 bytes long.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Password and password confirmation do not match.';
}

if (!$termsAgreed) {
    $errors[] = 'You must agree to the community standards and terms.';
}

if (!empty($errors)) {
    $errorMsg = implode(' ', $errors);
    if ($isAjax) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg, 'errors' => $errors]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    $_SESSION['old_input'] = ['full_name' => $fullName, 'email' => $email, 'society_id' => $societyId];
    header('Location: /neighbournet/pages/register.php');
    exit;
}

try {
    $pdo = getDBConnection();

    if ($societyId !== null) { $societyCheck=$pdo->prepare('SELECT id FROM societies WHERE id=?'); $societyCheck->execute([$societyId]); if (!$societyCheck->fetchColumn()) { http_response_code(422); if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Select a valid neighbourhood.']); exit; } $_SESSION['flash_error']='Select a valid neighbourhood.'; header('Location: /neighbournet/pages/register.php'); exit; } }

    // 3. Duplicate Email Check with Prepared Statement
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errorMsg = 'An account with this email address is already registered.';
        if ($isAjax) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        $_SESSION['flash_error'] = $errorMsg;
        $_SESSION['old_input'] = ['full_name' => $fullName, 'society_id' => $societyId];
        header('Location: /neighbournet/pages/register.php');
        exit;
    }

    // 4. Secure Password Hashing (Never store plain-text)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

    // 5. Insert New User Record
    $insertStmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password_hash, role, society_id, account_status, reputation_score)
        VALUES (?, ?, ?, 'resident', ?, 'active', 15)
    ");
    $insertStmt->execute([$fullName, $email, $passwordHash, $societyId]);

    $newUserId = (int)$pdo->lastInsertId();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'message'  => 'Registration successful! You can now log in.',
            'redirect' => '/neighbournet/pages/login.php'
        ]);
        exit;
    }

    $_SESSION['flash_success'] = 'Registration successful! Welcome to NeighbourNet. Please log in.';
    header('Location: /neighbournet/pages/login.php');
    exit;

} catch (PDOException $e) {
    error_log('Registration Error: ' . $e->getMessage());
    $errorMsg = 'A system error occurred during registration. Please try again.';
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    $_SESSION['flash_error'] = $errorMsg;
    header('Location: /neighbournet/pages/register.php');
    exit;
}
