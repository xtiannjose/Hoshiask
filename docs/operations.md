# Operations: run and monitor

Everything a server needs besides the code. To set up your own computer, see [setup.md](setup.md). Every setting is in [configuration.md](configuration.md).

## What a server needs

| Part | What it is for |
|---|---|
| A web server pointing at `public/` | Serves the site over HTTPS. `storage/` and `bootstrap/cache/` must be writable by the app's user. |
| PHP 8.3+ with the extensions in [setup.md](setup.md) | Runs the app. `pdo_pgsql` for PostgreSQL, `zlib` for backups. `pg_dump` must be installed for PostgreSQL backups. |
| PostgreSQL 16 (or MySQL/MariaDB) | The data. |
| **A cron entry** for the scheduler | Reminder emails, automatic closing, nightly backups, clean-up. |
| **A queue worker** | Sends the queued emails. |
| An SMTP mail account | Real email delivery. |
| ClamAV (optional but recommended) | Virus scanning of uploads. See [security.md](security.md). |

## Scheduler (required)

Without it there are no reminder emails, no automatic closing and no nightly backups.

Production: one cron entry that runs every minute.

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Development: `php artisan schedule:work`.

| When | Command | What it does |
|---|---|---|
| Every 5 minutes | `tickets:send-alert-emails` | Reminders to staff (SLA, unassigned, quiet Pending tickets), merged reply emails to clients, and a heartbeat for the status check. |
| Daily 02:40 | `hoshiask:backup` | The encrypted backup of the database and every uploaded file. |
| Daily 03:10 | `hoshiask:cleanup-runtime` | Removes old backups, exports, import files, System Logs entries, used-up password handoffs and expired rows of the database cache. |
| Daily 03:40 | `tickets:auto-close-resolved` | Warns clients, then closes tickets that stayed Resolved for 7 days. |
| Daily 03:45 | `tickets:auto-cancel-pending` | Cancels Pending tickets with no activity for 14 days (`HOSHIASK_PENDING_AUTO_CANCEL_DAYS`). |
| Daily 03:55 | `hoshiask:enforce-retention` | Removes personal details from old tickets. Does nothing until you switch it on. |
| Sunday 04:20 | `hoshiask:backup-verify` | Checks the newest backup again. |

The times follow `APP_TIMEZONE`. If a job fails, the failure is written to System Logs as `scheduler.command.failed`. `hoshiask:ops-status` tells you when the cron entry is not running: an old or missing heartbeat shows up as an advisory.

## Queue worker (required unless `QUEUE_CONNECTION=sync`)

Ticket emails are queued, so a worker must run all the time. Keep this command running. On a Linux server, let systemd or Supervisor start it and restart it when it stops:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

After you change the code or `.env`, restart the workers so they load it: `php artisan queue:restart`.

To run a worker on a Windows PC while you test:

```powershell
.\scripts\start-hoshiask-queue-worker.ps1          # start it in the background (nothing happens if one is already running)
.\scripts\register-hoshiask-queue-worker-task.ps1  # start it automatically at sign-in (task "Hoshiask Queue Worker")
```

These cover only the worker. Keep `php artisan schedule:work` running as well to test reminders and backups. If a worker was started by an older version of the script, stop it first, or the script will not recognise it.

## Backups (required for production)

Every night at 02:40 the scheduler makes one encrypted file holding the database and every uploaded file, checks it, and copies it to the second location if you set one. Every Sunday it checks the newest backup again.

```bash
php artisan hoshiask:backup-key        # once: print a new key. Store it OUTSIDE the server, then set HOSHIASK_BACKUP_ENCRYPTION_KEY
php artisan hoshiask:backup            # make a backup now (what the scheduler runs)
php artisan hoshiask:backup-verify     # check the newest backup (--copy: the newest one in HOSHIASK_BACKUP_COPY_PATH)
php artisan hoshiask:backup-restore FILE EMPTY_FOLDER   # unpack a backup and print the commands that put it back
```

The second copy, restoring on a new server and a quarterly rehearsal are explained step by step in [backup-and-restore.md](backup-and-restore.md). `hoshiask:backup-database` exports only the database; use it before running migrations.

## Email delivery

Set the mail values in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO_ADDRESS`; see [configuration.md](configuration.md)). Then:

```bash
php artisan config:clear
php artisan mail:test your-email@example.com
```

`mail:test` is switched off in production so nobody can send a flood by mistake. Check SMTP changes from a staging or local copy instead, or watch the first real assignment email.

## Status and health checks

```bash
php artisan hoshiask:ops-status                       # a quick status
php artisan hoshiask:ops-status --fail-on-warning     # exit with an error when there are warnings (for scripts and CI)
php artisan hoshiask:ops-status --json                # for scripts
```

It reports the PHP version, whether the database answers, the queue (connection, whether a worker is needed and running, pending and failed jobs, and when a worker last finished a job), the mailer, and the app version.

- **Warnings** are things that need fixing now. These are what `--fail-on-warning` fails on: PHP too old, no database, no queue worker, and in production `APP_DEBUG` on, `APP_URL` pointing at localhost, `SESSION_SECURE_COOKIE` off, or no `HOSHIASK_HEALTH_DETAIL_TOKEN`.
- **Advisories** (production only) are settings that leave the site up but quietly switch something off. They never make `--fail-on-warning` fail. Examples: failed queue jobs (one bounced email stays in `failed_jobs` until you retry or flush it) and a queue that has not moved for 15 minutes (waiting jobs alone are normal; on a Redis queue, waiting jobs with no job finished for 15 minutes), `MAIL_MAILER=log` (no email leaves the server), the placeholder `MAIL_FROM_ADDRESS`, `LOG_STACK=single` (one log file that is never rotated), `APP_TIMEZONE=UTC`, a scheduler that has not run for 15 minutes, no backup yet or an old one, a backup that is not encrypted, no recent backup check, backups that exist only on this server, retention clean-up switched off while tickets are past the period, virus scanning that is off or cannot reach ClamAV, `TRUSTED_PROXIES=*` (any sender may claim to be a proxy), and the example contact addresses still in the privacy notice (`LEGAL_DPO_EMAIL`, `LEGAL_SUPPORT_EMAIL`).

For monitoring tools there is a lightweight address:

```text
GET /health
```

It answers with the status (`ok`, `degraded` or `failed`) and the time of the check, nothing more, and HTTP `503` only for `failed` (no database, or PHP too old). Without the token it does not look at the process list, so it cannot tell whether a queue worker runs and does not count that as a problem. To get the details (database, queue, warnings, advisories) set `HOSHIASK_HEALTH_DETAIL_TOKEN` and send it as `Authorization: Bearer <token>` or `X-Health-Token: <token>`. A token in the address is not accepted, because addresses are usually logged.

## Sign-in lockout

Eight wrong passwords for one login lock that login for 15 minutes **for the address that typed them**. Someone guessing from another address cannot lock the real person out. One address may also fail 15 sign-ins a minute, with whichever logins it tries; successful sign-ins are not counted, so a whole office behind one address can sign in at once. Behind a proxy, set `TRUSTED_PROXIES`, or every visitor looks like the proxy.

To lift a lock early:

- The shadow account: Online Users, "Clear lockout" (clears it from every address).
- On the server, for example when the shadow account itself is locked: `php artisan hoshiask:unlock-login <username or email>`. It writes `auth.lockout_cleared` to System Logs.

A guesser with many different addresses gets eight tries on each, so strong passwords are the real defence.

## All the commands

| Command | What it does | When |
|---|---|---|
| `hoshiask:ops-status` | Status check (see above). | Any time, and after every update. |
| `hoshiask:backup` | Backup, check, and copy to the second location. | Scheduled. Run by hand before risky work. |
| `hoshiask:backup-database` | Export only the database. | Before running migrations. |
| `hoshiask:backup-key` | Print a new backup key (not saved anywhere). | Once, and if the key is lost. |
| `hoshiask:backup-verify` | Check that a backup is complete and usable. | Scheduled weekly. After setting up. |
| `hoshiask:backup-restore` | Unpack a backup into an empty folder. | Restoring or rehearsing. |
| `hoshiask:cleanup-runtime` | Remove old temporary data (see the table above). | Scheduled. |
| `hoshiask:enforce-retention` | Remove personal details from old tickets. `--dry-run` only lists them. | Scheduled. See [data-retention.md](data-retention.md). |
| `hoshiask:retention-hold` | Keep one ticket out of the clean-up (legal hold). | When asked by legal. |
| `hoshiask:unlock-login` | Lift a sign-in lockout. | A person is locked out. |
| `hoshiask:upload-scan-check` | Send the EICAR test file to the virus scanner. | After installing ClamAV. |
| `hoshiask:scrub-failed-login-text` | Mask text typed into the sign-in box in old failed sign-in log entries. `--dry-run` only counts. | Once, on an older database. |
| `hoshiask:build-workflow-pdf` | Rebuild the guide's PDF. | After editing the guide. |
| `tickets:send-alert-emails` | Reminders and merged reply emails. | Scheduled every 5 minutes. |
| `tickets:auto-close-resolved` | Warn, then close old Resolved tickets. | Scheduled. |
| `tickets:auto-cancel-pending` | Cancel Pending tickets quiet for 14 days. `--dry-run` only lists them. | Scheduled. |
| `tickets:import` | Import tickets from CSV or XLSX. | See [ticket-import.md](ticket-import.md). |
| `mail:test` | Send a test email. | Not in production. |

## Production checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_URL` is the real HTTPS address and `SESSION_SECURE_COOKIE=true`
- [ ] Strong secrets of your own: `APP_KEY`, the database and mail passwords, `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD`, `SHADOW_PASSWORD`
- [ ] `TRUSTED_PROXIES` lists your reverse proxy or load balancer, if there is one
- [ ] `HOSHIASK_HEALTH_DETAIL_TOKEN` is set
- [ ] The web root is `public/`, and `storage/` and `bootstrap/cache/` are writable
- [ ] `LOG_STACK=daily` and `LOG_LEVEL=warning` (one file a day, the last 14 kept)
- [ ] The starter departments exist (`php artisan migrate --seed` on a new installation, or `php artisan db:seed --class=StarterDataSeeder`; `hoshiask:ops-status` says when there are none)
- [ ] The cron entry for the scheduler and the queue worker are running
- [ ] Mail works: real `MAIL_*` values, and `MAIL_FROM_ADDRESS` is not the placeholder
- [ ] Backups: `HOSHIASK_BACKUP_ENCRYPTION_KEY` is set (and stored outside the server), a second copy or an off-server job exists, `hoshiask:backup` and `hoshiask:backup-verify` have run, and a restore was rehearsed ([backup-and-restore.md](backup-and-restore.md))
- [ ] Virus scanning works ([security.md](security.md)), or you decided to run without it
- [ ] An outside monitor watches `/health` and alerts two people, and a second person has done the test in [resilience.md](resilience.md)
- [ ] `HOSHIASK_QUEUE_WORKER_RUNNING` is blank (or the right value if the status check cannot see your worker)
- [ ] The framework caches are built: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] The shadow account's password was changed from the first one (Account Settings), and `SHADOW_PASSWORD` in `.env` was changed to match, so running its seeder again cannot put the old password back

## After each update

- [ ] Sign-in works
- [ ] Creating a ticket and replying works
- [ ] Assignment and notification emails are sent
- [ ] A queue worker is running
- [ ] `php artisan hoshiask:ops-status` shows no warnings

Data clean-up rules are in [data-retention.md](data-retention.md). Virus scanning is in [security.md](security.md).
