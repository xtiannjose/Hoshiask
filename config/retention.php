<?php

return [
    // Switched off by default: nothing is removed until this is true. Review with
    // `php artisan hoshiask:enforce-retention --dry-run` first.
    'enabled' => (bool) env('HOSHIASK_RETENTION_ENABLED', false),

    // Years after a ticket was closed or cancelled before its personal details are removed.
    // Keep it in step with the wording of LEGAL_RETENTION_PERIOD in the privacy notice.
    'years' => (int) env('HOSHIASK_RETENTION_YEARS', 3),

    // How many tickets one clean-up pass takes at a time.
    'batch_size' => 200,
];
