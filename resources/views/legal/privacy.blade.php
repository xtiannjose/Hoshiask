@extends('legal.public-layout')

@section('title', 'Privacy Notice and Consent | ' . config('app.name'))

@section('content')
@php
    $effectiveDate = (string) config('legal.effective_date');
    $privacyVersion = (string) config('legal.privacy_version');
@endphp

<h1 class="font-display text-2xl font-semibold text-slate-900">Privacy Notice and Consent</h1>
<p class="mt-2 text-sm text-slate-600">
    Effective date: {{ $effectiveDate }} | Version: {{ $privacyVersion }}
</p>

<div class="mt-6">
    @include('legal.partials.privacy-content')
</div>
@endsection
