# Pending actions for the maintainer

Things only the maintainer can do (they need the live server or the GitHub merge button), with the exact steps, saved on 2026-10-10 so nobody has to rebuild them. Tick a step off here when it is done, and delete this file when everything is.

Status on 2026-10-10: **none of this is done yet.** The maintainer could not do it that day.

## 1. Change the shadow account password (most urgent)

Why: the shadow account's old password is in four old commits of the repository history (the password text is deliberately not written anywhere in the project). It is no longer in any current file, and the repository is private, but anyone who can clone it can read the history. Issue #219.

Steps:

1. Sign in to the live site as `shadow`.
2. Open **Account Settings**, then the **Password** section.
3. Type the current password, then a new one twice (12 or more characters, not used anywhere else). Save. Other signed-in sessions are ended.
4. Save the new password in the password manager.
5. On the server, open `/opt/helpdesk/.env`. If `SHADOW_PASSWORD=` holds the old password, put the new one there. Otherwise re-running the seeder would reset the shadow account to the old password.
6. Run `cd /opt/helpdesk && php artisan config:clear`.
7. Sign out and back in as `shadow`, and unlock System Logs with the new password.
8. If locked out of `shadow` (pull request #245 must be merged and deployed first): `php artisan helpdesk:unlock-login shadow`.

Optional and not recommended: erasing the old password from the history means rewriting history with a force push. It breaks every clone and open pull request, and Claude is not allowed to force-push, so the maintainer would run it. Changing the password is enough for a private repository with two collaborators.

## 2. Install ClamAV on the server (virus scanning)

Why: pull request #239 turns virus scanning on by default in production, but it only works once ClamAV is installed. Until then uploads are allowed as before, and `php artisan helpdesk:ops-status` shows an advisory. ClamAV is free and needs about 1 GB of free memory.

Steps (Ubuntu or Debian):

1. Connect to the server and go to the app: `cd /opt/helpdesk`.
2. Check the system and memory: `cat /etc/os-release` and `free -m`.
3. Install it: `sudo apt update && sudo apt install -y clamav-daemon`.
4. Start it and keep it running after reboots: `sudo systemctl enable --now clamav-freshclam clamav-daemon`.
5. Wait 3 to 5 minutes while it downloads the virus signatures. `systemctl status clamav-daemon` must say "active (running)".
6. Let the website use it. Replace `www-data` if PHP runs as another user:
   - `sudo usermod -aG clamav www-data`
   - Restart PHP. The name depends on the version, for example `sudo systemctl restart php8.3-fpm`.
7. Edit `/opt/helpdesk/.env`:
   - Delete the line `HELPDESK_UPLOAD_SCAN=off` if it is there (otherwise scanning stays off).
   - Add `HELPDESK_UPLOAD_SCAN=clamav`.
   - Add `HELPDESK_CLAMAV_SOCKET=/var/run/clamav/clamd.ctl`.
8. Reload the settings: `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`.
9. Test: `php artisan helpdesk:upload-scan-check`. It must end with "Virus scanning works."
10. Final check: `php artisan helpdesk:ops-status` shows no scanning warning.

If step 9 cannot reach the scanner, wait a few more minutes, or look for the socket with `ls /var/run/clamav/`. Until it works, uploads are still allowed. The longer reference is in [docs/security.md](../security.md).

## 3. Merge the open pull requests, then deploy

The nine pull requests below pass all checks, including PostgreSQL, and do not conflict with each other, so the order does not matter.

| Pull request | What it does |
|---|---|
| #238 | Closes the web route to private storage, staff "stay signed in" lasts 60 days, typed search text is kept out of export logs, decisions written into the docs |
| #239 | At most 10 attachments per ticket or reply, virus scanning on by default in production |
| #240 | A double click no longer posts or emails a reply twice; a late reply cannot reopen a closed ticket; fixes an empty ticket status on JSON replies |
| #241 | The SLA rules move out of `Ticket` into their own class (no behaviour change) |
| #242 | The old text of an edited or deleted reply is kept in the shadow account's System Logs only |
| #243 | PDF and text attachment previews work in the ticket page |
| #244 | A time-sensitive test no longer flakes |
| #245 | A stranger cannot lock the real admin out; new command `php artisan helpdesk:unlock-login` |
| #246 | Route protection and client journey tests; fixes the shared rate-limit counter and the "mark as resolved" button that needed a comment at any rating; the PostgreSQL job fails if it is not really on PostgreSQL |

Merging does not put anything on the server. Deploy as usual ([docs/operations.md](../operations.md)); the deploy runs the migration for the two new database indexes.

After deploying:

1. Open one PDF attachment on a ticket. The preview must show the file. This was only tested with a text file.
2. As a client, upload one Word and one Excel file to a ticket. Old `.doc` and `.xls` files could not be tested here, and the type check might refuse some.
3. Run `php artisan helpdesk:ops-status` and read the advisories.

## 4. Still open, only if wanted

- Issue #233: some report and auto-close jobs load a lot at once and PDFs are built while the user waits. Fine at 10,000 tickets; measure before changing.
- Issue #229: what is left of the larger list (MFA and client password changes were decided against; see [docs/security.md](../security.md)).
- Popups (issue #202): not built, reasons are on the issue.
