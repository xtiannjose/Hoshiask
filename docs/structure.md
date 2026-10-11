# How the code is organised

Helpdesk is a normal Laravel application. This page shows where things are and how a request travels through them, so you can find the right file quickly.

## What happens when someone opens a page

1. **A route** in `routes/web.php` connects the address to a controller and lists the checks that run first (signed in, active account, accepted the terms, role, rate limit).
2. **Middleware** in `app/Http/Middleware` runs those checks. `RoleMiddleware` decides by role. `SetSecurityHeaders` adds the browser protections to every answer.
3. **A form request** in `app/Http/Requests` validates what the person sent. It can refuse the request too. Extra checks live in `app/Rules` (for example `UniqueUserEmail`, `NotACommonPassword`, `NoMalware`).
4. **A controller** in `app/Http/Controllers` stays thin. It asks a **policy** in `app/Policies` whether this person may touch this record, then calls a service.
5. **A service** in `app/Services` does the real work: status changes, assignment, reports, emails, backups, imports.
6. **A model** in `app/Models` reads and writes the database.
7. **A view** in `resources/views` (Blade) draws the page, and a script in `resources/js` adds behaviour.
8. Anything that matters for security, and every data export, is written to the audit trail with `SystemLogService::record('event.key', ...)`. It shows on the System Logs page.

Clients get "not found" instead of "forbidden" for tickets and files that are not theirs, so nobody can find out which ticket numbers exist.

## Roles

There are five roles: `client`, `technical` (technician), `super_user`, `admin` and `shadow` (the hidden monitoring account). Clients use the portal under `/client`. All staff use the console under `/admin`.

Access is decided by **role groups**, which are constants on `App\Models\User`. Routes use them as `role:ticket_console`, `role:admin_level` and so on. To change who may do something, change the group in `User`, not the route.

| Group | Roles | What it gates |
|---|---|---|
| `ticket_console` | shadow, admin, super user, technician | The whole staff console. |
| `admin_level` | shadow, admin, super user | Reports, user accounts, merging tickets, writing knowledge articles, managing canned responses, changing a ticket's dates and field office. |
| `ticket_creator` | admin, super user, technician | Logging a ticket on a client's behalf. |
| `system_admin` | shadow, admin | Departments, assignment rules, deleting tickets. |
| `super_user` alone | super user | Changing a ticket's type. |
| `shadow` alone | shadow | System Logs, Online Users, issuing temporary passwords. |

A technician sees and works only the tickets assigned to them, and can open other client tickets read-only. See "Who can do what" in the [guide](ticket-system-workflow.md).

## Folder map

```text
app/
  Console/Commands/      the artisan commands (list in operations.md)
  Enums/                 TicketStatus (statuses, labels, colours), TicketPriority (severity), RootCauseCategory, KnowledgeArticleCategory
  Events/, Listeners/    how a feature adds a note to helpdesk:ops-status (ProductionAdvisoriesRequested), and the queue worker heartbeat
  Http/
    Controllers/         Admin/ = staff console, Client/ = client portal, Concerns/ = shared traits,
                         top level = sign-in, attachments, health check, legal pages, notifications
    Middleware/          role, active account, consent, password change, security headers, rate limits, last seen
    Requests/            validation and permission checks, one folder per area
  Mail/                  TicketAlertMail, the one email class
  Models/                Ticket, TicketReply, User, Department, Category, Attachment, SystemLog, KnowledgeArticle ...
  Policies/              record-level permissions (tickets, attachments, users, departments, saved views, replies)
  Providers/             service providers
  Rules/                 custom validation rules
  Services/
    Admin/               ticket status workflow, assignment, merge, index and filters, users, reports (Reports/)
    Backup/              backup file, encryption, database dump, tar archive
    Client/              what the client portal lists
    Observability/       slow query logging
    Operations/          the status behind helpdesk:ops-status and /health
    Retention/           removing personal details from old tickets
    Security/            ClamAV virus scanner
    TicketImport/        CSV and XLSX import
    (top level)          audit log, email alerts, notifications, branding, acknowledgment, dashboard snapshot
  Support/               small helpers: SLA settings and the business-hours clock (BusinessHours), LIKE and date helpers for every database, upload limits, login lockout
  View/Composers/        data every page header needs
bootstrap/app.php       registers the middleware and the route files
config/                  helpdesk.php (feature settings), security.php, retention.php, legal.php, observability.php, and Laravel's own
database/
  migrations/            every table change, in order
  seeders/               sample data; E2eSmokeSeeder is only for the browser tests
  schema/                a PostgreSQL snapshot that makes a fresh install faster
lang/en/                 validation messages
public/                  the web root: index.php, images, and build/ (made by npm run build, not in git)
resources/
  css/app.css            Tailwind 4 styles
  js/                    app.js, lib/ (shared behaviours), pages/ (one module per page)
  views/                 admin/, client/, auth/, account/, emails/, errors/, legal/, layouts/, components/, partials/
  data/                  the list of provinces and municipalities
routes/
  web.php                every page and action
  console.php           the scheduled jobs
scripts/                 deploy scripts, Windows helpers for the queue worker, the backup decrypt tool, a fake virus scanner for testing
storage/app/private/     attachments/, backups/, imports/, seeded-client-credentials/ (no web address serves these)
storage/app/public/      department logos (served through the public/storage link)
tests/
  Feature/               tests that go through the whole app (HTTP, commands, database)
  Unit/                  tests of single classes
  e2e/                   Playwright browser tests
  Support/, Traits/      test helpers: CreatesTestData builds users, categories and tickets; TestDatabaseGuard refuses to run on a real database
docs/                    all documentation (index in docs/README.md)
.github/                 CI, CodeQL, Dependabot, issue and pull request templates
.claude/                 hooks and settings for Claude Code
```

## The front end

- `resources/js/app.js` starts the shared behaviours from `lib/` (modals, styled selects, form helpers) and loads **one module from `pages/`** for the page you are on. It finds the right one by a `data-...-page` attribute on the page's main element.
- Interactive pieces use Alpine in its **CSP build**, which cannot run inline code. So scripts always load from files, never from inline `<script>` tags, and Alpine components are registered with `Alpine.data()`.
- When all of a page's scripts have started, `app.js` sets `data-page-scripts-ready` on `<html>`. The browser tests wait for it.
- Vite bundles everything into `public/build`. Tailwind picks up class names from the Blade views.
- JavaScript tests (Vitest) sit next to their module as `*.test.js`.

## Scheduled jobs

Defined in `routes/console.php`. Each one is protected against running twice at once and reports a failure to System Logs.

| When | Command | What it does |
|---|---|---|
| Every 5 minutes | `tickets:send-alert-emails` | Reminder emails, the merged reply emails to clients, and the heartbeat the status check looks for. |
| Daily 02:40 | `helpdesk:backup` | The encrypted backup. |
| Daily 03:10 | `helpdesk:cleanup-runtime` | Removes old backups, exports and import files, old System Logs entries, used-up password handoffs and expired rows of the database cache. |
| Daily 03:40 | `tickets:auto-close-resolved` | Warns clients, then closes tickets that stayed Resolved for 7 days. |
| Daily 03:45 | `tickets:auto-cancel-pending` | Cancels Pending tickets with no activity for 14 days. |
| Daily 03:55 | `helpdesk:enforce-retention` | Removes personal details from old tickets (only when switched on). |
| Sunday 04:20 | `helpdesk:backup-verify` | Checks the newest backup again. |

The times follow `APP_TIMEZONE`.

## Where to change what

| To change | Look in |
|---|---|
| A ticket status, its label or colour | `app/Enums/TicketStatus.php` |
| Who may open a page or do an action | The role groups in `app/Models/User.php`, the route in `routes/web.php`, the policy in `app/Policies` |
| What happens when staff change a status | `app/Services/Admin/TicketStatusWorkflowService.php` (every staff status change goes through it) |
| A report number | [report-metrics.md](report-metrics.md) first, then `app/Services/Admin/Reports` |
| How SLA minutes are counted (round the clock or working hours, closed dates) | `app/Support/BusinessHours.php`; every SLA cut-off and elapsed time goes through it |
| An email to a client or to staff | `app/Services/TicketEmailAlertService.php` and `resources/views/emails` |
| A setting | The config file, then `.env.example` and [configuration.md](configuration.md) |
| A scheduled job | `routes/console.php`, copying the pattern of the existing entries |
| A word shown to people | Use the term in [GLOSSARY.md](../GLOSSARY.md) |
| A note in the production status check | A listener for `ProductionAdvisoriesRequested` in `app/Listeners` |

## Switching off or removing canned responses

Canned responses (saved reply templates) sit behind one flag, `HELPDESK_CANNED_REPLIES_ENABLED` (on by default). Set it to `false` and the menu link, the management page and the picker in the reply form disappear, and their pages answer "not found". Nothing in the database is touched, so it is safe to switch back on.

To remove the feature from the code for good, delete:

- `app/Models/CannedResponse.php` and `app/Http/Controllers/Admin/CannedResponseController.php`
- `resources/views/admin/canned-responses/`
- the `create_canned_responses_table` migration (add a new migration that drops the table if it already ran anywhere)
- the canned-response routes in `routes/web.php` and the entry in `resources/views/layouts/navigation.blade.php`
- the picker markup in `resources/views/admin/tickets/partials/show-conversation.blade.php`
- the "Canned response picker" block in `resources/js/pages/admin-ticket-show-page.js`
