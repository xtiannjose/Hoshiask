# Documentation index

| Document | What it is for |
|---|---|
| [setup.md](setup.md) | Prerequisites, install, seeded accounts, Windows notes |
| [configuration.md](configuration.md) | Every environment variable and what it does |
| [operations.md](operations.md) | Deploy, scheduler, queue worker, backups, email, health checks, production checklist |
| [backup-and-restore.md](backup-and-restore.md) | Step-by-step backup copy, restore on a new server, quarterly rehearsal |
| [data-retention.md](data-retention.md) | What the retention clean-up removes and keeps, and how to switch it on |
| [security.md](security.md) | Production security settings and optional virus scanning |
| [ticket-import.md](ticket-import.md) | Importing tickets from CSV or the old XLSX tracker |
| [testing.md](testing.md) | The quality gate, each tool, and how to run the browser tests |
| [ticket-system-workflow.md](ticket-system-workflow.md) | Plain-language workflow for clients, technicians and admins (also as [PDF](ticket-system-workflow.pdf)) |
| [report-metrics.md](report-metrics.md) | Authoritative definition of every report number |
| [adr/](adr/) | Architecture decision records: why field office, retention and backups work as they do |
| [agents/](agents/) | Working notes for Claude Code: plan and status, issue tracker, triage labels |
| [agents/pending-actions.md](agents/pending-actions.md) | What only the maintainer can do (server steps and merges), with exact steps |

Files that are not prose: [helpdesk-queue-worker.service.example](helpdesk-queue-worker.service.example) (systemd unit) and [legacy-ticket-import-template.csv](legacy-ticket-import-template.csv).

Also at the repository root: [GLOSSARY.md](../GLOSSARY.md) (the terms used in code, UI text and docs), [CLAUDE.md](../CLAUDE.md) (conventions and architecture in brief), [CONTRIBUTING.md](../CONTRIBUTING.md).

## Keeping docs honest

- A new setting goes in `.env.example` and in [configuration.md](configuration.md).
- After editing `ticket-system-workflow.md`, regenerate the PDF: `php artisan helpdesk:build-workflow-pdf`.
- A decision that is hard to reverse gets a record in `adr/`.
