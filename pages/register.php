<?php
$pageTitle = "Join NeighbourNet — Resident Registration";
$activePage = 'register';
require_once __DIR__ . '/../includes/auth_guard.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /neighbournet/pages/dashboard.php');
    exit;
}

$csrfToken = getCsrfToken();
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);

// Fetch societies list for dropdown
$societies = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, name, ward_sector FROM societies ORDER BY name ASC");
    $societies = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful fallback
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 p-md-5">
                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h2 class="fw-bold mb-1">Create Resident Account</h2>
                    <p class="text-secondary small mb-0">Join your local neighborhood safety network on NeighbourNet</p>
                </div>

                <!-- Registration Form -->
                <form id="registerForm" action="/neighbournet/api/auth/register.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-user"></i></span>
                            <input type="text" class="form-control border-start-0" id="full_name" name="full_name" 
                                   placeholder="e.g. Aarav Patel" required minlength="3" maxlength="100"
                                   value="<?= e($oldInput['full_name'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" 
                                   placeholder="resident@example.com" required
                                   value="<?= e($oldInput['email'] ?? '') ?>">
                        </div>
                        <div class="form-text" style="font-size: 0.75rem;">We will never share your email publicly.</div>
                    </div>

                    <!-- Society / Neighborhood -->
                    <div class="mb-3">
                        <label for="society_id" class="form-label small fw-semibold text-secondary">Neighbourhood / Society (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-building"></i></span>
                            <select class="form-select border-start-0" id="society_id" name="society_id">
                                <option value="">Select your local society / sector...</option>
                                <?php foreach ($societies as $soc): ?>
                                    <option value="<?= (int)$soc['id'] ?>" <?= ((int)($oldInput['society_id'] ?? 0) === (int)$soc['id']) ? 'selected' : '' ?>>
                                        <?= e($soc['name']) ?> (<?= e($soc['ward_sector']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Password with Visibility Toggle -->
                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold text-secondary">Password * (Min. 8 characters)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" 
                                   placeholder="Create a strong password" required minlength="8">
                            <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock-open"></i></span>
                            <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" 
                                   placeholder="Re-enter your password" required minlength="8">
                        </div>
                    </div>

                    <!-- Terms Agreement -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label small text-secondary" for="terms">
                            I agree to the <a href="#termsModal" data-bs-toggle="modal" class="text-primary fw-semibold">Community Standards & Reporting Terms</a>. I understand that false or misleading reports harm community trust.
                        </label>
                    </div>

                    <!-- Submit Button with Loading State -->
                    <button type="submit" id="btnSubmit" class="btn btn-civic-primary w-100 py-2">
                        <span id="btnText"><i class="fa-solid fa-user-check me-1"></i> Create Account</span>
                        <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </form>

                <!-- Footer Link -->
                <div class="text-center mt-4 pt-3 border-top small text-secondary">
                    Already have an account? <a href="/neighbournet/pages/login.php" class="text-primary fw-bold">Sign In here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Basic Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">NeighbourNet Community Standards</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small text-secondary">
                <p>By registering on NeighbourNet, you commit to:</p>
                <ul>
                    <li>Reporting genuine local incidents, hazards, and civic maintenance needs.</li>
                    <li>Not uploading abusive, defamatory, or promotional media.</li>
                    <li>Fairly verifying nearby issues to maintain neighbourhood data integrity.</li>
                    <li>Treating fellow residents and civic coordinators with mutual respect.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<script>
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

    // Form submission validation & loading state
    const form = document.getElementById('registerForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', (e) => {
        const pwd = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        
        if (pwd !== confirm) {
            e.preventDefault();
            alert('Passwords do not match. Please verify your password entry.');
            document.getElementById('confirm_password').focus();
            return false;
        }

        btnSubmit.disabled = true;
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
