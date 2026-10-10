<div class="space-y-6 lg:col-span-4">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-slate-900">User Statistics</h2>
        </div>
        <div class="border-t border-slate-200 px-4 py-5 sm:px-6">
            <ul class="space-y-4">
                <li>
                    <a href="{{ $statisticsLinks['total_tickets'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-slate-50">
                        <span class="text-sm font-medium text-slate-500">Total Tickets</span>
                        <span class="text-sm text-slate-900">{{ $statistics['total_tickets'] }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ $statisticsLinks['open_tickets'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-slate-50">
                        <span class="text-sm font-medium text-slate-500">Open Tickets</span>
                        <span class="text-sm text-slate-900">{{ $statistics['open_tickets'] }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ $statisticsLinks['closed_tickets'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-slate-50">
                        <span class="text-sm font-medium text-slate-500">Closed Tickets</span>
                        <span class="text-sm text-slate-900">{{ $statistics['closed_tickets'] }}</span>
                    </a>
                </li>
                @if($statistics['cancelled_tickets'] > 0)
                    <li>
                        <a href="{{ $statisticsLinks['cancelled_tickets'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-500">Cancelled Tickets</span>
                            <span class="text-sm text-slate-900">{{ $statistics['cancelled_tickets'] }}</span>
                        </a>
                    </li>
                @endif
                @if($statistics['show_assigned'])
                    <li>
                        <a href="{{ $statisticsLinks['assigned_tickets'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-500">Assigned Tickets</span>
                            <span class="text-sm text-slate-900">{{ $statistics['assigned_tickets'] }}</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </div>

    @if($canRevealManagedPassword ?? false)
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-slate-900">Password Access</h2>
                <p class="mt-1 text-sm text-slate-500">Shadow-only credential tools for this account.</p>
            </div>
            <div class="border-t border-slate-200 px-4 py-5 sm:px-6 space-y-3">
                @if(!empty($revealedManagedPassword))
                    <div>
                        <label for="managedUserPassword" class="text-sm font-medium text-slate-700">Temporary Login Password (One-Time Reveal)</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                id="managedUserPassword"
                                type="password"
                                readonly
                                value="{{ $revealedManagedPassword }}"
                                class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900"
                            >
                            <button
                                type="button"
                                id="toggleManagedUserPassword"
                                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                aria-pressed="false"
                            >
                                Show
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-red-700">Copy this temporary password now. It will not be shown again.</p>
                    </div>
                @elseif($activeCredentialHandoff)
                    <p class="text-xs text-sky-700">
                        A temporary password is ready and expires at {{ optional($activeCredentialHandoff->expires_at)->format('M j, Y g:i A') }}.
                    </p>
                    <form method="POST" action="{{ route('admin.users.password.reveal-temporary', $user) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-hoshishi-blue-200 px-3 py-2 text-xs font-semibold text-hoshishi-blue-800 transition hover:bg-hoshishi-blue-50">
                            Reveal Temporary Password
                        </button>
                    </form>
                @else
                    <p class="text-xs text-amber-700">No active temporary password is available for this account.</p>
                    <form method="POST" action="{{ route('admin.users.password.reset-default', $user) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">
                            Issue Temporary Password
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if($user->id !== auth()->id())
        @php
            $currentViewer = auth()->user();
            $canToggleAccount = $currentViewer->can('toggleStatus', $user);
            $canDeleteAccount = $currentViewer->can('delete', $user);
        @endphp
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-slate-900">Account Actions</h2>
            </div>
            <div class="border-t border-slate-200 px-4 py-5 sm:px-6">
                <div class="space-y-3">
                    @if($canToggleAccount)
                        <button
                            type="button"
                            class="js-toggle-user-status w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                            data-user-id="{{ $user->id }}"
                            data-user-name="{{ $user->name }}"
                            data-new-status="{{ $user->is_active ? '0' : '1' }}">
                            {{ $user->is_active ? 'Deactivate Account' : 'Activate Account' }}
                        </button>
                    @endif
                    @if($canDeleteAccount)
                        <button
                            type="button"
                            class="delete-user-btn w-full rounded-lg px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                            data-user-id="{{ $user->id }}"
                            data-user-name="{{ $user->name }}">
                            Delete Account
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
