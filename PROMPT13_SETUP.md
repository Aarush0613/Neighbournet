# NeighbourNet Prompt 13 — XAMPP Setup

## Fresh installation

1. Install XAMPP and start Apache and MySQL.
2. Extract the `neighbournet` folder into `C:\xampp\htdocs\`.
3. Open phpMyAdmin and import `database/neighbournet_full_schema.sql`. It creates `neighbournet_db`, the application tables, relationships, indexes, and non-personal reference data (societies and categories).
4. Check `config/db.php` and set your local MySQL connection details.
5. Enable PHP extensions `pdo_mysql`, `fileinfo`, and `mbstring` in XAMPP's `php.ini`, then restart Apache.
6. Open `http://localhost/neighbournet/` and register resident accounts through the normal signup page.
7. For the administrator account, register a separate account using a unique password, then promote it in phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-admin-email@example.com';
```

There are deliberately no shared default passwords, pre-created users, or synthetic incidents in the fresh schema. Use `[DEMO]` in the title of any incident you create solely for the hackathon demonstration.

## Existing Prompt 12 installation

1. Back up the existing `neighbournet_db`.
2. Apply `database/prompt12_database_integration_migration.sql` only if it has not already been applied.
3. Prompt 13 adds PWA/static assets and browser-local draft support; it does not require a new database migration.
4. Do not import the fresh-install SQL into an existing database.

## PWA and offline behaviour

- The manifest and service worker use the `/neighbournet/` base path for XAMPP.
- Install support requires a compatible browser and a secure context; `localhost` is treated as secure by modern browsers.
- Only public static assets are cached. HTML pages, API responses, authentication, uploads, private incident records, and admin pages are not cached.
- The offline page is a connectivity notice, not an offline copy of the application.
- The report form saves only title and description text in local browser storage. It does not save location, images, coordinates, voice metadata, credentials, or CSRF tokens. Drafts are never auto-submitted.

## External dependencies

- Voice input: browser Web Speech API/SpeechRecognition support and microphone permission.
- Translation: configured public translation provider; internet access and provider availability required.
- Maps: Leaflet/OpenStreetMap tiles require internet access.
- Bootstrap, Font Awesome, Chart.js, and Google Fonts currently load from CDNs and therefore need internet access unless locally vendored.
- Email verification requires working PHP `mail()`/SMTP configuration.

## Production checklist

Use HTTPS, a non-root MySQL user, unique administrator credentials, configured SMTP, backups, monitoring, secure error logging, and a documented privacy/retention policy. Apache must honor the included `.htaccess` upload protections.
