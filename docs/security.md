# Security notes

To report a problem, see [SECURITY.md](../SECURITY.md). The full server checklist is in [operations.md](operations.md).

## Production settings

- Do not use shared or default credentials in production.
- Set strong values of your own for `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD` and `SHADOW_PASSWORD`.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the real HTTPS address and `SESSION_SECURE_COOKIE=true`.
- Behind a reverse proxy or load balancer, set `TRUSTED_PROXIES`.
- Set `HELPDESK_HEALTH_DETAIL_TOKEN` before wiring detailed health monitoring.
- Keep `.env` out of version control.
- Make sure `storage` and `bootstrap/cache` are writable by the app's user.

## What the app already does

- **Browser protections on every page.** It refuses to be shown inside another site's frame (the one exception is its own PDF and text preview), stops browsers guessing file types, and limits what it sends in the `Referer` header. It switches off the camera, microphone and location. Over HTTPS it tells browsers to use HTTPS only (HSTS). A Content-Security-Policy allows scripts and styles only from the site itself: it is enforced in production and only reported elsewhere. Pages for signed-in people are never cached.
- **Sign-in.** Eight wrong passwords for one login lock it for 15 minutes for the address that typed them, and one address may fail 15 sign-ins a minute whichever logins it tries (successful sign-ins are not counted, so an office behind one address is not locked out). The answer is the same whether or not the account exists, and text typed into the sign-in box is masked in the logs unless it names a real account. A password needs at least 8 characters and must not be a very common one.
- **Sessions.** Kept in the database, encrypted, hidden from scripts, and sent over HTTPS only in production. They end after 2 hours without activity. Changing a password or a role signs that person out everywhere.
- **Permissions on the server.** Every page and action checks the role and, for a record, a policy. Hiding a button is never the only check. A client who asks for a ticket, file or notification that is not theirs gets "not found", the same as for one that does not exist, so ticket numbers cannot be guessed.
- **Input and output.** Everything is validated on the server. A list or array where a form expects text is ignored. Text that people type is escaped when shown. Searches treat `%` and `_` as plain characters. CSV exports neutralise cells that start with `=`, `+`, `-` or `@`, so a spreadsheet cannot run them as formulas.
- **Rate limits.** Sign-in and nearly every action that changes data has a limit per person and minute, and a busy page cannot use up the allowance of a rare action.
- **Duplicate submissions.** The same form sent twice (a double click, the back button) creates one ticket or one reply.
- **Files.** Only these types are accepted: images (`jpg`, `jpeg`, `png`), PDF, Word (`doc`, `docx`), text (`txt`) and Excel (`xls`, `xlsx`), at most 10 files of 10 MB per ticket or reply. They are stored under random names in a private folder that no web address serves, and sent only after a permission check. Only images, PDF and plain text open inline; SVG files are refused.
- **Audit trail.** Sign-ins and failures, password and role changes, forced sign-outs, exports, file downloads and many ticket actions are written to System Logs, which only the shadow account sees.
- **Secrets and backups.** A temporary password issued by the shadow account can be shown once, within 10 minutes. Backups are encrypted with their own key (AES-256-GCM).
- **The code itself.** CI scans for committed secrets, runs `composer audit` and `npm audit`, and Dependabot opens update pull requests every week.

## Decisions that are not defects

These were reviewed and chosen on purpose (2026-10-10). Reviewers should not file them again unless the situation changes.

- **Clients cannot change or reset their own password.** Accounts are issued by the organisation and shared inside the client's own team, so staff control the passwords. Staff and the shadow account can change theirs.
- **No second sign-in step (MFA).** Not needed for now. Staff who tick "stay signed in" are signed out after 60 days; sessions without it end after 2 hours idle.
- **A backup without an encryption key only warns.** `helpdesk:backup` and `helpdesk:ops-status` say so, but the backup is still written. Backups sit in `storage/app/private/backups`, which no web route serves. Set `HELPDESK_BACKUP_ENCRYPTION_KEY` on any server that holds real data.
- **Reply edits are not shown in the conversation.** If edit history is added it goes to the shadow account's System Logs only.
- **Every download of a file is recorded, a preview once an hour.** System Logs gets an `attachment.downloaded` row each time someone downloads a file. A preview (the image or PDF shown inside the ticket page) is recorded once an hour for the same person and file, so a page with ten images does not add ten rows every time it is opened. The row says `preview` in its details.

## Upload scanning (optional)

Attachments are checked for type and size (10 files per ticket or reply, 10 MB each) and stored under random names. Virus scanning is **on by default in production** (`HELPDESK_UPLOAD_SCAN` blank), but it only does something once ClamAV is installed on the server: until then uploads are allowed, the problem is written to System Logs once a minute, and `helpdesk:ops-status` shows an advisory. A server whose `.env` was copied from an older example may still say `HELPDESK_UPLOAD_SCAN=off`; delete that line or set `clamav`. With ClamAV (free) on the server, every ticket and reply attachment is scanned before it is accepted; a flagged file is refused with "... was blocked because it may contain a virus" and recorded in System Logs (`upload.malware_blocked`).

On a Debian or Ubuntu server:

1. `sudo apt install clamav-daemon`
2. `sudo systemctl enable --now clamav-freshclam clamav-daemon`. The first download of virus signatures takes a few minutes; the daemon starts answering after it.
3. Let the app reach the daemon. Either use its socket (usually `/var/run/clamav/clamd.ctl`; the PHP-FPM user must be allowed to use it, for example `sudo usermod -aG clamav www-data` and restart PHP-FPM), or enable `TCPSocket 3310` and `TCPAddr 127.0.0.1` in `/etc/clamav/clamd.conf` and restart `clamav-daemon`.
4. In `.env`: `HELPDESK_UPLOAD_SCAN=clamav` and either `HELPDESK_CLAMAV_SOCKET=/var/run/clamav/clamd.ctl` or the host and port.
5. `php artisan optimize`, then `php artisan helpdesk:upload-scan-check`. It sends the standard harmless EICAR test file to the scanner and must end with "Virus scanning works".

Things to know:

- **When the scanner is down.** `HELPDESK_UPLOAD_SCAN_ON_ERROR=allow` (default) accepts uploads without a check and records `upload.scan_unavailable` in System Logs; `block` refuses uploads until the scanner is back. The production status check (`php artisan helpdesk:ops-status`) says when scanning is off or the scanner cannot be reached.
- **Memory.** ClamAV needs about 1 GB of RAM for its signatures. On a server with 2 GB or less, check that the app still has room.
- **Size.** ClamAV's default stream limit (`StreamMaxLength`, 25 MB) is above the 10 MB per-file limit here, so no change is needed.
- **Not covered.** Files uploaded before scanning was switched on are not rescanned, and department logos (uploaded by administrators only) are not scanned. ClamAV catches known malware; it is one layer, not a guarantee.
- **Trying it without ClamAV.** `php scripts/fake-clamd.php 3310` starts a stand-in that answers like ClamAV and calls a file infected when it contains the text `HELPDESK-TEST-VIRUS-SIGNATURE`. Set `HELPDESK_UPLOAD_SCAN=clamav` and `HELPDESK_CLAMAV_PORT=3310` in a local `.env`. Never use it on a real server.
