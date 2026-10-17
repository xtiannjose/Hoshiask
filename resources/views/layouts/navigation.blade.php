@php
    $user = auth()->user();
    $isTicketConsole = $user->canAccessAdminTickets();
    $canManageConsole = $user->canManageTickets();
    $canAccessAccountSettings = $isTicketConsole;
    $isClient = !$isTicketConsole;
    $departmentBrand = \App\Models\User::departmentBrandAssets($user->department, $user->role);
    $departmentLogo = $departmentBrand['logo_url'];
    $departmentName = $departmentBrand['name'];

    if ($isTicketConsole) {
        $menuItems = [
            ...($user->canCreateClientTickets() ? [[
                'label' => 'New Ticket',
                'icon' => 'plus',
                'href' => route('admin.tickets.create'),
                'active' => request()->routeIs('admin.tickets.create'),
                'disabled' => false,
            ]] : []),
            ['label' => 'Dashboard', 'icon' => 'home', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard'), 'disabled' => false],
            ['label' => 'Tickets', 'icon' => 'ticket', 'href' => route('admin.tickets.index'), 'active' => request()->routeIs('admin.tickets.index') || request()->routeIs('admin.tickets.show'), 'disabled' => false],
            ['label' => 'Knowledge Base', 'icon' => 'help', 'href' => route('admin.knowledge-base.index'), 'active' => request()->routeIs('admin.knowledge-base.*'), 'disabled' => false],
        ];

        if ($canManageConsole) {
            $menuItems[] = ['label' => 'Reports', 'icon' => 'chart', 'href' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*'), 'disabled' => false];
        }

        if ($canManageConsole && config('hoshiask.features.canned_replies')) {
            $menuItems[] = ['label' => 'Canned Responses', 'icon' => 'canned', 'href' => route('admin.canned-responses.index'), 'active' => request()->routeIs('admin.canned-responses.*'), 'disabled' => false];
        }

        if ($canAccessAccountSettings) {
            $menuItems[] = ['label' => 'Account Settings', 'icon' => 'user', 'href' => route('account.settings'), 'active' => request()->routeIs('account.settings'), 'disabled' => false];
        }

        if ($canManageConsole) {
            $menuItems[] = ['label' => 'Users', 'icon' => 'users', 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'disabled' => false];
        }

        if ($user->canManageStaffAccounts()) {
            $menuItems[] = ['label' => 'Departments', 'icon' => 'building', 'href' => route('admin.departments.index'), 'active' => request()->routeIs('admin.departments.*'), 'disabled' => false];
            $menuItems[] = ['label' => 'Assignment Rules', 'icon' => 'shuffle', 'href' => route('admin.assignment-rules.index'), 'active' => request()->routeIs('admin.assignment-rules.*'), 'disabled' => false];
        }

        if ($user->isShadow()) {
            $menuItems[] = ['label' => 'Online Users', 'icon' => 'pulse', 'href' => route('admin.online-users.index'), 'active' => request()->routeIs('admin.online-users.*'), 'disabled' => false];
            $menuItems[] = ['label' => 'System Logs', 'icon' => 'book', 'href' => route('admin.system-logs.index'), 'active' => request()->routeIs('admin.system-logs.*'), 'disabled' => false];
        }
    } else {
        $menuItems = [
            ['label' => 'New Ticket', 'icon' => 'plus', 'href' => route('client.tickets.create'), 'active' => request()->routeIs('client.tickets.create'), 'disabled' => false],
            ['label' => 'Dashboard', 'icon' => 'home', 'href' => route('client.dashboard'), 'active' => request()->routeIs('client.dashboard'), 'disabled' => false],
            ['label' => 'My Tickets', 'icon' => 'ticket', 'href' => route('client.tickets.index'), 'active' => request()->routeIs('client.tickets.index') || request()->routeIs('client.tickets.show'), 'disabled' => false],
            ['label' => 'Knowledge Base', 'icon' => 'help', 'href' => route('client.knowledge-base.index'), 'active' => request()->routeIs('client.knowledge-base.*'), 'disabled' => false],
        ];
    }

    // Until consent is given every menu link would bounce back here, so show none of them.
    $consentPending = request()->routeIs('legal.acceptance.*');
    if ($consentPending) {
        $menuItems = [];
    }
@endphp

<div
    x-cloak
    x-bind:hidden="!sidebarOpen"
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-slate-900/45 lg:hidden"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-[#2a3b60] bg-hoshishi-dark text-slate-100 transition-transform duration-300 ease-out lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <div class="flex h-full flex-col">
        <div @class([
            'relative flex items-center justify-center border-b border-[#2c3d63] px-5',
            'h-28 lg:h-32' => $isTicketConsole,
            'h-32 lg:h-36' => $isClient,
        ])>
            <a href="{{ $isTicketConsole ? route('admin.dashboard') : route('client.dashboard') }}" class="mx-auto flex w-full justify-center">
                <span class="flex flex-col items-center text-center">
                    <span class="inline-flex h-20 w-52 items-center justify-center lg:h-24">
                        <img
                            src="{{ $departmentLogo }}"
                            alt="{{ $departmentName }} Logo"
                            class="h-full w-full object-contain"
                        >
                    </span>
                    <span class="mt-1 text-xs font-medium tracking-wide text-slate-300">{{ $departmentName }}</span>
                </span>
            </a>

            <button @click="sidebarOpen = false" class="app-pressable absolute right-5 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close sidebar">
                <x-ui.icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
            @if($consentPending)
                <p class="px-3 py-2 text-sm text-slate-300">Accept the terms on this page to start using the hoshiask.</p>
            @endif
            @foreach($menuItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'app-nav-link group flex items-center gap-3 rounded-xl px-3 py-3 transition',
                        'text-base font-semibold',
                        'bg-white/10 text-white' => $item['active'],
                        'text-slate-200 hover:bg-white/5 hover:text-white' => !$item['active'] && !$item['disabled'],
                        'cursor-not-allowed text-slate-500/80' => $item['disabled'],
                    ])
                    @if($item['disabled']) aria-disabled="true" @endif
                    @if($item['active'] && !$item['disabled']) aria-current="page" @endif
                >
                    <x-ui.icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                    <span>{{ $item['label'] }}</span>
                    @if($item['disabled'])
                        <span class="ml-auto rounded-lg bg-white/10 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-300">Soon</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-[#2c3d63] px-4 py-4 text-xs text-slate-300">
            <span class="mr-2">Legal:</span>
            <button type="button" @click="openLegalModal('terms')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-slate-200 hover:text-white">Terms</button>
            <span class="mx-1 text-slate-500">|</span>
            <button type="button" @click="openLegalModal('privacy')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-slate-200 hover:text-white">Privacy</button>
            <span class="mx-1 text-slate-500">|</span>
            <button type="button" @click="openLegalModal('ticket-consent')" class="app-menu-link border-0 bg-transparent px-0 py-1.5 font-semibold text-slate-200 hover:text-white">Ticket</button>
        </div>

    </div>
</aside>
