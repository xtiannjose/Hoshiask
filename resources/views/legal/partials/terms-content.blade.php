@php
    $organization = (string) config('legal.organization_name');
    $governingLaw = (string) config('legal.governing_law');
    $supportEmail = (string) config('legal.support_email');
@endphp

<div class="space-y-4 text-sm text-slate-700">
    <p>
        These Terms of Service govern the use of the Helpdesk Ticketing System operated by {{ $organization }}
        (the "Platform"). By accessing or using the Platform, you agree to these Terms.
    </p>

    <div>
        <h2 class="text-base font-semibold text-slate-900">1. Purpose of the Platform</h2>
        <p>
            The Platform is provided for receiving, tracking, assigning, resolving, and auditing technical support
            tickets and related communications between clients and authorized support personnel.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">2. Eligibility and Account Responsibility</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>You must be an authorized user account provisioned by system administrators.</li>
            <li>You are responsible for safeguarding your credentials and all actions under your account.</li>
            <li>You must promptly report suspected account compromise to {{ $supportEmail }}.</li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">3. Acceptable Use</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Use the Platform only for legitimate service support and operational coordination.</li>
            <li>Do not upload malicious code, unlawful content, or content that violates third-party rights.</li>
            <li>Do not attempt unauthorized access, privilege escalation, data scraping, or service disruption.</li>
            <li>Do not submit information you are not authorized to disclose.</li>
        </ul>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">4. Ticket Content and Attachments</h2>
        <p>
            You retain responsibility for the accuracy, legality, and appropriateness of ticket details and
            attachments. You grant {{ $organization }} and authorized support personnel a limited right to process this
            content strictly for ticket handling, security, compliance, and audit purposes.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">5. Roles and Access Control</h2>
        <p>
            Access is role-based (including client and support console roles). Your ability to view, update, or
            administer tickets and accounts depends on your assigned role and organization policy.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">6. Service Availability</h2>
        <p>
            The Platform is provided on an "as available" basis. Maintenance, upgrades, and security controls may
            temporarily affect availability. Response and resolution times are operational targets, not guarantees.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">7. Security and Monitoring</h2>
        <p>
            Platform activity may be logged for security, abuse prevention, compliance, and incident response.
            Unauthorized activity may result in suspension, termination, and referral under applicable law.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">8. Data Protection</h2>
        <p>
            Personal data processing is governed by the Privacy Notice and Consent. By using the Platform, you
            acknowledge that privacy-related terms apply together with these Terms.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">9. Suspension and Termination</h2>
        <p>
            {{ $organization }} may restrict or terminate access for policy violations, security risks, legal
            requirements, or operational necessity.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">10. Changes to Terms</h2>
        <p>
            Terms may be updated periodically. Material updates may require renewed acceptance before continued use.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">11. Governing Law</h2>
        <p>
            These Terms are governed by the laws of {{ $governingLaw }}, without prejudice to applicable mandatory
            rights.
        </p>
    </div>

    <div>
        <h2 class="text-base font-semibold text-slate-900">12. Contact</h2>
        <p>
            For Terms-related questions, contact: <a class="font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
        </p>
    </div>
</div>
