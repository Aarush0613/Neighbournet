# NeighbourNet 🏘️
> **Report It. Verify It. Act Together.**  
> Hyperlocal community safety and neighbourhood issue-management platform built for university hackathon demonstration.

---

## 🚀 Quick Setup Instructions

### 1. Requirements
- **XAMPP** (with Apache and MySQL / MariaDB)
- **VS Code**

### 2. Start Services
1. Open **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### 3. Import Database
1. Open your browser and go to `http://localhost/phpmyadmin/`.
2. Click **Import** tab.
3. Choose file: `C:\xampp\htdocs\neighbournet\database\neighbournet.sql`.
4. Click **Go** / **Import** (creates `neighbournet_db` with seed data).

*Alternatively via Command Line:*
```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\neighbournet\database\neighbournet.sql
```

### 4. Open Application
Navigate in your browser to:
👉 **`http://localhost/neighbournet/`**

---

## 🔑 Demo Accounts (Ready to Test)

| Role | Email | Password | Permissions |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@neighbournet.org` | `admin123` | Full access, status overrides, user moderation |
| **Moderator** | `mod@neighbournet.org` | `mod123` | Issue verification, status progression, updates |
| **Resident** | `resident@neighbournet.org` | `resident123` | Report issues with GPS pin, verify, comment |

*(Or register a new resident account directly from the signup page!)*

---

## 📁 Project Architecture

```text
neighbournet/
├── assets/
│   ├── css/
│   │   └── style.css            # Custom theme & responsive styling
│   ├── js/
│   │   ├── app.js               # Global application API wrapper
│   │   ├── map.js               # Leaflet.js interactive map logic
│   │   └── charts.js            # Chart.js analytics logic
│   └── images/
├── config/
│   └── db.php                   # Reusable PDO connection with prepared statements
├── includes/
│   ├── header.php               # HTML Head, Bootstrap 5, Leaflet, FontAwesome
│   ├── navbar.php               # Responsive navigation & role-based dropdowns
│   ├── footer.php               # Footer & script links
│   └── auth_guard.php           # Session authorization & role enforcement
├── api/
│   ├── auth/                    # JSON authentication endpoints
│   ├── incidents/               # CRUD, Geolocation & Voting endpoints
│   ├── community/               # Comments & coordination endpoints
│   ├── profile/                 # Profile and reputation endpoints
│   └── admin/                   # Moderation & management endpoints
├── database/
│   └── neighbournet.sql         # Database schema, foreign keys & mock data
├── pages/                       # User-facing application views
│   ├── dashboard.php            # Incident feed & filters
│   ├── map-view.php             # Fullscreen interactive Leaflet map
│   ├── report.php               # Incident submission with pin picker
│   ├── incident-details.php     # Issue lifecycle, votes & discussion
│   ├── analytics.php            # Chart.js metrics & trends
│   ├── profile.php              # User profile & reputation score
│   ├── login.php                # Authentication page
│   ├── register.php             # Resident registration
│   └── admin.php                # Moderator & Admin control center
├── uploads/
│   └── incidents/               # Image attachments
├── index.php                    # Hero landing page
├── manifest.json                # PWA manifest
└── service-worker.js            # Offline shell caching
```

---

## 🛠️ Technology Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript, Bootstrap 5.3, Font Awesome 6.5, Leaflet.js, Chart.js.
- **Backend:** Core PHP (PHP 8.2), PHP Sessions, RESTful JSON Endpoints.
- **Database:** MySQL / MariaDB via PHP PDO with parameterized prepared statements.
- **PWA:** Service Worker caching & Web App Manifest.

## Prompt 8 — Automatic Incident Clustering

The project now includes a non-destructive, explainable incident similarity engine.

### Database setup
- For a fresh installation, import `database/neighbournet.sql` as usual.
- For an existing installation, back up your database first, then import `database/prompt8_clustering_migration.sql` into the existing `neighbournet_db` database. This migration creates the three clustering tables without dropping existing incidents.

### Configurable thresholds
Edit `config/clustering.php`, or set these environment variables:
- `CLUSTER_MAX_DISTANCE_METERS` (default 650)
- `CLUSTER_MAX_TIME_HOURS` (default 72)
- `CLUSTER_MIN_SCORE` (default 65)

Category compatibility pairs can be configured in `compatible_categories`, using category ID pairs such as `[[1, 2]]` only when those categories can describe the same underlying event.

### Admin review
Administrators can review suggested clusters from the Administration page, approve or reject them, add incident IDs, and separate incorrectly grouped reports. Administrative decisions are written to `cluster_audit_logs`. Original incident records and reporters are never merged or deleted by clustering.

### Tests
From the `neighbournet` project directory, run:

```bash
php tests/clustering_test.php
```

The tests cover same-block reports, distant reports, category mismatch, time-window mismatch, unrelated descriptions, and availability of the admin separation action.

## Prompt 9 — User Profile & Personalized Settings

1. Import `database/prompt9_profile_settings_migration.sql` in phpMyAdmin after the existing NeighbourNet schema and Prompt 8 migration. Run this migration only once.
2. Sign in and open **Profile & Settings** from the user menu (`pages/profile.php`).
3. Profile images are stored under `uploads/profiles/` (JPG/PNG/WebP, maximum 2 MB).
4. Email changes are pending until the new address is verified. Configure PHP `mail()`/SMTP on the local or production server for verification messages to be delivered.
5. Language, appearance mode and colour theme are saved per user in `user_preferences`. The selected language updates the document language metadata; full translation of every static page string is not yet implemented.
6. Neighbourhood changes are queued for administrator approval in the Admin Console.
7. Account closure anonymizes the user record and disables login while retaining incident/report history and its foreign-key relationships.

The profile page uses CSRF-protected POST actions for profile edits, preferences, password changes, neighbourhood requests and account closure.


## Prompt 10 — Community Action Board

1. Import `database/prompt10_community_action_board_migration.sql` into the existing `neighbournet_db` database after the Prompt 8 and Prompt 9 migrations. Do not re-import the base schema over an existing database.
2. Sign in and open `http://localhost/neighbournet/pages/community-actions.php`.
3. Residents can browse tasks linked to their own neighbourhood/incidents, volunteer once per task, and withdraw. The incident reporter, same-neighbourhood residents, or administrators can create a task. Only its creator or an administrator can post progress updates/change task status.
4. Task creators and administrators can post updates; active volunteers receive in-app notifications.
5. Immediate danger must be referred to official emergency services. The board is for safe voluntary coordination only. The API rejects several explicitly unsafe task instructions; this is a guardrail, not a substitute for moderation.

The Prompt 10 API uses the existing session authentication and CSRF token helper.

Database-backed verification steps for the Community Action Board are documented in `tests/PROMPT10_TEST_PLAN.md`.


## Prompt 12 — Database integration and verification

### Fresh installation (recommended for a clean demo)
1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin and import `database/neighbournet_full_schema.sql` into a fresh environment. The file creates `neighbournet_db`, tables, keys, seed categories, demo records, and the Prompt 8–12 feature schema.
3. **Important:** `neighbournet_full_schema.sql` is for a NEW empty database only. The legacy base SQL contains DROP statements and must not be imported over an existing live database.
4. Set database credentials in `config/db.php` or environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
5. Place the project folder at `C:/xampp/htdocs/neighbournet`, then open `http://localhost/neighbournet/`.
6. Ensure PHP extensions `pdo_mysql`, `fileinfo`, and `mbstring` are enabled. Speech recognition requires a supported browser and microphone permission. HTTPS or localhost is required by many browsers for microphone APIs.

### Existing installation upgrade
Back up `neighbournet_db`, then import only `database/prompt12_database_integration_migration.sql` after Prompt 11. Do not import the full fresh schema into an existing database. Existing Prompt 8–11 migration order remains unchanged.

### Prompt 12 integration notes
- Added canonical `incident_history` with a one-time legacy backfill from `incident_updates`. Existing pages continue to read `incident_updates` for compatibility; both status update endpoints now write to both records in the same transaction.
- Added upload MIME/content validation, size checks, location/coordinate bounds, voice metadata allowlists, and cleanup of uploaded files when database save fails.
- GET-only incident APIs reject other methods and return generic errors; internal database exception details are written to the server log rather than returned to clients.
- Logout now requires POST plus a CSRF token; navigation and profile controls use forms.
- Database connection responses no longer return raw connection exception text to API clients.

### Verification boundaries
The source was linted and JavaScript syntax-checked in the build environment. A live MySQL database, browser session, microphone, and remote translation connectivity were not available in that environment, so registration/reporting/voting/task/profile/admin browser workflows and live SQL import were not claimed as passed. Use `tests/PROMPT12_TEST_REPORT.md` as the local acceptance checklist and record each result after running it in XAMPP.

### External services and browser APIs
- Voice-to-text uses the browser SpeechRecognition/Web Speech API; availability, language packs, and network-backed recognition vary by browser/device. No API key is configured by this project for speech recognition.
- Optional translation uses the public MyMemory endpoint (no project API key configured by default), with a small local civic phrase fallback. The public endpoint may rate-limit or be unreachable; translation errors are surfaced rather than reported as success.
- Maps use externally hosted OpenStreetMap tiles and Leaflet assets; internet access is required for map tiles unless self-hosted.


## Prompt 13 — PWA, offline support, and demo readiness

- Installable PWA manifest with generated 192px/512px icons and browser install prompt support.
- Service worker caches only the public static shell; navigation falls back to a generic offline page. Authentication, API, private HTML, uploads, and incident records are never cached.
- Report drafts store only title and description in localStorage; no location, photo, account, transcript metadata, or CSRF values are saved. Offline submission is blocked and never shown as successful.
- Map markers are reconciled by incident ID rather than clearing/recreating every marker on refresh.
- Fresh schema no longer seeds shared demo accounts or unlabelled synthetic incidents. Create accounts through registration and label demonstration reports `[DEMO]`.
- See `PROMPT13_SETUP.md` and `PROMPT13_DEMO_GUIDE.md` for setup and the hackathon walkthrough.
