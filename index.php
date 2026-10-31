<?php
$pageTitle = "NeighbourNet — Hyperlocal Community Safety & Issue Management";
$activePage = 'home';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <!-- ========================================================
         2. HERO SECTION
         ======================================================== -->
    <section class="hero-civic">
        <div class="container">
            <div class="row align-items-center g-5">
                <!-- Hero Left: Mission & Action -->
                <div class="col-lg-6">
                    <div class="hero-pill">
                        <i class="fa-solid fa-bullhorn text-warning"></i>
                        <span>Civic Intelligence &bull; Hyperlocal Response</span>
                    </div>
                    <h1 class="fw-extrabold text-white mb-3">
                        Report It. Verify It. <br>
                        <span style="color: #93c5fd;">Act Together.</span>
                    </h1>
                    <p class="lead text-slate-300 mb-4" style="color: #cbd5e1; font-size: 1.1rem; line-height: 1.6;">
                        NeighbourNet connects residents to report local hazards, verify issue accuracy through crowdsourced consensus, and coordinate transparent community solutions.
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="/neighbournet/pages/report.php" class="btn-civic-primary">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Report an Issue</span>
                        </a>
                        <a href="/neighbournet/pages/map-view.php" class="btn-civic-secondary">
                            <i class="fa-solid fa-map-location-dot text-primary"></i>
                            <span>Explore Community Map</span>
                        </a>
                    </div>

                    <!-- Credibility Indicators -->
                    <div class="d-flex align-items-center gap-4 pt-2 text-slate-400 small" style="color: #94a3b8;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-shield-check text-success"></i>
                            <span>Crowdsourced Verification</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-lock text-info"></i>
                            <span>Zero False Alarms</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-location-dot text-danger"></i>
                            <span>Exact GPS Accuracy</span>
                        </div>
                    </div>
                </div>

                <!-- Hero Right: Authentic Map & Incident Preview Visual -->
                <div class="col-lg-6">
                    <div class="hero-map-card">
                        <div class="hero-map-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small px-2 py-1">
                                    <i class="fa-solid fa-circle-dot me-1"></i> Live Radius: 2.5 km
                                </span>
                                <span class="text-secondary small fw-medium">Central Ward Sector 4</span>
                            </div>
                            <span class="badge bg-light text-secondary border small">Simulated GPS</span>
                        </div>

                        <!-- Simulated Map Grid with Real Coordinates & Pins -->
                        <div class="hero-map-canvas">
                            <div class="map-road-1"></div>
                            <div class="map-road-2"></div>
                            <div class="map-park-area">
                                <span><i class="fa-solid fa-tree me-1"></i>City Park</span>
                            </div>

                            <!-- Map Pin 1: Open Manhole -->
                            <div class="map-pin-marker pin-danger" style="top: 38%; left: 36%;" title="Open Manhole - Verified">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Critical Hazard</span>
                            </div>

                            <!-- Map Pin 2: Broken Streetlight -->
                            <div class="map-pin-marker pin-warning" style="top: 68%; left: 18%;" title="Broken Streetlight">
                                <i class="fa-solid fa-lightbulb"></i>
                                <span>Dark Stretch</span>
                            </div>

                            <!-- Map Pin 3: Clean Water Leakage -->
                            <div class="map-pin-marker pin-info" style="top: 22%; left: 62%;" title="Water Leakage">
                                <i class="fa-solid fa-faucet-drip"></i>
                                <span>Water Main</span>
                            </div>

                            <!-- Map Pin 4: Resolved Sanitation -->
                            <div class="map-pin-marker pin-success" style="top: 72%; left: 68%;" title="Waste Cleared">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Resolved</span>
                            </div>
                        </div>

                        <!-- Interactive Footer of the Preview Card -->
                        <div class="hero-map-footer">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-users text-primary"></i>
                                <span class="text-secondary"><strong>18 Residents</strong> nearby participating</span>
                            </div>
                            <a href="/neighbournet/pages/map-view.php" class="text-primary fw-semibold small">
                                Expand Map <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         3. HOW IT WORKS SECTION
         ======================================================== -->
    <section id="how-it-works" class="section-pad bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-tag">Methodology</span>
                <h2>How NeighbourNet Works</h2>
                <p class="section-desc">
                    A structured civic workflow ensuring local issues are documented with precision, validated by real neighbours, and acted upon collaboratively.
                </p>
            </div>

            <div class="row g-4">
                <!-- Step 1 -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-num-badge">1</div>
                        <h4 class="fw-bold mb-2">Report an Issue</h4>
                        <p class="small text-secondary mb-0">
                            Drop a GPS pin on the exact location, select an issue category, describe the problem, and attach a clear photo.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-num-badge">2</div>
                        <h4 class="fw-bold mb-2">Community Verifies It</h4>
                        <p class="small text-secondary mb-0">
                            Nearby residents vote on accuracy or flag duplicates. Verified reports gain priority weighting and trust scores.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-num-badge">3</div>
                        <h4 class="fw-bold mb-2">Track Its Progress</h4>
                        <p class="small text-secondary mb-0">
                            Follow the issue lifecycle in real time: Reported &rarr; Community Verified &rarr; In Progress &rarr; Resolved.
                        </p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-num-badge">4</div>
                        <h4 class="fw-bold mb-2">Work Together</h4>
                        <p class="small text-secondary mb-0">
                            Coordinate community-led fixes, share status updates, or submit structured evidence packets to local municipal bodies.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         4. PLATFORM FEATURES
         ======================================================== -->
    <section id="features" class="section-pad bg-light border-top border-bottom">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-tag">Capabilities</span>
                <h2>Designed for Civic Resilience</h2>
                <p class="section-desc">
                    Practical tools built for real neighbourhoods, volunteer community groups, and civic moderators.
                </p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-map-location-dot text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Interactive Incident Map</h4>
                        <p class="small text-secondary mb-0">
                            Real-time geospatial visualization powered by OpenStreetMap and Leaflet.js with category filters and radius controls.
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-users-viewfinder text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Community Verification</h4>
                        <p class="small text-secondary mb-0">
                            Crowdsourced accuracy voting prevents spam, weeds out obsolete claims, and builds community trust scores.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-object-group text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Incident Clustering</h4>
                        <p class="small text-secondary mb-0">
                            Intelligent grouping connects related hazards within close proximity, avoiding fragmented reports on the same pothole or leak.
                        </p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-microphone-lines text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Voice-Based Reporting</h4>
                        <p class="small text-secondary mb-0">
                            Fast audio input mode for residents walking by hazards, enabling effortless transcription and hands-free reporting.
                        </p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-handshake-angle text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Community Action Board</h4>
                        <p class="small text-secondary mb-0">
                            A dedicated space for residents to organize local volunteer clean-ups, safety walks, and coordinate with ward councilors.
                        </p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-clipboard-check text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Transparent Resolution Tracking</h4>
                        <p class="small text-secondary mb-0">
                            Every status transition is logged with notes and timestamps, ensuring accountability from first report to final fix.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         5. LIVE COMMUNITY ACTIVITY PREVIEW (CLEARLY LABELLED DEMO)
         ======================================================== -->
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div>
                    <span class="section-tag">Live Feed Preview</span>
                    <h2 class="mb-1">Recent Neighbourhood Activity</h2>
                    <p class="text-secondary small mb-0">Sample reports demonstrating the verified issue lifecycle.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="/neighbournet/pages/dashboard.php" class="btn btn-sm btn-civic-secondary">
                        <i class="fa-solid fa-list me-1"></i> View Full Feed
                    </a>
                </div>
            </div>

            <!-- Mandatory Demonstration Disclaimer -->
            <div class="demo-banner d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-info text-primary fs-5"></i>
                <div>
                    <strong>Demonstration Data:</strong> The incidents below are simulated demonstration records prepared for university hackathon evaluation.
                </div>
            </div>

            <!-- Demo Cards Grid -->
            <div class="row g-4">
                <!-- Card 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="incident-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="category-pill">
                                <i class="fa-solid fa-triangle-exclamation text-danger"></i> Public Safety
                            </span>
                            <span class="status-pill status-verified">
                                <i class="fa-solid fa-check-double"></i> Verified
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">
                            <a href="/neighbournet/pages/incident-details.php?id=1" class="text-dark">
                                Deep Uncovered Manhole Near Metro Gate 2
                            </a>
                        </h5>
                        <p class="small text-secondary flex-grow-1 mb-3">
                            High-risk open drainage pit without warning cones. Poses immediate hazard to pedestrians and two-wheelers at night.
                        </p>
                        <div class="small text-muted mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-location-dot text-danger"></i>
                            <span>Metro Pillar 142, Gate 2, Central Road</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top small">
                            <span class="text-success fw-bold">
                                <i class="fa-solid fa-thumbs-up me-1"></i> 6 Verifications
                            </span>
                            <span class="text-muted">Reported 2h ago</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="incident-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="category-pill">
                                <i class="fa-solid fa-lightbulb text-warning"></i> Street Lighting
                            </span>
                            <span class="status-pill status-in_progress">
                                <i class="fa-solid fa-screwdriver-wrench"></i> In Progress
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">
                            <a href="/neighbournet/pages/incident-details.php?id=2" class="text-dark">
                                Dark Stretch on 4th Main Cross Road
                            </a>
                        </h5>
                        <p class="small text-secondary flex-grow-1 mb-3">
                            Series of 6 consecutive light poles non-functional since Monday. Ward contractor notified and bulb replacement scheduled.
                        </p>
                        <div class="small text-muted mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-location-dot text-danger"></i>
                            <span>Opposite Green Valley Community Park</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top small">
                            <span class="text-success fw-bold">
                                <i class="fa-solid fa-thumbs-up me-1"></i> 4 Verifications
                            </span>
                            <span class="text-muted">Reported 1d ago</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="incident-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="category-pill">
                                <i class="fa-solid fa-trash-can text-success"></i> Sanitation
                            </span>
                            <span class="status-pill status-resolved">
                                <i class="fa-solid fa-circle-check"></i> Resolved
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">
                            <a href="/neighbournet/pages/incident-details.php?id=4" class="text-dark">
                                Waste Clearance at Community Clinic Gate
                            </a>
                        </h5>
                        <p class="small text-secondary flex-grow-1 mb-3">
                            Overflowing dumpster cleared by sanitation team following community alert. Area disinfected and bin replaced.
                        </p>
                        <div class="small text-muted mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-location-dot text-danger"></i>
                            <span>Near Community Health Centre Gate</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top small">
                            <span class="text-success fw-bold">
                                <i class="fa-solid fa-check me-1"></i> 8 Confirmations
                            </span>
                            <span class="text-muted">Resolved today</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         6. CIVIC IMPACT SECTION
         ======================================================== -->
    <section id="impact" class="section-pad bg-light border-top border-bottom">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="section-tag">Measurable Value</span>
                    <h2 class="mb-3">Why Structured Community Reporting Works</h2>
                    <p class="text-secondary mb-4">
                        Conventional social media complaint groups create noise without accountability. NeighbourNet transforms individual grievances into structured, actionable civic datasets.
                    </p>

                    <div class="impact-item">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-filter-circle-dollar text-primary me-2"></i>Eliminating Emergency Hotline Overload</h5>
                        <p class="small text-secondary mb-0">
                            Non-critical infrastructure issues are triaged locally so emergency dispatch channels remain open for life-threatening situations.
                        </p>
                    </div>

                    <div class="impact-item">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-file-shield text-primary me-2"></i>Verified Evidence for Ward Authorities</h5>
                        <p class="small text-secondary mb-0">
                            Instead of vague complaints, civic engineers receive exact GPS coordinates, timestamped photographs, and verified community impact scores.
                        </p>
                    </div>

                    <div class="impact-item">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-network-wired text-primary me-2"></i>Hyperlocal Street Awareness</h5>
                        <p class="small text-secondary mb-0">
                            Residents consult the live community map before commuting, avoiding flooded underpasses, dark streets, or hazardous roadwork.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 rounded-3 bg-white">
                        <h5 class="fw-bold mb-3 text-navy">The Community Verification Loop</h5>
                        <div class="p-3 bg-light rounded-2 mb-3 border">
                            <div class="d-flex justify-content-between small fw-bold text-secondary mb-1">
                                <span>Unverified Report</span>
                                <span class="text-warning">Trust Weight: 25%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: 25%"></div>
                            </div>
                            <small class="text-muted d-block mt-2">Single reporter logs issue. Pending nearby resident corroboration.</small>
                        </div>

                        <div class="p-3 bg-light rounded-2 mb-3 border">
                            <div class="d-flex justify-content-between small fw-bold text-secondary mb-1">
                                <span>3+ Neighbor Confirmations</span>
                                <span class="text-primary">Trust Weight: 75%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 75%"></div>
                            </div>
                            <small class="text-muted d-block mt-2">Corroborated by independent residents. Marked as High Priority.</small>
                        </div>

                        <div class="p-3 bg-light rounded-2 border">
                            <div class="d-flex justify-content-between small fw-bold text-secondary mb-1">
                                <span>Action & Verified Resolution</span>
                                <span class="text-success">Trust Weight: 100%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                            </div>
                            <small class="text-muted d-block mt-2">Fix verified on ground. Closing loop and awarding contributor reputation.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         7. CALL TO ACTION SECTION
         ======================================================== -->
    <section class="cta-civic text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="text-white fw-bold mb-3">Ready to Make Your Neighbourhood Safer?</h2>
                    <p class="lead text-slate-300 mb-4" style="color: #cbd5e1;">
                        Join fellow residents in building safer streets, reliable public infrastructure, and transparent civic action.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="/neighbournet/pages/register.php" class="btn-civic-primary">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>Join Your Community</span>
                        </a>
                        <a href="/neighbournet/pages/report.php" class="btn-civic-outline">
                            <i class="fa-solid fa-flag"></i>
                            <span>Report an Issue Now</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         ABOUT PROJECT MODAL / ANCHOR SECTION
         ======================================================== -->
    <section id="about" class="py-4 bg-white border-top">
        <div class="container text-center small text-secondary">
            <strong>About NeighbourNet:</strong> A practical civic technology demonstration created for university hackathons. Engineered with native web standards to prove that lightweight, high-performance community tools can operate reliably without heavy framework bloat.
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
