# Ticket System Workflow

This guide explains how the helpdesk is used, from the moment a client files a ticket until it is closed. It is written in plain language so that clients, technicians, super users and admins all follow the same process.

## What the system is for

- keep every support request in one place
- give each ticket a clear owner
- show progress from start to finish
- keep the whole conversation, with files
- collect the client's confirmation and rating

## The people

| Role | Works in | In one sentence |
| --- | --- | --- |
| Client | The client portal | Files tickets and follows them. |
| Technician | The staff console | Works the tickets assigned to them. |
| Super user | The staff console | Acknowledges, assigns, escalates and reports on every ticket. |
| Admin | The staff console | Does everything a super user does, and also manages departments, staff accounts and ticket deletion. |
| Shadow | The staff console (hidden) | A monitoring account that sees the audit trail and who is online. Clients see the support team's name instead of "Shadow". |

"Staff" means technicians, super users, admins and the shadow account.

### Who can do what

| | Client | Technician | Super user | Admin | Shadow |
| --- | --- | --- | --- | --- | --- |
| File a ticket and follow it | own tickets | | | | |
| See tickets | own | assigned to them (others read-only) | all | all | all |
| Log a ticket for a client | | yes | yes | yes | |
| Reply, add internal notes, change status or severity, escalate | public replies on own tickets | assigned tickets | all | all | all |
| Acknowledge a ticket | | | yes | yes | yes |
| Change a ticket's type | | | yes | | |
| Change a ticket's dates or field office | | | yes | yes | yes |
| Merge tickets | | | yes | yes | yes |
| Delete tickets | | | | yes | yes |
| Reports and custom reports | | | yes | yes | yes |
| Read the knowledge base | published | yes | yes | yes | yes |
| Write knowledge articles, manage canned responses | | | yes | yes | yes |
| Manage user accounts | | | clients | clients, technicians, super users | every other account |
| Manage departments and assignment rules | | | | yes | yes |
| Audit trail (System Logs), Online Users, temporary passwords | | | | | yes |

Every staff member can use canned responses, save ticket list views, receive notifications and use Account Settings. Clients have notifications but no Account Settings page.

## Signing in

- Sign in with your **username or email address** and your password. Clients land on their dashboard, staff on the staff dashboard.
- The first time (and again whenever the terms change) you are asked to accept the **Terms of Service**, the **Privacy Notice** and a statement that you are authorised to use the platform.
- A session ends after **2 hours without activity**. Staff who tick "stay signed in" stay signed in for 60 days. After a session ends the sign-in page says so.
- **Forgotten passwords:** there is no self-service reset. The sign-in page says "Forgot your password? Contact your administrator", followed by the contact set in `HELPDESK_PASSWORD_HELP_CONTACT` (an email address, phone number or name) when one is configured. The same advice is added to the message people see after too many wrong passwords.
- **Too many wrong passwords:** eight wrong passwords lock that login for 15 minutes for the address that typed them. The shadow account can lift the lock early from the Online Users page, and anyone with server access can with `php artisan helpdesk:unlock-login` (see [operations.md](operations.md)).
- **The bell** in the top bar lists tickets with news since you last looked. Opening a ticket clears its entry, and clearing the list removes them all.
- **Appearance:** every account has the sun/moon button in the top bar to switch between light and dark. Staff can also choose **System** (follow the device) under Account Settings → Appearance. The choice is remembered per browser.

## Simple status meaning

Use these meanings consistently so everyone reads a ticket the same way.

| Status | Simple meaning | When to use it |
| --- | --- | --- |
| `Open` | The ticket was received but work has not really started. | A new ticket, or a ticket reopened for more follow-up. |
| `In Progress` | Someone is working on it. | A technician is checking, troubleshooting or coordinating. |
| `Pending` | The ticket is waiting on something. | Waiting for the client, a schedule, an approval, parts or outside action. |
| `Resolved` | The issue looks fixed, but the ticket is not yet closed. | The work is done and the team waits for the client's confirmation or the automatic close. |
| `Closed` | The ticket is finished. | The final status, once a close reason is recorded. |
| `Cancelled` | The ticket was called off before real work happened. | The client or staff cancel a request that is no longer needed while it is still `Open` or `Pending`. |

An **active ticket** is one that is `Open`, `In Progress` or `Pending`. A **completed ticket** is `Resolved` or `Closed`.

## The life of a ticket

### 1. A client files a ticket

The client opens **New Ticket** and fills in:

- name, contact number and email
- province and municipality or city
- subject
- category
- description of the issue
- attachments (optional; a screenshot helps the team solve it faster)
- the consent checkbox

The client does not choose a severity. The ticket starts as **Severity not set**, and staff set it.

What happens next:

- the system creates a unique ticket number (`TK-` and eight characters)
- the ticket starts as `Open`
- super users, admins and the shadow account are emailed that a new ticket arrived, and the client gets a "We received your ticket" email
- sending the same form twice (a double click, the back button) still creates only one ticket

### 2. Staff review the new ticket

Staff check that:

- the request is complete
- the category is right
- the attachments can be opened
- the right technician is assigned

Recommended first steps:

1. Read the subject, the description and the attachments.
2. **Acknowledge** the ticket (super user, admin or shadow), so SLA tracking shows it was formally received.
3. Set the **severity** (Severity 1 is the most urgent, then 2 and 3).
4. Assign the right technician.
5. Send a public reply if the client needs to know that work has started.

If staff post a normal public reply on an `Open` ticket, the system moves it to `In Progress` by itself.

### 3. The ticket is assigned

An assigned ticket has a clear owner. Staff can assign one or more technicians and change the assignment later. One of them is the **primary** assignee. Assign as early as possible, avoid leaving tickets unassigned, and reassign quickly when the first person is away. The ticket's page keeps a note of every handover.

### 4. A technician works on it

While working, a technician:

- reads the full ticket
- asks questions and gives updates in replies
- attaches proof, screenshots or documents when needed
- keeps the status accurate: `In Progress` while actively working, `Pending` only when waiting for something outside their control (for example a reply from the client, an approval from another office, replacement equipment or a site visit)

### 5. Public replies and internal notes

Staff can send two kinds of messages:

- **Public reply:** the client sees it, and may be emailed about it. Use it for progress, questions, instructions and confirmations.
- **Internal note:** only staff see it. Use it for coordination, technical observations, reminders and handover notes.

An internal note must never hold something the client still needs in order to act.

#### Closed and cancelled tickets

Once a ticket is `Closed` or `Cancelled`, the client can no longer reply. Staff can still add notes, but only as **internal notes**:

- The reply box says so, and the "Internal note" box is locked on.
- The server enforces it too, so a note on a closed or cancelled ticket is never shown to the client and never emailed to them, even if the form is tampered with.
- A `Resolved` ticket is different: the conversation stays open (the client can still reply, rate or reopen), so staff can still send public replies.

### 6. The ticket reaches a solution

When the issue is fixed, staff move the ticket to `Resolved`. That means the team believes the problem is addressed, but the ticket is not yet in its final state. It is the stage for asking the client to test the fix and for preparing the close reason.

### 7. The client confirms and rates

The client can confirm that the issue is resolved with **Mark as Resolved**. They must:

- give a rating from 1 to 5 stars
- tick the confirmation

A comment is optional. It becomes required only for 1 or 2 stars, so the team learns what went wrong. The rating feeds the satisfaction figures in the reports.

### 8. Closing the ticket

`Closed` is the final status.

- A **close reason** is required before a ticket can be closed.
- Technicians (on their tickets), super users, admins and the shadow account can close a ticket when the reason is given.
- A closed ticket no longer takes replies from the client.
- **Root cause (optional):** when resolving or closing, staff can record a root cause category (`Hardware`, `Network`, `Software Bug`, `User Error`, `External / Vendor` or `Other`) and a short note. Filling it in makes the Root Cause breakdown of the Custom Report meaningful.

`Closed` means: the work is complete, the client got the support they needed, and the case needs no more routine follow-up.

## Reopening and reverting

### When a client replies again

If a client replies after the ticket was marked `Resolved`, the system moves it back to `Open` so the team can continue. A client can also **reopen** a resolved or closed ticket themselves with a reason, but a `Closed` ticket only within **7 days** of its closing.

### When staff revert a ticket

Staff can **revert** a resolved or recently closed ticket to `In Progress` ("Revert to In Progress"). A `Closed` ticket can only be reverted within **7 days** of its closing. After that it can no longer be reverted through the normal workflow.

## Cancelling a ticket

Sometimes a request turns out not to be needed. Cancelling records that clearly, instead of leaving the ticket `Open` for ever.

- The client who owns the ticket, or a technician, super user or admin, can cancel it.
- Only while it is `Open` or `Pending`. Once work has started or the ticket is resolved or closed, it follows the normal resolve and close path instead.
- A **reason is required**, whichever way it is done: the client's Cancel Ticket button, a status change, the quick update or the bulk action.
- The ticket moves to `Cancelled` and leaves the Needs Attention and active-work views. It stays under the **History** tab. The reason is saved as the **Cancellation Reason**, and shown with **Cancelled At** in the Ticket Details panel for staff and client. (It is also a column of the ticket export and the last column of the detailed custom report CSV.)
- The client can no longer reply. Staff can only add internal notes.
- "Acknowledge Ticket" is not offered: acknowledgment feeds SLA tracking, which does not apply to a withdrawn request.
- Cancelled tickets are left out of the resolution rate, the open backlog and the SLA figures (see "What the reports count").
- Staff can move a cancelled ticket back to `Open` if it was cancelled by mistake. There is no time limit, and reopening clears the cancellation date and reason.

## Escalating a ticket

Escalation hands a ticket to another staff member for a closer look (a senior technician, a specialist), with a record of why and by whom.

- A technician, super user or admin working the ticket can **Escalate Ticket** to another staff member (not to themselves). A reason is required.
- The ticket shows an "Escalated" badge to staff and to the client. The client sees that it was escalated, not the reason or the person.
- Escalating does not change the status. It is a separate flag on top of the normal workflow.
- Whoever can manage the ticket can **Clear Escalation** when it no longer applies. The history stays in the internal notes and the audit trail.

## Merging duplicates

When the same problem was filed more than once, an admin-level staff member (super user, admin or shadow) can merge the tickets: tick two or more in the ticket list and choose **Merge**.

- The **oldest** ticket stays. The others are set to `Closed`, and their replies and files move to the one that stays.
- An internal note on the remaining ticket names each merged ticket. The people assigned to the merged tickets are added to the one that stays.
- If the tickets belong to different requesters, an extra box must be ticked to confirm. Otherwise the merge is refused.
- Merged duplicates count as Closed tickets in the reports.

## Messages and files

### Starting a ticket

- Attachments are optional. Each file can be up to `10 MB`, at most 10 files per ticket or reply. Allowed types: images (`jpg`, `jpeg`, `png`), PDF, Word, text and Excel files.
- Staff who log a ticket for a client use **New Ticket** (technician, super user and admin). They pick the requester account from a list that shows the first 50 accounts by name. The search box above the list finds any other account by name, username, email, phone or department, and the line under the list says how many accounts match.
- An **internal ticket** has a staff member as the requester and is assigned to a staff member. It is only seen by the people involved, no client emails are sent for it, and the reports leave it out.

### Replies

- A reply can hold a message, an attachment, or both, so a short note with a screenshot is fine.
- A ticket page shows the newest 200 messages. When a conversation is longer, a notice above the thread says how many of the oldest messages are not shown, and **Show all messages** opens the whole thread.
- Opening an image or a PDF inside the page is a preview. Everything else downloads.

### Editing and deleting replies

- **Clients** can edit or delete their own messages for 3 hours after sending (the time is `HELPDESK_REPLY_EDIT_WINDOW_MINUTES`).
- **Staff** can edit or delete their own replies from the conversation.
- A deleted message stays in the thread as "This message was deleted." The old text of an edited or deleted message is kept in the shadow account's System Logs only, and is never shown in a conversation.

## Working with the ticket list (staff)

The **Tickets** page has four tabs:

| Tab | What it shows |
| --- | --- |
| Tickets | Active tickets. |
| Needs Attention | Active tickets that are more than 16 hours old. Technicians do not see this tab. |
| SLA Breached | Active tickets older than the SLA target (240 minutes by default). They need action right now. |
| History | Resolved, closed and cancelled tickets. |

A technician sees only the tickets assigned to them. Everyone else sees all tickets.

- **Filters:** status, severity (including "Severity not set"), category, assigned staff (or "Unassigned"), requester account, field office, province and municipality, ticket type, and a month or date range.
- **Search:** matches the subject, the ticket number, the description, the field office, and the requester's name or email.
- **Export CSV** downloads the tickets that match the current filters. The last two columns are Cancellation Reason and Field Office. Every export is written to the audit trail.
- **Saved views:** any staff member can save the current filters under a name and open them again with one click. Saving under a name you already used replaces that view. Saved views are personal and can be deleted by their owner. They suit repeat lookups such as "unassigned Severity 1 tickets".

### Bulk actions

Tick tickets (up to 200 at a time) and use the buttons that appear:

| Button | What it does | Who |
| --- | --- | --- |
| Assign | Assigns the chosen staff to all of them. | Staff, on the tickets they can see |
| Status | Changes the status. Closing or cancelling needs a reason. | Staff, on the tickets they can see |
| Severity | Sets the severity. | Staff, on the tickets they can see |
| Merge | Merges duplicates (see above). | Super user, admin, shadow |
| Delete | **Permanently** removes the tickets with their replies and files. | Admin, shadow |

Deleting cannot be undone, except from a backup. Cancelling or closing is usually what you want.

## Automatic help

### SLA reminders and the SLA Breached tab

The SLA target is how long a ticket should take: **240 minutes** by default (`HELPDESK_SLA_RESOLUTION_TARGET_MINUTES`). Staff are reminded by email:

- the assigned technicians, 210 minutes after the assignment, if the ticket is still active
- super users, admins and the shadow account, when an active ticket stays unassigned: once at 120 minutes ("Unchecked Ticket Alert") and once at 180 minutes ("Unassigned SLA Warning"), counted from when the ticket was created

### Assignment rules

Admins and the shadow account can set rules per category (Admin → Assignment Rules): a category is linked to one or more technicians with a relative weight (for example "Starlink: Mark 70, Eric 30"). When a ticket arrives without an assignee and its category has rules, the system assigns it:

- **Weighted random:** each new ticket rolls against the weights.
- **Rotating counter:** the ticket goes to whichever technician is furthest below their target share, based on the category's assignment history.

The method is one setting for all rules, changed on the same page. If a category has no rules, or every technician in it is deactivated, the ticket stays unassigned.

### Automatic closing

A resolved ticket that sits untouched for **7 days** (counted from when it was resolved) is closed by the daily job `tickets:auto-close-resolved`. The assignments and a standard close-reason note are kept.

The client is warned first: **5 days** after the resolution (2 days before the close) they get one email, and the ticket is not closed until at least **24 hours** after that email. A ticket is closed without a warning only if its client cannot be emailed (no address, inactive account), the warning is switched off, or the client was never sent the "resolved" email for that resolution (for example because it was logged after the fact, see below). If a warning keeps failing to send, the ticket is closed anyway 3 days after its normal close date, so a mail problem cannot leave tickets open for ever.

### Emails to the client

A client is emailed only at moments that need their attention, and every email about a ticket lands in one conversation in their inbox (they share a subject such as `[TK-4XJ7QWPA] Printer is jammed`).

| When | Email |
| --- | --- |
| The ticket was received (filed by the client, or for them by staff) | "We received your ticket" with the ticket number, sent once. |
| Staff move it to Resolved | "Your ticket has been resolved": confirm or reopen, and the automatic close date. Sent once per resolution. A ticket that is reopened and resolved again emails again. |
| 5 days after the resolution | One reminder that it closes automatically in 2 days. |
| Staff reply publicly | One email per ticket with all the replies of the last few minutes merged (5 minutes by default). |
| Closed or cancelled by staff, or closed automatically | Off by default (`HELPDESK_CLIENT_EMAIL_CLOSED=true` turns it on). |

**Tickets logged after the fact.** When staff enter a past ticket (a date more than 24 hours back, or a ticket that is already resolved, closed or cancelled when saved), the client is not told: there is no "We received your ticket" email, and no resolved, closed or cancelled email when that date is more than 24 hours old. The auto-close reminder only follows a "resolved" email that was really sent.

Clients are never emailed about their own actions, internal notes, assignment changes or status shuffles, and no email goes to the requester of an internal ticket. Each moment can be switched off with the `HELPDESK_CLIENT_EMAIL_*` settings. The app does not read incoming mail: a client's emailed reply goes to the reply-to mailbox (`MAIL_REPLY_TO_ADDRESS`) and never into the ticket, and each email tells the client to use the Open Ticket button instead.

### Canned responses

Staff can insert saved reply templates when replying. Super users, admins and the shadow account write and manage them (Canned Responses in the menu). The whole feature can be switched off with `HELPDESK_CANNED_REPLIES_ENABLED=false`: the menu link, the management page and the picker in the reply form disappear, and nothing in the database is touched.

### Knowledge base

A self-service library of FAQs, policies and guides, separate from the ticket list, so people can find an answer without filing a ticket.

- Super users, admins and the shadow account write and manage articles (Knowledge Base in the menu): a title, a category (`FAQ`, `Policy` or `Guide`) and the content. An article is either a draft or published.
- Technicians can read published articles but not write them.
- Clients have their own Knowledge Base page where they can search and read published articles. They never see drafts.
- An article is not linked to a ticket category: it is a general library.
- **Starter content:** `php artisan db:seed --class=StarterContentSeeder` adds 7 published articles and 7 canned responses. It needs an active admin or super user account to credit the entries to. It is safe to run again, including on the live server: an entry is added only when none with the same title exists, so staff edits are never overwritten.

## Reports and monitoring

### Dashboard

The staff dashboard shows the number of tickets, the active ones, the Severity 1 tickets, tickets that need attention, the latest tickets, this month's tickets by status and severity, a **30-day chart** of tickets created per day, and a **Top Issues** panel with the 5 most common categories this month. Clicking a category jumps to its tickets. A technician's dashboard covers only their own tickets.

### What the reports count

- **Client tickets only.** The Reports page leaves out internal (staff-to-staff) tickets, so its totals can be lower than the dashboard, which counts every ticket. The Reports page says so under its heading.
- **Cancelled tickets** count as *received* volume (they appear in the totals and as their own "Cancelled" slice of the Total Tickets Breakdown), but not in the performance measures: the resolution rate, the open backlog and the SLA figures all leave them out. A withdrawn request is neither "resolved" nor "left unresolved".
- The exact formulas are in [report-metrics.md](report-metrics.md).

Super users, admins and the shadow account open **Reports**. "Download Monthly PDF" and "Export CSV" give the month's summary, the status and severity breakdown and the category breakdown.

### Custom report

Reports → Custom Report builds a report from any mix of filters: date range, status (including `Cancelled`), severity, category, field office and assignee. Like the Reports page, it counts client tickets only.

- **Summary counts:** totals grouped by one thing at a time: status, severity, category, root cause, assignee or field office.
- **Detailed list:** the matching tickets, one row each.

Either layout exports as CSV or PDF with the filters you applied. A date range wider than 730 days (about 2 years) is capped automatically. The detailed PDF lists the newest 250 matching tickets (it says so at the top, and the page warns before you download). The CSV always has every ticket.

### Field office

Every ticket stores the department it came from at the moment it was created: the client's own department, or the department of the client a staff member filed it for. The filter, the grouping and the Office column all read that stored value, so a ticket stays under the right office when its client later moves to another department or is deleted. Renaming a department in Admin → Departments renames it on its tickets too. Admins, super users and the shadow account can correct one ticket with **Update Field Office** in the ticket's sidebar; the change is written to the audit trail. Tickets imported from the old tracker are sorted by the department at the start of the subject (see [ticket-import.md](ticket-import.md)).

### Audit trail and Online Users (shadow account)

- **System Logs** (Admin → System Logs) is the audit trail: sign-ins and failures, password and role changes, exports, file downloads, ticket actions and system events. It sits behind a password lock: the shadow account enters its password once to unlock the monitoring tools, and can lock them again. **Export Audit Trail (CSV)** exports every entry that matches the current filters.
- **Online Users** (Admin → Online Users, behind the same lock) shows who is using the system right now:
  - People active in the last few minutes (`HELPDESK_PRESENCE_ONLINE_WINDOW_MINUTES`, 5 by default), split into All, Staff and Clients, with their role, status and number of sessions. The list refreshes itself every 20 seconds.
  - Accounts are **flagged** when they have sessions from several addresses at once, or were deactivated but are still signed in.
  - Recent failed sign-ins and lockouts are shown alongside, and a lockout can be cleared from the page.
  - **Sign out of all devices:** the shadow account can force any other user out everywhere, including browsers with "Remember me". They can sign in again with their password. To keep them out, deactivate the account or reset its password.
  - Opening the monitor, unlocking it, sign-outs and clearing lockouts are all written to the audit trail.
- A technician opening a client ticket that is not assigned to them (read-only) is recorded in System Logs as `ticket.viewed_read_only`.

## Administration

### User accounts

Super users, admins and the shadow account open **Users**. There are two lists: Staff and Clients (a super user sees only the clients).

- **Who can manage whom:** the shadow account manages every other account. An admin manages technicians, super users and clients. A super user manages clients.
- **Create** an account with a username, a name, a role, a department and a password. Usernames use lowercase letters, numbers, dots, dashes and underscores. Email and phone are optional. An email address must be unique, ignoring capital letters. The roles you can choose depend on your own role (see the table above).
- **Edit, deactivate or delete.** A deactivated account cannot sign in. You cannot deactivate or delete your own account, nor delete a **locked profile** until it is unlocked. Deleting an account keeps the history: its tickets, replies, knowledge articles and canned responses move to a hidden archive account ("Deleted Support Account" or "Deleted Client Account"), and its assignments are removed.
- **Passwords:** a new staff account (and a staff password reset) must choose its own password at the first sign-in, so the person who typed the first one does not keep knowing it. Clients are not forced, because they do not use Account Settings. A password needs at least 8 characters and must not be a very common one (such as `password`, `12345678` or `Welcome2026!`). Super users cannot change a client's password.
- **Temporary passwords:** only the shadow account can reset another account's password, with **Issue Temporary Password** on the person's page. The system makes a random password and signs that person out everywhere, and a staff member must choose a new password at the next sign-in. **Reveal Temporary Password** shows it once, within 10 minutes: copy it straight away, because it cannot be shown again. If the 10 minutes pass, issue a new one.
- **Account Settings** (staff only): everyone can change their name, phone and password. The shadow account and admins can also change their own username, email and department; a super user or technician asks an admin for those. Changing a password, or an admin's or shadow's own username or email, asks for the current password. Changing a password signs the person out of their other devices.

### Departments

Admins and the shadow account manage departments (Admin → Departments): a name and a logo (`png`, `jpg`, `jpeg`, `webp`, `bmp` or `gif`, up to 2 MB). A name must be unique, ignoring capital letters. Departments cannot be deleted. The department of a client is the field office of their tickets.

### Hidden from clients

A client who opens a ticket, file or notification that is not theirs sees the same "not found" page as for one that does not exist, so ticket numbers cannot be guessed.

### Technicians can read their colleagues' tickets

A technician can open any client ticket, for example from a link a colleague shared while consulting, but only to read it. The page shows a "Read-only view" notice, hides the reply box and every change control, and the server refuses any change. The technician's own ticket list, counts and notifications still show only their tickets. Each read-only view is recorded in System Logs. Internal tickets stay limited to the people involved: anyone else is told the ticket is assigned to someone else. Super users, admins and the shadow account can open and manage every ticket.

## Suggested day-to-day routine

### For receiving staff (super user, admin)

1. Open the new ticket.
2. Read the concern, the attachment and the contact details.
3. Acknowledge it, set the severity and confirm the category.
4. Assign the right technician.
5. Send a public reply if the client needs to know it was received.
6. Move the ticket to `In Progress`, or let a reply do it.

### For technicians

1. Open your assigned tickets as soon as possible.
2. Reply to the client when you need details.
3. Use internal notes for team-only updates.
4. Use `Pending` only when waiting on something.
5. Move the ticket to `Resolved` when the work is finished.

### For super users and admins

1. Watch the active tickets, the Needs Attention and SLA Breached tabs, and anything unassigned.
2. Check that resolved tickets get follow-up.
3. Add the close reason before closing.
4. Close a ticket only when the process is truly complete.

## A simple example

1. A client files a printer problem with a photo and a description.
2. The ticket is created as `Open`.
3. A super user acknowledges it, sets the severity and assigns a technician.
4. The technician replies "We are checking this now", and the ticket becomes `In Progress`.
5. The technician finds that a part is needed and sets the ticket to `Pending`.
6. After the part is installed, the technician tests the printer and sets the ticket to `Resolved`.
7. The client confirms that the printer works and gives a star rating (with an optional comment).
8. Staff add the close reason and close the ticket. If nobody does, it closes by itself 7 days after it was resolved.

## Rules that keep the process clean

- do not leave tickets without an assignee for long
- do not use `Pending` as a parking place without a reason
- do not close a ticket without a clear close reason
- do not put information the client still needs in an internal note
- do not mark a ticket `Resolved` before the work has reached a usable result

## Quick reference

The normal path:

`Open` → `In Progress` → `Pending` if waiting → `Resolved` → `Closed`

If more work is needed after resolution: `Resolved` → back to `Open` or `In Progress`.

If a recently closed ticket needs more work: `Closed` → `In Progress` within `7 days`.

If a request turns out not to be needed: `Open` or `Pending` → `Cancelled` (only before work starts).
