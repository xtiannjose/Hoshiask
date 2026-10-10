# Contributing

Short version of how changes get into `main`. The architecture and conventions in brief are in [CLAUDE.md](CLAUDE.md); the words used in code and UI are in [GLOSSARY.md](GLOSSARY.md).

## Workflow

1. **Find or file an issue.** One problem per issue. Labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix` (see [docs/agents/triage-labels.md](docs/agents/triage-labels.md)).
2. **Branch from `main`.** Name it `feature/...`, `fix/...` or `docs/...`.
3. **One issue per commit.** For a bug, write the test first and check that it fails without the fix.
4. **Run the gate** before pushing: `composer qa && npm run qa` (add `npm run qa:full` when the UI changes). See [docs/testing.md](docs/testing.md).
5. **Open a pull request** with the template. `Closes #N` in the body closes the issue on merge.
6. **Review, then merge.** `main` is never pushed to directly. Branches are deleted after merge.

## Rules of thumb

- Commit messages and PR titles are plain-English sentences about behaviour ("Do not create two tickets when the same form is sent twice"), with no `fix:` or `feat:` prefixes.
- Services own the logic; controllers stay thin. Every staff status change goes through `TicketStatusWorkflowService`.
- Queries must work on SQLite, PostgreSQL and MySQL. Use `App\Support\DatabaseDateExpressions` and `App\Support\LikePattern`.
- A risky feature ships switched off behind a config flag, with a `--dry-run` where it applies and an ops-status advisory.
- Name things with the terms in `GLOSSARY.md`.
- Never commit `.env`, backups, exported files or attachments.

## Review checklist

- [ ] The change does what the issue says, and only that
- [ ] A test fails without it
- [ ] Authorization is checked on the server (policy or role middleware), not only hidden in the UI
- [ ] User-supplied text is escaped; no `{!! !!}` on input
- [ ] New settings are in `.env.example` and `docs/configuration.md`
- [ ] `composer qa` and `npm run qa` pass

## Security problems

Do not open a public issue. See [SECURITY.md](SECURITY.md).
