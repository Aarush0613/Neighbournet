<?php
/**
 * NeighbourNet - Navigation Bar
 * Section 1: Navigation bar (Home, How It Works, Community Map, About, Login, Join Community)
 */
require_once __DIR__ . '/auth_guard.php';
$currentUser = getCurrentUser();
$activePage = $activePage ?? '';
?>
<header>
    <nav class="navbar navbar-expand-lg civic-navbar sticky-top">
        <div class="container">
            <!-- Brand Logo & Name -->
            <a class="navbar-brand d-flex align-items-center gap-2 text-white text-decoration-none" href="/neighbournet/index.php">
                <span class="brand-badge shadow-sm">
                    <i class="fa-solid fa-map-location-dot"></i>
                </span>
                <div class="d-flex flex-column lh-1">
                    <span class="fw-bold fs-5 tracking-tight text-white">NeighbourNet</span>
                    <small style="font-size: 0.65rem; color: #94a3b8; letter-spacing: 0.05em; text-transform: uppercase;">
                        Report &bull; Verify &bull; Act
                    </small>
                </div>
            </a>

            <!-- Mobile Menu Toggle Button -->
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?= ($activePage === 'home') ? 'active' : '' ?>" href="/neighbournet/index.php">
                            Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="/neighbournet/index.php#how-it-works">
                            How It Works
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?= ($activePage === 'map') ? 'active' : '' ?>" href="/neighbournet/pages/map-view.php">
                            Community Map
                        </a>
                    </li>
                    <?php if ($currentUser): ?>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?= ($activePage === 'actions') ? 'active' : '' ?>" href="/neighbournet/pages/community-actions.php">Community Actions</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="/neighbournet/index.php#features">
                            Features
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="/neighbournet/index.php#about">
                            About
                        </a>
                    </li>
                </ul>

                <!-- Right Side Actions (Auth or User profile) -->
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <?php if ($currentUser): ?>
                        <a href="/neighbournet/pages/report.php" class="btn btn-sm btn-civic-primary">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Report Issue</span>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-civic-outline dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user"></i>
                                <span><?= htmlspecialchars($currentUser['name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><h6 class="dropdown-header">Signed in as <strong><?= htmlspecialchars($currentUser['email']) ?></strong></h6></li>
                                <li><a class="dropdown-item" href="/neighbournet/pages/dashboard.php"><i class="fa-solid fa-list-check me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="/neighbournet/pages/profile.php"><i class="fa-solid fa-user me-2"></i>Profile & Reputation</a></li>
                                <?php if (in_array($currentUser['role'], ['admin', 'moderator'], true)): ?>
                                    <li><a class="dropdown-item text-primary" href="/neighbournet/pages/admin.php"><i class="fa-solid fa-shield-halved me-2"></i>Moderation Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="/neighbournet/api/auth/logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><button class="dropdown-item text-danger" type="submit"><i class="fa-solid fa-right-from-bracket me-2"></i>Log Out</button></form></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/neighbournet/pages/login.php" class="btn btn-sm btn-civic-outline">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Log In
                        </a>
                        <a href="/neighbournet/pages/register.php" class="btn btn-sm btn-civic-primary">
                            Join Community
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
