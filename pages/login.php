<?php
$pageTitle = "Sign In — NeighbourNet";
$activePage = 'login';
require_once __DIR__ . '/../includes/auth_guard.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$csrfToken = getCsrfToken();
$oldEmail = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 p-md-5">
                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </div>
                    <h2 class="fw-bold mb-1">Sign In to NeighbourNet</h2>
                    <p class="text-secondary small mb-0">Access your local community dashboard and reports</p>
                </div>

                <!-- Login Form -->
                <form id="loginForm" action="/neighbournet/api/auth/login.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" 
                                   placeholder="resident@neighbournet.org" required autofocus
                                   value="<?= e($oldEmail) ?>">
                        </div>
                    </div>

                    <!-- Password with Visibility Toggle -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button with Loading State -->
                    <button type="submit" id="btnLoginSubmit" class="btn btn-civic-primary w-100 py-2 mb-3">
                        <span id="btnLoginText"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Sign In</span>
                        <span id="btnLoginSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </form>

                <!-- Hackathon Demo One-Click Account Helper -->
                <div class="p-3 bg-light rounded-2 border mt-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold text-navy"><i class="fa-solid fa-key text-warning me-1"></i> Demo Evaluation Accounts</span>
                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">Quick Fill</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary flex-fill" onclick="fillCredentials('resident@neighbournet.org', 'resident123')">
                            <i class="fa-solid fa-user me-1"></i> Resident
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger flex-fill" onclick="fillCredentials('admin@neighbournet.org', 'admin123')">
                            <i class="fa-solid fa-shield-halved me-1"></i> Admin
                        </button>
                    </div>
                </div>

                <!-- Footer Link -->
                <div class="text-center mt-4 pt-3 border-top small text-secondary">
                    Don't have an account yet? <a href="/neighbournet/pages/register.php" class="text-primary fw-bold">Sign Up here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCredentials(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}

document.addEventListener('DOMContentLoaded', () => {
    // Password visibility toggle
    const toggleBtn = document.getElementById('togglePassword');
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    toggleBtn.addEventListener('click', () => {
        const isPassword = pwdInput.type === 'password';
        pwdInput.type = isPassword ? 'text' : 'password';
        eyeIcon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });

    // Form submission loading state
    const form = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnLoginSubmit');
    const btnText = document.getElementById('btnLoginText');
    const btnSpinner = document.getElementById('btnLoginSpinner');

    form.addEventListener('submit', () => {
        btnSubmit.disabled = true;
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
