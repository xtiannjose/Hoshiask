@php
    $organization = (string) config('legal.organization_name');
@endphp

<div class="space-y-4 text-sm text-slate-700">
    <p>
        Before submitting a support ticket, you must confirm the following:
    </p>
    <ul class="list-disc space-y-1 pl-5">
        <li>You are authorized to submit the ticket and provide the attached information.</li>
        <li>
            Any personal data included in the ticket or attachments may be processed by {{ $organization }} and
            authorized support personnel for diagnosis, resolution, communication, and audit.
        </li>
        <li>
            You will avoid including unnecessary sensitive or confidential data not required for resolving the issue.
        </li>
        <li>
            Attachments may be stored and accessed by authorized staff for service handling, security, and compliance.
        </li>
        <li>
            The ticket may trigger service updates by email and in-system notifications relevant to issue handling.
        </li>
    </ul>

    <p>
        If you do not agree to this consent statement, do not submit the ticket through this Platform.
    </p>
</div>
