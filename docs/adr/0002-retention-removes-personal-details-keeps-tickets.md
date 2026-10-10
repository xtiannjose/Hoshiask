# Retention clean-up removes personal details but keeps the ticket

The privacy notice promises personal details are not kept past the retention period. We remove them (requester contact details, subject, description, reply text, typed reasons, consent IP and browser, files, and file names in the audit trail) and keep the ticket row with its number, status, severity, category, field office, location, assignees, dates, rating and root cause category, so reports and response times stay correct. Deleting tickets outright was the alternative; it would have rewritten past reports.

## Consequences

- Removal cannot be undone except from a backup, so the clean-up ships switched off (`HELPDESK_RETENTION_ENABLED=false`) with a `--dry-run`, a legal hold, and an ops-status advisory when tickets are past the period.
- Staff and client accounts are out of scope. Backups keep removed details until they age out (14 days).
