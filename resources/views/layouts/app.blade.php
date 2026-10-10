<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Do not paint until the whole page has been read: otherwise a slow load shows the shell first and the content pops in, resizing things. --}}
    <link rel="expect" href="#page-end" blocking="render">
    @php
        $tabUser = auth()->user();
        $tabYieldedTitle = trim((string) $__env->yieldContent('title'));
        $tabRouteName = (string) optional(request()->route())->getName();
        $supportLogoUrl = \App\Models\User::supportLogoUrl();
        $tabDepartmentBrand = $tabUser
            ? \App\Models\User::departmentBrandAssets($tabUser->department, $tabUser->role)
            : \App\Models\User::departmentBrandAssets(\App\Models\User::supportDepartment());

        $tabPageLabel = '';
        if ($tabYieldedTitle !== '') {
            $titleSuffixes = array_filter([
                config('app.name'),
                \App\Models\User::supportOrganizationName(),
                \App\Models\User::supportBrandName(),
            ]);

            $tabPageLabel = $tabYieldedTitle;
            foreach ($titleSuffixes as $titleSuffix) {
                $tabPageLabel = preg_replace('/\s*[-|]\s*'.preg_quote((string) $titleSuffix, '/').'.*$/i', '', $tabPageLabel) ?? $tabPageLabel;
            }
            $tabPageLabel = trim($tabPageLabel);
        }

        if ($tabPageLabel === '') {
            $tabPageLabel = match (true) {
                str_starts_with($tabRouteName, 'admin.dashboard') => 'Dashboard',
                str_starts_with($tabRouteName, 'admin.tickets.show') => isset($ticket) ? 'Ticket #' . $ticket->ticket_number : 'Ticket Details',
                str_starts_with($tabRouteName, 'admin.tickets.') => 'Tickets',
                str_starts_with($tabRouteName, 'admin.reports.') => 'Reports',
                str_starts_with($tabRouteName, 'admin.users.') => 'Users',
                str_starts_with($tabRouteName, 'admin.system-logs.') => 'System Logs',
                str_starts_with($tabRouteName, 'admin.online-users.') => 'Online Users',
                str_starts_with($tabRouteName, 'client.dashboard') => 'Dashboard',
                str_starts_with($tabRouteName, 'client.tickets.create') => 'New Ticket',
                str_starts_with($tabRouteName, 'client.tickets.show') => isset($ticket) ? 'Ticket #' . $ticket->ticket_number : 'Ticket Details',
                str_starts_with($tabRouteName, 'client.tickets.') => 'My Tickets',
                str_starts_with($tabRouteName, 'account.settings') => 'Account Settings',
                default => 'Helpdesk',
            };
        }

        $tabContextLabel = 'Helpdesk';
        if ($tabUser) {
            $tabContextLabel = match ($tabUser->normalizedRole()) {
                \App\Models\User::ROLE_SHADOW => 'Admin Console',
                \App\Models\User::ROLE_ADMIN => 'Admin Console',
                \App\Models\User::ROLE_SUPER_USER => 'Super User Console',
                \App\Models\User::ROLE_TECHNICAL => 'Technical Console',
                default => $tabDepartmentBrand['name'] . ' Client Portal',
            };
        }

        $tabTitle = trim($tabPageLabel . ' | ' . $tabContextLabel);
    @endphp
    <title>{{ $tabTitle }}</title>
    <link rel="preload" as="image" href="{{ $supportLogoUrl }}">
    @if($tabUser)
        <link rel="preload" as="image" href="{{ $tabDepartmentBrand['logo_url'] }}">
    @endif
    @include('partials.head-assets')
</head>
<body class="app-authenticated bg-[#f3f5f7] font-sans antialiased text-slate-900">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div
        class="min-h-dvh bg-[#f3f5f7]"
        x-data="appShellState"
        @keydown.escape.window="closeSidebar"
    >
        @include('layouts.navigation')

        <div class="min-h-dvh lg:pl-64">
            @include('layouts.partials.app-header')

            <main id="main-content" tabindex="-1" class="motion-page-enter px-4 py-6 focus:outline-none sm:px-6 lg:px-8">
                @if (session('success') && !session('suppress_success_banner'))
                    <div class="app-success-alert mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if (isset($header))
                    <header class="mb-6">
                        {{ $header }}
                    </header>
                @endif

                @yield('content')
            </main>
        </div>
        @include('legal.modal')
    </div>
    <span id="page-end" hidden></span>
</body>
</html>


