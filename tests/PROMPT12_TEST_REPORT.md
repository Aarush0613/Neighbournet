# Prompt 12 — Integration and Test Report

## Automated checks run in the build environment

| Check | Result | Evidence / boundary |
|---|---|---|
| PHP syntax lint | PASS | All 31 PHP files passed `php -l` after Prompt 12 changes. |
| JavaScript syntax | PASS | All files under `assets/js/` passed `node --check`. |
| Clustering unit tests | PASS | All 6 existing `tests/clustering_test.php` cases passed. |
| Requested schema tables / uniqueness constraints | PASS (static check) | 21 CREATE TABLE statements detected; all 12 requested tables and vote/volunteer/cluster unique constraints are present in the consolidated SQL. This is not a live MySQL import test. |
| MySQL driver availability | BLOCKED | `PDO::getAvailableDrivers()` returned no drivers; `pdo_mysql` and `mbstring` are absent in the build runtime. |
| SQL import / foreign-key validation | NOT RUN | No MySQL/MariaDB client or PDO MySQL driver is installed in the build environment. |
| Browser end-to-end workflows | NOT RUN | No XAMPP browser session or live MySQL service is available here. |
| Speech recognition / microphone | NOT RUN | Requires supported browser, microphone permission, and usually localhost/HTTPS. |
| External translation connectivity | NOT RUN | Depends on outbound access and MyMemory availability/rate limits. |

## Local XAMPP acceptance checklist

Run each test against a disposable test database and record PASS/FAIL with screenshots or logs:

1. **Registration:** create a new resident, confirm `users` row and bcrypt `password_hash`, then login and load dashboard.
2. **Incident:** submit text report with/without image; verify `incidents` row, feed item, detail page, and no orphan upload on rejected submission.
3. **Voice:** test Hindi and English browser speech input; edit transcript; submit; verify transcript metadata. Test translation online and offline/error state.
4. **Voting:** vote TRUE/FALSE/MAYBE, verify aggregate counts, change vote, and confirm one row per `(incident_id,user_id)`.
5. **Clustering:** submit similar reports in configured time/distance; verify suggested cluster, then approve/reject/separate as admin.
6. **Community tasks:** create task, join from another resident, reject duplicate join, post update, complete task, verify notifications.
7. **Profile:** edit name/preferences, reload and login again, verify persisted values. Test invalid image and oversized avatar.
8. **Admin:** review report, change status, verify `incident_updates`, `incident_history`, and `admin_audit_logs` entries.
9. **Authorization:** access protected pages/API logged out; call admin APIs as resident; both must be denied.
10. **Injection/input:** try SQL metacharacters in search/title/email; values must be treated as data and validation errors shown where appropriate.
11. **Uploads:** test `.php` renamed as `.jpg`, invalid image bytes, and >5 MB image; each must be rejected.
12. **CSRF:** omit/alter token on registration, vote, tasks, admin and logout; writes must be rejected.
13. **Uniqueness:** attempt duplicate vote and volunteer join; database/API must preserve one row per user+target.
14. **Logout:** GET must not log out; POST without CSRF must fail; valid POST clears session and redirects.

## Known environment limitation

The build runtime exposes PDO and `fileinfo`, but not `pdo_mysql` or `mbstring`; consequently PHP database calls and `mb_*` functions cannot be exercised here. XAMPP should enable `pdo_mysql`, `fileinfo`, and `mbstring`. The actual MySQL import and all eight browser workflows remain pending local verification.
