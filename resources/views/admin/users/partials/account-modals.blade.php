<!-- Account Status Modal -->
<dialog id="statusConfirmModal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="status"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
    <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-6 w-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 id="statusModalTitle" class="mt-2 text-lg font-medium text-slate-900">Deactivate Account</h3>
            <div class="mt-2 px-7 py-3">
                <p id="statusModalPrompt" class="text-sm text-slate-500">
                    <span id="statusPromptText">Are you sure you want to deactivate</span> <strong id="statusTargetUserName"></strong>?
                </p>
                <label id="statusCheckboxLabel" class="mt-3 inline-flex items-center text-sm text-slate-700">
                    <input id="statusConfirmCheckbox" type="checkbox" class="mr-2 ticket-checkbox">
                    <span id="statusCheckboxText">I understand this user will not be able to sign in.</span>
                </label>
            </div>
            <p id="statusModalError" class="mx-4 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
            <div class="items-center px-4 py-3">
                <button id="confirmStatusChange" type="button" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-60" disabled>
                    Deactivate Account
                </button>
                <button id="cancelStatusChange" type="button" class="btn-secondary mt-3 w-full">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</dialog>

<!-- Action Notification -->
<div id="actionNotification" class="fixed right-4 top-4 z-[70] hidden max-w-sm rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-lg">
    <p id="actionNotificationMessage"></p>
</div>

<!-- Deactivate User Modal -->
<dialog id="deleteModal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
    <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="mt-2 text-lg font-medium text-slate-900">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-slate-500">
                    Are you sure you want to delete <strong id="deleteUserName"></strong>? Ticket history will be preserved.
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-slate-700">
                    <input id="deleteConfirmCheckbox" type="checkbox" required aria-required="true" class="mr-2 ticket-checkbox">
                    I understand this action is permanent.
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" disabled class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-60">
                    {{ $deleteActionLabel ?? 'Delete User' }}
                </button>
                <button id="cancelDelete" type="button" class="btn-secondary mt-3 w-full">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</dialog>
