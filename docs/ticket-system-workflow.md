# Ticket System Workflow

This guide explains how the helpdesk ticket system should be used from start to finish.

It is written in simple language so staff, coordinators, and end users can all understand the same process.

## Purpose

The ticket system is used to:

- record support requests in one place
- assign the right support person to the issue
- track progress from start to finish
- keep a clear message history
- collect client confirmation and feedback

## Who Uses The System

- `Client`: the person who submits a ticket and follows up on it
- `Technical User / Technician`: the person assigned to work on the ticket
- `Super User / Admin`: the person who monitors, reviews, assigns, escalates, closes, and reports on tickets

## Simple Status Meaning

Use these meanings consistently so everyone interprets the ticket the same way.

| Status | Simple meaning | When to use it |
| --- | --- | --- |
| `Open` | The ticket was received but work has not really started yet. | New ticket, or ticket reopened for more follow-up. |
| `In Progress` | Someone is already working on it. | A technician has started checking, troubleshooting, or coordinating. |
| `Pending` | The ticket is waiting on something. | Waiting for client reply, schedule, approval, parts, or outside action. |
| `Resolved` | The issue appears fixed or completed, but the ticket is not yet fully closed. | Support already completed the work and is waiting for final confirmation or the close window. |
| `Closed` | The ticket is fully finished. | Final status after resolution is confirmed and closing requirements are complete. |
| `Cancelled` | The ticket was called off before real work happened. | The client or staff cancels a request that is no longer needed, while it is still `Open` or `Pending`. |

## Full Workflow

### 1. Client submits a ticket

The client goes to the ticket form and fills in:

- name
- contact number
- email
- province
- municipality or city
- subject
- category
- severity
- description of the issue
- attachments (optional; a screenshot helps the team solve it faster)
- consent checkbox

What happens next:

- the system creates a unique ticket number
- the ticket starts with status `Open`
- the support team is notified that a new request was submitted

### 2. Support reviews the new ticket

The support team checks the new ticket and confirms:

- the request is complete
- the category is correct
- the severity is correct
- the attachments are usable
- the right technician is assigned

Recommended first action for staff:

1. Read the subject, description, and attachment.
2. Acknowledge the ticket so SLA tracking shows it has been formally received.
3. Check if the severity is reasonable.
4. Assign the correct technician or support owner.
5. Send a public reply if the client needs to know work has started.

Important note:

- If a support user sends a normal reply on an `Open` ticket, the system moves the ticket to `In Progress`.

### 3. Ticket is assigned

Once assigned, the ticket has a clear owner.

Staff can:

- assign one or more technicians
- change the assignment later if needed
- keep the correct people attached to the ticket for visibility and accountability

Best practice:

- assign as early as possible
- avoid leaving tickets unassigned
- reassign quickly if the original assignee is unavailable

### 4. Technician works on the issue

While the technician is handling the request, they should:

- review the full ticket details
- use replies to ask questions or give updates
- attach proof, screenshots, or documents when needed
- keep the ticket status accurate

Recommended status use during work:

- keep `In Progress` while active troubleshooting is happening
- use `Pending` only when waiting for something outside the technician's immediate control

Examples of `Pending`:

- waiting for the client to send more details
- waiting for approval from another office
- waiting for replacement equipment
- waiting for a scheduled onsite visit

### 5. Internal notes vs public replies

Support staff can send two kinds of updates:

- Public reply: visible to the client
- Internal note: hidden from the client

Use a public reply for:

- progress updates
- follow-up questions
- instructions to the client
- confirmation that work has started or finished

Use an internal note for:

- internal coordination
- technical observations not meant for the client
- reminders between support staff
- handoff notes during reassignment

Important rule:

- Internal notes should not contain information that the client still needs in order to act.

#### Closed and cancelled tickets

Once a ticket is `Closed` or `Cancelled`, the client can no longer reply. Staff can still add notes so that follow-up observations are recorded, but only as **internal notes**:

- The reply box shows a notice saying the ticket is closed/cancelled and that anything posted is saved as an internal note. The "Internal note" checkbox is locked on.
- The system enforces this on the server too, so a note added to a closed or cancelled ticket is never shown to the client and never emailed to them, even if the form is tampered with.
- `Resolved` tickets are different: the conversation stays open there (the client can still reply, rate or reopen), so staff can still send public replies.

### 6. Ticket reaches a solution

When the issue is fixed or service is completed, the ticket should move to `Resolved`.

`Resolved` means:

- the support team believes the issue is already addressed
- the case is essentially finished
- the ticket is not yet in its final archived state

This is the best stage for:

- asking the client to test the fix
- waiting for final confirmation
- preparing the final close reason if needed

### 7. Client confirmation and rating

Clients can confirm that the issue has been resolved.

When the client marks the ticket as resolved, they must also provide:

- a rating from `1` to `5`, chosen by tapping a star
- confirmation that the issue is resolved

A comment about the support experience is optional. It becomes required only when the client gives `1` or `2` stars, so the team learns what went wrong.

This helps the team:

- confirm service completion
- record satisfaction feedback
- review support quality later in reports

### 8. Closing the ticket

`Closed` is the final status.

Before closing:

- make sure the issue is already resolved
- add the required close reason
- confirm no more action is needed

Important closing rules in the current system:

- A close reason is required before a ticket can be closed.
- Technical users, super users, and admins can close a resolved ticket when the required close reason is provided.
- In the client screen, a `Closed` ticket no longer allows a normal reply.

Root cause (optional): when resolving or closing a ticket, staff can also record a root cause category (`Hardware`, `Network`, `Software Bug`, `User Error`, `External / Vendor`, `Other`) plus a short note. It is not required, but filling it in makes the "Root Cause" breakdown on the Custom Report page meaningful — see [Reporting And Dashboards](#reporting-and-dashboards).

Recommended meaning of `Closed`:

- work is complete
- the client already received the needed support
- the case does not need more routine follow-up

## Reopening And Reverting

Sometimes a ticket needs to be worked on again.

### When a client replies again

If a client replies after the ticket was already marked `Resolved`, the system can move the ticket back to `Open` so the team can continue follow-up.

### When support reverts a closed ticket

Support can revert a resolved or recently closed ticket back to `In Progress` if more work is needed.

Important system rule:

- A `Closed` ticket can only be reverted within `7 days` from the time it was closed.
- After that 7-day window, it can no longer be reverted through the normal workflow.

## Cancelling A Ticket

Sometimes a request is submitted and then turns out not to be needed anymore. Cancelling records that clearly, instead of leaving the ticket to sit `Open` forever or forcing staff to resolve/close something nobody worked on.

Who can cancel, and when:

- The client who owns the ticket, or any technical user/super user/admin, can cancel it.
- A ticket can only be cancelled while it is still `Open` or `Pending`. Once work has started (`In Progress`) or the ticket has already been resolved/closed, it can no longer be cancelled — it has to follow the normal resolve/close path instead.
- A reason is required, the same way a close reason is required. This applies to every way of cancelling: the client's Cancel button, a staff status change, the quick update, and the bulk status action.

What happens after cancelling:

- The ticket moves to `Cancelled` and drops out of the "Needs Attention" and active-work views.
- It still shows up under the ticket list's `History` tab, alongside resolved and closed tickets.
- The reason is saved on the ticket as its **Cancellation Reason** and shown, together with **Cancelled At**, in the Ticket Details panel for both staff and the client. Tickets cancelled before reasons were stored on the ticket show "Not recorded". The reason is also the last column of the ticket export and the detailed custom-report CSV.
- The client can no longer reply (their reply box is replaced by a notice), and staff can only add internal notes (see "Closed and cancelled tickets" under section 5).
- The "Acknowledge Ticket" button is not offered: acknowledgment feeds SLA tracking, which does not apply to a withdrawn request.
- Cancelled tickets are not measured in the reports' resolution rate, open backlog or SLA figures (see "What the reports count" below).
- Staff can move a cancelled ticket back to `Open` if it turns out it was cancelled by mistake — there is no time-limited window on this the way there is for reverting a closed ticket. Reopening clears the cancellation date and reason.

## Escalating A Ticket

Escalation is for handing a ticket to someone else for a closer look — a senior technician, a specialist, or whoever is better placed to move it forward — while keeping a clear record of why and by whom.

How it works:

- Any technical user, super user, or admin working the ticket can escalate it to another staff member (you cannot escalate a ticket to yourself).
- A reason is required.
- Once escalated, the ticket shows an "Escalated" badge — visible both to ITD staff on the admin ticket view and to the client on their own ticket page (the client sees that it was escalated, not the internal reason or who it went to).
- Escalating does not change the ticket's status (`Open`, `In Progress`, etc.) — it is a separate flag layered on top of the normal workflow.
- Any staff member with access to manage the ticket can clear the escalation once it no longer applies. Clearing it does not delete the record — the escalation history stays visible in the ticket's internal notes and the audit trail.

## Attachments And Messages

### Ticket creation

- Attachments are optional when creating a new ticket. (Before 2026-09-30 at least one file was required.)
- Each file can be up to `10 MB`.

### Replies

- Replies can contain a message, an attachment, or both.
- This allows users to send screenshots, documents, or follow-up proof even if the text message is short.

### Editing and deleting replies

For clients:

- their own replies can be edited only within `3 hours`
- their own replies can be deleted only within `3 hours`

For support:

- support can edit or delete their own replies from the ticket conversation

## Saved Views

Admins and super users can save the current ticket list filters (tab, status, search, severity, category, assigned technician, account, field office, date range, and similar filters) as a named "saved view" from the admin ticket list.

How it works:

- Saving a view under a name you already used replaces that view with the current filters.
- Saved views are personal — each staff member only sees and manages their own.
- Opening a saved view re-applies its filters to the ticket list in one click.
- A saved view can be deleted by the staff member who owns it.

This is meant to speed up repeat lookups (for example, "my overdue tickets" or "unassigned Severity 1 tickets") without re-entering the same filters every time.

## Knowledge Base

A self-service library of FAQs, policies, and reference guides, separate from the ticket list. The goal is to let people find an answer without filing a ticket at all.

- Admin-level staff write and manage articles (Admin → Knowledge Base): a title, a category (`FAQ`, `Policy`, or `Guide`), and the article content. An article can be saved as a draft or published.
- Technicians can browse published articles but cannot create or edit them.
- Clients have their own Knowledge Base page (accessible from their sidebar) where they can search and read published articles. Drafts are never visible to clients.
- There is no link yet between a specific article and a specific ticket category — it is a general reference library, browsed and searched on its own.
- Starter articles: `php artisan db:seed --class=StarterContentSeeder` adds 7 published articles (how to submit a ticket, how to write a good one, what the statuses mean, attachments, cancelling, how resolved tickets are closed, and account security) together with 7 canned responses (see below). It is safe to run more than once, including on the live server: an entry is added only when none with the same title exists, so staff edits are never overwritten. The entries are credited to the first active admin account (or a super user if there is no admin).

## Automation Features

### SLA Breached tab

The admin ticket list has a "SLA Breached" tab that only shows tickets that are still open (not resolved or closed) and have already passed the SLA resolution target (240 minutes by default). This surfaces tickets that need attention right now, separate from the general "Needs Attention" tab.

### Assignment automation rules

Admins and shadow accounts can configure automatic assignment rules per category (Admin → Assignment Rules), mapping a category to one or more technicians with a relative weight (for example, "Starlink: Mark 70, Eric 30"). When a new ticket is created without an explicit assignee and its category has configured rules, the system automatically assigns it:

- **Weighted random** — each new ticket independently rolls against the configured weights.
- **Rotating counter** — the next ticket always goes to whichever technician is furthest below their target share, based on the category's assignment history.

The distribution method is a single setting shared across all rules, changeable from the same page. If a category has no rules, or every configured technician has been deactivated, the ticket is left unassigned as before.

### Auto-close after inactivity

Resolved tickets that sit untouched for 7 or more days (measured from `resolved_at`) are automatically closed by a daily scheduled job (`tickets:auto-close-resolved`), so tickets that a client never confirms or reopens don't stay in `Resolved` indefinitely. Existing assignments and the standard close-reason audit note are preserved.

The client is warned first: 5 days after resolution (2 days before the close) the same job emails them once, and it will not close a warned ticket until at least 24 hours after that email. A ticket is closed without a warning only if its client cannot be emailed (no address, inactive account) or the warning is switched off, or the client was never sent the "resolved" email for that resolution (for example because it was logged after the fact, see below). If a warning keeps failing to send, the ticket is closed anyway 3 days after its normal close date so a mail problem cannot leave tickets open forever.

### Emails to the client

A client is emailed only at the moments that need their attention, and every email about a ticket lands in one conversation in their inbox (they share a subject such as `[TK-4XJ7QWPA] Printer is jammed` and reference the first email's Message-ID):

| When | Email |
| --- | --- |
| Ticket received (client-filed, or filed for them by staff) | "We received your ticket" with the ticket number, sent once. None of these emails go to the requester of an internal staff-to-staff ticket: they link to the client's own ticket page, which staff cannot open |
| Staff move it to Resolved | "Your ticket has been resolved": confirm or reopen, and the automatic close date. Sent once per resolution; a reopened and re-resolved ticket emails again |
| 5 days after resolution | One reminder that it closes automatically in 2 days |
| Staff reply publicly | One email per ticket, merged: replies posted within the batching window (default 5 minutes, plus up to 5 for the scheduler) share a single email |
| Closed or cancelled by staff (or auto-closed) | Off by default; enable with `HELPDESK_CLIENT_EMAIL_CLOSED=true` |

**Tickets logged after the fact.** When staff enter a past ticket (a logged date more than 24 hours in the past, or a ticket that is already resolved, closed or cancelled when it is saved) the client is not told about it: there is no "We received your ticket" email, and no "resolved", "closed" or "cancelled" email when that date is more than 24 hours in the past. The auto-close reminder only follows a "resolved" email that was actually sent, so a ticket whose resolution the client never heard about is closed at 7 days without one.

Clients are never emailed about their own actions (they resolve, reopen or cancel a ticket themselves) or about internal notes, assignment changes or status shuffles. Each moment can be switched off individually with the `HELPDESK_CLIENT_EMAIL_*` settings in `.env.example`; `HELPDESK_CLIENT_EMAIL_REPLY_BATCH_MINUTES=0` sends every reply immediately. The app does not read incoming mail, so a client's emailed reply goes to the configured reply-to mailbox (`MAIL_REPLY_TO_ADDRESS`) and never into the ticket; each email tells the client to use the Open Ticket button instead.

### Canned responses (optional)

Staff can optionally reuse saved reply templates ("Canned Responses") when replying to tickets. This entire feature is gated behind a single config flag, `helpdesk.features.canned_replies` (env: `HELPDESK_CANNED_REPLIES_ENABLED`, default on). Setting it to `false` immediately hides the navigation link, the management page, the reply-form picker, and its routes (they return 404), without touching the database or any other code. To remove the feature entirely, delete: `app/Models/CannedResponse.php`, `app/Http/Controllers/Admin/CannedResponseController.php`, `resources/views/admin/canned-responses/`, the `canned_responses` migration, the canned-response routes and nav entry, the picker markup in `resources/views/admin/tickets/partials/show-conversation.blade.php`, and the "Canned response picker" block in `resources/js/pages/admin-ticket-show-page.js`.

### Monthly report CSV export

The Reports page's "Export CSV" button (next to "Download Monthly PDF") downloads the same monthly summary, status/severity breakdown, and category breakdown shown in the PDF, as a spreadsheet-friendly CSV.

## Reporting And Dashboards

### Dashboard: Top Issues and ticket volume

The admin dashboard shows a 30-day ticket volume chart (tickets created per day, scoped to what the viewer can see) and a "Top Issues" panel listing the 5 most common categories this month. Clicking a category in "Top Issues" jumps straight to that category's tickets.

### What the reports count

- **Client tickets only.** The Reports page leaves out internal staff-to-staff tickets, so its totals can be lower than the dashboard, which counts every ticket. The Reports page says so under its heading.
- **Cancelled tickets** still count as *received* volume (they appear in totals and in the Total Tickets Breakdown as their own "Cancelled" slice), but they are **not** part of the performance measures: the resolution rate (headline, monthly and all-time), the open backlog and the SLA figures all leave them out. A withdrawn request is neither "resolved" nor "left unresolved".
- The exact formulas are in `docs/report-metrics.md`.

### Custom Report Generation

Admin → Reports → Custom Report builds a report from any combination of filters — date range, status (including `Cancelled`), severity, category, field office, and assignee — instead of the fixed monthly report.

Like the Reports page, it counts client tickets only: internal staff-to-staff tickets are left out, so both pages give the same totals for the same period.

Two layouts:

- **Summary counts**: totals grouped by one dimension at a time — status, severity, category, root cause, assignee, or field office.
- **Detailed list**: the matching tickets themselves, one row per ticket.

**Field office.** Every ticket stores the department it came from at the moment it was created: a client's own department, or the department of the client a staff member filed it for. The filter, the grouping and the Office column all read that stored value, so a ticket stays under the right office when its client is later moved to another department or deleted. Renaming a department in Admin → Departments renames it on its tickets too. Tickets imported from the old tracker were all filed under one placeholder account in the support department, so the importer (and the update that added this field) reads the department from the start of the subject: `Alder - Printer jammed` goes to Alder. A subject that starts with an organisation that is not one of your departments stays with the support department. Admins and super users can correct a single ticket with *Update Field Office* in the ticket's sidebar; the change is recorded in the audit trail. The staff ticket list has a Field Office filter (it is saved with a saved view and applied to the CSV export), the search box also matches the field office, and the CSV export ends with a Field Office column.

Either layout can be exported as CSV or PDF using the same filters currently applied. A custom date range wider than 2 years is automatically capped, so an accidental very-wide range does not try to load an unbounded number of tickets at once.

The detailed PDF lists the newest 250 matching tickets (it says so at the top, and the report page warns before you download it); the CSV always has every ticket.

### Audit trail export

Shadow accounts can export the System Logs audit trail (Admin → System Logs → Export Audit Trail (CSV)) using whatever search/category/event filters are currently applied on that page. The export covers every matching entry, not just what is shown on the current page.

### Online Users monitor (shadow accounts)

Admin → Online Users shows who is using the system right now. It sits behind the same password lock as System Logs: a shadow account enters its password once to unlock the monitoring tools, and can lock them again from the page.

- **Who is listed:** accounts with activity in the last few minutes (the window is `HELPDESK_PRESENCE_ONLINE_WINDOW_MINUTES`, default 5), split into All / Staff / Clients, with each person's role, status and number of active sessions. The list refreshes itself every 20 seconds.
- **Flagged accounts:** an account is flagged when it has sessions from several IP addresses at once or has been deactivated but is still signed in.
- **Recent failed sign-ins and lockouts** are shown alongside, and a lockout can be cleared from the page.
- **Sign out of all devices:** a shadow account can force any other user out everywhere, including browsers where they chose "Remember me". The person can sign in again with their password; to keep them out, deactivate the account or reset its password.
- Opening the monitor, unlocking it, sign-outs and lockout clearing are all recorded in the audit trail.

## Accounts, Passwords And Access

- **Forgotten passwords:** there is no self-service reset. The sign-in page says "Forgot your password? Contact your administrator", followed by the contact set in `HELPDESK_PASSWORD_HELP_CONTACT` (an email address, phone number or name) when one is configured. The same advice is added to the message people see after too many failed sign-in attempts.
- **New staff accounts:** a technician, super user or admin account created by an administrator must choose its own password the first time it signs in, so the administrator who typed the first password does not keep knowing it. Client accounts are not forced, because clients do not use Account Settings. Resetting a staff password also forces a change.
- **Password rules:** at least 8 characters, and not one of the very common passwords (for example `password`, `12345678`, `qwerty123`, or a common word followed only by digits such as `Welcome2026!`). The forms say so. Passwords that already exist keep working until they are changed.
- **Not found, not forbidden:** a client who opens a ticket, file or notification that is not theirs sees the same "not found" page as for one that does not exist, so ticket numbers cannot be probed.
- **Technicians can read their colleagues' tickets:** a technician can open any client ticket, for example from a link a colleague shared while consulting, but only to read it. The page shows a "Read-only view" notice, hides the reply box and every change control, and the server refuses any change. A technician's own ticket list, counts and notifications still show only the tickets assigned to them. Each read-only view is recorded in the System Logs as `ticket.viewed_read_only`, so access to other people's tickets can be reviewed. Staff-to-staff (internal) requests stay limited to the people involved; anyone else is told the ticket is assigned to someone else. Super users, admins and the shadow account can open and manage every ticket, as before.

## Appearance (Light / Dark / System)

Staff accounts can choose how the console looks under Account Settings → Appearance: **Light**, **Dark**, or **System** (follow the device setting). Client accounts do not have an Account Settings page, but every account, clients included, has the sun/moon button in the top bar to switch between light and dark. The choice is remembered per browser, so it is not shared between devices, and the login page follows the same setting.

## Suggested Day-To-Day Workflow For Staff

This is the simplest routine staff can follow every day.

### For receiving staff

1. Open the new ticket.
2. Read the concern carefully.
3. Check the attachment and contact details.
4. Confirm the correct category and severity.
5. Assign the correct technician.
6. Send a public reply if the client needs acknowledgment.
7. Move or allow the ticket to move into `In Progress`.

### For technicians

1. Review the assigned ticket as soon as possible.
2. Reply to the client if more details are needed.
3. Use internal notes for team-only updates.
4. Change the status to `Pending` only when waiting on something.
5. Move the ticket to `Resolved` when the work is finished.

### For supervisors or admins

1. Monitor open and pending tickets.
2. Check for unassigned or overdue items.
3. Confirm that resolved tickets have proper follow-up.
4. Add the close reason before closing.
5. Close tickets only when the process is truly complete.

## Simple Example

Here is a plain example of how one ticket may move:

1. A client submits a printer issue with a photo and description.
2. The ticket is created as `Open`.
3. A support coordinator assigns it to a technician.
4. The technician sends a public reply: "We are checking this now."
5. The ticket becomes `In Progress`.
6. The technician discovers a part is needed and sets it to `Pending`.
7. After the part is installed, the technician tests the printer and sets the ticket to `Resolved`.
8. The client confirms the printer is working and submits a star rating (with an optional comment).
9. The support team adds the close reason and closes the ticket.

## Recommended Staff Rules

To keep the workflow clean and easy to audit:

- do not leave tickets without an assignee for long
- do not use `Pending` as a long-term parking status without a reason
- do not close a ticket without a clear close reason
- do not use internal notes for information the client still needs to see
- do not mark a ticket `Resolved` if the work has not actually reached a usable outcome

## Quick Reference

If you only remember one simple flow, use this:

`Open` -> `In Progress` -> `Pending` if waiting -> `Resolved` -> `Closed`

If more work is needed after resolution:

`Resolved` -> back to `Open` or `In Progress`

If a recently closed ticket needs more work:

`Closed` -> `In Progress` within `7 days`

If a request turns out not to be needed:

`Open` or `Pending` -> `Cancelled` (only before work starts)
