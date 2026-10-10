@extends('legal.public-layout')

@section('title', 'Ticket Submission Consent | ' . config('app.name'))

@section('content')
@php
    $effectiveDate = (string) config('legal.effective_date');
    $ticketConsentVersion = (string) config('legal.ticket_consent_version');
@endphp

<h1 class="font-display text-2xl font-semibold text-slate-900">Ticket Submission Consent</h1>
<p class="mt-2 text-sm text-slate-600">
    Effective date: {{ $effectiveDate }} | Version: {{ $ticketConsentVersion }}
</p>

<div class="mt-6">
    @include('legal.partials.ticket-consent-content')
</div>
@endsection
