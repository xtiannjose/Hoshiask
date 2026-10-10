# Security notes

## Production settings

- Do not use shared/default credentials in production.
- Set strong environment-specific values for `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD`, and `SHADOW_PASSWORD`.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Set `APP_URL` to the real HTTPS production URL.
- Set `SESSION_SECURE_COOKIE=true` in production (HTTPS only).
- Set `HELPDESK_HEALTH_DETAIL_TOKEN` before wiring detailed health monitoring.
- Keep `.env` out of version control.
- Ensure `storage` and `bootstrap/cache` are writable by the app process.

## Decisions that are not defects

These were reviewed and chosen on purpose (2026-10-10). Reviewers should not file them again unless the situation changes.

- **Clients cannot change or reset their own password.** Accounts are issued by the organisation and shared inside the client's own team, so staff control the passwords. Staff and the shadow account can change theirs.
- **No second sign-in step (MFA).** Not needed for now. Staff who tick "stay signed in" are signed out after 60 days; sessions without it end after 2 hours idle.
- **A backup without an encryption key only warns.** `helpdesk:backup` and `helpdesk:ops-status` say so, but the backup is still written. Backups sit in `storage/app/private/backups`, which no web route serves. Set `HELPDESK_BACKUP_ENCRYPTION_KEY` on any server that holds real data.
- **Reply edits are not shown in the conversation.** If edit history is added it goes to the shadow account's System Logs only.

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
