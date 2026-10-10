# Operations: deploy, run and monitor

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

## Deploy

### With the scripts (recommended)

On Windows, from the project folder:

```powershell
./scripts/deploy-helpdesk.ps1                   # push the branch, then deploy it
./scripts/deploy-helpdesk.ps1 -SkipPush         # deploy what is already pushed
./scripts/deploy-helpdesk.ps1 -Branch main -RemoteHost <server> -RemoteUser <user>
```

The helper **pushes the branch first**, then runs `scripts/deploy-helpdesk-remote.sh` on the server over SSH. Both scripts only work inside `/opt/helpdesk` and refuse to run when the server's checkout has uncommitted changes, so production is never used as an editing workspace.

The remote script does this, in order:

1. Puts the site in maintenance mode (`php artisan down`).
2. Pulls the branch (`git pull --ff-only`).
3. Checks PHP against `composer.json`, validates and audits the Composer packages, and installs them without dev tools.
4. Rebuilds the front end (`npm ci`, `npm run build`, then removes `node_modules`) only when `package*.json`, the Vite, Tailwind or PostCSS configuration, `resources/js`, `resources/css`, `resources/views` or `app/` changed.
5. Exports the database (`helpdesk:backup-database`), then runs `php artisan migrate --force`.
6. Clears and rebuilds the caches, runs `helpdesk:cleanup-runtime`, and restarts the queue workers.
7. Runs `php artisan helpdesk:ops-status --fail-on-warning` up to 5 times, 3 seconds apart.
8. If the check passes, takes the site out of maintenance mode.

**If any step fails**, the script undoes the migrations this deploy applied, goes back to the previous commit, rebuilds, and brings the site up again only if the check then passes. Otherwise it leaves the site in maintenance mode for a person to look at.

### By hand

The same steps, in the same order:

```bash
php artisan down
git pull --ff-only
php scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules       # only when front-end files changed
php artisan helpdesk:backup-database                 # before migrating
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
php artisan helpdesk:ops-status --fail-on-warning
php artisan up
```

## Scheduler (required)

Without it there are no reminder emails, no automatic closing and no nightly backups.

Production: one cron entry that runs every minute.

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Development: `php artisan schedule:work`.

| When | Command | What it does |
|---|---|---|
| Every 5 minutes | `tickets:send-alert-emails` | Reminders to staff, merged reply emails to clients, and a heartbeat for the status check. |
| Daily 02:40 | `helpdesk:backup` | The encrypted backup of the database and every uploaded file. |
| Daily 03:10 | `helpdesk:cleanup-runtime` | Removes old backups, exports, import files, System Logs entries and used-up password handoffs. |
| Daily 03:40 | `tickets:auto-close-resolved` | Warns clients, then closes tickets that stayed Resolved for 7 days. |
| Daily 03:55 | `helpdesk:enforce-retention` | Removes personal details from old tickets. Does nothing until you switch it on. |
| Sunday 04:20 | `helpdesk:backup-verify` | Checks the newest backup again. |

The times follow `APP_TIMEZONE`. If a job fails, the failure is written to System Logs as `scheduler.command.failed`. `helpdesk:ops-status` tells you when the cron entry is not running: an old or missing heartbeat shows up as an advisory.

## Queue worker (required unless `QUEUE_CONNECTION=sync`)

Ticket emails are queued, so a worker must run all the time. On Linux, use systemd: copy [helpdesk-queue-worker.service.example](helpdesk-queue-worker.service.example), adjust the paths, and enable it. The command inside it is:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

After every deploy restart the workers so they load the new code: `php artisan queue:restart` (the deploy script does this).

To run a worker on a Windows PC while you test:

```powershell
.\scripts\start-helpdesk-queue-worker.ps1          # start it in the background (nothing happens if one is already running)
.\scripts\register-helpdesk-queue-worker-task.ps1  # start it automatically at sign-in (task "Helpdesk Queue Worker")
```

These cover only the worker. Keep `php artisan schedule:work` running as well to test reminders and backups. If a worker was started by an older version of the script, stop it first, or the script will not recognise it.

## Backups (required for production)

Every night at 02:40 the scheduler makes one encrypted file holding the database and every uploaded file, checks it, and copies it to the second location if you set one. Every Sunday it checks the newest backup again.

```bash
php artisan helpdesk:backup-key        # once: print a new key. Store it OUTSIDE the server, then set HELPDESK_BACKUP_ENCRYPTION_KEY
php artisan helpdesk:backup            # make a backup now (what the scheduler runs)
php artisan helpdesk:backup-verify     # check the newest backup (--copy: the newest one in HELPDESK_BACKUP_COPY_PATH)
php artisan helpdesk:backup-restore FILE EMPTY_FOLDER   # unpack a backup and print the commands that put it back
```

The second copy, restoring on a new server and a quarterly rehearsal are explained step by step in [backup-and-restore.md](backup-and-restore.md). `helpdesk:backup-database` exports only the database; the deploy script uses it before migrating.

## Email delivery

Set the mail values in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO_ADDRESS`; see [configuration.md](configuration.md)). Then:

```bash
php artisan config:clear
php artisan mail:test your-email@example.com
```

`mail:test` is switched off in production so nobody can send a flood by mistake. Check SMTP changes from a staging or local copy instead, or watch the first real assignment email.

## Status and health checks

```bash
php artisan helpdesk:ops-status                       # a quick status
php artisan helpdesk:ops-status --fail-on-warning     # exit with an error when there are warnings (used by the deploy)
php artisan helpdesk:ops-status --json                # for scripts
```

It reports the PHP version, whether the database answers, the queue (connection, whether a worker is needed and running, pending and failed jobs), the mailer, and the app version.

- **Warnings** are things that need fixing now. They fail a deploy and roll it back: PHP too old, no database, no queue worker, pending or failed jobs, and in production `APP_DEBUG` on, `APP_URL` pointing at localhost, `SESSION_SECURE_COOKIE` off, or no `HELPDESK_HEALTH_DETAIL_TOKEN`.
- **Advisories** (production only) are settings that leave the site up but quietly switch something off. They never fail a deploy. Examples: `MAIL_MAILER=log` (no email leaves the server), the placeholder `MAIL_FROM_ADDRESS`, `LOG_STACK=single` (one log file that is never rotated), `APP_TIMEZONE=UTC`, a scheduler that has not run for 15 minutes, no backup yet or an old one, a backup that is not encrypted, no recent backup check, backups that exist only on this server, retention clean-up switched off while tickets are past the period, and virus scanning that is off or cannot reach ClamAV.

For monitoring tools there is a lightweight address:

```text
GET /health
```

It answers with the status (`ok`, `degraded` or `failed`) and the time of the check, nothing more, and HTTP `503` only for `failed` (no database, or PHP too old). To get the details (database, queue, warnings, advisories) set `HELPDESK_HEALTH_DETAIL_TOKEN` and send it as `Authorization: Bearer <token>` or `X-Health-Token: <token>`. A token in the address is not accepted, because addresses are usually logged.

## Sign-in lockout

Eight wrong passwords for one login lock that login for 15 minutes **for the address that typed them**. Someone guessing from another address cannot lock the real person out. The sign-in route also allows each address 5 attempts a minute in production. Behind a proxy, set `TRUSTED_PROXIES`, or every visitor looks like the proxy.

To lift a lock early:

- The shadow account: Online Users, "Clear lockout" (clears it from every address).
- On the server, for example when the shadow account itself is locked: `php artisan helpdesk:unlock-login <username or email>`. It writes `auth.lockout_cleared` to System Logs.

A guesser with many different addresses gets eight tries on each, so strong passwords are the real defence.

## All the commands

| Command | What it does | When |
|---|---|---|
| `helpdesk:ops-status` | Status check (see above). | After every deploy, and any time. |
| `helpdesk:backup` | Backup, check, and copy to the second location. | Scheduled. Run by hand before risky work. |
| `helpdesk:backup-database` | Export only the database. | The deploy script. |
| `helpdesk:backup-key` | Print a new backup key (not saved anywhere). | Once, and if the key is lost. |
| `helpdesk:backup-verify` | Check that a backup is complete and usable. | Scheduled weekly. After setting up. |
| `helpdesk:backup-restore` | Unpack a backup into an empty folder. | Restoring or rehearsing. |
| `helpdesk:cleanup-runtime` | Remove old temporary data (see the table above). | Scheduled. |
| `helpdesk:enforce-retention` | Remove personal details from old tickets. `--dry-run` only lists them. | Scheduled. See [data-retention.md](data-retention.md). |
| `helpdesk:retention-hold` | Keep one ticket out of the clean-up (legal hold). | When asked by legal. |
| `helpdesk:unlock-login` | Lift a sign-in lockout. | A person is locked out. |
| `helpdesk:upload-scan-check` | Send the EICAR test file to the virus scanner. | After installing ClamAV. |
| `helpdesk:scrub-failed-login-text` | Mask text typed into the sign-in box in old failed sign-in log entries. `--dry-run` only counts. | Once, on an older database. |
| `helpdesk:build-workflow-pdf` | Rebuild the guide's PDF. | After editing the guide. |
| `tickets:send-alert-emails` | Reminders and merged reply emails. | Scheduled every 5 minutes. |
| `tickets:auto-close-resolved` | Warn, then close old Resolved tickets. | Scheduled. |
| `tickets:import` | Import tickets from CSV or XLSX. | See [ticket-import.md](ticket-import.md). |
| `mail:test` | Send a test email. | Not in production. |

## Production checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_URL` is the real HTTPS address and `SESSION_SECURE_COOKIE=true`
- [ ] Strong secrets of your own: `APP_KEY`, the database and mail passwords, `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD`, `SHADOW_PASSWORD`
- [ ] `TRUSTED_PROXIES` lists your reverse proxy or load balancer, if there is one
- [ ] `HELPDESK_HEALTH_DETAIL_TOKEN` is set
- [ ] The web root is `public/`, and `storage/` and `bootstrap/cache/` are writable
- [ ] `LOG_STACK=daily` and `LOG_LEVEL=warning` (one file a day, the last 14 kept)
- [ ] The cron entry for the scheduler and the queue worker are running
- [ ] Mail works: real `MAIL_*` values, and `MAIL_FROM_ADDRESS` is not the placeholder
- [ ] Backups: `HELPDESK_BACKUP_ENCRYPTION_KEY` is set (and stored outside the server), a second copy or an off-server job exists, `helpdesk:backup` and `helpdesk:backup-verify` have run, and a restore was rehearsed ([backup-and-restore.md](backup-and-restore.md))
- [ ] Virus scanning works ([security.md](security.md)), or you decided to run without it
- [ ] `HELPDESK_QUEUE_WORKER_RUNNING` is blank (or the right value if the status check cannot see your worker)
- [ ] The framework caches are built: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] The shadow account's password was changed from the first one (Account Settings), and `SHADOW_PASSWORD` in `.env` was changed to match, so running its seeder again cannot put the old password back

## After each deploy

- [ ] Sign-in works
- [ ] Creating a ticket and replying works
- [ ] Assignment and notification emails are sent
- [ ] A queue worker is running
- [ ] `php artisan helpdesk:ops-status` shows no warnings

Data clean-up rules are in [data-retention.md](data-retention.md). Virus scanning is in [security.md](security.md).
