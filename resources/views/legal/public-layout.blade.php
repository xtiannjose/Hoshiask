<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($supportLogoUrl = \App\Models\User::supportLogoUrl())
    <title>@yield('title')</title>
    @include('partials.head-assets')
</head>
<body
    class="min-h-dvh bg-slate-100 font-sans text-slate-900 antialiased"
    x-data="publicLegalPageState"
>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="motion-page-enter mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('login') }}" class="app-pressable inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <img src="{{ $supportLogoUrl }}" alt="{{ \App\Models\User::supportOrganizationName() }} logo" class="h-6 w-auto">
                <span>{{ config('app.name') }}</span>
            </a>
            <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                <button
                    type="button"
                    @click="toggleDarkMode"
                    class="app-pressable inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50"
                    :aria-pressed="darkMode.toString()"
                    aria-label="Toggle dark mode"
                >
                    <x-ui.icon name="sun" class="theme-icon-sun h-4 w-4" />
                    <x-ui.icon name="moon" class="theme-icon-moon h-4 w-4" />
                </button>
                <a href="{{ route('legal.terms') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Privacy</a>
                <a href="{{ route('legal.ticket-consent') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Ticket Consent</a>
            </div>
        </div>

        <main id="main-content" tabindex="-1" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm focus:outline-none sm:p-8">
            @yield('content')
        </main>
    </div>
</body>
</html>
