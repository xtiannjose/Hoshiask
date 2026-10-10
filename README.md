# Helpdesk

[![CI](https://github.com/xtiannjose/Helpdesk/actions/workflows/ci.yml/badge.svg)](https://github.com/xtiannjose/Helpdesk/actions/workflows/ci.yml)

A Laravel 13 help desk: clients raise tickets in a **client portal** (`/client`), staff work them in a **staff console** (`/admin`). The current version is in [`VERSION`](VERSION) (also shown by `php artisan helpdesk:ops-status`).

**Stack:** PHP 8.3+, Laravel 13, PostgreSQL (or MySQL/MariaDB), Blade + Alpine.js (CSP build) + Tailwind CSS 4, bundled by Vite 8.

## What it does

- Role-based sign-in (`client`, `technical`, `super_user`, `admin`, `shadow`) with username or email
- Ticket lifecycle (`open`, `in_progress`, `pending`, `resolved`, `closed`, `cancelled`) with policy gates, assignment, escalation, merge, SLA reminders and auto-close
- Replies with attachments (optional virus scanning), internal notes, canned responses, a knowledge base
- Reports with month and day drill-down, CSV and PDF export, saved views
- Audit trail (System Logs), online users, legal consent tracking, data retention clean-up
- Encrypted backups with verify and restore commands

## Quick start

```bash
composer install && npm install
cp .env.example .env            # PowerShell: Copy-Item .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve               # and `npm run dev` in a second terminal
```

Seeded accounts and Windows notes: [docs/setup.md](docs/setup.md).

## Everyday commands

| Task | Command |
|---|---|
| Run the PHP tests (about 2 minutes) | `php artisan test` |
| One test class | `php artisan test --filter=ClientTicketReopenTest` |
| JavaScript tests | `npm run test:unit` |
| Full gate (what CI runs) | `composer qa && npm run qa` |
| Plus browser tests | `npm run qa:full` |
| Fix PHP style | `vendor/bin/pint` |
| Static analysis | `composer analyse` |
| Runtime health | `php artisan helpdesk:ops-status` |

## Where things are

```text
app/Http/Controllers/   Admin/ = staff console, Client/ = client portal, shared ones at the top
app/Services/           business logic (controllers stay thin): Admin/, Backup/, Retention/, TicketImport/ ...
app/Policies/           record-level authorization
app/Models/ app/Enums/  Eloquent models; TicketStatus is the one source for status values
app/Console/Commands/   artisan commands: backup, retention, import, ops-status, alert emails
app/Support/            small helpers (SlaSettings, LikePattern, UploadLimits ...)
config/helpdesk.php     feature knobs (also security.php, retention.php, legal.php, observability.php)
routes/                 web.php (HTTP) and console.php (scheduler)
resources/views/        Blade: admin/, client/, auth/, emails/, layouts/, components/
resources/js/           app.js; lib/ = shared behaviours; pages/ = one module per page
database/               migrations and seeders (E2eSmokeSeeder is for browser tests)
tests/                  Feature/, Unit/, e2e/ (Playwright), Traits/ (CreatesTestData)
docs/                   all documentation, start at docs/README.md
scripts/                deploy and Windows helper scripts, backup decrypt tool
.github/                CI, CodeQL, Dependabot, issue and PR templates
.claude/                Claude Code hooks and settings
```

## Documentation

Start with [docs/README.md](docs/README.md). The short version:

| If you want to... | Read |
|---|---|
| Run it locally | [docs/setup.md](docs/setup.md) |
| Understand the words used in code and UI | [GLOSSARY.md](GLOSSARY.md) |
| Understand the workflow clients and staff follow | [docs/ticket-system-workflow.md](docs/ticket-system-workflow.md) |
| Change a setting | [docs/configuration.md](docs/configuration.md) |
| Deploy, schedule, back up, monitor | [docs/operations.md](docs/operations.md) |
| Run or add tests | [docs/testing.md](docs/testing.md) |
| Contribute | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Report a security problem | [SECURITY.md](SECURITY.md) |

## License

Private, internal project. No license file is included, so no right to copy or redistribute it is granted.
