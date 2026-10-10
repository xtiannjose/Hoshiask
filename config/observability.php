<?php

return [
    'slow_queries' => [
        'enabled' => env('OBSERVABILITY_SLOW_QUERY_ENABLED'),
        'threshold_ms' => (int) env('OBSERVABILITY_SLOW_QUERY_THRESHOLD_MS', 250),
        'include_bindings' => (bool) env('OBSERVABILITY_SLOW_QUERY_INCLUDE_BINDINGS', false),
        'log_to_system_logs' => (bool) env('OBSERVABILITY_SLOW_QUERY_TO_SYSTEM_LOGS', false),
    ],
    'system_logs' => [
        'retention_days' => (int) env('OBSERVABILITY_SYSTEM_LOG_RETENTION_DAYS', 365),
    ],
];
