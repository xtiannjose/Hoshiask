<?php

return [
    // Comma-separated proxy IPs/CIDRs allowed to set X-Forwarded-* headers, or "*" to trust all.
    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

    'csp' => [
        'enabled_in_production' => true,
        'force' => env('SECURITY_FORCE_CSP', false),
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            // Own pages only: the ticket page frames the PDF and text attachment preview.
            'frame-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'media-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'style-src' => ["'self'"],
            'script-src' => ["'self'"],
            'script-src-attr' => ["'none'"],
            'style-src-attr' => ["'none'"],
            'connect-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
            'upgrade-insecure-requests' => [],
            'block-all-mixed-content' => [],
        ],
    ],

    // Virus scanning of uploaded ticket and reply attachments. It is on by default in production (ClamAV must
    // be installed on the server for it to do anything; until then uploads are allowed and an advisory shows)
    // and off elsewhere. Set HELPDESK_UPLOAD_SCAN=off or clamav to decide explicitly; see "Upload scanning"
    // in docs/security.md.
    'upload_scan' => [
        // "off" or "clamav". Blank follows APP_ENV. Any other value leaves scanning off.
        'driver' => strtolower(trim((string) env('HELPDESK_UPLOAD_SCAN'))) ?: (env('APP_ENV') === 'production' ? 'clamav' : 'off'),
        'clamav' => [
            // A socket path (for example /var/run/clamav/clamd.ctl) wins over host and port.
            'socket' => env('HELPDESK_CLAMAV_SOCKET'),
            'host' => env('HELPDESK_CLAMAV_HOST', '127.0.0.1'),
            'port' => (int) env('HELPDESK_CLAMAV_PORT', 3310),
        ],
        // Seconds to wait for the scanner to connect and then to answer.
        'timeout' => (float) env('HELPDESK_UPLOAD_SCAN_TIMEOUT', 10),
        // What to do with an upload when the scanner cannot be reached: "allow" it (and record the
        // problem) or "block" it. Any value other than "block" allows.
        'on_error' => strtolower(trim((string) env('HELPDESK_UPLOAD_SCAN_ON_ERROR', 'allow'))),
    ],
];
