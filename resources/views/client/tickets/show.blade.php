@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - ' . config('app.name'))

@section('content')
@php
    $departmentLogo = static function (?string $department, bool $isSupport = false): string {
        if ($isSupport) {
            return \App\Models\User::supportLogoUrl();
        }

        return \App\Models\User::departmentBrandAssets($department)['logo_url'];
    };
    $clientCompanyLogo = $departmentLogo($ticket->department ?: auth()->user()->department);
    $supportCompanyLogo = \App\Models\User::supportLogoUrl();
    $hasResolveValidationErrors = $errors->has('resolve_confirmation') || $errors->has('rating') || $errors->has('comment');
    $hasReopenValidationErrors = $errors->has('reopen_reason');
    $hasCancelValidationErrors = $errors->has('cancel_reason');
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-client-ticket-show-page data-resolve-modal-open="{{ $hasResolveValidationErrors ? 'true' : 'false' }}" data-reopen-modal-open="{{ $hasReopenValidationErrors ? 'true' : 'false' }}" data-cancel-modal-open="{{ $hasCancelValidationErrors ? 'true' : 'false' }}">
    <div class="mb-3">
        <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center py-1.5 text-sm text-slate-500 hover:text-slate-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Tickets
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('client.tickets.partials.show-header')

            @if($ticket->status === 'resolved' && ! $ticket->satisfaction_rating)
                {{-- The one thing a resolved ticket needs from the client, before the long conversation. --}}
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900 sm:px-6" role="status" data-resolved-banner>
                    <p class="font-semibold">Your ticket was resolved. Please confirm it is fixed, or reopen it.</p>
                    <p class="mt-1">
                        Rate our support to confirm, or reopen the ticket if the problem is still there.
                        @if($ticket->resolved_at)
                            If we do not hear from you it closes automatically on {{ $ticket->resolved_at->copy()->addDays(\App\Models\Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS)->format('M j, Y') }}.
                        @endif
                    </p>
                    <a href="#ticket-actions" class="mt-2 inline-flex items-center py-1 font-semibold underline">Go to the rating and reopen buttons</a>
                </div>
            @endif

            @include('client.tickets.partials.show-conversation')
        </div>

        <div class="space-y-6">
            @include('client.tickets.partials.show-sidebar-details')
            @include('client.tickets.partials.show-sidebar-actions')
        </div>
    </div>
</div>

@if(! $ticket->isCancelled())
    @if(!in_array($ticket->status, ['resolved', 'closed']))
        @include('client.tickets.partials.show-resolve-modal')
    @else
        @include('client.tickets.partials.show-reopen-modal')
    @endif

    @if($ticket->isCancellable())
        @include('client.tickets.partials.show-cancel-modal')
    @endif
@endif

@include('client.tickets.partials.show-attachment-modal')
@include('client.tickets.partials.show-delete-reply-modal')
@endsection
