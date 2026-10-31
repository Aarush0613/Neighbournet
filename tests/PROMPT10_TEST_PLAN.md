# Prompt 10 — Community Action Board test plan

Run after importing `database/prompt10_community_action_board_migration.sql` into the existing database and signing in with two resident accounts in the same society plus an administrator account.

1. **Create:** Open an incident detail page, follow Community Action Tasks, create a task with title, description, priority and optional volunteer limit. Confirm it appears in the list and on the incident detail page.
2. **Join:** Sign in as a second resident and volunteer. Confirm the count increments and the task creator receives an in-app notification.
3. **Duplicate join:** Submit the same join action again (or double-click). Confirm the API returns a conflict and the volunteer count remains one for that resident.
4. **Withdraw:** Withdraw as the volunteer. Confirm the resident can join again and the count decreases.
5. **Progress update:** Sign in as task creator and post an update. Confirm it appears in task history and participating volunteers receive notifications.
6. **Authorization:** Attempt to post an update as a non-creator resident. Confirm the API rejects it. Repeat as an administrator and confirm it succeeds.
7. **Volunteer limit:** Set a limit of one, join from one account, and attempt to join from another. Confirm the second join is rejected.
8. **Safety guardrail:** Attempt to create a task encouraging confrontation, entering a flooded/unsafe area, or repairing live electrical infrastructure. Confirm the API rejects the task.
9. **Incident linkage:** Open the linked incident detail page and confirm the related task preview and count are visible; open the board from the incident link and confirm the incident filter is applied.
10. **Notifications:** Mark notifications as read and reload. Confirm the unread count is updated and notification history remains visible.

The PHP and JavaScript syntax checks are automated in the delivered build. The above database-backed scenarios require a running MySQL/XAMPP instance and should be completed locally.
