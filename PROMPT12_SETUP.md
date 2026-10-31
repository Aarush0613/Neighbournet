# NeighbourNet Prompt 12 — Setup

## Fresh XAMPP installation

1. Install/start XAMPP and start **Apache** and **MySQL**.
2. Copy the `neighbournet` folder into `C:\xampp\htdocs\`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
4. Import `database/neighbournet_full_schema.sql`. It creates `neighbournet_db`, all application tables, foreign keys, indexes and demo data.
5. Check `config/db.php`. Defaults are host `127.0.0.1`, port `3306`, database `neighbournet_db`, user `root`, blank password. Prefer environment variables for deployment.
6. In XAMPP's PHP configuration, enable `pdo_mysql`, `fileinfo`, and `mbstring`, then restart Apache.
7. Open `http://localhost/neighbournet/`.

### Demo accounts

- Admin: `admin@neighbournet.org` / `admin123`
- Resident: `resident@neighbournet.org` / `resident123`

These are demo-only credentials. Remove demo accounts and quick-fill credential buttons before production deployment; create a new administrator with a unique strong password.

## Existing Prompt 11 installation

1. Back up the existing `neighbournet_db` database.
2. Import only `database/prompt12_database_integration_migration.sql`.
3. Do not import `neighbournet_full_schema.sql` into an existing installation. The legacy base schema includes DROP statements and the full schema is deliberately for a fresh empty database.

## External dependencies

- Speech-to-text uses browser Web Speech/SpeechRecognition support and microphone permission; no speech API key is bundled.
- Translation calls the public MyMemory endpoint; no API key is required by the default provider. It can be rate-limited or unavailable. The app surfaces translation failure.
- Email change verification uses PHP `mail()`. Configure SMTP/PHP mail in XAMPP or the deployment host; no SMTP credentials are bundled.
- Map display depends on Leaflet and OpenStreetMap tile access; internet access is required unless self-hosted.

## Security before deployment

Use HTTPS, non-root MySQL credentials with least privilege, a strong unique admin password, production error logging, and secure backups. The included `uploads/incidents/.htaccess` and `uploads/profiles/.htaccess` disable directory listing and deny PHP-like script execution when Apache honors `.htaccess`. Keep credentials out of source control.
