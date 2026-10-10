@props([
    'statuses' => \App\Enums\TicketStatus::cases(),
    'selected' => null,
    'disabled' => [],
])

@foreach($statuses as $status)
    @php
        $statusEnum = $status instanceof \App\Enums\TicketStatus ? $status : \App\Enums\TicketStatus::tryFrom($status);
        $value = $statusEnum?->value ?? (string) $status;
        $label = $statusEnum?->label() ?? ucfirst(str_replace('_', ' ', $value));
    @endphp
    <option value="{{ $value }}" {{ (string) $selected === $value ? 'selected' : '' }} {{ in_array($value, $disabled, true) ? 'disabled' : '' }}>{{ $label }}</option>
@endforeach
