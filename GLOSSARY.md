# Helpdesk

A ticketing system where clients from the field offices ask the support team for help, and staff track each request from receipt to closure.

## People

**Client**:
A person outside the support team who files tickets and follows them in the client portal.
_Avoid_: customer, end user

**Requester**:
The person a ticket is for: usually a client, or a staff member on an internal ticket.
_Avoid_: requestor, ticket owner

**Staff**:
Anyone who works tickets in the staff console: technicians, super users, admins and the shadow account.
_Avoid_: support, agent, ITD staff

**Technician**:
A staff member who works the tickets assigned to them and may read, but not change, other client tickets.
_Avoid_: technical user, tech

**Super User**:
A staff supervisor who acknowledges, assigns, escalates and reports on every ticket.
_Avoid_: supervisor, coordinator

**Admin**:
A top-level administrator who, beyond a super user, manages departments and staff accounts.
_Avoid_: super admin

**Shadow account**:
A hidden top-level account for monitoring the system itself: audit trail, online users, sign-in history.
_Avoid_: root, superadmin

**Department**:
The organisation unit a user belongs to. The support department is the team that runs the helpdesk.

**Field Office**:
The department a ticket came from, fixed when the ticket is created so it survives the requester moving department.
_Avoid_: office, branch, department (when talking about a ticket)

## Tickets

**Ticket**:
One support request, identified by its ticket number (`TK-` and eight characters).
_Avoid_: case, request, issue

**Internal ticket**:
A staff-to-staff ticket whose requester is a staff member. Reports leave it out.
_Avoid_: staff ticket

**External ticket**:
A ticket whose requester is a client.

**Category**:
What a ticket is about, such as Printer or Starlink. Assignment rules hang off it.
_Avoid_: type, topic

**Severity**:
How urgent a ticket is: Severity 1 (most urgent), 2 or 3, or "Severity not set".
_Avoid_: priority

**Root cause**:
Why the problem happened (Hardware, Network, Software Bug, User Error, External / Vendor, Other), recorded optionally when resolving or closing.

**Logged after the fact**:
A ticket entered with a date more than 24 hours in the past, or already finished when saved. The client is not emailed about it.
_Avoid_: back-dated, historical ticket

**Imported ticket**:
A ticket brought in from a CSV export or the old helpdesk-tracker spreadsheet rather than filed in the app.
_Avoid_: legacy ticket

## Lifecycle

**Status**:
Where a ticket stands: Open, In Progress, Pending, Resolved, Closed or Cancelled.

**Active ticket**:
A ticket that is Open, In Progress or Pending: work is still owed.
_Avoid_: open ticket (Open is one status, not the group)

**Pending**:
The status for a ticket that waits on the client or on something outside the team's control. Its SLA clock does not count: the row badge reads "Awaiting client", and the SLA Breached tab, the Needs Attention count and the SLA reminders leave it out. Staff are reminded of a quiet one after 3 days, and it is cancelled automatically after 14 days with no activity.

**Completed ticket**:
A ticket that is Resolved or Closed.
_Avoid_: closed ticket (Closed is one status, not the group), done

**Resolved**:
Support believes the issue is fixed; the client can still confirm, rate, reply or reopen.

**Closed**:
The final status after resolution. Requires a close reason.

**Cancelled**:
A request withdrawn while still Open or Pending, before real work started. Requires a cancellation reason.
_Avoid_: deleted, void

**Reopen**:
Move a completed or cancelled ticket back to Open. A client may reopen a closed ticket only within 7 days of closure.

**Merge**:
Staff combining duplicate tickets into one primary ticket. The duplicates are set to Closed and count in reports like any other Closed ticket (see `docs/report-metrics.md`).

**Revert**:
Staff moving a Resolved or Closed ticket back to In Progress, for a Closed ticket only within 7 days of closure.

**Auto-close**:
The daily closing of Resolved tickets the client left untouched for 7 days, after one warning email.

**Acknowledgment**:
A super user, admin or shadow account formally marking a ticket as received.
_Avoid_: receipt, first response

**Escalation**:
A flag handing a ticket to another staff member for a closer look, with a reason. It sits on top of the status and does not change it.

## Working a ticket

**Assignee**:
A staff member responsible for a ticket. A ticket can have several; one of them is the primary assignee.
_Avoid_: owner, handler

**Assignment rule**:
A per-category weighting that picks the assignee for a new ticket filed without one.

**Public reply**:
A message on a ticket that the client sees and may be emailed about.
_Avoid_: comment, response

**Internal note**:
A message on a ticket that only staff see. After Closed or Cancelled, staff can only add internal notes.
_Avoid_: private reply, comment

**Read-only view**:
A technician viewing a client ticket that is not assigned to them. Every such view is recorded in the audit trail.

**Canned response**:
A saved reply template staff can insert into a reply.
_Avoid_: macro, template

**Saved view**:
A staff member's personal, named set of ticket-list filters.

**Knowledge article**:
A published FAQ, Policy or Guide in the self-service knowledge base. A draft is never shown to clients.
_Avoid_: KB post, help page

## Measuring

**SLA target**:
The time a ticket should take to be completed: 4 hours by default. SLA reminders and the SLA Breached tab measure against it.

**Business hours** (SLA clock):
The working days and hours that SLA minutes count when `HELPDESK_SLA_BUSINESS_HOURS_ENABLED` is on. Nights and weekends do not use up the SLA target. Public holidays are not skipped. Off by default, when SLA minutes run around the clock.

**SLA breach**:
An active ticket, or a ticket completed late, that passed the resolution target.

**Volume figures**:
Report numbers that count every received ticket, cancelled ones included.

**Performance figures**:
Report numbers that judge the team: resolution rate, open backlog and every SLA measure. Cancelled tickets are left out.

**Resolution rate**:
The share of tickets created in a period that are now completed, cancelled tickets left out.
_Avoid_: completion rate, closure rate

**Open backlog**:
Tickets created by a cutoff date that were still neither completed nor cancelled at that date.

## Privacy and operations

**Consent**:
A user's acceptance of a specific version of the terms, privacy notice and ticket consent.

**Retention period**:
How long a ticket's personal details are kept after it is Closed or Cancelled (3 years by default).

**Retention clean-up**:
Removing a ticket's personal details, text and files once its retention period ends, keeping the facts reports need. The ticket then reads "Removed after the retention period".
_Avoid_: anonymisation, purge, deletion

**Legal hold**:
A flag that keeps one ticket out of the retention clean-up until it is released.

**Audit trail**:
The permanent record of security-relevant actions, exports and system events, shown on the System Logs page.
_Avoid_: activity log, history

**Advisory**:
A plain-English note from the production status check about something quietly switched off. Unlike a warning, it never fails a deploy.

**Warning**:
A production status check finding that fails the deploy health check.

**Credential handoff**:
A temporary password the shadow account issues for another account, revealed once within 10 minutes and then used up.

**Backup key**:
The separate key that encrypts backups. Without it a backup cannot be opened, so it is kept off the server.
