# Data retention

The reasons for this design are recorded in [adr/0002-retention-removes-personal-details-keeps-tickets.md](adr/0002-retention-removes-personal-details-keeps-tickets.md).

The privacy notice says personal details are not kept forever (`LEGAL_RETENTION_PERIOD`, "3 years from ticket closure" by default). `php artisan helpdesk:enforce-retention` carries that out, and it runs every day at 03:55.

**What is removed** from a ticket that was closed or cancelled more than `HELPDESK_RETENTION_YEARS` years ago: the requester's name, phone number and email, the subject and description, the text of every reply, the free text typed when closing, cancelling, rating or escalating, the consent IP address and browser, every attached file, and the file names in the audit trail's download entries, and the old text that System Logs keeps for edited or deleted replies. A short note replaces the text and the ticket then reads "Removed after the retention period".

**What is kept**, so reports and response times keep working: the ticket number, status, severity, category, department, province and municipality, ticket type, who it was assigned to, every date, the satisfaction rating and the root cause category. Staff and client accounts are not touched by this clean-up.

**It is switched off.** Nothing is removed until `HELPDESK_RETENTION_ENABLED=true`, because removal cannot be undone (except from a backup). To turn it on:

1. Run `php artisan helpdesk:enforce-retention --dry-run` and read the list. While your oldest closed ticket is younger than the period (three years by default), the list is empty. `--years=1` lets you rehearse with a shorter period.
2. Set `HELPDESK_RETENTION_ENABLED=true` and `HELPDESK_RETENTION_YEARS`, then `php artisan config:clear`.
3. Keep the wording of `LEGAL_RETENTION_PERIOD` in step with `HELPDESK_RETENTION_YEARS`: the notice must say what the system does.

**Legal hold.** `php artisan helpdesk:retention-hold TK-ABCD1234 --reason="Complaint 2026-14"` stops a ticket from ever being cleaned until `--release` is used. A ticket that was already cleaned cannot be held.

Every run writes a `retention.enforced` entry to System Logs (counts and ticket numbers only). A run that fails part way is safe: files are removed first, and a ticket that did not finish is picked up again next time. The production status check (`php artisan helpdesk:ops-status`) tells you when tickets are past the period and the clean-up is switched off, or when it is on but has not removed anything for three days.

Backups keep the removed details for up to `HELPDESK_BACKUP_RETENTION_DAYS` (14) days, so a removed ticket disappears from the oldest backup after that. The privacy notice says so, and says how long System Logs are kept (`OBSERVABILITY_SYSTEM_LOG_RETENTION_DAYS`, 365 days): both numbers are filled in from these settings, so the notice follows them.
