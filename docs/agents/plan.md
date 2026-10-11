# Improvement plan and status

Where the work to improve Helpdesk with Claude Code stands, what was decided, and what comes next. Claude reads this at the start of a session (`CLAUDE.md` points here) and updates it when a step finishes, so the plan survives a new device or a new conversation.

Last updated: 2026-10-11 (the maintainer's answers to the third deep check are built on `fix/after-third-check-decisions`, plus the follow-up rounds: the small gaps (Needs Attention in working hours, closed dates, working time on the ticket page, technician availability, a queue heartbeat, unused dark-theme rules), Pending auto-cancel, legacy Office files refused, the public `/health` answer, the finding that the PostgreSQL schema file must not be regenerated yet, and the demo-app notes; before that the third deep check of the whole app, with measurements on PostgreSQL; before it, the second deep check and a rewrite of the docs, and the first check, the audit by QA, security and senior developer reviewers).

**Waiting on the maintainer:** nothing right now. There is no live server (stated 2026-10-11), so everything in [pending-actions.md](pending-actions.md) that needs one (change the shadow password, install ClamAV, deploy `main`, the server settings, monitor and restore rehearsal that follow the decisions of 2026-10-11) is deferred until a server exists. Do not list server steps as pending; keep working on code, tests and docs. Merging the pull requests is still the maintainer's.

**Issue and pull request numbers.** The numbers below (#190 to #246) belong to the repository's history before it was reset to a single "Initial commit" on 2026-10-10. The tracker on GitHub now starts again at #1 and has no open issues, so those numbers do not resolve there. The changes themselves are all in `main`: the deep check of 2026-10-10 found each of them in the code (the 10-file cap, the double-submit guard, `TicketSla`, `helpdesk:unlock-login`, the PDF preview, the 10-minute alert lock, the blank-`SESSION_SECURE_COOKIE` rule, `ThrottlePerLimit` and the two index migrations).

## The plan

| Phase | What | Status |
|---|---|---|
| 0. Setup | `CLAUDE.md`, `GLOSSARY.md`, `docs/adr/`, `docs/agents/`, GitHub triage labels, git guardrail and Pint hooks | Done (#190, #191) |
| 1. Find what to improve | `/ponytail-audit`, `/code-review high app/`, check report numbers; findings filed as issues #192 to #203 and triaged with agent briefs | Done |
| 2. Plan each change | `/grill-with-docs` → `/to-spec` → `/to-tickets` for new ideas | Not needed yet; Phase 1 issues were small enough to fix directly |
| 3. Build | Fix one issue per commit, with a test that fails before the fix | Done for #192 (#204) and #193 to #203 (#205). #202 front end merged: page transitions #207, modals #208, date picker #209, and #215 (keep the seconds of an unedited ticket date, found by review). Popups declined (see Decisions). Review fixes #210 to #212 merged in #214 |
| 4. Review and ship | `/code-review`, `/security-review`, `/pr`; the maintainer merges | Ongoing for every PR |
| 5. Keep improving | Architecture review done 2026-10-10 (#210 to #213, merged). Whole-app audit done the same day by three reviewers (QA tester, security tester, senior developer): findings are issues #219 to #233; fixes are in PRs #230, #234, #235 and #236. `/retro` after a session, `/handoff` when a chat gets long, optional scheduled `/triage` | Audit done; habits not started |

## Next steps, in order

1. **Merge the open pull requests** (the maintainer merges): #4 (the third deep check) first, then #5 (the decisions that followed it).
2. **Nothing needs a server right now.** There is no live server (stated 2026-10-11), so every server step is deferred in [pending-actions.md](pending-actions.md) and is not reported as pending. They become live work when a server exists.
3. **Before real people and real data go in**, revisit the gaps left open on purpose while this is a demo app: [docs/security.md](../security.md), "Left open on purpose while this is a demo app" (password length, no MFA, shared client password, unencrypted backups, example database password, upload scanning that fails open).
4. **Nothing is waiting for a decision.** The small gaps are built or recorded as left on purpose (see "Small gaps" below).
5. **New work** goes through the full loop: idea, `/grill-with-docs`, `/to-spec`, `/to-tickets`, `/triage`, fix, `/code-review`, PR.
6. **Phase 5 habits**: run `/retro` at the end of a working session, and `/handoff` before a conversation gets too long.

## Second deep check (2026-10-10)

Read in full: sign-in, middleware, policies, the status workflow, assignment, merge, replies and attachments, exports, the backup archive, retention, the alert and auto-close jobs, the dashboard, deploy and CI. Swept the whole tree for raw SQL, unescaped output, `innerHTML`, unused code, config against `.env.example` and the docs, and docs against the files they name. Ran every gate (`composer qa`, `npm run qa`), the browser tests (18 of 18, on a database of their own), the whole PHPUnit suite on PostgreSQL 17 (1078 passed, 1 skipped on purpose), PHPStan at level 7, and `route:cache`, `view:cache`, `config:cache`, `event:cache`.

Fixed on branch `fix/deep-check-cleanup` (one commit per topic, not pushed yet), each with a test that fails without the fix:

- Two accounts could share an email that differed only in letter case, while sign-in compares in lower case (`App\Rules\UniqueUserEmail`).
- The audit trail wrote "Downloaded attachment" for a file that was missing or whose preview was refused. Every inline image or PDF preview also wrote its own row, so a ticket with ten images added ten rows every time it was opened; a preview is now written once an hour per person and file, and a real download is always written (decision by the maintainer, recorded in `docs/security.md`).
- The ticket page kept asking a failing or throttled reply feed every 5 seconds; it now backs off like an idle one.
- A ticket with more than 200 replies silently dropped the oldest from the page and counted only the 200 shown. The page now says how many are hidden, shows the real count, and "Show all messages" (`?all_messages=1`) loads them all.
- The new-ticket form listed only the first 500 clients and had no search, so the 501st client could not be picked. It now lists the first 50 and has a search box that asks the server (`GET /admin/tickets/requester-accounts`; name, email, username, phone or department).
- The login screenshots in `tests/e2e/accessibility-visual.spec.js-snapshots` still showed the old iOne brand; they are regenerated.

Cleaned: auto-close loaded each client with its own query; the unused `laravel/sail` dependency (and `symfony/yaml` with it), `Ticket::slaElapsedMinutes` and an unneeded export; CI runs with a read-only token and replaces a superseded pull request run; ten environment variables were missing from `docs/configuration.md`. Added `brianium/paratest`, so `php artisan test --parallel` runs the suite in about 70 seconds instead of 165.

Browser tests: Playwright used to reuse whatever answered on port 8000, which on this machine was another project's dev server. It now starts its own unless `PLAYWRIGHT_REUSE_SERVER=1`. Two specs clicked buttons before the page's script had attached its handlers and failed now and then under load; `app.js` now sets `data-page-scripts-ready` on `<html>` and the specs wait for it. `docs/testing.md` has the recipe for running them on a database of their own.

Looked at and left alone (decisions for the maintainer, not defects):

- The ticket search scanned the table (#232) and the import tests wrote into the real import folder. Both are fixed; see the third deep check below.
- `database/schema/pgsql-schema.sql` was many migrations behind. That was harmless: the later migrations ran after it loaded. A first regeneration on 2026-10-11 failed 36 tests on PostgreSQL, because starter data lived in migrations and a tables-only dump records every migration as run, so a fresh database had no departments; the old file was put back. The data then moved out of the migrations into `Database\Seeders\StarterDataSeeder` (`migrate --seed`; it fills an empty table only), and the file was regenerated the same day. Do not put data into a migration again.
- The `php -l` step in CI repeats what Pint and the tests already catch.
- PHPStan stays at level 5: level 7 only adds missing generics and return types, no defects.
- Not run: the workflow YAML on GitHub (it parses; it needs a push to run).

## Third deep check (2026-10-11)

Read the whole app again as a senior developer, a QA tester and a security analyst, then measured it on PostgreSQL 17 with 60,000 tickets in a scratch database (`helpdesk_audit_test`, dropped afterwards). Fixed on branch `fix/third-deep-check`, one commit per topic, each with a test that fails without the fix (the search rewrite keeps what is found, so its tests are guards):

- **Reminder emails ignore imported tickets.** After an import the first scheduler run mailed every open legacy ticket (technicians once, super users twice per ticket).
- **A technician added later no longer gets every new ticket.** The rotating counter now compares shares over the last 30 days (`HELPDESK_ASSIGNMENT_ROTATION_WINDOW_DAYS`), not all tickets ever.
- **A cancelled ticket shows "Cancelled"**, not "Unread" with the open-ticket dot.
- **The Resolution Time bands read raw rows in chunks.** The all-time Reports page took 6.7 s and 174 MB more memory at 60,000 tickets; it now takes 1.8 s with flat memory (`TicketSla::minutesToCompletion` is the one rule for "done").
- **The staff ticket search uses its indexes.** One set of ids per column joined with UNION, plus two new trigram indexes (description, department). 13.0 s to 0.3 s for a rare word and 10.1 s to 1.3 s for a common one, same results.
- **The nightly clean-up removes expired database cache rows**, and the Reports cache key is hashed (a long `month` value could pass the 255-character key column).
- **A bulk status change checks the cancel and revert rules on the locked ticket.**
- **Two new advisories:** `TRUSTED_PROXIES=*`, and the example `LEGAL_DPO_EMAIL` / `LEGAL_SUPPORT_EMAIL` still in the privacy notice.
- **The Online Users page no longer scrolls sideways on a phone** (positioned scroll boxes, plus a test that keeps all of them so).
- **The bulk severity form no longer offers "Severity not set"**, which the server always refused.
- **Removed:** `HasFactory` on 12 models (no factories exist), the `fakerphp/faker` package, `Ticket::slaResolutionMinutes()`, the `ilike` switch in the ticket filter.
- **Tests and CI:** the import tests use a temporary folder; the client journey spec waits for the page script before clicking Reopen; GitHub Actions are pinned to commits, and a weekly `audit.yml` runs `composer audit` and `npm audit`.

Pull request #4 holds all of it; every CI job passed (secret-scan, verify, postgres, e2e, CodeQL).

Checked and clean: `composer audit` and `npm audit`; no unused PHP methods, config keys, routes or JS files; axe (WCAG 2 A and AA, light and dark) on 60 page loads for every role, and no sideways scrolling at 375 px (except the Online Users page, fixed); the monthly and custom report PDFs and the alert emails render correctly; the ClamAV protocol matches clamd's INSTREAM (a real daemon was not available); the full suite passes on SQLite (1109 tests) and on PostgreSQL 17 (1108 passed, 1 skipped on purpose).

Measured and left alone: the first scheduler run on 60,000 open tickets sent 18,000 alert emails (18,000 queries, 31 s) and the first auto-close of 5,400 resolved tickets took 48 s (32,000 queries). Both are one-off backlog runs; the nightly run handles only the day's tickets.

### Needs the maintainer's decision

1. **Pending (waiting on the client) tickets.** The row badge says "Awaiting client" and never "Breached", but the SLA Breached tab, the Needs Attention count and the technician SLA reminders still include them. Recommended: leave Pending out of all three, as the badge does.
2. **The deploy health check fails on any pending or failed job** (`docs/operations.md`, "Status and health checks") and rolls the deploy back, `migrate:rollback` included. One bounced email in `failed_jobs` blocks every later deploy. Recommended: make the two queue counts advisories, or warn only above a large number.
3. **The deploy script keeps the site in maintenance while it audits and builds** (`scripts/deploy-helpdesk-remote.sh`), a new advisory fails the deploy in the middle, and reading the migration batch needs `php artisan tinker` (so `laravel/tinker` cannot move to `require-dev`). Recommended: audit and build before `php artisan down`, or build in CI; read the batch with a small command.
4. **The sign-in limit is 5 a minute per address in production and counts successful sign-ins.** Several people behind one office address can lock each other out at the start of the day. The per-login lockout (8 tries) already guards guessing. Recommended: about 30 a minute.
5. **Upload scanning fails open** (`HELPDESK_UPLOAD_SCAN_ON_ERROR=allow`), and `.doc` and `.xls` (macro-capable) are accepted. Recommended: `block` once ClamAV works, and consider dropping the two legacy formats.
6. **The bulk status form starts on "Open"**, so Apply without choosing reopens every selected ticket. Recommended: a "Choose a status" placeholder.
7. **The SLA clock runs 24 hours a day** and does not pause on Pending, so a ticket filed on Friday at 5 pm is "breached" by 9 pm and alerts fire overnight. Business hours would also change the report numbers (`docs/report-metrics.md`).
8. **Pending tickets never close or get a follow-up.** Only Resolved tickets auto-close. Possible: a client reminder after N days and a close or cancel after M.
9. **Rotating assignment ignores availability** (leave, off hours) and open workload. Confirm the 30-day window.
10. **Imported open tickets get no reminders at all.** Confirm that is wanted (the alternative is one reminder when a ticket is first worked).
11. **Security posture to revisit (all recorded decisions):** staff passwords may be 8 characters (12 for staff?), no second factor for admin and shadow, client accounts can share one fixed password, backups are not encrypted unless a key is set, and `.env.example` ships `DB_PASSWORD=postgres`.
12. **Legal text (needs the organisation's DPO):** the privacy notice does not say that backups keep removed data for `HELPDESK_BACKUP_RETENTION_DAYS`, nor the right to complain to the National Privacy Commission, and System Logs are kept 365 days.
13. **Resilience:** sessions, cache, queue and data share one database (Redis for cache and queue is the first step), one maintainer, no stated recovery time or restore drill, and no external uptime monitor on `/health`.
14. **Not worth doing without a reason:** export paging by id (it would change the CSV order), chunking the auto-close job (a backlog only after a long outage), and 24 dark-theme colour rules that nothing uses.

### Answered on 2026-10-11, and what was built

Branch `fix/after-third-check-decisions` (on top of `fix/third-deep-check`), one commit per topic, each with tests:

| # | Answer | What it became |
|---|---|---|
| 1 | Yes | Pending tickets are left out of SLA Breached, Needs Attention and the SLA reminders (`Ticket::SLA_CLOCK_STATUSES`) |
| 2 | Recommended fix | Waiting and failed queue jobs are advisories; a queue that has not moved for 15 minutes is named. A deploy no longer fails or rolls back on them |
| 3 | Recommended fix | The deploy script audits and builds before `php artisan down`, deploys the exact audited commit, keeps the previous build for rollback, reads the migration batch with `helpdesk:migration-batch`; `laravel/tinker` is a dev dependency |
| 4 | Count only failures | The sign-in limit is 15 failed sign-ins a minute per address; successful sign-ins no longer count |
| 5 | Left to Claude | `.doc` and `.xls` are no longer accepted (macro-capable; save as `.docx`/`.xlsx`). The scanner still fails open by default, because without ClamAV `block` would refuse every upload; the ClamAV steps now end with setting `HELPDESK_UPLOAD_SCAN_ON_ERROR=block` |
| 6 | Recommended fix | The bulk status form starts on "Choose a status" |
| 7 | Business hours | `HELPDESK_SLA_BUSINESS_HOURS_*`, off by default (`App\Support\BusinessHours`); public holidays are not skipped |
| 8 | Staff, not the client | One reminder email per staff member for Pending tickets quiet for 3 days (`HELPDESK_PENDING_FOLLOW_UP_DAYS`); no client email. Then 14 days: `tickets:auto-cancel-pending` cancels them (`HELPDESK_PENDING_AUTO_CANCEL_DAYS`, `--dry-run`, off at 0) |
| 9 | No change | Rotating assignment stays as it is |
| 10 | Confirmed | Imported tickets get no reminders or emails |
| 11 | Left open, this is a demo app | Recorded with the exact change to make before real use in [docs/security.md](../security.md), "Left open on purpose while this is a demo app" |
| 12 | Yes | The privacy notice states backup and log retention and the National Privacy Commission route; version `2026-10-11` (the data protection officer should read it) |
| 13 | Yes | [resilience.md](../resilience.md): failure table, recovery targets and restore log, outside monitor, Redis steps, second maintainer checklist. The server-side parts are in [pending-actions.md](pending-actions.md) section 4 |
| 14 | Do both, and remove the unused dark-theme rules | Done: exports and auto-close page by id, 23 dark-theme selectors removed; see "Small gaps" |

## Small gaps (2026-10-11)

Found by the third deep check and left alone at first; the maintainer chose the best fix for every one of them, including the two "not worth doing" items. Built on `fix/after-third-check-decisions`.

| Gap | What was done |
|---|---|
| **Needs Attention** counted calendar hours with business hours on | The tab and the dashboard count use 16 *working* hours (`BusinessHours::cutoff(960)`) when business hours are on |
| **Public holidays** were not skipped | `HELPDESK_SLA_BUSINESS_HOURS_CLOSED_DATES` (comma-separated `YYYY-MM-DD`) counts like a weekend; an entry that is not a date is ignored and `helpdesk:ops-status` names it |
| **Ticket page showed real time only** | With business hours on, the Resolution Time line reads "2d 18h (3h working)" for staff and client |
| **Rotating assignment ignored leave** | `users.assignment_paused_until`, set as a "Back on" day on the Assignment Rules page (admin and shadow). Both distribution methods skip someone who is away; it ends by itself; manual assignment is unaffected. Workload balancing was built afterwards as a third distribution method, "Least workload" (Open and In Progress tickets held, divided by the rule weight) |
| **The status check could not see a Redis queue** | A worker heartbeat (`RecordQueueWorkerHeartbeat`, written at most every 30 s) and `Queue::size()`: "jobs are waiting and no job has finished for 15 minutes" works for a non-database queue, and the status shows when the last job finished |
| **24 unused dark-theme colour rules** | Removed from `resources/css/app.css` (23 selectors, 3 whole rules); their classes appear nowhere in the source |

Then, at the maintainer's word, the two items first left alone:

- **Export paging by id.** The custom report CSV reads 200 rows at a time by id (`chunkById`; the order is still id ascending, which is what `chunk()` used because that query has no order of its own). The System Logs CSV reads by id descending (`reorder()->chunkByIdDesc`), the same order as before because log rows are written in time order. A page deep in a big table now costs the same as the first. Tests export more than one page and check the order.
- **Chunking auto-close.** `tickets:auto-close-resolved` reads the tickets to warn and the tickets to close 200 at a time by id, so a backlog of thousands after an outage no longer sits in memory at once. A test closes 201 and warns 201.

Found on the way: the knowledge base popup spec failed about one run in five when run in parallel. A `close` event of the first close arrived after the dialog had been opened again and cancelled the new close timer, so Escape left the popup open. `modal-kit.js` now ignores a close event while the dialog is open and does not add `is-open` to a dialog that was closed before the first frame; `modal-kit.test.js` covers both, and the spec passes 72 of 72 repeated runs.

The closed dates in `.env.example` are the 18 nationwide regular and special non-working holidays of 2026 from the Official Gazette (https://www.officialgazette.gov.ph/nationwide-holidays/2026/). Eid'l Fitr and Eid'l Adha are proclaimed later and are not in it. Replace the list each year; a test keeps every entry a real date.

## Docs rewrite (2026-10-10)

Every document in `docs/`, `README.md` and `CONTRIBUTING.md` was rewritten against the code so that a new reader can follow it, and `docs/structure.md` is new (request flow, roles, folders, scheduled jobs, where to change what). They are committed on `fix/deep-check-cleanup`, one commit per topic, and not pushed yet; the maintainer decides when to push.

Mistakes found in the old text and fixed:

- The deploy steps did not follow the order the deploy scripts use.
- The guide said clients choose a severity (they do not; staff set it) and that saved views are for some staff only (every staff member has them).
- `HELPDESK_QUEUE_WORKER_RUNNING` was described as a flag to set; leave it blank and the status check looks for a running `queue:work` (`.env.example` now says so).
- Setup did not say that the seeders need `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD` and `SHADOW_PASSWORD`, and offered `db:seed` as a way to reset accounts (it also adds 8 sample tickets).
- Security, backup and glossary text said an admin or a new account gets temporary passwords; only the shadow account issues them, when it resets a password.
- Some terms did not follow `GLOSSARY.md` (ITD staff, technical user, priority, open ticket, staff ticket).

The staff guide gained a "who can do what" table and sections on signing in, the ticket list, bulk actions, merging, user accounts and departments. Its PDF was rebuilt (`php artisan helpdesk:build-workflow-pdf`).

## Decisions already made

Do not re-ask these.

- The SLA Overview shows only the Resolution Time bands. The other SLA measures and their two settings were deleted (#203).
- The Custom Report counts client (External) tickets only, like the Reports page (#196).
- When a status changed during the same request, the staff change is refused with a message and Auto-close skips the ticket (#194).
- Laravel Boost: declined for now.
- Clients cannot change their own password; accounts are issued and shared inside the client's team (see `docs/security.md`, "Decisions that are not defects"). No MFA for now. Staff "stay signed in" lasts 60 days. An unencrypted backup only warns (#222).
- Merged duplicate tickets count as Closed tickets in reports, as they do today (#228, `docs/report-metrics.md`).
- Ticket search was measured on 2026-10-11 (PostgreSQL 17, 60,000 tickets: 13 s for a rare word, about 2 s at 10,000 tickets) and rewritten to use its trigram indexes (#232). See the third deep check below.
- The private disk is not served over the web (`serve` is off), so backups and attachments are only reachable through the app.
- Popups stay JavaScript for now (reviewed 2026-10-10, see the comment on #202). `popover="auto"` cannot delete `exclusive-popups.js` because recent searches and the styled dropdowns are not button-triggered, and the header and message menus would need CSS anchor positioning plus a fallback.
- The date fields use the browser's own `datetime-local` format (#209). Close that PR to keep flatpickr.
- Reviewed and not worth doing: splitting `TicketEmailAlertService` by audience (client vs staff) is only a line-count split, and moving the `Ticket::apply*Constraint` helpers off the model would churn every caller for no new depth (`CLAUDE.md` names them as the convention).
- This is a demo app with no live server (2026-10-11). Password length, MFA, the shared client password, unencrypted backups, the example database password and the upload scanner that fails open stay as they are, recorded in `docs/security.md`, until real data is planned. Server steps are deferred, not pending.
- Pending tickets: staff reminder after 3 days, automatic cancel after 14 days with no activity (2026-10-11). The client is not emailed about either.
- `.doc` and `.xls` uploads are refused (2026-10-11); `.docx` and `.xlsx` stay.
- SLA business hours exist behind a setting that is off. When on, Needs Attention counts 16 working hours and the closed dates list counts like a weekend (2026-10-11).
- A technician can be marked away until a day; the assignment rules skip them (2026-10-11). The "Least workload" distribution method balances by Open and In Progress tickets held; weighted random stays the default (2026-10-11).
- Starter departments and categories live in `StarterDataSeeder`, never in a migration (2026-10-11).
- Claude may push feature branches and open PRs. Pushes to `main`, force pushes, `reset --hard`, `clean -f`, `branch -D` and discarding all changes are blocked by `.claude/hooks/block-dangerous-git.mjs`. The maintainer merges every PR.

## Working with the maintainer

- Explain in simple, numbered steps; the maintainer has asked for this more than once.
- Run at most about 4 subagents at once; 12 at once hit the session limit.
- Fix one issue per commit and close it from the PR (`Closes #N`). For a bug, add a test that fails without the fix: check by stashing only the fix and running the test.
- Write commit messages and PR titles as plain-English sentences (see `CLAUDE.md`).

## Continue on a new device

Do the steps in order.

1. **Install the tools.** PHP 8.3+, Composer, Node.js 22.12+, Git, the GitHub CLI (`gh auth login`), and Claude Code.
2. **Get the code.**
   ```bash
   git clone https://github.com/xtiannjose/Helpdesk.git
   cd Helpdesk
   composer install
   npm ci
   cp .env.example .env          # PowerShell: Copy-Item .env.example .env
   php artisan key:generate
   ```
   `.env` is never committed. The tests do not need a database server: they use SQLite in memory.
3. **Install the Claude Code plugins.** Run these inside Claude Code:
   ```text
   /plugin marketplace add JuliusBrussee/caveman
   /plugin install caveman@caveman
   /plugin marketplace add DietrichGebert/ponytail
   /plugin install ponytail@ponytail
   /plugin marketplace add bradautomates/claude-video
   /plugin install watch@claude-video
   /plugin install claude-code-setup@claude-plugins-official
   ```
4. **Install the skills** (from a normal terminal, global install):
   ```bash
   npx skills add mattpocock/skills -g
   npx skills add Leonxlnx/taste-skill -g
   npx skills add vercel-labs/skills -g
   npx skills add emilkowalski/skills -g
   ```
   This setup uses these skills: from `mattpocock/skills`, everything in the engineering set (triage, to-spec, to-tickets, implement, grill-with-docs, improve-codebase-architecture, domain-modeling, writing-for-agents, pr, handoff, retro, wayfinder, git-guardrails-claude-code and the rest); `design-taste-frontend`; `find-skills`; and `apple-design`. The account skills (docs, pdf, xlsx and others) sync by themselves when you sign in.
5. **Start Claude inside the project folder**: `claude` (on Windows PowerShell, `claude.cmd`, or run `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` once). Starting inside the folder loads `CLAUDE.md`, this plan, and both hooks in `.claude/`.
6. **Check it works.** Ask Claude: "Read docs/agents/plan.md and tell me where we are." Then run `composer qa` (about 3 minutes, serial; `php artisan test --parallel` runs just the tests in about 70 seconds) and `npm run qa`.

The git guardrail in `.claude/hooks/` protects this repo only. To protect every project on the new device as well, copy `block-dangerous-git.mjs` to `~/.claude/hooks/` and add the same `PreToolUse` entry to `~/.claude/settings.json`.
