# Hoshiask

[![CI](https://github.com/xtiannjose/Hoshiask/actions/workflows/ci.yml/badge.svg)](https://github.com/xtiannjose/Hoshiask/actions/workflows/ci.yml)

A help desk for an organisation with field offices. **Clients** file support tickets in a **client portal** (`/client`). **Staff** work them in a **staff console** (`/admin`): they assign, answer, escalate, close and report on tickets. The version is in [`VERSION`](VERSION) and is also shown by `php artisan hoshiask:ops-status`.

**Built with:** PHP 8.3, Laravel 13, PostgreSQL (MySQL or SQLite also work), Blade, Alpine.js, Tailwind CSS 4 and Vite 8.

## What it does

- **Sign-in** with a username or email, for five roles: `client`, `technical`, `super_user`, `admin` and `shadow` (a hidden monitoring account).
- **Tickets** that move through Open, In Progress, Pending, Resolved, Closed and Cancelled, with assignment to one or more staff, escalation, merging duplicates, bulk actions, SLA reminders, automatic closing of resolved tickets and automatic cancelling of Pending tickets that nobody has touched for 14 days.
- **Conversation** on each ticket: public replies, internal notes, attachments (with optional virus scanning) and canned responses.
- **Emails** to clients at the moments that need their attention, and reminders to staff.
- **Knowledge base** of FAQs, policies and guides.
- **Reports** by month, a custom report, CSV and PDF export, saved ticket list views, and a dashboard.
- **Control and privacy:** an audit trail (System Logs), an online users monitor, terms and consent tracking, and a clean-up that removes personal details from old tickets.
- **Safety:** encrypted backups with verify and restore commands, and a health check.

## Quick start

You need PHP 8.3+, Composer, Node.js 22.12+ and a database. The full steps, with every choice explained, are in [docs/setup.md](docs/setup.md). The short version:

```bash
composer install && npm install
cp .env.example .env            # PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Then open `.env` and set two things (details in [docs/setup.md](docs/setup.md)):

1. The database: the `DB_*` lines.
2. **Three passwords**: `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD` and `SHADOW_PASSWORD`. They are blank in `.env.example`, and the next command stops until you fill them in.

```bash
php artisan migrate --seed
php artisan storage:link
npm run build                   # PowerShell: npm.cmd run build
php artisan serve               # then open http://127.0.0.1:8000/login
```

The seeded accounts (`admin`, `super.user`, `technical`, `shadow`, `client.one`, `client.two`) and their passwords are listed in [docs/setup.md](docs/setup.md#sign-in).

## Everyday commands

| Task | Command |
|---|---|
| Run the PHP tests (about 3 minutes) | `php artisan test` |
| The same, faster, across all CPU cores | `php artisan test --parallel` |
| One test class | `php artisan test --filter=ClientTicketReopenTest` |
| JavaScript tests | `npm run test:unit` |
| Everything CI checks | `composer qa && npm run qa` |
| The same plus the browser tests | `npm run qa:full` (read [docs/testing.md](docs/testing.md) first) |
| Fix PHP code style | `vendor/bin/pint` |
| Static analysis | `composer analyse` |
| Check the health of a running system | `php artisan hoshiask:ops-status` |

## Where things are

The main folders are `app/` (the code), `resources/` (pages, styles, scripts), `routes/`, `database/`, `config/`, `tests/`, `docs/` and `scripts/`. [docs/structure.md](docs/structure.md) explains each folder, how a request travels through the code, who may do what, and where to change what.

## Documentation

Everything is indexed in [docs/README.md](docs/README.md). Start here:

| If you want to... | Read |
|---|---|
| Run it on your computer | [docs/setup.md](docs/setup.md) |
| Find your way around the code | [docs/structure.md](docs/structure.md) |
| Learn how clients and staff work a ticket | [docs/ticket-system-workflow.md](docs/ticket-system-workflow.md) |
| Understand the words used in code and screens | [GLOSSARY.md](GLOSSARY.md) |
| Change a setting | [docs/configuration.md](docs/configuration.md) |
| Schedule, back up and monitor a server | [docs/operations.md](docs/operations.md) |
| Run or write tests | [docs/testing.md](docs/testing.md) |
| Make a change and get it merged | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Report a security problem | [SECURITY.md](SECURITY.md) |
| See where the project stands and what is next | [docs/agents/plan.md](docs/agents/plan.md) |

## License

Private, internal project. No license file is included, so no right to copy or redistribute it is granted.
