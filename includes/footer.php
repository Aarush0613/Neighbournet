<footer class="footer-civic mt-auto">
    <div class="container">
        <div class="row g-4 mb-4">
            <!-- Col 1: Brand & Tagline -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="brand-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </span>
                    <span class="fw-bold text-white fs-5">NeighbourNet</span>
                </div>
                <p class="text-secondary small mb-3">
                    A hyperlocal community safety and neighbourhood issue-management platform.
                    <br><strong>Report It. Verify It. Act Together.</strong>
                </p>
                <div class="small text-muted">
                    <i class="fa-solid fa-graduation-cap me-1 text-primary"></i> University Hackathon Demonstration Project
                </div>
            </div>

            <!-- Col 2: Navigation Links -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.05em;">Platform</h6>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><a href="/neighbournet/index.php" class="footer-link">Home</a></li>
                    <li><a href="/neighbournet/index.php#how-it-works" class="footer-link">How It Works</a></li>
                    <li><a href="/neighbournet/pages/map-view.php" class="footer-link">Community Map</a></li>
                    <li><a href="/neighbournet/pages/report.php" class="footer-link">Report an Issue</a></li>
                    <li><a href="/neighbournet/pages/dashboard.php" class="footer-link">Incident Feed</a></li>
                </ul>
            </div>

            <!-- Col 3: Resources -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.05em;">Resources</h6>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><a href="/neighbournet/index.php#features" class="footer-link">Features</a></li>
                    <li><a href="/neighbournet/index.php#impact" class="footer-link">Civic Impact</a></li>
                    <li><a href="/neighbournet/pages/dashboard.php" class="footer-link">Community Updates</a></li>
                    <li><a href="/neighbournet/index.php#about" class="footer-link">About Project</a></li>
                </ul>
            </div>

            <!-- Col 4: Technical Stack & Civic Transparency -->
            <div class="col-lg-4 col-md-12">
                <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.05em;">Open Technology Stack</h6>
                <p class="small text-secondary mb-2">
                    Built with Core PHP 8, MySQL/PDO prepared statements, Leaflet.js, OpenStreetMap, Chart.js, and Vanilla JavaScript.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-dark border border-secondary text-light">Core PHP</span>
                    <span class="badge bg-dark border border-secondary text-light">MySQL PDO</span>
                    <span class="badge bg-dark border border-secondary text-light">Leaflet.js</span>
                    <span class="badge bg-dark border border-secondary text-light">Bootstrap 5</span>
                    <span class="badge bg-dark border border-secondary text-light">PWA Ready</span>
                </div>
            </div>
        </div>

        <div class="border-top border-secondary pt-3 mt-3 d-flex flex-column flex-md-row justify-content-between align-items-center small text-secondary">
            <div>
                &copy; <?= date('Y') ?> NeighbourNet Platform. Developed for civic collaboration.
            </div>
            <div class="mt-2 mt-md-0">
                <span class="text-white-50">Local Server Deployment: XAMPP / Apache</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Custom App JS -->
<script src="/neighbournet/assets/js/app.js"></script>

<!-- Register Service Worker -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/neighbournet/service-worker.js')
                .then(reg => console.info('NeighbourNet offline shell ready:', reg.scope))
                .catch(err => console.warn('Offline support unavailable:', err));
        });
    }
</script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>
</body>
</html>
