# Contributing

How a change gets into `main`. To find your way around the code, read [docs/structure.md](docs/structure.md). The words used in code and on screen are in [GLOSSARY.md](GLOSSARY.md). Claude Code reads [CLAUDE.md](CLAUDE.md), which holds the same conventions in brief.

## Workflow

1. **Find or file an issue.** One problem per issue. Labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix` (see [docs/agents/triage-labels.md](docs/agents/triage-labels.md)).
2. **Branch from `main`.** Name it `feature/...`, `fix/...` or `docs/...`.
3. **One issue per commit.** For a bug, write the test first and check that it fails without the fix.
4. **Update the docs in the same change.** If behaviour, a setting, a command or a screen changes, change the matching file in `docs/` (the index in [docs/README.md](docs/README.md) says which one).
5. **Run the checks** before you push: `composer qa && npm run qa`. Add `npm run qa:full` when a screen changes. While you work, `php artisan test --parallel` runs the PHP tests in about a minute. See [docs/testing.md](docs/testing.md).
6. **Open a pull request** with the template. `Closes #N` in the body closes the issue when it is merged.
7. **Review, then merge.** Nobody pushes to `main` directly. The branch is deleted after the merge.

## Rules

**Commits and pull requests**

- Write the commit message and the pull request title as a plain sentence about behaviour: "Do not create two tickets when the same form is sent twice". No `fix:` or `feat:` prefixes.
- The pull request body has two parts: **Summary** (how it was before, how it is now) and **Evidence** (the tests you ran, with counts or measurements).
- Never commit `.env`, backups, exported files or attachments.
- Files use LF line endings (`.gitattributes`). A file saved with CRLF fails `vendor/bin/pint --test`.

**Code**

- Services own the logic and controllers stay thin. Every staff status change goes through `TicketStatusWorkflowService`.
- Every query must work on SQLite, PostgreSQL and MySQL. Use `App\Support\DatabaseDateExpressions` for dates and `App\Support\LikePattern` for LIKE.
- Check permission on the server (a policy or `role:` middleware), not only by hiding a button.
- Security-relevant actions and every data export write an entry with `SystemLogService::record()`.
- Report numbers follow [docs/report-metrics.md](docs/report-metrics.md) and count only `Ticket::applyReportableConstraint()` tickets.
- Escape text people typed. Do not use `{!! !!}` on it.
- A risky feature ships switched off behind a setting, with a `--dry-run` where it applies and a note in `helpdesk:ops-status`.
- Name things with the terms in [GLOSSARY.md](GLOSSARY.md), in code, on screen, in docs and in pull requests.

**Tests**

- A new behaviour gets a test. A bug fix gets a test that fails without the fix.
- Test data comes from `tests/Traits/CreatesTestData` (`createClient`, `createSupportUser`, `createTicket`).
- Tests that read the source text of Blade or JavaScript (`UiConsistencyGuardTest`, `QaAccessibilityMarkupTest`) must be updated on purpose when you rename a shared partial or class.

**Documentation**

- A new setting goes in `.env.example`, the config file and [docs/configuration.md](docs/configuration.md).
- After you edit `docs/ticket-system-workflow.md`, run `php artisan helpdesk:build-workflow-pdf` and commit the new PDF.
- A decision that is hard to undo gets a short record in `docs/adr/`.

## Review checklist

- [ ] The change does what the issue says, and only that
- [ ] A test fails without it
- [ ] Permission is checked on the server
- [ ] People's text is escaped
- [ ] Security-relevant actions and exports are in the audit trail
- [ ] It works on SQLite and PostgreSQL (CI runs both)
- [ ] New settings are in `.env.example` and `docs/configuration.md`
- [ ] The docs say what the code now does (and the guide PDF is rebuilt if the guide changed)
- [ ] `composer qa` and `npm run qa` pass

## Security problems

Do not open a public issue. See [SECURITY.md](SECURITY.md).
