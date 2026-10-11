# Configuration

Every setting is an environment variable in the `.env` file. `.env.example` lists all of them with safe starting values: copy it to `.env` and change what you need.

- After you change `.env` on a running server, run `php artisan config:clear`, then `php artisan queue:restart` so the queue worker also sees the change.
- The code reads settings through `config/helpdesk.php`, `security.php`, `retention.php`, `legal.php`, `observability.php` and Laravel's own config files. It never calls `env()` anywhere else.
- A new setting is added in three places: `.env.example`, its config file, and this page.
- Never put `.env` in git. It holds passwords.

## Set these first

| Setting | Why |
|---|---|
| `APP_KEY` | Run `php artisan key:generate`. It encrypts cookies, sessions and one-time passwords. Keep it secret. Changing it later signs everyone out. |
| `APP_URL` | The real address people type, with `https://` on a server. It is used in links inside emails. |
| `DB_*` | Your database (see [Database](#database)). |
| `STAFF_DEFAULT_PASSWORD`, `SHADOW_PASSWORD`, and `CLIENT_DEFAULT_PASSWORD` | Needed before `php artisan db:seed`. They are blank in `.env.example`, and the seeder stops with "Missing required password configuration" until you fill them in (see [Accounts and seeding](#accounts-and-seeding)). |
| `MAIL_*` | Needed for any email to leave the server (see [Mail](#mail)). While developing you can use `MAIL_MAILER=log`. |

For a production server also read the checklist in [operations.md](operations.md).

## App basics

| Setting | Example | What it does |
|---|---|---|
| `APP_NAME` | `"Helpdesk"` | The product name in page titles, the sign-in page and emails. |
| `APP_ENV` | `local` | `local` or `production`. **Production switches on** the enforced Content-Security-Policy, Secure cookies, the 5-a-minute sign-in limit (100 elsewhere), virus scanning by default, the production advisories in `helpdesk:ops-status`, and turns `mail:test` off. |
| `APP_KEY` | blank | See above. |
| `APP_DEBUG` | `false` | Shows error details in the browser. Never `true` on a server. |
| `APP_TIMEZONE` | `Asia/Manila` | The time zone for every date shown and for the daily jobs (backup 02:40, auto-close 03:40, and so on). |
| `APP_URL` | `http://localhost:8000` | See above. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `en` | The language of built-in messages. |
| `APP_FAKER_LOCALE` | `en_US` | Only used to make sample data in seeders and tests. |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | `file`, `database` | Where `php artisan down` keeps its on/off flag. Leave as they are. |
| `BCRYPT_ROUNDS` | `12` | How slow it is to hash a password. Higher is stronger and slower. |

## Branding

What people see as the name and logo of the support team.

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_SUPPORT_DEPARTMENT` | `"Hoshishi"` | The department that is the support team. New staff accounts start in it. |
| `HELPDESK_SUPPORT_BRAND_NAME` | `"Hoshishi"` | The brand name used in support-facing text. |
| `HELPDESK_SUPPORT_ORGANIZATION_NAME` | `"Hoshishi Corporation"` | The organisation name. It is also the default for `LEGAL_ORGANIZATION_NAME`. |
| `HELPDESK_SUPPORT_TEAM_NAME` | `"Hoshishi Technical Team"` | The name clients see instead of the hidden shadow account. |
| `HELPDESK_SUPPORT_LOGO_PATH` | `"images/Hoshishi Logo.png"` | A file under `public/`. When it does not exist the default logo is used. |

## Database

| Setting | Example | What it does |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | `pgsql` (PostgreSQL, used in production), `mysql` or `sqlite`. |
| `DB_URL` | blank | A single address that replaces the next five lines, if you prefer one. |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `5432` | Where the database server is. |
| `DB_DATABASE` | `hoshishi_ticketing` | The database name (for SQLite, the path of the file). |
| `DB_USERNAME`, `DB_PASSWORD` | `postgres` | The database login. Use your own values. |
| `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION` | blank, `utf8`, `utf8_unicode_ci` | Only for MySQL or MariaDB. |
| `DB_FOREIGN_KEYS` | `true` | SQLite only. |
| `MYSQL_ATTR_SSL_CA` | blank | MySQL only: a certificate file for an encrypted connection. |

## Sessions, cookies and proxies

| Setting | Example | What it does |
|---|---|---|
| `SESSION_DRIVER` | `database` | Where sessions are kept (the `sessions` table). |
| `SESSION_CONNECTION`, `SESSION_STORE` | blank | Only for another connection or cache store. |
| `SESSION_LIFETIME` | `120` | Minutes without activity before a session ends. Staff who tick "stay signed in" stay for 60 days. |
| `SESSION_EXPIRE_ON_CLOSE` | `true` | End the session when the browser closes. |
| `SESSION_ENCRYPT` | `true` | Encrypt what the session stores. |
| `SESSION_PATH`, `SESSION_DOMAIN` | `/`, `null` | Cookie scope. Leave as they are. |
| `SESSION_SECURE_COOKIE` | blank | `true` sends cookies over HTTPS only. Blank means "follow `APP_ENV`": on in production, off elsewhere. |
| `TRUSTED_PROXIES` | `127.0.0.1,::1` | Addresses of reverse proxies or load balancers that may tell the app the real client address and whether the visit was HTTPS. Use `*` to trust all. **Set it correctly behind a proxy**, or every person looks like the proxy and shares one sign-in lockout. |
| `SECURITY_FORCE_CSP` | `false` | Production always enforces the Content-Security-Policy. Set `true` to enforce it elsewhere too. Otherwise it is only reported in the browser console. |

## Mail

| Setting | Example | What it does |
|---|---|---|
| `MAIL_MAILER` | `smtp` | `smtp` sends real email. `log` writes emails to the log instead (good for development). |
| `MAIL_HOST`, `MAIL_PORT` | `smtp.gmail.com`, `587` | Your mail server. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | placeholders | Its login. |
| `MAIL_ENCRYPTION` | `tls` | `tls`, `ssl`, or blank for none. |
| `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL` | blank | Rarely needed. `MAIL_URL` replaces the host, port and login lines with one address. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `"helpdesk@yourdomain.com"` | The sender shown on every email. Change the placeholder: `helpdesk:ops-status` warns while it is still there. |
| `MAIL_REPLY_TO_ADDRESS`, `MAIL_REPLY_TO_NAME` | `"support@yourdomain.com"` | Where a client's emailed reply goes. The app does not read incoming mail, so replies never enter the ticket. |

Emails are queued, so a queue worker must run (see [operations.md](operations.md)).

## Logging

| Setting | Example | What it does |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Leave as it is. |
| `LOG_STACK` | `daily` | `daily` starts a new log file every day and keeps 14 (change with `LOG_DAILY_DAYS`). `single` makes one file that grows forever. |
| `LOG_LEVEL` | `warning` | The app only logs warnings and errors, so this loses nothing and keeps files small. |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Leave as it is. |

## Queue, cache and files

| Setting | Example | What it does |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Where queued jobs (emails) wait. `sync` runs them at once with no worker: handy for development. |
| `CACHE_STORE`, `CACHE_PREFIX` | `database`, blank | Where the cache lives and a prefix for its keys. |
| `FILESYSTEM_DISK` | `local` | The default disk. `local` is the private folder `storage/app/private`. |
| `BROADCAST_CONNECTION` | `log` | Not used by this app. |
| `HELPDESK_QUEUE_WORKER_RUNNING` | blank | Leave blank: `helpdesk:ops-status` looks for a running `queue:work` process. Set `true` or `false` only when it cannot see your worker, for example one that runs in another container. |

## Accounts and seeding

These are used by `php artisan db:seed` and by account management. The seeder refuses to run when one it needs is blank.

| Setting | Example | What it does |
|---|---|---|
| `STAFF_DEFAULT_PASSWORD` | blank | Starting password of the seeded `admin`, `super.user` and `technical` accounts. They must change it at first sign-in. |
| `SHADOW_PASSWORD` | blank | Password of the hidden `shadow` account. Use a long, unique one. |
| `CLIENT_PASSWORD_MODE` | `fixed` | `fixed`: every seeded client gets `CLIENT_DEFAULT_PASSWORD`. `random`: each gets a random password written once to a private file. |
| `CLIENT_DEFAULT_PASSWORD` | blank | Needed when the mode is `fixed`. |
| `SEED_CLIENT_CREDENTIALS_DISK`, `SEED_CLIENT_CREDENTIALS_PATH` | `local`, `seeded-client-credentials` | Where the random client passwords are written. (The old name `HELPDESK_SEEDED_CLIENT_CREDENTIALS_PATH` still works.) |
| `HELPDESK_SEEDED_CLIENT_CREDENTIAL_RETENTION_DAYS` | `14` | Days the daily clean-up keeps that file. |
| `HELPDESK_PASSWORD_HELP_CONTACT` | blank | An email, phone number or name shown as "Forgot your password? Contact your administrator at ...". Blank shows only "Contact your administrator". |

## Tickets and SLA

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_REPLY_EDIT_WINDOW_MINUTES` | `180` | How long a client can edit or delete their own message. |
| `HELPDESK_SLA_RESOLUTION_TARGET_MINUTES` | `240` | The time a ticket should take. The SLA Breached tab and the reminders measure against it. |
| `HELPDESK_SLA_ASSIGNMENT_REMINDER_MINUTES` | `210` | The assigned technicians are reminded this long after a ticket was assigned to them, if it is still active. |
| `HELPDESK_SLA_SUPER_USER_NEW_TICKET_NOTIFY` | `true` | Email super users, admins and the shadow account when a new ticket arrives. |
| `HELPDESK_SLA_SUPER_USER_UNCHECKED_MINUTES` | `120` | Super users, admins and the shadow account get an "unchecked ticket" alert when an active ticket stays unassigned this long. |
| `HELPDESK_SLA_SUPER_USER_UNASSIGNED_SLA_MINUTES` | `180` | The "unassigned SLA warning" for the same situation, at a later point. |
| `HELPDESK_SLA_RESOLUTION_UNDER_ONE_HOUR_MINUTES` | `60` | The first band limit of the Resolution Time chart on the Reports page. |
| `HELPDESK_SLA_RESOLUTION_UNDER_FOUR_HOURS_MINUTES` | `240` | The second band limit of that chart. |
| `HELPDESK_SLA_RESOLUTION_UNDER_TWENTY_FOUR_HOURS_MINUTES` | `1440` | The third band limit of that chart. |
| `HELPDESK_ASSIGNMENT_ROTATION_WINDOW_DAYS` | `30` | The "rotating counter" assignment compares each technician's share of a category over this many days. |
| `HELPDESK_CANNED_REPLIES_ENABLED` | `true` | `false` hides canned responses everywhere without touching the database. |

## Emails to clients

One email per milestone, all in one conversation in the client's inbox. See "Emails to the client" in the [guide](ticket-system-workflow.md).

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_CLIENT_EMAIL_RECEIVED` | `true` | "We received your ticket". |
| `HELPDESK_CLIENT_EMAIL_RESOLVED` | `true` | "Your ticket has been resolved". |
| `HELPDESK_CLIENT_EMAIL_AUTO_CLOSE_WARNING` | `true` | The reminder two days before an automatic close. |
| `HELPDESK_CLIENT_EMAIL_CLOSED` | `false` | "Closed" or "cancelled" notices. |
| `HELPDESK_CLIENT_EMAIL_REPLY_BATCH_MINUTES` | `5` | Replies that staff post within this many minutes become one email per ticket. `0` sends every reply at once. Needs the scheduler and a queue worker. |

## Uploads and virus scanning

Details and the ClamAV set-up are in [security.md](security.md).

| Setting | Example | What it does |
|---|---|---|
| `ATTACHMENTS_DISK` | `local` | Where attachments are stored. `local` is private: files are only sent after a permission check. |
| `HELPDESK_UPLOAD_SCAN` | blank | `clamav` scans every upload. `off` does not. Blank means `clamav` in production and `off` elsewhere. Scanning only works once ClamAV is installed. |
| `HELPDESK_CLAMAV_SOCKET` | blank | The ClamAV socket file, for example `/var/run/clamav/clamd.ctl`. It wins over host and port. |
| `HELPDESK_CLAMAV_HOST`, `HELPDESK_CLAMAV_PORT` | `127.0.0.1`, `3310` | Where ClamAV listens when you use the network instead of a socket. |
| `HELPDESK_UPLOAD_SCAN_TIMEOUT` | `10` | Seconds to wait for the scanner. |
| `HELPDESK_UPLOAD_SCAN_ON_ERROR` | `allow` | What to do while the scanner cannot be reached: `allow` the upload (and log it) or `block` it. |

## Monitoring

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_HEALTH_DETAIL_TOKEN` | blank | Without it `/health` answers only `ok`, `degraded` or `failed`. With it, a request that sends the token gets the details. |
| `HELPDESK_PRESENCE_ONLINE_WINDOW_MINUTES` | `5` | The Online Users page lists people who were active in the last this many minutes. |
| `HELPDESK_PRESENCE_ACTIVE_WINDOW_MINUTES` | `2` | Online people quieter than this are shown as idle. |
| `OBSERVABILITY_SLOW_QUERY_ENABLED` | blank | Log slow database queries. Blank means on in `staging` and `production`, off elsewhere. |
| `OBSERVABILITY_SLOW_QUERY_THRESHOLD_MS` | `250` | How many milliseconds make a query slow. |
| `OBSERVABILITY_SLOW_QUERY_INCLUDE_BINDINGS` | `false` | Also log the values in the query. These can contain personal data, so leave it off. |
| `OBSERVABILITY_SLOW_QUERY_TO_SYSTEM_LOGS` | `false` | Also write slow queries to System Logs. |
| `OBSERVABILITY_SYSTEM_LOG_RETENTION_DAYS` | `365` | The daily clean-up deletes System Logs entries older than this. `0` keeps them for ever. |

## Backups

Step by step in [backup-and-restore.md](backup-and-restore.md).

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_BACKUP_DISK`, `HELPDESK_BACKUP_PATH` | `local`, `backups` | Where backups are written (`storage/app/private/backups`). |
| `HELPDESK_BACKUP_ENCRYPTION_KEY` | blank | From `php artisan helpdesk:backup-key`. Without it backups are not encrypted and never copied away. Keep a copy **outside** the server. |
| `HELPDESK_BACKUP_RETENTION_DAYS` | `14` | The daily clean-up removes backups older than this. The newest three are always kept. |
| `HELPDESK_BACKUP_COPY_PATH` | blank | A folder on a second disk or mounted drive. Each encrypted backup is copied there and checked. |
| `HELPDESK_BACKUP_OFFSITE_CONFIRMED` | `false` | Set `true` when your own job (rsync, rclone) copies backups off the server, so the status check stops warning. |

## Data retention

Details in [data-retention.md](data-retention.md).

| Setting | Example | What it does |
|---|---|---|
| `HELPDESK_RETENTION_ENABLED` | `false` | The clean-up that removes personal details from old tickets does nothing until this is `true`. |
| `HELPDESK_RETENTION_YEARS` | `3` | Years after a ticket was closed or cancelled before its personal details are removed. Keep it in step with `LEGAL_RETENTION_PERIOD`. |

## Legal text

These fill the Terms, Privacy Notice and Ticket Consent pages. When you change a text, change its version too: everyone must then accept the new version the next time they sign in.

| Setting | Example | What it does |
|---|---|---|
| `LEGAL_REQUIRE_ACCEPTANCE` | `true` | `false` turns the acceptance screen off. Do not do this in production. |
| `LEGAL_TERMS_VERSION`, `LEGAL_PRIVACY_VERSION`, `LEGAL_PLATFORM_CONSENT_VERSION`, `LEGAL_TICKET_CONSENT_VERSION` | `2026-02-25` | The version of each text. |
| `LEGAL_EFFECTIVE_DATE` | `"February 25, 2026"` | The date shown on the texts. |
| `LEGAL_ORGANIZATION_NAME` | `"Hoshishi Corporation"` | The organisation in the texts. |
| `LEGAL_GOVERNING_LAW` | `"Republic of the Philippines"` | The law the terms refer to. |
| `LEGAL_DPO_EMAIL`, `LEGAL_SUPPORT_EMAIL` | placeholders | The contacts printed in the texts. Change them. |
| `LEGAL_CONTACT_ADDRESS` | `"Pasig City, ..."` | The postal address printed in the texts. |
| `LEGAL_RETENTION_PERIOD` | `"3 years from ticket closure ..."` | The sentence about how long data is kept. It must say what the system really does. |

## Importing tickets

Details in [ticket-import.md](ticket-import.md).

| Setting | Example | What it does |
|---|---|---|
| `TICKET_IMPORT_DISK`, `TICKET_IMPORT_PATH` | `local`, `imports` | Where import files are put. (The old name `HELPDESK_IMPORT_RETENTION_PATH` still works.) |
| `TICKET_IMPORT_TIMEZONE` | `Asia/Manila` | The time zone of dates in import files that have none. |
| `HELPDESK_IMPORT_RETENTION_DAYS` | `7` | Days the daily clean-up keeps uploaded import files. |

## Optional drivers

Only needed when `CACHE_STORE`, `SESSION_DRIVER` or `QUEUE_CONNECTION` is changed to Redis, Memcached or DynamoDB, or files are stored on S3. They are commented out at the bottom of `.env.example`: `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, `MEMCACHED_HOST`, `MEMCACHED_PORT`, `DYNAMODB_CACHE_TABLE`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`. Laravel also reads a few more for these drivers (`REDIS_URL`, `AWS_URL`, ...); see its documentation.
