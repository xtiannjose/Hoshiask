<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | These values keep user-facing product copy aligned while still allowing
    | the support organization and support department to be configured.
    |
    */
    'support_department' => env('HOSHIASK_SUPPORT_DEPARTMENT', 'Hoshishi'),
    'support_brand_name' => env('HOSHIASK_SUPPORT_BRAND_NAME', 'Hoshishi'),
    'support_organization_name' => env('HOSHIASK_SUPPORT_ORGANIZATION_NAME', 'Hoshishi Corporation'),
    'support_team_name' => env('HOSHIASK_SUPPORT_TEAM_NAME', 'Hoshishi Technical Team'),
    'support_logo_path' => env('HOSHIASK_SUPPORT_LOGO_PATH', 'images/Hoshishi Logo.png'),

    /*
    |--------------------------------------------------------------------------
    | Seed Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used by seeders and account management flows.
    | They should be explicitly configured in .env per environment.
    |
    */
    'staff_default_password' => env('STAFF_DEFAULT_PASSWORD'),
    'client_password_mode' => env('CLIENT_PASSWORD_MODE', 'fixed'),
    'client_default_password' => env('CLIENT_DEFAULT_PASSWORD'),
    'shadow_password' => env('SHADOW_PASSWORD'),
    'seed_client_credentials_disk' => env('SEED_CLIENT_CREDENTIALS_DISK', 'local'),
    // HOSHIASK_SEEDED_CLIENT_CREDENTIALS_PATH / HOSHIASK_IMPORT_RETENTION_PATH are older
    // duplicate names, still honoured so existing .env files keep working.
    'seed_client_credentials_path' => env('SEED_CLIENT_CREDENTIALS_PATH', env('HOSHIASK_SEEDED_CLIENT_CREDENTIALS_PATH', 'seeded-client-credentials')),
    'ticket_import_disk' => env('TICKET_IMPORT_DISK', 'local'),
    'ticket_import_path' => env('TICKET_IMPORT_PATH', env('HOSHIASK_IMPORT_RETENTION_PATH', 'imports')),
    'ticket_import_timezone' => env('TICKET_IMPORT_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'attachments_disk' => env('ATTACHMENTS_DISK', 'local'),
    // How long clients may edit or delete their own chat messages.
    'reply_edit_window_minutes' => (int) env('HOSHIASK_REPLY_EDIT_WINDOW_MINUTES', 180),
    'backup' => [
        'disk' => env('HOSHIASK_BACKUP_DISK', env('FILESYSTEM_DISK', 'local')),
        'path' => env('HOSHIASK_BACKUP_PATH', 'backups'),
        // From `php artisan hoshiask:backup-key`. Kept OUTSIDE the server too: without it backups cannot be opened.
        'encryption_key' => env('HOSHIASK_BACKUP_ENCRYPTION_KEY'),
        // A folder on another disk or a mounted network drive; each encrypted backup is copied there and checked.
        'copy_path' => env('HOSHIASK_BACKUP_COPY_PATH'),
        // True when backups are copied away by your own job (rsync, rclone); silences the "only on this server" note.
        'offsite_confirmed' => (bool) env('HOSHIASK_BACKUP_OFFSITE_CONFIRMED', false),
    ],
    // Emails sent to the client who owns a ticket. Deliberately few: one email per
    // milestone, all threaded under the "ticket received" message. The client is never
    // emailed about their own actions or about internal notes.
    'client_emails' => [
        'received' => (bool) env('HOSHIASK_CLIENT_EMAIL_RECEIVED', true),
        'resolved' => (bool) env('HOSHIASK_CLIENT_EMAIL_RESOLVED', true),
        'auto_close_warning' => (bool) env('HOSHIASK_CLIENT_EMAIL_AUTO_CLOSE_WARNING', true),
        // Closed or cancelled by staff (or auto-closed). Off until the office decides it is wanted.
        'closed' => (bool) env('HOSHIASK_CLIENT_EMAIL_CLOSED', false),
        // Agent replies posted within this window are merged into one email per ticket.
        // The scheduler sweeps every five minutes, so real delay is this value plus up to five.
        // 0 sends every reply immediately.
        'reply_batch_minutes' => (int) env('HOSHIASK_CLIENT_EMAIL_REPLY_BATCH_MINUTES', 5),
    ],
    // "Rotating counter" assignment compares each technician's share of a category's tickets over this many days.
    // Counting every ticket ever would send all new tickets to a newly added technician until the lifetime shares match.
    'assignment' => [
        'rotation_window_days' => max(1, (int) env('HOSHIASK_ASSIGNMENT_ROTATION_WINDOW_DAYS', 30)),
    ],
    // A Pending ticket with no activity (status change or reply) for this many days is listed in one reminder email to
    // its assignees, or to the super users when nobody is assigned, and again every this many days while it stays
    // quiet. 0 switches the reminder off. The client is not emailed and the ticket is not closed.
    'pending_follow_up_days' => max(0, (int) env('HOSHIASK_PENDING_FOLLOW_UP_DAYS', 3)),
    // A Pending ticket with no activity for this many days is cancelled by the daily `tickets:auto-cancel-pending`,
    // with the reason written in its notes. 0 switches it off. The client is not emailed about the cancel.
    'pending_auto_cancel_days' => max(0, (int) env('HOSHIASK_PENDING_AUTO_CANCEL_DAYS', 14)),
    // Who people should contact when they forget their password (there is no self-service reset).
    // An email address, phone number or name; shown on the sign-in page and in the lockout message.
    'password_help_contact' => env('HOSHIASK_PASSWORD_HELP_CONTACT'),
    'sla' => [
        // Off: SLA minutes run around the clock. On: only the working days and hours below count, in the application
        // time zone, so a ticket logged on Friday evening starts its target on Monday morning. Applies to the SLA
        // Breached tab, the Needs Attention tab (16 working hours), the SLA badge, the SLA reminders and the
        // Resolution Time report. Days are ISO weekdays, 1 = Monday to 7 = Sunday. closed_dates are public holidays
        // or other closed days, comma-separated YYYY-MM-DD, that count like a weekend.
        'business_hours' => [
            'enabled' => (bool) env('HOSHIASK_SLA_BUSINESS_HOURS_ENABLED', false),
            'days' => env('HOSHIASK_SLA_BUSINESS_HOURS_DAYS', '1,2,3,4,5'),
            'start' => env('HOSHIASK_SLA_BUSINESS_HOURS_START', '08:00'),
            'end' => env('HOSHIASK_SLA_BUSINESS_HOURS_END', '17:00'),
            'closed_dates' => env('HOSHIASK_SLA_BUSINESS_HOURS_CLOSED_DATES', ''),
        ],
        'resolution_target_minutes' => (int) env('HOSHIASK_SLA_RESOLUTION_TARGET_MINUTES', 240),
        'assignment_reminder_minutes' => (int) env('HOSHIASK_SLA_ASSIGNMENT_REMINDER_MINUTES', 210),
        'super_user_new_ticket_notify' => (bool) env('HOSHIASK_SLA_SUPER_USER_NEW_TICKET_NOTIFY', true),
        'super_user_unchecked_minutes' => (int) env('HOSHIASK_SLA_SUPER_USER_UNCHECKED_MINUTES', 120),
        'super_user_unassigned_sla_minutes' => (int) env('HOSHIASK_SLA_SUPER_USER_UNASSIGNED_SLA_MINUTES', 180),
        'resolution_buckets' => [
            'under_one_hour_minutes' => (int) env('HOSHIASK_SLA_RESOLUTION_UNDER_ONE_HOUR_MINUTES', 60),
            'under_four_hours_minutes' => (int) env('HOSHIASK_SLA_RESOLUTION_UNDER_FOUR_HOURS_MINUTES', 240),
            'under_twenty_four_hours_minutes' => (int) env('HOSHIASK_SLA_RESOLUTION_UNDER_TWENTY_FOUR_HOURS_MINUTES', 1440),
        ],
    ],
    'health' => [
        'detail_token' => env('HOSHIASK_HEALTH_DETAIL_TOKEN'),
    ],
    // Shadow "Online Users" monitor. A session counts as online while its last request
    // is within online_window_minutes, and as active (not idle) within active_window_minutes.
    'presence' => [
        'online_window_minutes' => (int) env('HOSHIASK_PRESENCE_ONLINE_WINDOW_MINUTES', 5),
        'active_window_minutes' => (int) env('HOSHIASK_PRESENCE_ACTIVE_WINDOW_MINUTES', 2),
    ],
    // Cleanup prunes the same folders configured above (backup.path,
    // seed_client_credentials_path, ticket_import_path); only retention is set here.
    'cleanup' => [
        'backup_retention_days' => (int) env('HOSHIASK_BACKUP_RETENTION_DAYS', 14),
        'seeded_client_credentials_retention_days' => (int) env('HOSHIASK_SEEDED_CLIENT_CREDENTIAL_RETENTION_DAYS', 14),
        'import_retention_days' => (int) env('HOSHIASK_IMPORT_RETENTION_DAYS', 7),
    ],
    'ops' => [
        'queue_worker_running' => env('HOSHIASK_QUEUE_WORKER_RUNNING'),
    ],
    'features' => [
        // Toggle off to instantly hide/disable canned replies without touching code;
        // see docs/structure.md for the full list of files to delete if
        // removing the feature entirely.
        'canned_replies' => (bool) env('HOSHIASK_CANNED_REPLIES_ENABLED', true),
    ],
];
