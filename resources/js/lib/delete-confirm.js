// Deleting asks "are you sure?" in one shared dialog (components/ui/delete-confirm-modal.blade.php).
// A trigger is any [data-delete-confirm] button (components/ui/delete-button.blade.php); clicking it fills the
// dialog with the trigger's title, name and form address and opens it. Nothing is sent until the box is ticked
// and the red button is pressed, and a trigger marked aria-disabled (a department that is in use) does nothing.

const TRIGGER_SELECTOR = '[data-delete-confirm]';

export const readDeleteTrigger = (trigger) => {
    const data = (trigger && trigger.dataset) || {};

    return {
        action: data.deleteAction || '',
        title: data.deleteTitle || 'Delete',
        name: data.deleteName || '',
        note: data.deleteNote || '',
    };
};

export const isBlockedTrigger = (trigger) => !trigger
    || !trigger.getAttribute
    || trigger.getAttribute('aria-disabled') === 'true';

export const registerDeleteConfirm = () => {
    const dialog = document.getElementById('delete-confirm-modal');
    if (!dialog || !window.ModalKit) return;

    const form = dialog.querySelector('#delete-confirm-form');
    const title = dialog.querySelector('#delete-confirm-title');
    const name = dialog.querySelector('#delete-confirm-name');
    const note = dialog.querySelector('#delete-confirm-note');
    const checkbox = dialog.querySelector('#delete-confirm-checkbox');
    const submit = dialog.querySelector('#delete-confirm-submit');
    const cancel = dialog.querySelector('#delete-confirm-cancel');
    if (!form || !title || !name || !note || !checkbox || !submit || !cancel) return;

    const syncSubmit = () => {
        submit.disabled = !checkbox.checked;
    };

    const controller = window.ModalKit.bind(dialog, {
        closeButtons: [cancel],
        onClose: () => {
            checkbox.checked = false;
            syncSubmit();
        },
    });
    if (!controller) return;

    checkbox.addEventListener('change', syncSubmit);

    // A form can also be sent with Enter; never let that skip the tick box.
    form.addEventListener('submit', (event) => {
        if (!checkbox.checked || !form.getAttribute('action')) event.preventDefault();
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest(TRIGGER_SELECTOR) : null;
        if (!trigger) return;

        event.preventDefault();
        if (isBlockedTrigger(trigger)) return;

        const details = readDeleteTrigger(trigger);
        form.setAttribute('action', details.action);
        title.textContent = details.title;
        name.textContent = details.name;
        note.textContent = details.note;
        note.classList.toggle('hidden', details.note === '');
        submit.textContent = details.title;
        checkbox.checked = false;
        syncSubmit();

        controller.open();
    });
};
