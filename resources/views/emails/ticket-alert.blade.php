<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;color:#0f172a;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dbe3ea;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#1a2640;color:#ffffff;padding:18px 22px;">
                            <h1 style="margin:0;font-size:18px;font-weight:700;">{{ $headline }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px;">
                            <p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;">{{ $messageLine }}</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 18px 0;">
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:13px;"><strong>Ticket Number:</strong> {{ $ticket->ticket_number }}</td>
                                </tr>
                                @foreach($details as $label => $value)
                                    @if($label !== 'Ticket Number')
                                        <tr>
                                            <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:13px;"><strong>{{ $label }}:</strong> {{ $value }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>

                            @if($actionUrl)
                                <p style="margin:0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:10px 16px;border-radius:8px;background:#2c3d63;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;">{{ $actionLabel ?: 'Open Ticket' }}</a>
                                </p>
                            @endif

                            @if($clientThread)
                                <p style="margin:18px 0 0 0;font-size:12px;line-height:1.5;color:#475569;">Replying to this email will not add your message to the ticket. To write to us, use the Open Ticket button.</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
