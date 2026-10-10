@extends('layouts.app')

@section('title', 'Create Ticket - ' . config('app.name'))

@section('content')
<div
    class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8"
    data-admin-ticket-create-page
    data-requester-search-url="{{ route('admin.tickets.requester-accounts') }}"
    data-current-support-user-id="{{ in_array(auth()->user()?->normalizedRole(), \App\Models\User::TICKET_ASSIGNABLE_ROLES, true) ? (int) auth()->id() : '' }}"
>
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <a href="{{ route('admin.dashboard') }}" class="text-slate-500 hover:text-slate-700" aria-label="Go to dashboard">
                            <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="h-5 w-5 flex-shrink-0 text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="{{ route('admin.tickets.index') }}" class="ml-4 text-sm font-medium text-slate-500 hover:text-slate-700">Tickets</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="h-5 w-5 flex-shrink-0 text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-slate-900">Create Ticket</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="rounded-lg bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="mb-6">
                <h1 class="mb-2 text-lg font-medium leading-6 text-slate-900">Create Support Ticket</h1>
                <p class="text-sm text-slate-600" data-ticket-create-description>
                    Use this form when a client contacts support directly and the ticket needs to be logged by a support user.
                </p>
            </div>

            <form id="admin-ticket-create-form" action="{{ route('admin.tickets.store') }}" method="POST" enctype="multipart/form-data" data-submit-feedback>
                @csrf
                <x-form-error-summary :file-note="true" />
                @include('partials.location-suggestions')
                @php
                    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
                    $selectedTicketType = old('ticket_type', \App\Models\Ticket::TYPE_EXTERNAL);
                    $selectedRequesterGroup = $selectedTicketType === \App\Models\Ticket::TYPE_INTERNAL ? 'support' : 'client';
                    $isStaffTicket = $selectedTicketType === \App\Models\Ticket::TYPE_INTERNAL;
                    $selectedAssignedTo = collect((array) old('assigned_to', []))
                        ->filter(fn ($id) => filled($id))
                        ->map(fn ($id) => (string) $id)
                        ->first();
                    $requesterAccounts = [
                        'client' => $clientAccounts['accounts'],
                        'support' => $supportAccounts['accounts'],
                    ];
                    $requesterAccountTotals = [
                        'client' => $clientAccounts['total'],
                        'support' => $supportAccounts['total'],
                    ];
                    $initialRequesterAccounts = $requesterAccounts[$selectedRequesterGroup];
                @endphp
                <script type="application/json" data-requester-accounts>@json($requesterAccounts)</script>
                <script type="application/json" data-requester-account-totals>@json($requesterAccountTotals)</script>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="xl:col-span-2">
                        <fieldset>
                            <legend class="form-label">Ticket Type <span class="text-red-600">*</span></legend>
                            <div class="mt-2 inline-grid w-full max-w-md grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
                                <div class="min-w-0">
                                    <input
                                        type="radio"
                                        name="ticket_type"
                                        id="ticket_type_external"
                                        value="{{ \App\Models\Ticket::TYPE_EXTERNAL }}"
                                        class="peer sr-only"
                                        data-ticket-type-input
                                        {{ $selectedTicketType === \App\Models\Ticket::TYPE_EXTERNAL ? 'checked' : '' }}
                                    >
                                    <label
                                        for="ticket_type_external"
                                        class="flex h-10 items-center justify-center rounded-lg px-3 text-center text-sm font-semibold text-slate-600 transition hover:text-slate-900 peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm"
                                    >
                                        <span>Client Ticket</span>
                                    </label>
                                </div>

                                <div class="min-w-0">
                                    <input
                                        type="radio"
                                        name="ticket_type"
                                        id="ticket_type_internal"
                                        value="{{ \App\Models\Ticket::TYPE_INTERNAL }}"
                                        class="peer sr-only"
                                        data-ticket-type-input
                                        {{ $selectedTicketType === \App\Models\Ticket::TYPE_INTERNAL ? 'checked' : '' }}
                                    >
                                    <label
                                        for="ticket_type_internal"
                                        class="flex h-10 items-center justify-center rounded-lg px-3 text-center text-sm font-semibold text-slate-600 transition hover:text-slate-900 peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm"
                                    >
                                        <span>Staff Ticket</span>
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                        @error('ticket_type')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <label for="user_id" class="form-label mt-4" data-requester-account-label>{{ $isStaffTicket ? 'Staff Requester Account' : 'Client Requester Account' }} <span class="text-red-600">*</span></label>
                        <label for="requester_search" class="sr-only">Search accounts by name, email, department or phone</label>
                        <input
                            type="search"
                            id="requester_search"
                            class="form-input mb-2"
                            placeholder="Search by name, email, department or phone"
                            autocomplete="off"
                            maxlength="100"
                            data-requester-search
                        >
                        <select
                            name="user_id"
                            id="user_id"
                            required
                            class="form-input @error('user_id') border-red-500 @enderror"
                            data-client-account-select
                        >
                            <option value="" data-placeholder-option>{{ $isStaffTicket ? 'Select a staff requester account' : 'Select a client requester account' }}</option>
                            @foreach($initialRequesterAccounts as $requesterAccount)
                                <option
                                    value="{{ $requesterAccount['value'] }}"
                                    data-name="{{ $requesterAccount['name'] }}"
                                    data-email="{{ $requesterAccount['email'] }}"
                                    data-phone="{{ $requesterAccount['phone'] }}"
                                    data-role="{{ $requesterAccount['role'] }}"
                                    data-account-group="{{ $requesterAccount['group'] }}"
                                    {{ old('user_id') == $requesterAccount['value'] ? 'selected' : '' }}
                                >
                                    {{ $requesterAccount['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-slate-500" aria-live="polite" data-requester-account-help>
                            {{ $isStaffTicket ? 'Staff tickets show only active staff requester accounts.' : 'Client tickets show only active client requester accounts.' }}
                        </p>
                        @error('user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2 {{ $isStaffTicket ? '' : 'hidden' }}" data-staff-assignment-wrap>
                        <label for="assigned_to" class="form-label">Assign To <span class="text-red-600">*</span></label>
                        <select
                            name="assigned_to"
                            id="assigned_to"
                            class="form-input @error('assigned_to') border-red-500 @enderror"
                            data-staff-assignment-select
                            {{ $isStaffTicket ? '' : 'disabled' }}
                        >
                            <option value="">Select staff</option>
                            @foreach($assignmentAssignees as $assignee)
                                <option
                                    value="{{ $assignee->id }}"
                                    {{ $selectedAssignedTo === (string) $assignee->id ? 'selected' : '' }}
                                >
                                    {{ $assignee->publicDisplayName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="form-label">Name <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" required
                               class="form-input @error('name') border-red-500 @enderror"
                               value="{{ old('name') }}"
                               placeholder="Client full name">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                        <div>
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" id="contact_number" inputmode="tel"
                                   class="form-input @error('contact_number') border-red-500 @enderror"
                                   value="{{ old('contact_number') }}"
                                   placeholder="e.g. 09123456789">
                            @error('contact_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email"
                                   class="form-input @error('email') border-red-500 @enderror"
                                   value="{{ old('email') }}"
                                   placeholder="client@example.com">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <p class="inline-block max-w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" data-requester-snapshot-note>
                            {{ $isStaffTicket
                                ? 'This staff ticket will be linked to the selected staff account, while the contact details below capture the request snapshot used for follow-up.'
                                : 'This ticket will be linked to the selected client account, while the contact details below capture the request snapshot used for support follow-up.' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                        <div>
                            <label for="province" class="form-label">Province <span class="text-red-600">*</span></label>
                            <input type="text" name="province" id="province" required
                                   list="province-options" data-location-province autocomplete="off"
                                   class="form-input @error('province') border-red-500 @enderror"
                                   value="{{ old('province') }}"
                                   placeholder="e.g. Iloilo">
                            @error('province')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="municipality" class="form-label">Municipality / City <span class="text-red-600">*</span></label>
                            <input type="text" name="municipality" id="municipality" required
                                   list="municipality-options" autocomplete="off"
                                   data-location-municipalities-url="{{ route('locations.municipalities') }}"
                                   class="form-input @error('municipality') border-red-500 @enderror"
                                   value="{{ old('municipality') }}"
                                   placeholder="e.g. Iloilo City">
                            @error('municipality')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label for="subject" class="form-label">Subject <span class="text-red-600">*</span></label>
                        <input type="text" name="subject" id="subject" required
                               class="form-input @error('subject') border-red-500 @enderror"
                               value="{{ old('subject') }}"
                               placeholder="Brief description of the issue">
                        @error('subject')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="category_id" class="form-label">Category <span class="text-red-600">*</span></label>
                        <select name="category_id" id="category_id" required
                                class="form-input @error('category_id') border-red-500 @enderror">
                            <option value="">Select a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Severity is assigned by staff after the ticket is reviewed.
                        </p>
                    </div>

                    <div class="xl:col-span-2">
                        <label for="description" class="form-label">Description <span class="text-red-600">*</span></label>
                        <textarea name="description" id="description" rows="6" required
                                  class="form-input @error('description') border-red-500 @enderror"
                                  placeholder="Provide the issue details, context, and any steps already taken.">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <label for="attachments" class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" id="attachments" multiple
                               class="form-input cursor-pointer @error('attachments.*') border-red-500 @enderror"
                               accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.xls,.xlsx"
                               aria-describedby="attachments-help attachments-guard"
                               data-upload-guard="attachments-guard"
                               data-max-files="{{ \App\Support\UploadLimits::MAX_FILES }}"
                               data-max-file-bytes="{{ \App\Support\UploadLimits::perFileBytes() }}"
                               data-max-request-bytes="{{ \App\Support\UploadLimits::perRequestBytes() }}">
                        <p id="attachments-help" class="mt-1 text-sm text-slate-500">
                            Optional. Upload supporting files if available. Maximum {{ \App\Support\UploadLimits::perFileLabel() }} per file, {{ \App\Support\UploadLimits::MAX_FILES }} files at a time.
                        </p>
                        <p id="attachments-guard" class="mt-1 hidden text-sm font-medium text-red-600" role="alert"></p>
                        @error('attachments')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($canSetManualDates)
                    <div class="xl:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-700">Manual Logging Dates (Optional)</p>
                            <p class="mt-1 text-sm text-slate-500">
                                Use these only when entering a ticket that was already handled on paper before being logged here. Leave blank to use the current date and time.
                            </p>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label for="created_at" class="form-label">Logged Date</label>
                                    <input type="datetime-local" name="created_at" id="created_at"
                                           autocomplete="off"
                                           class="form-input rounded-2xl @error('created_at') border-red-500 @enderror"
                                           value="{{ old('created_at') }}"
                                           max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                                    @error('created_at')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="resolved_at" class="form-label">Resolved Date</label>
                                    <input type="datetime-local" name="resolved_at" id="resolved_at"
                                           autocomplete="off"
                                           class="form-input rounded-2xl @error('resolved_at') border-red-500 @enderror"
                                           value="{{ old('resolved_at') }}"
                                           max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                                    @error('resolved_at')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="closed_at" class="form-label">Closed Date</label>
                                    <input type="datetime-local" name="closed_at" id="closed_at"
                                           autocomplete="off"
                                           class="form-input rounded-2xl @error('closed_at') border-red-500 @enderror"
                                           value="{{ old('closed_at') }}"
                                           max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}">
                                    @error('closed_at')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">
                                Setting a resolved or closed date will create the ticket with that status already applied.
                            </p>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <a href="{{ route('admin.tickets.index') }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">
                        Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
