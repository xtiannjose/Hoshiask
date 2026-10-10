@extends('legal.public-layout')

@section('title', 'Terms of Service | ' . config('app.name'))

@section('content')
@php
    $effectiveDate = (string) config('legal.effective_date');
    $termsVersion = (string) config('legal.terms_version');
@endphp

<h1 class="font-display text-2xl font-semibold text-slate-900">Terms of Service</h1>
<p class="mt-2 text-sm text-slate-600">
    Effective date: {{ $effectiveDate }} | Version: {{ $termsVersion }}
</p>

<div class="mt-6">
    @include('legal.partials.terms-content')
</div>
@endsection
