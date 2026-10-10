# Importing tickets

Use the built-in importer for ticket batches instead of direct DB inserts. One command handles both CSV exports and the old helpdesk-tracker `.xlsx` workbooks (picked by file extension), and CSV files must include `created_at` so imported tickets keep their original dates.

Sample template:

- [legacy-ticket-import-template.csv](legacy-ticket-import-template.csv)

Check a file first. A dry run lists **every** problem row with its row number and saves nothing:

```bash
php artisan tickets:import legacy-batch.csv --default-user=35 --source-timezone=Asia/Manila --dry-run
```

Then import (several files, CSV and XLSX mixed, can go in one run):

```bash
php artisan tickets:import legacy-batch.csv legacy-tracker.xlsx --default-user=35 --source-timezone=Asia/Manila
```

Notes:

- If any row has a problem, nothing is saved; fix the rows, or add `--skip-invalid` to save the valid rows and list the rest
- The old `tickets:import-csv` and `tickets:import-xlsx` names still work
- Relative file paths resolve from `storage/app/private/imports/`
- Supported user lookup columns are `user_id`, `user_email`, and `user_username`
- Supported category lookup columns are `category_id` and `category`
- If `updated_at` is omitted, the importer uses the source `created_at`
- Existing rows are skipped unless you pass `--update-existing`
- The XLSX tracker importer preserves source `ticket_number` values when the sheet includes them
- The XLSX tracker importer can populate requester snapshot fields from a `Requestor Details` column
- The XLSX tracker importer tries to map `Attended by` to a real support assignee when the display name matches exactly
- A resolved, closed or updated date earlier than the logged date (a typing slip in the source sheet) is saved as the logged date, so no ticket is closed before it existed; tracker files keep the original dates as text in the description
- Every ticket stores the department it came from (the "Field Office" in reports). A requester with a real department keeps it. Rows filed under a requester in the support department (the usual placeholder `--default-user`) are sorted by the department named at the start of the subject, as in `Alder - Printer jammed`, and keep the support department when the start of the subject names none of your departments; an administrator can correct a single ticket on its page
