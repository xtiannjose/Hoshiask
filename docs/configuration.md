# Configuration

Every setting is an environment variable read from `.env`. `.env.example` lists them all with safe defaults; the code reads them through `config/helpdesk.php`, `security.php`, `retention.php`, `legal.php` and `observability.php`. A new variable also goes in `.env.example` and in this list.

User identity behavior:

- `username` is the primary sign-in identifier
- `name` is the display name shown across the app
- Admin user management exposes both fields separately
- Login accepts `username` or `email`, not display name

These project variables are used by seeders and account workflows:

- `STAFF_DEFAULT_PASSWORD`
- `CLIENT_PASSWORD_MODE` (`fixed` or `random`)
- `CLIENT_DEFAULT_PASSWORD` (used when `CLIENT_PASSWORD_MODE=fixed`)
- `SHADOW_PASSWORD`
- `ATTACHMENTS_DISK` (defaults to `local`, keeps uploads in private storage)
- `HELPDESK_UPLOAD_SCAN` (blank follows `APP_ENV`: `clamav` in production, `off` elsewhere. `clamav` sends every uploaded attachment to ClamAV before it is accepted, see Upload scanning. Set `off` or `clamav` to decide explicitly. An old `.env` that still says `HELPDESK_UPLOAD_SCAN=off` stays off)
- `HELPDESK_CLAMAV_SOCKET`, or `HELPDESK_CLAMAV_HOST` (defaults to `127.0.0.1`) and `HELPDESK_CLAMAV_PORT` (defaults to `3310`): where ClamAV listens; a socket path wins
- `HELPDESK_UPLOAD_SCAN_TIMEOUT` (defaults to `10` seconds)
- `HELPDESK_UPLOAD_SCAN_ON_ERROR` (defaults to `allow`; `block` refuses uploads while the scanner cannot be reached)
- `HELPDESK_BACKUP_DISK` (defaults to `local`)
- `HELPDESK_BACKUP_PATH` (defaults to `backups`)
- `HELPDESK_BACKUP_ENCRYPTION_KEY` (from `php artisan helpdesk:backup-key`; without it backups are not encrypted and never copied away. Keep it outside the server too)
- `HELPDESK_BACKUP_COPY_PATH` (optional folder on a second disk or mounted drive; each encrypted backup is copied there and checked)
- `HELPDESK_BACKUP_OFFSITE_CONFIRMED` (defaults to `false`; set `true` when your own rsync/rclone job copies backups away)
- `HELPDESK_HEALTH_DETAIL_TOKEN` (required to see detailed `/health` diagnostics)
- `SEED_CLIENT_CREDENTIALS_DISK` (defaults to `local`)
- `SEED_CLIENT_CREDENTIALS_PATH` (defaults to `seeded-client-credentials`; the older name `HELPDESK_SEEDED_CLIENT_CREDENTIALS_PATH` is still honoured so existing `.env` files keep working)
- `TICKET_IMPORT_DISK` (defaults to `local`)
- `TICKET_IMPORT_PATH` (defaults to `imports`; the older name `HELPDESK_IMPORT_RETENTION_PATH` is still honoured so existing `.env` files keep working)
- `TICKET_IMPORT_TIMEZONE` (defaults to `APP_TIMEZONE`)
- `HELPDESK_SLA_RESOLUTION_TARGET_MINUTES` (defaults to `240`)
- `HELPDESK_SLA_ASSIGNMENT_REMINDER_MINUTES` (defaults to `210`)
- `HELPDESK_SLA_RESOLUTION_UNDER_ONE_HOUR_MINUTES` (defaults to `60`)
- `HELPDESK_SLA_RESOLUTION_UNDER_FOUR_HOURS_MINUTES` (defaults to `240`)
- `HELPDESK_SLA_RESOLUTION_UNDER_TWENTY_FOUR_HOURS_MINUTES` (defaults to `1440`)
- `HELPDESK_PASSWORD_HELP_CONTACT` (optional; an email address, phone number or name shown as "Forgot your password? Contact your administrator at ..." on the sign-in page and in the lockout message. Left empty, the line says only "Contact your administrator")
- `HELPDESK_CLIENT_EMAIL_RECEIVED`, `HELPDESK_CLIENT_EMAIL_RESOLVED`, `HELPDESK_CLIENT_EMAIL_AUTO_CLOSE_WARNING` (default `true`) and `HELPDESK_CLIENT_EMAIL_CLOSED` (default `false`) switch each client-facing email on or off
- `HELPDESK_CLIENT_EMAIL_REPLY_BATCH_MINUTES` (defaults to `5`; agent replies posted within this window are merged into one email per ticket, `0` sends each immediately. Needs the scheduler and a queue worker running)
- `HELPDESK_SLA_SUPER_USER_NEW_TICKET_NOTIFY` (defaults to `true`; email the super users when a new ticket is submitted)
- `HELPDESK_SLA_SUPER_USER_UNCHECKED_MINUTES` (defaults to `120`; minutes an active ticket may stay unassigned before the super users get an "unchecked ticket" alert)
- `HELPDESK_SLA_SUPER_USER_UNASSIGNED_SLA_MINUTES` (defaults to `180`; minutes an active ticket may stay unassigned before the super users get an "unassigned SLA warning")
- `HELPDESK_SUPPORT_DEPARTMENT`, `HELPDESK_SUPPORT_BRAND_NAME`, `HELPDESK_SUPPORT_ORGANIZATION_NAME` and `HELPDESK_SUPPORT_TEAM_NAME` (branding: the support department's name, the product name, and the organisation and team names shown to users; all default to the Hoshishi values in `config/helpdesk.php`)
- `HELPDESK_SUPPORT_LOGO_PATH` (defaults to `images/Hoshishi Logo.png`; a file under `public/`, the app falls back to the default logo when it does not exist)
- `HELPDESK_BACKUP_RETENTION_DAYS` (defaults to `14`; the daily runtime clean-up deletes backup files older than this, but always keeps the newest three)
- `HELPDESK_SEEDED_CLIENT_CREDENTIAL_RETENTION_DAYS` (defaults to `14`) and `HELPDESK_IMPORT_RETENTION_DAYS` (defaults to `7`): days the daily runtime clean-up keeps the seeded-client credential exports and the uploaded import files
- `HELPDESK_CANNED_REPLIES_ENABLED` (defaults to `true`; `false` hides canned replies without a code change)

Legal/consent behavior is versioned and configurable using:

- `LEGAL_REQUIRE_ACCEPTANCE`
- `LEGAL_TERMS_VERSION`
- `LEGAL_PRIVACY_VERSION`
- `LEGAL_PLATFORM_CONSENT_VERSION`
- `LEGAL_TICKET_CONSENT_VERSION`
- `LEGAL_DPO_EMAIL`
- `LEGAL_SUPPORT_EMAIL`
- `LEGAL_EFFECTIVE_DATE`
- `LEGAL_ORGANIZATION_NAME`
- `LEGAL_GOVERNING_LAW`
- `LEGAL_CONTACT_ADDRESS`
- `LEGAL_RETENTION_PERIOD`
- `HELPDESK_RETENTION_ENABLED` (defaults to `false`; the clean-up that removes personal details from old tickets does nothing until this is `true`, see Data Retention)
- `HELPDESK_RETENTION_YEARS` (defaults to `3`; years after a ticket was closed or cancelled before its personal details are removed)

Security, maintenance, and observability variables:

- `SECURITY_FORCE_CSP` (defaults to `false`; enforces the Content-Security-Policy header instead of sending it Report-Only)
- `TRUSTED_PROXIES` (comma-separated proxy IPs/CIDRs allowed to set `X-Forwarded-*` headers, or `*`; defaults to `127.0.0.1,::1`)
- `HELPDESK_REPLY_EDIT_WINDOW_MINUTES` (defaults to `180`; how long clients can edit or delete their own messages)
- `HELPDESK_PRESENCE_ONLINE_WINDOW_MINUTES` (defaults to `5`; how recently a session must have made a request to count as online in the shadow Online Users monitor)
- `HELPDESK_PRESENCE_ACTIVE_WINDOW_MINUTES` (defaults to `2`; online sessions quieter than this are shown as idle)
- `APP_MAINTENANCE_DRIVER` (defaults to `file`)
- `APP_MAINTENANCE_STORE` (defaults to `database`)
- `HELPDESK_QUEUE_WORKER_RUNNING` (set by the queue-worker deploy scripts; read by `/health` and ops-status checks)
- `OBSERVABILITY_SLOW_QUERY_ENABLED`
- `OBSERVABILITY_SLOW_QUERY_THRESHOLD_MS` (defaults to `250`)
- `OBSERVABILITY_SLOW_QUERY_INCLUDE_BINDINGS` (defaults to `false`)
- `OBSERVABILITY_SLOW_QUERY_TO_SYSTEM_LOGS` (defaults to `false`)
- `OBSERVABILITY_SYSTEM_LOG_RETENTION_DAYS` (defaults to `365`)

Optional infra/config variables (Redis/Memcached/DynamoDB/S3 keys are listed, commented out, at the bottom of `.env.example`):

- `DB_URL`, `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_FOREIGN_KEYS`
- `MYSQL_ATTR_SSL_CA`
- `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT`
- `MEMCACHED_PERSISTENT_ID`, `MEMCACHED_USERNAME`, `MEMCACHED_PASSWORD`, `MEMCACHED_PORT`
- `SESSION_CONNECTION`, `SESSION_STORE`
- `REDIS_URL`, `REDIS_PREFIX`, `REDIS_USERNAME`, `REDIS_CLUSTER`, `REDIS_DB`, `REDIS_CACHE_DB`
- `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL`
