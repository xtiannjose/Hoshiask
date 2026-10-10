import { parseAssignedIds } from './admin-ticket-filters';

export const setMultiSelectValues = (select, values) => {
    if (!(select instanceof HTMLSelectElement)) return;

    const selectedValues = new Set(values);
    Array.from(select.options).forEach((option) => {
        option.selected = selectedValues.has(option.value);
    });
    select.dispatchEvent(new Event('change', { bubbles: true }));
};

export const syncCheckboxControlledSubmitState = ({ checkbox, submitButton }) => {
    if (!checkbox || !submitButton) return;

    submitButton.disabled = !checkbox.checked;
};

export const syncEditCloseReasonVisibility = ({ statusSelect, closeReasonWrap, closeReasonInput }) => {
    if (!statusSelect || !closeReasonWrap || !closeReasonInput) return;

    const needsReason = statusSelect.value === 'closed' || statusSelect.value === 'cancelled';
    closeReasonWrap.classList.toggle('hidden', !needsReason);
    closeReasonInput.required = needsReason;
    if (!needsReason) {
        closeReasonInput.value = '';
    }
};

export const populateAdminTicketEditModal = ({
    button,
    editForm,
    editTicketText,
    editAssignedSelect,
    editStatusSelect,
    editCloseReasonInput,
    editCloseHint,
    editPrioritySelect,
    editTicketTypeSelect,
    editDeleteButton,
    quickUpdateRouteTemplate,
    onStatusStateSync,
}) => {
    const ticketId = button.dataset.ticketId;
    if (!ticketId || !editForm) return;

    editForm.action = quickUpdateRouteTemplate.replace('__TICKET__', ticketId);
    if (editTicketText) {
        editTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
    }
    setMultiSelectValues(editAssignedSelect, parseAssignedIds(button.dataset.assignedTo || '[]'));
    if (editStatusSelect) editStatusSelect.value = button.dataset.status || 'open';
    if (editCloseReasonInput) editCloseReasonInput.value = '';
    if (editPrioritySelect) editPrioritySelect.value = button.dataset.priority || '';
    if (editTicketTypeSelect) editTicketTypeSelect.value = button.dataset.ticketType || 'external';

    if (editStatusSelect) {
        const canRevert = button.dataset.canRevert === '1';
        const isClosedTicket = (button.dataset.status || '') === 'closed';
        const closedOption = editStatusSelect.querySelector('option[value="closed"]');

        ['open', 'in_progress', 'pending', 'resolved'].forEach((statusValue) => {
            const option = editStatusSelect.querySelector(`option[value="${statusValue}"]`);
            if (option) {
                option.disabled = isClosedTicket && !canRevert;
            }
        });

        if (closedOption) {
            closedOption.disabled = false;
            closedOption.textContent = 'Closed';
        }

        if (isClosedTicket && !canRevert) {
            editStatusSelect.value = 'closed';
        }

        if (editCloseHint) {
            if (isClosedTicket && !canRevert) {
                editCloseHint.classList.remove('hidden');
                editCloseHint.textContent = 'Closed tickets cannot be reverted after 7 days.';
            } else {
                editCloseHint.classList.add('hidden');
                editCloseHint.textContent = '';
            }
        }
    }

    onStatusStateSync();

    if (editDeleteButton) {
        editDeleteButton.dataset.ticketId = ticketId;
        editDeleteButton.dataset.ticketNumber = button.dataset.ticketNumber || '';
    }
};

export const hasOpenModal = (modals) => {
    return modals.some((modal) => modal && modal.open);
};
