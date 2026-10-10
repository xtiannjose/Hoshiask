{{ $headline }}

{{ $messageLine }}

Ticket Number: {{ $ticket->ticket_number }}
@foreach($details as $label => $value)
@if($label !== 'Ticket Number')
{{ $label }}: {{ $value }}
@endif
@endforeach

@if($actionUrl)
{{ $actionLabel ?: 'Open Ticket' }}: {{ $actionUrl }}
@endif
@if($clientThread)

Replying to this email will not add your message to the ticket. To write to us, use the Open Ticket link above.
@endif
