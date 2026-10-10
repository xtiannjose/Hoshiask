# Improvement plan and status

Where the work to improve Helpdesk with Claude Code stands, what was decided, and what comes next. Claude reads this at the start of a session (`CLAUDE.md` points here) and updates it when a step finishes, so the plan survives a new device or a new conversation.

Last updated: 2026-10-10 (second deep check of the whole app; the first was the audit by QA, security and senior developer reviewers).

**Waiting on the maintainer:** the step-by-step list in [pending-actions.md](pending-actions.md) (change the shadow password, install ClamAV, deploy `main`). Read it before suggesting anything else.

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

1. **Deploy `main`** (the maintainer deploys; see [pending-actions.md](pending-actions.md) section 3). Everything that was in the pull requests #218, #230, #234 to #236 and #238 to #246 is already in `main`:
   - #218 documentation split: short `README.md`, `docs/README.md` index, `CONTRIBUTING.md`, `SECURITY.md`, issue and PR templates.
   - #230 tests cannot touch a real database; test timezone pinned; alert job lock is 10 minutes (closes #226).
   - #234 two missing indexes (closes #231).
   - #235 legal-consent redirect, CSV export order, filter cache key, bulk select count (closes #225 and #227).
   - #236 sign-ins end on an admin password or role change; blank `SESSION_SECURE_COOKIE` follows `APP_ENV`; newest three backups always kept; backup work folder mode (closes #220 and #221; part of #222).
2. **#219, the maintainer only:** the shadow account password is in four old commits. Check it on the production server and change it if it is still that value. The password text is deliberately not written in the issue.
3. **Decisions the maintainer owes:**
   - #222: refuse to write an unencrypted backup in production? (Today it warns and shows an ops-status advisory.)
   - #228: what a merged duplicate counts as in reports.
   - #213: SLA badge logic out of `Ticket` (optional).
4. **Ready for an agent, not started:** #223 (attachment count cap, real Office file test; also decide the upload-scanning default), #224 (reply double-submit and stale-status race).
5. **Needs measuring or planning first:** #232 (ticket search cannot use its trigram indexes; run `EXPLAIN` on real data), #233 (report and auto-close jobs load whole tables), #229 (login lockout, client password change, MFA, reply history, missing browser tests and per-route role tests).
6. **#202 popups**: only if the maintainer asks for it; see Decisions. #202 can be closed once that is decided.
7. **New work** goes through the full loop: idea → `/grill-with-docs` → `/to-spec` → `/to-tickets` → `/triage` → fix → `/code-review` → PR.
8. **Phase 5 habits**: run `/retro` at the end of a working session, and `/handoff` before a conversation gets too long.

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

- The ticket search still scans the table (#232).
- The import tests write their fixture files into the real `storage/app/private/imports` and leave them there (the folder is git-ignored). A test with the same file name would overwrite a real import file; point them at a temporary folder when convenient.
- `database/schema/pgsql-schema.sql` is 14 migrations behind. That is harmless (the rest run after the dump loads); regenerate it with `php artisan schema:dump` on a PostgreSQL database when convenient.
- The `php -l` step in CI repeats what Pint and the tests already catch.
- PHPStan stays at level 5: level 7 only adds missing generics and return types, no defects.
- Not run: the workflow YAML on GitHub (it parses; it needs a push to run).

## Decisions already made

Do not re-ask these.

- The SLA Overview shows only the Resolution Time bands. The other SLA measures and their two settings were deleted (#203).
- The Custom Report counts client (External) tickets only, like the Reports page (#196).
- When a status changed during the same request, the staff change is refused with a message and Auto-close skips the ticket (#194).
- Laravel Boost: declined for now.
- Clients cannot change their own password; accounts are issued and shared inside the client's team (see `docs/security.md`, "Decisions that are not defects"). No MFA for now. Staff "stay signed in" lasts 60 days. An unencrypted backup only warns (#222).
- Merged duplicate tickets count as Closed tickets in reports, as they do today (#228, `docs/report-metrics.md`).
- Ticket search stays as it is for now. It scans the table, which is expected to be fine at 10,000 tickets but has not been measured; measure it on real data and revisit if search feels slow (#232).
- The private disk is not served over the web (`serve` is off), so backups and attachments are only reachable through the app.
- Popups stay JavaScript for now (reviewed 2026-10-10, see the comment on #202). `popover="auto"` cannot delete `exclusive-popups.js` because recent searches and the styled dropdowns are not button-triggered, and the header and message menus would need CSS anchor positioning plus a fallback.
- The date fields use the browser's own `datetime-local` format (#209). Close that PR to keep flatpickr.
- Reviewed and not worth doing: splitting `TicketEmailAlertService` by audience (client vs staff) is only a line-count split, and moving the `Ticket::apply*Constraint` helpers off the model would churn every caller for no new depth (`CLAUDE.md` names them as the convention).
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
