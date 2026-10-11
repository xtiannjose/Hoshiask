Hello {{ $recipientName }},

{{ $total === 1 ? 'This ticket has' : 'These tickets have' }} been Pending with no activity for at least {{ $days }} days.
Check whether {{ $total === 1 ? 'it is' : 'they are' }} still waiting, contact the client if needed, or resolve or cancel the ticket if it is no longer needed. The client has not been emailed.@if($cancelAfterDays > 0) A Pending ticket with no activity for {{ $cancelAfterDays }} days is cancelled automatically.@endif

@foreach($tickets as $row)
{{ $row['ticket_number'] }} - {{ $row['subject'] }} ({{ $row['waiting'] }})
{{ $row['url'] }}

@endforeach
@if($total > count($tickets))
and {{ $total - count($tickets) }} more. Open the Tickets list and filter by Pending to see them all.
@endif
