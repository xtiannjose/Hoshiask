# Operations: deploy, run and monitor

Everything a server needs besides the code. Setup of a development machine is in [setup.md](setup.md); every setting is in [configuration.md](configuration.md).

## Deploy

For server deployments, update dependencies and rebuild frontend assets whenever `package-lock.json`, frontend source files under `resources/`, or Vite/Tailwind dependencies change:

```bash
php scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader
npm ci
npm run build
rm -rf node_modules
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan helpdesk:backup-database
php artisan queue:restart
php artisan helpdesk:ops-status
```

Windows PowerShell deploy helper:

```powershell
./scripts/deploy-helpdesk.ps1
```

That script only deploys inside `/opt/helpdesk` on the configured VPS and runs the shared remote deploy script in `scripts/deploy-helpdesk-remote.sh`.
The remote deploy script now fails fast when the server PHP version does not satisfy `composer.json`, removes `node_modules` after bundling frontend assets, and retries the final `helpdesk:ops-status --fail-on-warning` health check after restarting the queue worker.
Both deploy helpers are intentionally locked to `/opt/helpdesk` so they do not operate on any other app path on the VPS.
The remote deploy helper also refuses to run against a dirty server-side working tree so production is not used as an ad hoc edit workspace.

## Scheduler (required for alerts, backups and auto-close)

Development:

```bash
php artisan schedule:work
```

Production cron (every minute):

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

`php artisan helpdesk:ops-status` tells you when this cron entry is not running: the five-minute alert command leaves a heartbeat, and an old or missing heartbeat shows up as an advisory.

## Queue worker (required for queued emails)

Ticket alert emails are queued when `QUEUE_CONNECTION` is not `sync`, so production needs a long-running worker in addition to the scheduler.

Development worker:

```bash
php artisan queue:work
```

Windows helpers, for running the worker on a Windows PC during local testing (production on Linux uses the systemd example below):

```powershell
.\scripts\start-helpdesk-queue-worker.ps1          # start this project's worker in the background (no-op if already running)
.\scripts\register-helpdesk-queue-worker-task.ps1  # start it automatically at logon ("Helpdesk Queue Worker" task)
```

These only cover the queue worker. Reminder emails, nightly backups and auto-close also need the scheduler, so keep `php artisan schedule:work` running while you test. Workers started by older versions of the start script aren't recognised as "already running"; stop them first.

Production worker example:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

Systemd example:

- [helpdesk-queue-worker.service.example](helpdesk-queue-worker.service.example)

After each deploy, restart workers so they pick up the new code:

```bash
php artisan queue:restart
php artisan helpdesk:ops-status
```

## Backups (required for production)

Every night at 02:40 the scheduler makes one encrypted file holding the database and every uploaded file, checks it, and copies it to the second location if one is set. Every Sunday it checks the newest backup again.

```bash
php artisan helpdesk:backup-key        # once: print a new key; store it OUTSIDE the server, set HELPDESK_BACKUP_ENCRYPTION_KEY
php artisan helpdesk:backup            # make a backup now (what the scheduler runs)
php artisan helpdesk:backup-verify     # check the newest backup (--copy: the newest in HELPDESK_BACKUP_COPY_PATH)
php artisan helpdesk:backup-restore FILE EMPTY_FOLDER   # unpack a backup and print the commands that put it back
```

Setup, the second copy, restoring on a new server and a quarterly rehearsal are described step by step in [backup-and-restore.md](backup-and-restore.md). PostgreSQL backups need `pg_dump` on the server (MySQL/MariaDB: `mysqldump`). `php artisan helpdesk:backup-database` still exports only the database; the deploy script uses it before migrating.

## Email delivery (external SMTP)

Set mail values in `.env`:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=helpdesk@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
MAIL_REPLY_TO_ADDRESS=support@yourdomain.com
MAIL_REPLY_TO_NAME="${APP_NAME}"
```

Then clear config cache and verify mail from a non-production environment:

```bash
php artisan config:clear
php artisan mail:test your-email@example.com
```

In production, `mail:test` is intentionally disabled to prevent accidental mail floods. Use normal ticket assignment notifications or a staging/local environment to validate SMTP changes.

## Status and health checks

Use this command any time you want a quick production-facing status check:

```bash
php artisan helpdesk:ops-status
```

It reports:

- Current PHP version versus the `composer.json` PHP requirement
- Queue connection mode and whether a worker is required
- Pending and failed job counts
- Active mailer and scheduled alert command summary
- Advisories (production only): settings that leave the site up but quietly switch something off, such as `MAIL_MAILER=log` (no email is sent), `LOG_STACK=single` (one log file that is never rotated), `APP_TIMEZONE=UTC`, or a scheduler that has not run for 15 minutes. They are listed under "Advisories", appear in the detailed `/health` output, and never fail a deploy or `--fail-on-warning`

To fail a script when warnings are present:

```bash
php artisan helpdesk:ops-status --fail-on-warning
```

For automation-friendly output:

```bash
php artisan helpdesk:ops-status --json
```

For HTTP-based monitoring, the app also exposes a lightweight public health endpoint:

```text
GET /health
```

It returns a minimal public JSON payload with `ok`, `degraded`, or `failed` status. Detailed database, queue, and warning diagnostics are only returned when `HELPDESK_HEALTH_DETAIL_TOKEN` is configured and the request includes either `Authorization: Bearer <token>` or `X-Health-Token: <token>` (query-string tokens are not accepted, since URLs are commonly logged). The endpoint returns HTTP `503` only for critical failures such as database connectivity or unsupported PHP runtime.

## Sign-in lockout

Eight wrong passwords for one login name lock that login for 15 minutes **for the address that typed them**. Someone guessing from another address cannot lock the real person out, and the real person, signing in from their own address, is not affected. The sign-in route also limits each address to 5 attempts a minute in production.

To lift a lock early:

- The shadow account: Online Users, "Clear lockout" (clears it from every address).
- On the server, for example when the shadow account itself is locked: `php artisan helpdesk:unlock-login <username or email>`. It clears the lock from every address and writes `auth.lockout_cleared` to System Logs.

The trade-off: a guesser who has many different addresses gets eight tries on each. Strong passwords are the defence against that.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` is the real HTTPS URL and `SESSION_SECURE_COOKIE=true`
- [ ] Strong, environment-specific secrets (`APP_KEY`, database and mail credentials, `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD`, `SHADOW_PASSWORD`)
- [ ] `HELPDESK_HEALTH_DETAIL_TOKEN` is set
- [ ] The web root points to `public/`; `storage/` and `bootstrap/cache/` are writable
- [ ] `LOG_STACK=daily` and `LOG_LEVEL=warning` (one file per day, the last 14 kept)
- [ ] Backups: `HELPDESK_BACKUP_ENCRYPTION_KEY` set (key stored outside the server), a second copy or off-server job, `php artisan helpdesk:backup` and `helpdesk:backup-verify` run, a restore rehearsed ([backup-and-restore.md](backup-and-restore.md))
- [ ] Framework caches built: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## After each deploy

- [ ] Sign-in works
- [ ] Creating a ticket and replying works
- [ ] Assignment and notification emails are sent
- [ ] A queue worker is running if `QUEUE_CONNECTION` is not `sync`
- [ ] `php artisan helpdesk:ops-status` shows no warnings

Data clean-up rules are in [data-retention.md](data-retention.md); optional virus scanning is in [security.md](security.md).