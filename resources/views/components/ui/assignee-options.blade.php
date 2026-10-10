@props([
    'assignees' => [],
    'selected' => null,
    'exclude' => [],
])

@php
    $selectedValues = is_array($selected) ? array_map('strval', $selected) : [(string) $selected];
@endphp

@foreach($assignees as $assignee)
    @continue(in_array($assignee->id, $exclude, true))
    <option value="{{ $assignee->id }}" {{ in_array((string) $assignee->id, $selectedValues, true) ? 'selected' : '' }}>{{ $assignee->publicDisplayName() }}</option>
@endforeach
