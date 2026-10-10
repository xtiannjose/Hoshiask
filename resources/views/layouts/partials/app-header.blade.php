            <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#f3f5f7]/95 backdrop-blur">
                @php
                    $user = auth()->user();
                    $isTicketConsole = $user->canAccessAdminTickets();
                    $isClient = !$isTicketConsole;
                    $canAccessAccountSettings = $isTicketConsole;
                    $departmentBrand = \App\Models\User::departmentBrandAssets($user->department, $user->role);
                    $clientCompanyName = $departmentBrand['name'];
                    $clientCompanyLogo = $departmentBrand['logo_url'];
                    $activeRouteName = (string) optional(request()->route())->getName();
                    $headerSearchRouteName = match (true) {
                        str_starts_with($activeRouteName, 'admin.users.') => 'admin.users.index',
                        str_starts_with($activeRouteName, 'admin.tickets.') => 'admin.tickets.index',
                        str_starts_with($activeRouteName, 'client.tickets.') => 'client.tickets.index',
                        default => $isClient ? 'client.tickets.index' : 'admin.tickets.index',
                    };
                    $headerSearchAction = route($headerSearchRouteName);
                    $headerSearchQuery = trim((string) request('search', ''));
                    $headerSearchPlaceholder = $isClient ? 'Search my tickets...' : 'Search tickets or users...';
                    $headerSearchCarryParams = collect(request()->query())
                        ->except(['search', 'page'])
                        ->all();
                    // Search and "New ticket" would only bounce back to the consent page until it is accepted.
                    $consentPending = request()->routeIs('legal.acceptance.*');
                    $notificationsEnabled = ! $consentPending;
                    $notifications = $notificationsEnabled ? ($headerNotifications ?? collect()) : collect();
                    $notificationCount = $notificationsEnabled
                        ? (int) ($headerNotificationUnreadCount ?? $notifications->where('is_viewed', false)->count())
                        : 0;
                    $notificationClearAction = $notificationsEnabled
                        ? route($isClient ? 'client.notifications.clear' : 'admin.notifications.clear')
                        : null;
                    $consoleLabel = match ($user->normalizedRole()) {
                        'shadow' => 'Admin Console',
                        'admin' => 'Admin Console',
                        'super_user' => 'Super User Console',
                        default => 'Technical Console',
                    };
                @endphp

                <div class="app-header-row flex h-20 items-center gap-3 px-3 sm:px-5 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            @click="sidebarOpen = true"
                            class="app-pressable inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 lg:hidden"
                            aria-label="Open sidebar"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="toggleDarkMode"
                            class="app-pressable inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                            :aria-pressed="darkMode.toString()"
                            aria-label="Toggle dark mode"
                        >
                            <x-ui.icon name="sun" class="theme-icon-sun h-5 w-5" />
                            <x-ui.icon name="moon" class="theme-icon-moon h-5 w-5" />
                        </button>
                    </div>

                    <div class="hidden min-w-0 flex-1 xl:block">
                        @unless($consentPending)
                            @include('layouts.partials.header-search-form')
                        @endunless
                    </div>

                    <div class="relative z-10 ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                        @if($consentPending)
                            {{-- No shortcuts until the terms are accepted. --}}
                        @elseif($isClient)
                            <a href="{{ route('client.tickets.create') }}" class="app-pressable inline-flex items-center rounded-xl bg-hoshishi-dark px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#111a2e] sm:px-4 sm:py-2.5">
                                New Ticket
                            </a>
                        @elseif($user->canCreateClientTickets())
                            <a href="{{ route('admin.tickets.create') }}" class="app-pressable inline-flex items-center rounded-xl bg-hoshishi-dark px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#111a2e] sm:px-4 sm:py-2.5">
                                New Ticket
                            </a>
                        @endif

                        @if(($headerOnlineStaffCount ?? null) !== null)
                            <a
                                href="{{ route('admin.online-users.index', ['segment' => 'staff']) }}"
                                class="app-pressable hidden items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                                title="Staff accounts active in the last {{ config('helpdesk.presence.online_window_minutes') }} minutes"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                {{ $headerOnlineStaffCount }} staff online
                            </a>
                        @endif

                        @if($isTicketConsole)
                            <span class="hidden items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 xl:inline-flex">
                                {{ $consoleLabel }}
                            </span>
                        @endif

                        @if($notificationsEnabled)
                            <div class="relative" x-data="headerNotificationDropdown">
                                <button
                                    @click.stop="toggleNotifications"
                                    type="button"
                                    class="app-pressable relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                                    aria-label="Notifications"
                                    aria-haspopup="true"
                                    :aria-expanded="notificationOpen ? 'true' : 'false'"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5A6.965 6.965 0 0012 5a6.965 6.965 0 00-7.5 8.5L1 17h5m9 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    @if($notificationCount > 0)
                                        <span class="js-header-notification-badge absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white" data-count="{{ $notificationCount }}">
                                            {{ $notificationCount > 9 ? '9+' : $notificationCount }}
                                        </span>
                                    @endif
                                </button>

                                <div
                                    x-cloak
                                    x-bind:hidden="!notificationOpen"
                                    @click.away="closeNotifications"
                                    @keydown.escape.window="closeNotifications"
                                    role="region"
                                    aria-label="Notifications"
                                    class="fixed left-2 right-2 top-20 z-40 max-h-[70vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80 sm:max-w-[calc(100vw-1rem)]"
                                >
                                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                        <h2 class="text-sm font-semibold text-slate-900">Notifications</h2>
                                        <form method="POST" action="{{ $notificationClearAction }}" class="js-header-notification-clear-wrap {{ $notifications->count() > 0 ? '' : 'hidden' }}">
                                            @csrf
                                            <button type="submit" class="app-pressable inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                                                Clear
                                            </button>
                                        </form>
                                    </div>
                                    @if($notifications->count() > 0)
                                        <div class="js-header-notification-list max-h-72 overflow-y-auto">
                                            @foreach($notifications as $notification)
                                                <div
                                                    @class([
                                                    'js-header-notification flex items-start gap-2 border-b border-slate-100 px-2 py-1 hover:bg-slate-50',
                                                    'bg-slate-50/70' => !empty($notification['is_viewed']),
                                                    'is-viewed' => !empty($notification['is_viewed']),
                                                    ])
                                                    data-ticket-id="{{ $notification['ticket_id'] }}"
                                                    data-activity-at="{{ $notification['activity_at'] }}"
                                                    data-viewed="{{ !empty($notification['is_viewed']) ? '1' : '0' }}"
                                                >
                                                    <a href="{{ $notification['url'] }}" class="block min-w-0 flex-1 px-2 py-2">
                                                        <p class="text-sm font-semibold text-slate-900">{{ $notification['title'] }}</p>
                                                        <p class="mt-0.5 truncate text-sm text-slate-600">{{ $notification['meta'] }}</p>
                                                        <p class="mt-1 text-xs text-slate-500">
                                                            {{ $notification['time'] }}
                                                            @if(!empty($notification['is_viewed']))
                                                                <span class="notification-viewed-pill ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Viewed</span>
                                                            @endif
                                                        </p>
                                                    </a>
                                                    @if(!empty($notification['can_dismiss']) && !empty($notification['dismiss_url']) && !empty($notification['key']))
                                                        <form method="POST" action="{{ $notification['dismiss_url'] }}" class="pt-2">
                                                            @csrf
                                                            <input type="hidden" name="ticket_id" value="{{ $notification['ticket_id'] }}">
                                                            <input type="hidden" name="activity_at" value="{{ $notification['activity_at'] }}">
                                                            <button type="submit" class="notification-dismiss-btn inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Dismiss notification">
                                                                <x-ui.icon name="x" class="h-4 w-4" />
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="js-header-notification-empty hidden px-4 py-6 text-center">
                                            <p class="text-sm font-semibold text-slate-700">No notifications</p>
                                            <p class="mt-1 text-xs text-slate-500">You are all caught up.</p>
                                        </div>
                                    @else
                                        <div class="js-header-notification-list hidden max-h-72 overflow-y-auto"></div>
                                        <div class="js-header-notification-empty px-4 py-6 text-center">
                                            <p class="text-sm font-semibold text-slate-700">No notifications</p>
                                            <p class="mt-1 text-xs text-slate-500">You are all caught up.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="relative" x-data="profileMenuDropdown">
                            <button
                                @click.stop="toggleMenu"
                                class="app-pressable inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 sm:px-4"
                                aria-haspopup="true"
                                :aria-expanded="open ? 'true' : 'false'"
                            >
                                <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                    <img src="{{ $clientCompanyLogo }}" alt="{{ $clientCompanyName }} logo" class="avatar-logo">
                                </span>
                                <span class="hidden text-left xl:block">
                                    <span class="block max-w-44 truncate text-base font-semibold text-slate-800">{{ $user->name }}</span>
                                    <span class="block text-sm text-slate-500">{{ $clientCompanyName }}</span>
                                </span>
                            </button>

                            <div
                                x-cloak
                                x-bind:hidden="!open"
                                @click.away="closeMenu"
                                @keydown.escape.window="closeMenu"
                                role="menu"
                                aria-label="Account menu"
                                class="absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                @if($canAccessAccountSettings)
                                    <a href="{{ route('account.settings') }}" class="app-menu-link block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Account Settings</a>
                                @endif
                                <form method="POST" action="{{ route('logout', absolute: false) }}">
                                    @csrf
                                    <button type="submit" class="app-menu-link block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Sign out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                @unless($consentPending)
                    <div class="px-3 pb-3 sm:px-5 lg:px-8 xl:hidden">
                        @include('layouts.partials.header-search-form', ['wrapperClass' => 'relative w-full'])
                    </div>
                @endunless
            </header>
