# Report Metric Definitions

This document explains the business meaning of the report metrics so future UI or query changes do not silently shift how people interpret the numbers.

## Monthly Completion Rate

- Source area: `App\Http\Controllers\Admin\ReportController` via `App\Services\Admin\Reports\MonthlyReportDatasetService`
- Definition: `tickets created in the month that are now resolved/closed / (tickets created in the month - cancelled tickets created in the month)`
- Important nuance: the ticket is credited to its **creation month**, not the month it was resolved.
- Cancelled tickets are left out of the denominator: a withdrawn request is neither resolved nor left unresolved. They still count in the month's `received` volume. The same rule applies to the headline KPI card and to the all-time row.

Example:

- A ticket created on `June 24, 2025` and resolved on `July 16, 2025` still contributes to the **June 2025** completion rate.
- It does **not** increase June's monthly `resolved` count, because `resolved` is still tracked by the month the completion timestamp happened.

## Monthly Resolved Count

- Definition: tickets with `resolved_at` or `closed_at` inside the selected month.
- Purpose: measures operational throughput by completion month.

## Open Backlog (Month End)

- Definition: tickets created on or before the month-end cutoff that were still not resolved/closed **and not cancelled** at that point in time.
- A ticket cancelled on or before the cutoff is not backlog. In the running month-by-month count it stops counting from the month it was cancelled (`cancelled_at`).
- Purpose: historical snapshot of open workload at period end.

## Resolution Rate Card Copy

The reports page and PDF should describe the metric as:

- "Tickets created this period that are now resolved/closed. Cancelled tickets are not counted."

That wording matches the current report calculation and avoids confusing it with same-month throughput.

## Scope: what the reports include

- **Client tickets only.** `Ticket::applyReportableConstraint` leaves out internal staff-to-staff tickets, on the Reports page and in the Custom Report (page, CSV and PDF), so both give the same totals for the same period. Because of this, report totals can be lower than the admin dashboard, which counts every ticket. Both pages show a short note explaining it.
- **Cancelled tickets** stay in *volume* figures (received, total tickets in scope, the Total Tickets Breakdown, where they have their own "Cancelled" slice so the slices add up to the total) but are excluded from *performance* figures: the resolution rate, the open backlog and every SLA measure below.
- **Merged tickets** count like any other Closed ticket. Merging marks each duplicate Closed with the merge time as its resolved time, so a duplicate adds to the completed count and the resolution rate, and gets a resolution time in the Resolution Time bands. This is a decision (2026-10-10, issue #228), not an oversight: do not exclude merged duplicates unless the maintainer changes it.

## SLA Overview: Resolution Time Compliance

- Source area: `App\Http\Controllers\Admin\ReportController` via `App\Services\Admin\Reports\SlaReportService`
- Scope: completed tickets created inside the selected reports scope (`selected month`, or `detail filter` when applied), **excluding cancelled tickets** (they are not measured against SLA targets)
- Completion uses `resolved_at`, with `closed_at` as a fallback when `resolved_at` is missing; the time counted is from `created_at` to completion.
- The time is counted in minutes round the clock, unless `HELPDESK_SLA_BUSINESS_HOURS_ENABLED=true` (added 2026-10-11, off by default). Then only the working days and hours count (`App\Support\BusinessHours`), so the bands read in working time and a month reported before and after switching the setting on is not comparable. Public holidays are not skipped.
- Bands (each limit is configurable with the `HELPDESK_SLA_RESOLUTION_*` settings):
  - `Under 1 Hour`: `< 1 hour`
  - `Under 4 Hours`: `>= 1 hour` and `< 4 hours`
  - `Under 24 Hours`: `>= 4 hours` and `< 24 hours`
  - `Above 24 Hours`: `>= 24 hours`
- Reports UI: the share of completed tickets in each band, with the count as supporting detail. This is the only SLA measure the page shows; first response, acknowledgment rate, breach rate and satisfaction SLA were removed from the page on 2026-04-07 and from the calculation on 2026-10-09.
