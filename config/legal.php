<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Consent Enforcement
    |--------------------------------------------------------------------------
    |
    | Keep this enabled in production so authenticated users must accept the
    | current legal documents before using the system.
    |
    */
    'require_acceptance' => (bool) env('LEGAL_REQUIRE_ACCEPTANCE', true),

    /*
    |--------------------------------------------------------------------------
    | Policy Metadata
    |--------------------------------------------------------------------------
    |
    | Bump these versions whenever legal text changes. Users are prompted to
    | re-accept once any version is updated.
    |
    */
    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-02-25'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-02-25'),
    'platform_consent_version' => env('LEGAL_PLATFORM_CONSENT_VERSION', '2026-02-25'),
    'ticket_consent_version' => env('LEGAL_TICKET_CONSENT_VERSION', '2026-02-25'),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', 'February 25, 2026'),

    /*
    |--------------------------------------------------------------------------
    | Organization Details
    |--------------------------------------------------------------------------
    */
    'organization_name' => env('LEGAL_ORGANIZATION_NAME', env('HELPDESK_SUPPORT_ORGANIZATION_NAME', 'Hoshishi Corporation')),
    'governing_law' => env('LEGAL_GOVERNING_LAW', 'Republic of the Philippines'),
    'dpo_email' => env('LEGAL_DPO_EMAIL', 'privacy@example.com'),
    'support_email' => env('LEGAL_SUPPORT_EMAIL', 'support@example.com'),
    'contact_address' => env('LEGAL_CONTACT_ADDRESS', 'Pasig City, Metro Manila, Philippines'),
    'retention_period' => env('LEGAL_RETENTION_PERIOD', '3 years from ticket closure unless a longer period is required by law or a valid legal hold.'),
];
