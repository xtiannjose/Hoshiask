@php
    $toneClasses = [
        'success' => 'bg-green-100 text-green-800',
        'danger' => 'bg-red-100 text-red-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'neutral' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<div class="mt-6 bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h2 class="text-lg leading-6 font-medium text-slate-900">Sign-in Activity</h2>
        <p class="mt-1 text-sm text-slate-500">Recent sign-ins, sign-outs and failed attempts for this account.</p>
    </div>
    <div class="border-t border-slate-200">
        @if($signInActivity === null)
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6">
                <p class="text-sm text-slate-600">Sign-in activity includes IP addresses, so it is behind the monitoring password lock.</p>
                <a href="{{ route('admin.system-logs.unlock.show', ['return_to' => route('admin.users.show', $user, absolute: false)]) }}" class="btn-secondary">
                    Unlock to view
                </a>
            </div>
        @elseif(empty($signInActivity))
            <p class="px-4 py-6 text-sm text-slate-500 sm:px-6">No sign-in activity has been recorded for this account yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600 sm:px-6">Event</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">When</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">IP / Device</th>
                        </tr>
                    </thead>
                    <tbody class="app-table-body divide-y divide-slate-100">
                        @foreach($signInActivity as $event)
                            <tr>
                                <td class="px-4 py-3 align-top sm:px-6">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $toneClasses[$event['tone']] ?? $toneClasses['neutral'] }}">
                                        {{ $event['label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap text-sm text-slate-700">
                                    @if($event['occurred_at'])
                                        <span title="{{ $event['occurred_at']->format('M j, Y g:i:s A') }}">{{ $event['occurred_at']->diffForHumans() }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-slate-700">
                                    @if($event['actor_name'])
                                        <span class="text-slate-500">By {{ $event['actor_name'] }}</span>
                                    @else
                                        <div class="font-mono text-xs">{{ $event['ip_address'] ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">{{ $event['device'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
