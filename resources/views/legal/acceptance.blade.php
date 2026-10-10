@extends('layouts.app')

@section('title', 'Legal Consent Required')

@section('content')
@php
@endphp

<div class="mx-auto max-w-3xl">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="font-display text-2xl font-semibold text-slate-900">Consent Required Before You Continue</h1>
        <p class="mt-2 text-sm text-slate-600">
            You need to review and accept the current legal documents to use the ticketing system.
        </p>

        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <ul class="list-disc space-y-1 pl-5 text-slate-700">
                <li><button type="button" @click="openLegalModal('terms')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Read Terms of Service</button></li>
                <li><button type="button" @click="openLegalModal('privacy')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Read Privacy Notice and Consent</button></li>
                <li><button type="button" @click="openLegalModal('ticket-consent')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-hoshishi-blue-700 hover:text-hoshishi-blue-900">Read Ticket Submission Consent</button></li>
            </ul>
        </div>

        <form action="{{ route('legal.acceptance.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                <input type="checkbox" name="accept_terms" value="1" class="ticket-checkbox mt-1">
                <span>I agree to the Terms of Service.</span>
            </label>
            @error('accept_terms')
                <p class="-mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                <input type="checkbox" name="accept_privacy" value="1" class="ticket-checkbox mt-1">
                <span>I acknowledge and consent to the Privacy Notice.</span>
            </label>
            @error('accept_privacy')
                <p class="-mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                <input type="checkbox" name="accept_platform_consent" value="1" class="ticket-checkbox mt-1">
                <span>I confirm I am authorized to use this platform and provide data needed for support.</span>
            </label>
            @error('accept_platform_consent')
                <p class="-mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-between gap-3 pt-2">
                <button type="submit" class="btn-primary">
                    Accept and Continue
                </button>
                <button type="submit"
                   form="legal-logout-form"
                   class="app-menu-link text-sm font-semibold text-slate-500 hover:text-slate-700">
                    Sign out
                </button>
            </div>
        </form>
        <form id="legal-logout-form" action="{{ route('logout', absolute: false) }}" method="POST" class="hidden">
            @csrf
        </form>
    </div>
</div>
@endsection
