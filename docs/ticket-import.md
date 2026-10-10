# Importing tickets

Use the built-in importer for batches of tickets instead of inserting rows by hand. One command handles both CSV exports and the old helpdesk-tracker `.xlsx` workbooks (it picks the format from the file extension). CSV files must include `created_at`, so imported tickets keep their original dates. Reading `.xlsx` files needs the PHP `zip` extension.

A sample CSV to copy: [legacy-ticket-import-template.csv](legacy-ticket-import-template.csv).

1. Put the file in `storage/app/private/imports/` (a relative path in the command is read from there).
2. Check it first. A dry run lists **every** problem row with its row number and saves nothing:

   ```bash
   php artisan tickets:import legacy-batch.csv --default-user=35 --source-timezone=Asia/Manila --dry-run
   ```

3. Import it. Several files, CSV and XLSX mixed, can go in one run:

   ```bash
   php artisan tickets:import legacy-batch.csv legacy-tracker.xlsx --default-user=35 --source-timezone=Asia/Manila
   ```

Uploaded import files are removed by the daily clean-up after `HELPDESK_IMPORT_RETENTION_DAYS` days (7).

## Options

| Option | What it does |
|---|---|
| `--default-user=` | The requester (user id, email or username) for rows that name none. **Required for `.xlsx` files.** |
| `--default-category=Other` | The category (id or name) for rows without one. |
| `--default-priority=medium` | The severity for rows without one. |
| `--default-status=open` | The status for rows without one. `.xlsx` rows that have a resolved date are closed. |
| `--source-timezone=` | The time zone of dates that carry no offset. Without it, `TICKET_IMPORT_TIMEZONE` is used. |
| `--delimiter=,` | The column separator of a CSV file. |
| `--dry-run` | Check every row and save nothing. |
| `--skip-invalid` | Save the good rows even when some rows have problems, and list the rest. |
| `--update-existing` | Update tickets that already exist (found by ticket number; `.xlsx` rows without a number by subject and date received). Without it they are skipped. |

The old names `tickets:import-csv` and `tickets:import-xlsx` still work.

## Notes

- If any row has a problem, nothing is saved. Fix the rows, or add `--skip-invalid`.
- Supported user lookup columns are `user_id`, `user_email`, and `user_username`
- Supported category lookup columns are `category_id` and `category`
- If `updated_at` is omitted, the importer uses the source `created_at`
- The XLSX tracker importer preserves source `ticket_number` values when the sheet includes them
- The XLSX tracker importer can populate requester snapshot fields from a `Requestor Details` column
- The XLSX tracker importer tries to map `Attended by` to a real support assignee when the display name matches exactly
- A resolved, closed or updated date earlier than the logged date (a typing slip in the source sheet) is saved as the logged date, so no ticket is closed before it existed; tracker files keep the original dates as text in the description
- Every ticket stores the department it came from (the "Field Office" in reports). A requester with a real department keeps it. Rows filed under a requester in the support department (the usual placeholder `--default-user`) are sorted by the department named at the start of the subject, as in `Alder - Printer jammed`, and keep the support department when the start of the subject names none of your departments; an administrator can correct a single ticket on its page
