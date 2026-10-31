# Prompt 11 — Admin Dashboard & Incident Management Test Plan

Run after importing `database/prompt11_admin_dashboard_migration.sql` into `neighbournet_db` and logging in with an active account whose database `users.role` is `admin`.

## Authorization tests
1. Open `/pages/admin.php` as an active admin: dashboard loads.
2. Open `/pages/admin.php` as a resident: redirected to resident dashboard; no admin data is rendered.
3. POST `/api/admin/manage.php` as a resident: expect HTTP 403 JSON.
4. POST `/api/admin/clusters.php` as a resident: expect HTTP 403 JSON.
5. Change an admin account's database role to `resident` while its browser session remains open; refresh admin page and retry an admin API call. Both must be denied because role is checked from the database on each request.
6. Suspend a resident account in the admin panel. The resident's next protected page/API request must be denied because account activity is checked from the database.
7. Submit an admin action with a missing or invalid CSRF token: expect HTTP 403 and no database mutation.

## Dashboard and incident workflow
8. Compare total and per-status tiles against SQL `SELECT status, COUNT(*) FROM incidents GROUP BY status`.
9. Test search by incident title, description fragment and numeric ID.
10. Test each status, category, society, start-date and end-date filter; combine filters and move between pages.
11. Open an incident's details and evidence image as admin. Verify the evidence URL is the stored upload path, and no private reporter contact details are shown.
12. Change status from pending to under verification. Confirm `incidents.status` changes and `incident_updates` records old status, new status, admin user ID, timestamp and note. Confirm `admin_audit_logs` also records the action.
13. Add an internal note. Confirm it appears in the admin-only notes list and is not rendered on the resident incident page.
14. Flag a report suspicious/abusive, then clear the flag. Confirm `review_flag` and `review_note` update and the action is audited.
15. Test a status update with invalid status, invalid incident ID and invalid CSRF token. No status change should occur.

## Moderation and cluster workflows
16. Hide a regular comment and a community-vote observation; confirm each is marked `is_hidden=1`, hidden from the resident incident page where applicable, then restore it.
17. Suspend and reactivate a resident; verify active sessions are rejected after suspension.
18. Cancel an unsafe/inappropriate community task; verify task status and audit log.
19. Approve and reject a suggested incident cluster. Add an incident and separate/remove an incident. Confirm original incident rows are preserved and both cluster audit logs are recorded.
20. Approve/reject a pending neighbourhood change request and verify its audit entry.

## Charts and Neighborhood Issue-Management Index
21. Verify daily chart counts against grouped incident creation dates for the last 14 days.
22. Verify category chart counts against the category aggregation query.
23. Independently calculate the index: 50% resolved share + 30% resolution-speed component (linear decline to zero at 14 average days) + 20% share of unresolved reports created in the last 7 days. It is an issue-management performance indicator only, not a safety score.

## Expected security properties
- Every admin page/API checks the current database role and active account status.
- All state-changing admin requests are POST-only and CSRF-protected.
- SQL values are parameterized; output is HTML-escaped.
- Official status changes, notes, moderation and cluster actions are audited.
- Incident reports remain independent records; clustering never deletes original reports.
