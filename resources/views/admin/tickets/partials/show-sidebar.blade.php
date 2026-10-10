@php
    $formatTicketDuration = static function ($startAt, $endAt): ?string {
        if (! $startAt || ! $endAt || $endAt->lt($startAt)) {
            return null;
        }

        $minutes = (int) $startAt->diffInMinutes($endAt);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($remainingMinutes > 0 || $parts === []) {
            $parts[] = $remainingMinutes.'m';
        }

        return implode(' ', $parts);
    };
    $resolutionEndedAt = $ticket->resolved_at ?? $ticket->closed_at;
    $resolutionTimeDisplay = $formatTicketDuration($ticket->created_at, $resolutionEndedAt);
@endphp

<div class="space-y-6">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-slate-900">Ticket Details</h2>
        </div>
        <div class="border-t border-slate-200 px-4 py-5 sm:px-6">
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Name</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->name ?? $ticket->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Contact Number</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->contact_number ?? ($ticket->user->phone ?? 'Not provided') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Email</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->email ?? $ticket->user->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Province</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->province ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Municipality</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->municipality ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Category</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->category->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Field Office</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->department ?: 'Not specified' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Ticket Type</dt>
                    <dd class="text-sm text-slate-900">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ticket->ticket_type_badge_class }}">
                            {{ $ticket->ticket_type_label }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Assigned Staff</dt>
                    <dd class="text-sm text-slate-900">{{ $ticket->assigned_users_label }}</dd>
                </div>
                @if($ticket->resolved_at)
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Resolved At</dt>
                        <dd class="text-sm text-slate-900">{{ $ticket->resolved_at->format('M j, Y \a\t g:i A') }}</dd>
                    </div>
                @endif
                @if($resolutionTimeDisplay)
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Resolution Time</dt>
                        <dd class="text-sm text-slate-900">{{ $resolutionTimeDisplay }}</dd>
                    </div>
                @endif
                @if($ticket->closed_at)
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Closed At</dt>
                        <dd class="text-sm text-slate-900">{{ $ticket->closed_at->format('M j, Y \a\t g:i A') }}</dd>
                    </div>
                @endif
                @if($ticket->isCancelled())
                    @if($ticket->cancelled_at)
                        <div>
                            <dt class="text-sm font-medium text-slate-500">Cancelled At</dt>
                            <dd class="text-sm text-slate-900">{{ $ticket->cancelled_at->format('M j, Y \a\t g:i A') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Cancellation Reason</dt>
                        <dd class="whitespace-pre-line text-sm text-slate-900">{{ $ticket->cancellation_reason ?: 'Not recorded' }}</dd>
                    </div>
                @endif
                @if($ticket->root_cause_category)
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Root Cause</dt>
                        <dd class="text-sm text-slate-900">
                            {{ $ticket->root_cause_category_label }}
                            @if($ticket->root_cause_notes)
                                <span class="block text-slate-600">{{ $ticket->root_cause_notes }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($ticket->satisfaction_rating)
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Client Rating</dt>
                        <dd class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="h-4 w-4 {{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-slate-300' }}" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-sm font-medium text-slate-700">{{ $ticket->satisfaction_rating }} / 5</span>
                            </div>
                            @if($ticket->satisfaction_comment)
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Comment / Suggestion / Complaint</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $ticket->satisfaction_comment }}</p>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-slate-900">Actions</h2>
        </div>
        <div class="border-t border-slate-200 px-4 py-5 sm:px-6 space-y-4">
            @if($canManageTicket)
                @if(in_array($ticket->status, ['resolved', 'closed'], true))
                    @if($canRevertTicket)
                        <button type="button" id="open-revert-modal-btn" class="btn-secondary w-full">Revert to In Progress</button>
                    @else
                        <button type="button" class="btn-secondary w-full opacity-60 cursor-not-allowed" disabled>Revert expired</button>
                        <p class="text-xs text-slate-500">
                            This ticket can no longer be reverted because it has been closed for more than {{ $closedRevertWindowDays }} days.
                        </p>
                    @endif
                @endif

                <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <div>
                        <label for="assigned_to" class="form-label">Assign Staff</label>
                        <select
                            name="assigned_to[]"
                            id="assigned_to"
                            class="form-input"
                            multiple
                            data-enhanced-multiselect="1"
                            data-placeholder="Select staff"
                        >
                            <x-ui.assignee-options :assignees="$assignees" :selected="old('assigned_to', $ticket->assigned_user_ids)" />
                        </select>
                        @error('assigned_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="mt-2 btn-secondary w-full">Update Assignment</button>
                    </div>
                </form>

                @if($ticket->isEscalated())
                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                        <h3 class="text-sm font-semibold text-orange-800">Escalated</h3>
                        <p class="mt-1 text-sm text-orange-700">
                            To {{ $ticket->escalatedTo?->publicDisplayName() ?? 'a staff member' }}
                            by {{ $ticket->escalatedBy?->publicDisplayName() ?? 'staff' }}
                            {{ $ticket->escalated_at?->diffForHumans() }}.
                        </p>
                        @if($ticket->escalation_reason)
                            <p class="mt-1 text-sm text-orange-700">Reason: {{ $ticket->escalation_reason }}</p>
                        @endif
                        <form action="{{ route('admin.tickets.escalate.clear', $ticket) }}" method="POST" class="mt-3" data-submit-feedback>
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="btn-secondary w-full">Clear Escalation</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('admin.tickets.escalate', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="escalated_to" class="form-label">Escalate To</label>
                            <select name="escalated_to" id="escalated_to" class="form-input">
                                <option value="">Select staff member</option>
                                <x-ui.assignee-options :assignees="$assignees" :selected="old('escalated_to')" :exclude="[auth()->id()]" />
                            </select>
                            @error('escalated_to')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <textarea
                                name="reason"
                                aria-label="Reason for escalating this ticket"
                                rows="2"
                                class="form-input mt-2 @error('reason') border-red-300 @enderror"
                                placeholder="Reason for escalating this ticket..."
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-2 btn-secondary w-full">Escalate Ticket</button>
                        </div>
                    </form>
                @endif

                <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <div>
                        <label for="status" class="form-label">Status</label>
                        @php
                            $disabledStatuses = [];
                            if ($ticket->status === 'closed' && ! $canRevertTicket) {
                                $disabledStatuses = ['open', 'in_progress', 'pending', 'resolved'];
                            }
                            if (! $ticket->isCancellable() && ! $ticket->isCancelled()) {
                                $disabledStatuses[] = 'cancelled';
                            }
                        @endphp
                        <select name="status" id="status" class="form-input">
                            <x-ui.ticket-status-options :selected="old('status', $ticket->status)" :disabled="$disabledStatuses" />
                        </select>
                        @if(! $ticket->isCancelled() && ! $ticket->isCancellable())
                            <p class="mt-1 text-xs text-slate-500">A ticket can only be cancelled while it is Open or Pending.</p>
                        @endif
                        <div id="status-close-reason-wrap" class="mt-2 hidden">
                            <label for="status_close_reason" class="form-label">Close / Cancellation Reason <span class="text-red-500">*</span></label>
                            <textarea
                                name="close_reason"
                                id="status_close_reason"
                                rows="3"
                                class="form-input @error('close_reason') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                                placeholder="Provide a reason for closing or cancelling this ticket..."
                            >{{ old('close_reason') }}</textarea>
                            @error('close_reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @if($ticket->status === 'closed' && ! $canRevertTicket)
                            <p class="mt-2 text-xs text-red-700">
                                Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days.
                            </p>
                        @endif
                        <div id="status-root-cause-wrap" class="mt-2 hidden">
                            <label for="status_root_cause_category" class="form-label">Root Cause (optional)</label>
                            <select name="root_cause_category" id="status_root_cause_category" class="form-input">
                                <option value="">Not specified</option>
                                @foreach(\App\Enums\RootCauseCategory::cases() as $rootCauseCategory)
                                    <option value="{{ $rootCauseCategory->value }}" {{ old('root_cause_category', $ticket->root_cause_category) === $rootCauseCategory->value ? 'selected' : '' }}>{{ $rootCauseCategory->label() }}</option>
                                @endforeach
                            </select>
                            <textarea
                                name="root_cause_notes"
                                id="status_root_cause_notes"
                                aria-label="Root cause notes (optional)"
                                rows="2"
                                class="form-input mt-2"
                                placeholder="Optional notes on the root cause..."
                            >{{ old('root_cause_notes', $ticket->root_cause_notes) }}</textarea>
                        </div>
                        <button type="submit" class="mt-2 btn-secondary w-full">Update Status</button>
                    </div>
                </form>

                <form action="{{ route('admin.tickets.severity', $ticket) }}" method="POST" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <div>
                        <label for="severity" class="form-label">Severity</label>
                        <select name="severity" id="severity" class="form-input">
                            @if($ticket->priority === null)
                                <option value="" selected disabled>Severity not set</option>
                            @endif
                            <option value="severity_1" {{ $ticket->priority === 'severity_1' ? 'selected' : '' }}>Severity 1</option>
                            <option value="severity_2" {{ $ticket->priority === 'severity_2' ? 'selected' : '' }}>Severity 2</option>
                            <option value="severity_3" {{ $ticket->priority === 'severity_3' ? 'selected' : '' }}>Severity 3</option>
                        </select>
                        <button type="submit" class="mt-2 btn-secondary w-full">Update Severity</button>
                    </div>
                </form>

                @if($showCloseAction)
                    <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <input type="hidden" name="status" value="closed">
                        <div>
                            <label for="timed_close_reason" class="form-label">Close Ticket</label>
                            <textarea
                                id="timed_close_reason"
                                name="close_reason"
                                rows="3"
                                class="form-input"
                                placeholder="Provide a reason for closing this ticket..."
                                required
                            >{{ old('status') === 'closed' ? old('close_reason') : '' }}</textarea>
                            <button type="submit" class="mt-2 btn-danger w-full">Close Ticket</button>
                        </div>
                    </form>
                @endif

                @if(auth()->user()->canManageTicketType())
                    <form action="{{ route('admin.tickets.type', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="ticket_type" class="form-label">Ticket Type</label>
                            <select name="ticket_type" id="ticket_type" class="form-input">
                                <option value="{{ \App\Models\Ticket::TYPE_EXTERNAL }}" {{ old('ticket_type', $ticket->ticket_type) === \App\Models\Ticket::TYPE_EXTERNAL ? 'selected' : '' }}>External</option>
                                <option value="{{ \App\Models\Ticket::TYPE_INTERNAL }}" {{ old('ticket_type', $ticket->ticket_type) === \App\Models\Ticket::TYPE_INTERNAL ? 'selected' : '' }}>Internal</option>
                            </select>
                            @error('ticket_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Ticket Type</button>
                        </div>
                    </form>
                @endif

                @if(auth()->user()->canManageTickets())
                    @php
                        $departmentNames = \App\Models\User::allowedDepartments();
                    @endphp
                    <form action="{{ route('admin.tickets.department', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="ticket_department" class="form-label">Field Office</label>
                            <select name="department" id="ticket_department" class="form-input">
                                @unless(in_array($ticket->department, $departmentNames, true))
                                    <option value="" selected disabled>Not specified</option>
                                @endunless
                                @foreach($departmentNames as $departmentName)
                                    <option value="{{ $departmentName }}" {{ old('department', $ticket->department) === $departmentName ? 'selected' : '' }}>{{ $departmentName }}</option>
                                @endforeach
                            </select>
                            @error('department')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-slate-500">The office this ticket is counted under in reports. Change it when it was filed under the wrong one.</p>
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Field Office</button>
                        </div>
                    </form>
                @endif

                @if(auth()->user()->canSetManualTicketDates())
                    <form action="{{ route('admin.tickets.dates', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="dates_created_at" class="form-label">Logged Date</label>
                            <input type="datetime-local" name="created_at" id="dates_created_at"
                                   autocomplete="off"
                                   class="form-input rounded-2xl @error('created_at') border-red-500 @enderror"
                                   value="{{ old('created_at', $ticket->created_at->format('Y-m-d\TH:i')) }}"
                                   max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                            @error('created_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-3">
                            <label for="dates_resolved_at" class="form-label">Resolved Date</label>
                            <input type="datetime-local" name="resolved_at" id="dates_resolved_at"
                                   autocomplete="off"
                                   class="form-input rounded-2xl @error('resolved_at') border-red-500 @enderror"
                                   value="{{ old('resolved_at', optional($ticket->resolved_at)->format('Y-m-d\TH:i')) }}"
                                   max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                            @error('resolved_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-3">
                            <label for="dates_closed_at" class="form-label">Closed Date</label>
                            <input type="datetime-local" name="closed_at" id="dates_closed_at"
                                   autocomplete="off"
                                   class="form-input rounded-2xl @error('closed_at') border-red-500 @enderror"
                                   value="{{ old('closed_at', optional($ticket->closed_at)->format('Y-m-d\TH:i')) }}"
                                   max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                            @error('closed_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            Corrects the recorded dates only &mdash; it does not change the ticket's status. Use the Status action above for that.
                        </p>
                        <button type="submit" class="mt-2 btn-secondary w-full">Update Dates</button>
                    </form>
                @endif
            @else
                <p class="text-sm text-slate-600">
                    You are viewing this ticket read-only. Only the assigned staff and supervisors can update it.
                </p>
            @endif
        </div>
    </div>
</div>
