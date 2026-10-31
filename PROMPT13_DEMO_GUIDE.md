# NeighbourNet — Prompt 13 Hackathon Demo Guide

## Demo safety and data integrity

- Use records created during the demonstration and label each title with `[DEMO]` (for example, `[DEMO] Water supply disruption near Gate 2`).
- Do not use real residents' names, phone numbers, exact home addresses, or private photographs in the public demonstration.
- Do not describe seeded, sample, or manually created demonstration records as live community incidents.
- The project intentionally does not ship with a universal demo password or a pre-created administrator account.

## XAMPP setup

1. Extract `neighbournet/` into `C:\xampp\htdocs\neighbournet\`.
2. Start Apache and MySQL in XAMPP.
3. Create an empty database named `neighbournet_db` in phpMyAdmin.
4. Import `database/neighbournet_full_schema.sql`.
5. Review `config/db.php` and set the local MySQL username/password for your machine.
6. Open `http://localhost/neighbournet/` and register two accounts: one resident and one account reserved for administration.
7. To grant administrator access to the reserved account, run this in phpMyAdmin after replacing the email with the exact account you registered:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-admin-email@example.com';
```

Keep the administrator credentials private. Never promote an account by changing a hidden form field or browser session value.

## Suggested 6–8 minute demonstration

1. **Resident login:** Sign in with the resident account and show the dashboard and community map. Explain that the map only plots records with valid coordinates.
2. **Hindi voice report:** Open Report an Issue, choose Hindi (हिन्दी), start voice recording, and dictate a clearly labelled `[DEMO]` water-supply issue. Browser speech recognition requires microphone permission and a supported browser; localhost is treated as a secure context by modern browsers.
3. **Review and submit:** Review/edit the transcript and translation, select the final description, add a non-identifying landmark, and submit. Wait for the server redirect/confirmation before saying the report was submitted.
4. **Community map:** Open the map and locate the new `[DEMO]` record. If GPS is omitted, explain that the report remains in the feed but cannot be plotted as a marker.
5. **Community verification:** Open the incident as a second resident and cast a confirm/uncertain vote. Show the aggregate results and the ability to change the vote.
6. **Clustering:** Submit a second similar `[DEMO]` report in the same area and show the suggested cluster. Explain that it remains a suggestion until an administrator reviews it.
7. **Community action:** Create a safe task linked to the incident, volunteer from the second resident account, and show a progress update/completion.
8. **Administration:** Sign in as the administrator, review the incident, add an internal note if needed, and update its status. Return to the resident's My Reports/incident page to show the updated status and history.

## PWA / offline demonstration

- Open the app in Chrome or Edge on `http://localhost/neighbournet/` and wait for the service worker to install.
- Use the browser's install option when it becomes available. The app's install button appears only when the browser fires `beforeinstallprompt`.
- Visit a page, then simulate offline mode in DevTools. Navigations should show the generic offline page; API/private pages are not cached.
- On the report form, type a short title and description, then use Restore saved text. The local draft excludes location, coordinates, photo, transcript metadata, account data, and CSRF tokens.
- Attempting to submit while offline is blocked and clearly states that the report was **not submitted**. Reconnect and submit normally; only the server response counts as success.

## Important local configuration

- Voice recognition depends on browser Web Speech API support and microphone permissions.
- Translation calls the configured external translation provider and requires internet access; it may be rate-limited or unavailable.
- Map tiles and third-party CDN assets require internet access. The offline shell itself uses local files.
- For production, configure HTTPS, SMTP, backups, secure database credentials, monitoring, and a privacy/retention policy.
