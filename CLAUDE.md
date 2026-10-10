# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Helpdesk: Laravel 13 / PHP 8.3 ticketing system with a client portal (`/client`) and a staff console (`/admin`). Blade + Tailwind 4 + Alpine (CSP build), bundled by Vite. Production runs PostgreSQL.

## Commands

```bash
composer qa && npm run qa     # the full gate CI runs; add `npm run qa:full` (Playwright) when UI behaviour changes
php artisan test --filter=ClientTicketReopenTest      # one class, or a test_ method name
npx vitest run resources/js/lib/upload-guard.test.js
npx playwright test tests/e2e/admin-smoke.spec.js     # starts `php artisan serve` itself
vendor/bin/pint                                       # fix style
php artisan helpdesk:ops-status                       # runtime health and advisories
```

In PowerShell, call `npm.cmd` when script execution is restricted.

PHPUnit runs on in-memory SQLite; CI also runs the suite on PostgreSQL 16, so every query must work on SQLite, PostgreSQL and MySQL. Date grouping goes through `App\Support\DatabaseDateExpressions`, LIKE through `App\Support\LikePattern`.

## Architecture

**Roles.** `client`, `technical`, `super_user`, `admin`, `shadow` (hidden top-level monitor account). Role groups are constants on `App\Models\User` (`TICKET_CONSOLE_ROLES`, `ADMIN_LEVEL_ROLES`, ...) and routes reference them as `role:ticket_console` via `RoleMiddleware::ROLE_GROUPS`; change access by editing the group in `User`. Record-level rules live in `app/Policies`. Client-facing controllers authorize through `authorizeHidingFromClients()`, which answers 404 instead of 403 so clients cannot probe which tickets exist.

**Services own the logic; controllers stay thin.**
- Every staff status change goes through `Admin/TicketStatusWorkflowService` (row lock, transaction, reopen/cancel gates, emails).
- `SystemLogService::record('event.key', ...)` is the audit log. Security-relevant actions and every data export write an entry.
- Report numbers follow `docs/report-metrics.md` (authoritative) and count only `Ticket::applyReportableConstraint()` tickets.

**Tickets.** `App\Enums\TicketStatus` is the single source for status values, labels and badge colours; the `Ticket::*_STATUSES` lists derive from it. A ticket can have several assignees: `assigned_to` is the primary, the `assignedUsers` pivot holds all. Filter by assignee with `Ticket::applyAssignedToConstraint()`.

**Config.** Feature knobs live in `config/helpdesk.php`, `security.php`, `retention.php`, `legal.php`, `observability.php`; code reads `config()`. A new env var also goes in `.env.example` and `docs/configuration.md`.

**Production advisories.** A feature adds its note to `helpdesk:ops-status` / `/health` with a listener for `App\Events\ProductionAdvisoriesRequested` in `app/Listeners` (auto-discovered), leaving `HelpdeskOperationsStatusService` untouched.

**Scheduler.** `routes/console.php`. Each entry chains `->withoutOverlapping()->onFailure($reportFailure('<command>'))` so failures reach System Logs.

**Frontend.** `resources/js/app.js` registers shared behaviours from `lib/` and lazy-loads one `pages/` module per page, matched by a `data-*-page` attribute on the page root. Alpine components are registered with `Alpine.data()` (see `lib/theme-state.js`) because the CSP build cannot evaluate arbitrary inline JS; scripts load from files, never inline `<script>`. Vitest tests sit beside their module as `*.test.js`.

**Tests.** `tests/TestCase.php` fakes the attachments disk and disables Vite; fixtures come from `tests/Traits/CreatesTestData` (`createClient`, `createSupportUser`, `createTicket`). Guard tests such as `UiConsistencyGuardTest` and `QaAccessibilityMarkupTest` assert on Blade/JS source text: renaming a shared partial or class means updating them on purpose.

## Conventions

- Name things with the terms in `GLOSSARY.md` (code, UI text, docs, PRs). Before changing how field office, retention clean-up or backups work, read the matching record in `docs/adr/`.
- Risky features ship switched off behind a config flag, with a `--dry-run` where it applies and an ops-status advisory (see retention clean-up, upload scanning).
- Commit messages and PR titles are plain-English sentences about behaviour ("Do not create two tickets when the same form is sent twice"), without Conventional Commit prefixes.
- Branches: `feature/...`, `fix/...`, `docs/...`. PR body: `## Summary` (before / now) and `## Evidence` (tests run, counts, measurements).
- After editing `docs/ticket-system-workflow.md` (the staff/client guide), regenerate its PDF with `php artisan helpdesk:build-workflow-pdf`.

## Docs map

`README.md` is the front page; `docs/README.md` indexes everything else (setup, configuration, operations, testing, security, retention, import). Human contribution rules: `CONTRIBUTING.md`.

## Agent skills

### Plan and status

Where the improvement work stands, decisions already made, and the next steps: `docs/agents/plan.md`. Read it at the start of a session; update it when a step finishes.

### Issue tracker

GitHub Issues on `xtiannjose/Helpdesk`, via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
