# Documentation index

New here? Read [setup.md](setup.md) to run the app, then [structure.md](structure.md) to find your way around the code.

| Document | What it is for |
|---|---|
| [setup.md](setup.md) | Prerequisites, install, the three passwords you must set, seeded accounts, Windows notes, troubleshooting |
| [structure.md](structure.md) | How a request flows, the roles, a folder map, the scheduled jobs and where to change what |
| [configuration.md](configuration.md) | Every environment variable and what it does |
| [operations.md](operations.md) | Deploy, scheduler, queue worker, backups, email, health checks, all commands, production checklist |
| [backup-and-restore.md](backup-and-restore.md) | Step-by-step backup copy, restore on a new server, quarterly rehearsal |
| [resilience.md](resilience.md) | What breaks when a part fails, recovery targets and a restore log, the outside monitor on `/health`, when and how to add Redis, and the second maintainer checklist |
| [data-retention.md](data-retention.md) | What the retention clean-up removes and keeps, and how to switch it on |
| [security.md](security.md) | What the app already does, production security settings and optional virus scanning |
| [ticket-import.md](ticket-import.md) | Importing tickets from CSV or the old XLSX tracker |
| [testing.md](testing.md) | The quality gate, each tool, the PostgreSQL and browser test runs, and what CI does |
| [ticket-system-workflow.md](ticket-system-workflow.md) | Plain-language guide for clients, technicians, super users and admins, with a "who can do what" table (also as [PDF](ticket-system-workflow.pdf)) |
| [report-metrics.md](report-metrics.md) | Authoritative definition of every report number |
| [adr/](adr/) | Architecture decision records: why field office, retention and backups work as they do |
| [agents/](agents/) | Working notes for Claude Code: plan and status, issue tracker, triage labels |
| [agents/tooling.md](agents/tooling.md) | The plugins, skills, servers, hooks and tools Claude Code uses here, how to install them, and the Windows and hook quirks |
| [agents/pending-actions.md](agents/pending-actions.md) | What only the maintainer can do (server steps and merges), with exact steps |

Files that are not prose: [helpdesk-queue-worker.service.example](helpdesk-queue-worker.service.example) (systemd unit) and [legacy-ticket-import-template.csv](legacy-ticket-import-template.csv).

Also at the repository root: [README.md](../README.md) (the front page), [GLOSSARY.md](../GLOSSARY.md) (the terms used in code, UI text and docs), [CLAUDE.md](../CLAUDE.md) (conventions and architecture in brief), [CONTRIBUTING.md](../CONTRIBUTING.md) and [SECURITY.md](../SECURITY.md) (how to report a problem).

## Keeping docs honest

- Change behaviour, change the matching document in the same pull request.
- A new setting goes in `.env.example` and in [configuration.md](configuration.md).
- After editing `ticket-system-workflow.md`, regenerate the PDF: `php artisan helpdesk:build-workflow-pdf`.
- A decision that is hard to reverse gets a record in `adr/`.
- Use the words in [GLOSSARY.md](../GLOSSARY.md).
- Markdown files use LF line endings (`.gitattributes` enforces it); see [CONTRIBUTING.md](../CONTRIBUTING.md).
