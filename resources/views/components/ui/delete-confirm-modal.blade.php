{{--
    The one "are you sure?" dialog for deleting something. Put it once on a page and mark each trigger with
    <x-ui.delete-button>. resources/js/lib/delete-confirm.js fills in the title, the name and the form address when a
    trigger is clicked. The red button stays disabled until the box is ticked, like the other delete dialogs.
--}}
<dialog id="delete-confirm-modal" class="app-modal-root" aria-labelledby="delete-confirm-title">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete-confirm"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <form id="delete-confirm-form" method="POST" action="" class="text-center" data-submit-feedback>
                @csrf
                @method('DELETE')
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <x-ui.icon name="trash" class="h-6 w-6 text-red-600" />
                </div>
                <h3 id="delete-confirm-title" class="mt-2 text-lg font-medium text-slate-900">Delete</h3>
                <div class="mt-2 px-4 py-3">
                    <p class="text-sm text-slate-500">Are you sure you want to delete <strong id="delete-confirm-name" class="break-words text-slate-700"></strong>?</p>
                    <p id="delete-confirm-note" class="mt-1 hidden text-sm text-slate-500"></p>
                    <label for="delete-confirm-checkbox" class="mt-3 inline-flex cursor-pointer items-center text-sm text-slate-700">
                        <input id="delete-confirm-checkbox" type="checkbox" class="mr-2 ticket-checkbox">
                        I understand this action is permanent.
                    </label>
                </div>
                <div class="px-4 py-3">
                    <button id="delete-confirm-submit" type="submit" class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-60" data-loading-text="Deleting..." disabled>Delete</button>
                    <button id="delete-confirm-cancel" type="button" class="btn-secondary mt-3 w-full">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
