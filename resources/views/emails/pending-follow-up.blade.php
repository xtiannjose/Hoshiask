<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending tickets to follow up</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;color:#0f172a;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dbe3ea;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#1a2640;color:#ffffff;padding:18px 22px;">
                            <h1 style="margin:0;font-size:18px;font-weight:700;">{{ $total === 1 ? '1 pending ticket needs a follow-up' : $total.' pending tickets need a follow-up' }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px;">
                            <p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;">
                                Hello {{ $recipientName }}, {{ $total === 1 ? 'this ticket has' : 'these tickets have' }} been Pending with no activity for at least {{ $days }} days.
                                Check whether {{ $total === 1 ? 'it is' : 'they are' }} still waiting, contact the client if needed, or resolve or cancel the ticket if it is no longer needed. The client has not been emailed.
                                @if($cancelAfterDays > 0)
                                    A Pending ticket with no activity for {{ $cancelAfterDays }} days is cancelled automatically.
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 18px 0;">
                                @foreach($tickets as $row)
                                    <tr>
                                        <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:13px;">
                                            <a href="{{ $row['url'] }}" style="color:#2c3d63;font-weight:700;text-decoration:none;">{{ $row['ticket_number'] }}</a>
                                            &mdash; {{ $row['subject'] }}
                                            <span style="color:#475569;">({{ $row['waiting'] }})</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            @if($total > count($tickets))
                                <p style="margin:0;font-size:12px;color:#475569;">and {{ $total - count($tickets) }} more. Open the Tickets list and filter by Pending to see them all.</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
