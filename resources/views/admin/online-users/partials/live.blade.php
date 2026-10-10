@php
    $currentUserId = auth()->id();
    $segmentTabs = [
        'all' => ['label' => 'All', 'count' => $summary['total']],
        'staff' => ['label' => 'Staff', 'count' => $summary['staff']],
        'clients' => ['label' => 'Clients', 'count' => $summary['clients']],
    ];
    $emptyMessage = match ($segment) {
        'staff' => 'No staff accounts are online right now.',
        'clients' => 'No client accounts are online right now.',
        default => 'No one is online right now.',
    };
    $headerCellClass = 'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600';
@endphp

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Online now</p>
        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ $summary['total'] }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $summary['active'] }} active &middot; {{ $summary['idle'] }} idle</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</p>
        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ $summary['staff'] }}</p>
        <p class="mt-1 text-xs text-slate-500">Admins, super users and technicians</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clients</p>
        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ $summary['clients'] }}</p>
        <p class="mt-1 text-xs text-slate-500">Client portal accounts</p>
    </div>
    <div @class([
        'rounded-2xl border p-4 shadow-sm',
        'border-red-200 bg-red-50' => $summary['flagged'] > 0,
        'border-slate-200 bg-white' => $summary['flagged'] === 0,
    ])>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Flagged</p>
        <p @class(['mt-1 text-3xl font-semibold', 'text-red-700' => $summary['flagged'] > 0, 'text-slate-900' => $summary['flagged'] === 0])>{{ $summary['flagged'] }}</p>
        <p class="mt-1 text-xs text-slate-500">Several IPs at once, or deactivated</p>
    </div>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <nav class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm" aria-label="Filter online users">
            @foreach($segmentTabs as $segmentKey => $tab)
                <a
                    href="{{ route('admin.online-users.index', $segmentKey === 'all' ? [] : ['segment' => $segmentKey]) }}"
                    class="rounded-lg px-3 py-1.5 font-medium {{ $segment === $segmentKey ? 'bg-hoshishi-blue-100 text-hoshishi-blue-800' : 'text-slate-600 hover:bg-slate-100' }}"
                    @if($segment === $segmentKey) aria-current="page" @endif
                >
                    {{ $tab['label'] }} ({{ $tab['count'] }})
                </a>
            @endforeach
        </nav>
        <p class="text-xs text-slate-500">Updated {{ $refreshedAt->format('g:i:s A') }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="{{ $headerCellClass }}">User</th>
                    <th scope="col" class="{{ $headerCellClass }}">Role</th>
                    <th scope="col" class="{{ $headerCellClass }}">Status</th>
                    <th scope="col" class="{{ $headerCellClass }}">Sessions</th>
                    <th scope="col" class="{{ $headerCellClass }}">Flags</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="app-table-body divide-y divide-slate-100">
                @forelse($onlineUsers as $row)
                    @php
                        $onlineUser = $row['user'];
                        $isCurrentUser = $onlineUser->id === $currentUserId;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 align-top">
                            <a href="{{ route('admin.users.show', $onlineUser) }}" class="text-sm font-semibold text-slate-900 hover:underline">{{ $onlineUser->name }}</a>
                            @if($isCurrentUser)
                                <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">You</span>
                            @endif
                            <div class="font-mono text-xs text-slate-500">{{ '@'.$onlineUser->username }}</div>
                            <div class="text-xs text-slate-500 break-all">{{ $onlineUser->email }}</div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            @include('admin.users.partials.role-badge', ['role' => $onlineUser->role])
                            @if($onlineUser->department)
                                <div class="mt-1 text-xs text-slate-500">{{ $onlineUser->department }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['is_active'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $row['is_active'] ? 'bg-emerald-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                                {{ $row['is_active'] ? 'Active' : 'Idle' }}
                            </span>
                            <div class="mt-1 text-xs text-slate-500" title="{{ $row['last_activity_at']->format('M j, Y g:i:s A') }}">
                                {{ $row['last_activity_at']->diffForHumans() }}
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <ul class="space-y-1.5">
                                @foreach($row['sessions'] as $session)
                                    <li class="text-xs text-slate-700">
                                        <span class="font-medium">{{ $session['device'] }}</span>
                                        <span class="text-slate-500">&middot;</span>
                                        <span class="font-mono">{{ $session['ip_address'] ?? 'Unknown IP' }}</span>
                                        @if(count($row['sessions']) > 1)
                                            <span class="text-slate-500">&middot;</span>
                                            <span class="text-slate-500">{{ $session['last_activity_at']->diffForHumans() }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="px-4 py-3 align-top">
                            @forelse($row['flags'] as $flag)
                                <span class="mb-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800" title="{{ $flag['description'] }}">
                                    {{ $flag['label'] }}
                                </span>
                                <span class="sr-only">{{ $flag['description'] }}</span>
                            @empty
                                <span class="text-xs text-slate-500">-</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 align-top text-right">
                            @if($isCurrentUser)
                                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-sm font-medium text-slate-500">You</span>
                            @else
                                <form method="POST" action="{{ route('admin.online-users.sign-out', $onlineUser) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="js-online-user-sign-out inline-flex items-center rounded-lg px-2.5 py-1 text-sm font-medium text-red-600 transition-colors duration-150 hover:bg-red-50 hover:text-red-900"
                                        data-user-name="{{ $onlineUser->name }}"
                                    >
                                        Sign out
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-base font-semibold text-slate-900">Failed Sign-ins</h2>
            <p class="text-xs text-slate-500">
                Last {{ $failedSignInWindowHours }} hours &middot; {{ $failedSignIns['total'] }} failed {{ \Illuminate\Support\Str::plural('attempt', $failedSignIns['total']) }}.
                A login name is locked for {{ intdiv(\App\Support\LoginThrottle::LOCKOUT_SECONDS, 60) }} minutes after {{ \App\Support\LoginThrottle::MAX_ATTEMPTS }} failures.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="{{ $headerCellClass }}">Login name</th>
                        <th scope="col" class="{{ $headerCellClass }}">Attempts</th>
                        <th scope="col" class="{{ $headerCellClass }}">Source IPs</th>
                        <th scope="col" class="{{ $headerCellClass }}">Last attempt</th>
                        <th scope="col" class="{{ $headerCellClass }}">Status</th>
                    </tr>
                </thead>
                <tbody class="app-table-body divide-y divide-slate-100">
                    @forelse($failedSignIns['by_login'] as $failure)
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <div class="font-mono text-sm text-slate-900 break-all">{{ $failure['login'] }}</div>
                                <div class="text-xs text-slate-500">
                                    @if($failure['account'])
                                        <a href="{{ route('admin.users.show', $failure['account']) }}" class="hover:underline">{{ $failure['account']->name }}</a>
                                        &middot;
                                    @endif
                                    {{ $failure['reason'] }}
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm font-semibold text-slate-900">{{ $failure['attempts'] }}</td>
                            <td class="px-4 py-3 align-top font-mono text-xs text-slate-700">
                                {{ implode(', ', array_slice($failure['ip_addresses'], 0, 3)) ?: '-' }}
                                @if(count($failure['ip_addresses']) > 3)
                                    <span class="font-sans text-slate-500">+{{ count($failure['ip_addresses']) - 3 }} more</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top whitespace-nowrap text-xs text-slate-600" title="{{ $failure['last_attempt_at']->format('M j, Y g:i:s A') }}">
                                {{ $failure['last_attempt_at']->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if($failure['locked_for_seconds'] !== null)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                            Locked &middot; {{ (int) ceil($failure['locked_for_seconds'] / 60) }} min left
                                        </span>
                                        <form method="POST" action="{{ route('admin.online-users.lockouts.clear') }}">
                                            @csrf
                                            <input type="hidden" name="login" value="{{ $failure['login'] }}">
                                            <button type="submit" class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                                Clear lockout
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Not locked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No failed sign-ins in the last {{ $failedSignInWindowHours }} hours.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-base font-semibold text-slate-900">Top Source IPs</h2>
            <p class="text-xs text-slate-500">Where failed sign-ins came from. One IP trying many login names is a warning sign.</p>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($failedSignIns['by_ip'] as $source)
                <li class="flex items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <div class="font-mono text-sm text-slate-900 break-all">{{ $source['ip_address'] }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $source['logins'] }} login {{ \Illuminate\Support\Str::plural('name', $source['logins']) }} &middot; last {{ $source['last_attempt_at']->diffForHumans() }}
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                        {{ $source['attempts'] }} {{ \Illuminate\Support\Str::plural('attempt', $source['attempts']) }}
                    </span>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-slate-500">No failed sign-ins.</li>
            @endforelse
        </ul>
    </div>
</div>
