@php
    $organization = (string) config('legal.organization_name');
    $dpoEmail = (string) config('legal.dpo_email');
    $supportEmail = (string) config('legal.support_email');
    $address = (string) config('legal.contact_address');
    $retentionPeriod = (string) config('legal.retention_period');
    $backupDays = max(1, (int) config('hoshiask.cleanup.backup_retention_days', 14));
    $logDays = max(1, (int) config('observability.system_logs.retention_days', 365));
@endphp

<div class="space-y-4 text-sm text-slate-700">
    <p>
        This Privacy Notice explains how {{ $organization }} processes personal data in the Hoshiask Ticketing
        System. By using the Platform, you acknowledge this notice and provide consent where consent is the legal
        basis.
    </p>

    <div>
        <h2 class="text-base font-semibold text-slate-900">1. Personal Data We Process</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Account data: name, email, phone, department, role, and account status.</li>
            <li>Ticket data: subject, description, category, priority, status, ratings, and comments.</li>
            <li>Contact/location data submitted in tickets: name, contact number, email, province, municipality/city.</li>
            <li>Support communications: ticket replies, internal handling notes, and related metadata.</li>
            <li>Attachments: files uploaded with tickets and replies.</li>
            <li>System/security data: timestamps, session and activity logs, consent records, IP address, and user agent.</li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">2. Purposes of Processing</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Ticket intake, triage, assignment, support response, and resolution.</li>
            <li>User authentication, role-based access, and account administration.</li>
            <li>Service notifications and operational alerts.</li>
            <li>Audit, quality assurance, compliance, and security monitoring.</li>
            <li>Business continuity, dispute handling, and legal compliance.</li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">3. Legal Bases</h2>
        <p>
            Depending on context, processing is based on legitimate interests, contract/performance of service
            obligations, legal compliance, and consent (including explicit user acknowledgments in this Platform).
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">4. Data Sharing</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Authorized support personnel and administrators under role-based controls.</li>
            <li>Authorized service providers supporting platform infrastructure and communications.</li>
            <li>Regulators, law enforcement, or courts when legally required.</li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">5. Data Retention</h2>
        <p>
            Ticket and related account records are retained for {{ $retentionPeriod }}
        </p>
        <p class="mt-2">
            Encrypted backups of the Platform are kept for {{ $backupDays }} days, so personal data that has already
            been removed from the live system can remain in a backup until that backup is deleted.
        </p>
        <p class="mt-2">
            Security and audit logs, such as sign-ins and administrative actions, are kept for {{ $logDays }} days.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">6. Data Subject Rights</h2>
        <p>
            Subject to applicable law and operational constraints, you may request access, correction, deletion,
            objection, restriction, and portability, and may withdraw consent where consent is the basis of processing.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">7. Security Measures</h2>
        <p>
            The Platform applies access control, authentication, logging, and other technical/organizational safeguards
            to reduce unauthorized access, disclosure, alteration, and loss.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">8. Contact and Complaints</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Data Privacy Contact: <a class="font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900" href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></li>
            <li>Support Contact: <a class="font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
            <li>Address: {{ $address }}</li>
            <li>
                Complaints: if you believe your data protection rights were violated, you may also lodge a complaint
                with the National Privacy Commission of the Philippines
                (<a class="font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900" href="https://privacy.gov.ph">privacy.gov.ph</a>).
            </li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">9. Consent Statement</h2>
        <p>
            By accepting this Privacy Notice in the Platform, you confirm that you understand how your personal data is
            processed for Hoshiask operations and related compliance/security purposes.
        </p>
    </div>
</div>
