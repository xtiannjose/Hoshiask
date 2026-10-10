<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @include('partials.head-assets')
    @php
        // Error pages can be shown while the database or session is the thing that failed,
        // so nothing here may throw: fall back to plain links.
        $homeUrl = rescue(static function () {
            if (! auth()->check()) {
                return null;
            }

            return auth()->user()->canAccessAdminTickets() ? route('admin.dashboard') : route('client.dashboard');
        }, null, false);
    @endphp
</head>
<body class="font-sans antialiased" x-data="publicLegalPageState">
    <main id="main-content" class="motion-page-enter relative flex min-h-dvh items-center justify-center px-4 py-10 sm:px-6">
        <div class="login-card w-full max-w-xl rounded-2xl border border-slate-200 p-8 text-center shadow-2xl shadow-slate-200/70 sm:p-10">
            <img src="{{ \App\Models\User::supportLogoUrl() }}" alt="{{ \App\Models\User::supportOrganizationName() }} logo" class="mx-auto h-12 w-auto">
            @hasSection('art')
                <img src="{{ asset('images/brand/'.trim($__env->yieldContent('art')).'.png') }}" alt="" class="mx-auto mt-6 h-32 w-auto rounded-2xl">
            @endif
            <p class="mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">@yield('code')</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-slate-900">@yield('heading')</h1>
            <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-600">@yield('message')</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @if($homeUrl)
                    <a href="{{ $homeUrl }}" class="btn-primary">Go to my dashboard</a>
                @else
                    <a href="{{ url('/login') }}" class="btn-primary">Sign in</a>
                @endif
                @yield('actions')
            </div>
        </div>
    </main>
</body>
</html>
