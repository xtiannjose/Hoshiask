export const populateHiddenSelectedIds = (container, selectedIds) => {
    if (!container) return;

    container.innerHTML = '';
    selectedIds.forEach((id) => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'selected_ids[]';
        hiddenInput.value = id;
        container.appendChild(hiddenInput);
    });
};

export const createAdminTicketBulkSelection = ({ pageRoot, bulkActionButtons = [], bulkCountLabel }) => {
    const getRowCheckboxes = () => Array.from(pageRoot.querySelectorAll('.js-ticket-checkbox'));
    const getSelectAllCheckbox = () => pageRoot.querySelector('#select-all-tickets');

    // Every ticket is rendered twice (phone card and desktop table row) with the same value, so a ticket
    // counts as selected when any of its checkboxes is ticked, and is counted once.
    const selectedTicketIds = () => {
        return [...new Set(getRowCheckboxes().filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value))];
    };

    const allTicketIds = () => [...new Set(getRowCheckboxes().map((checkbox) => checkbox.value))];

    const syncBulkSelection = () => {
        const selectedCount = selectedTicketIds().length;
        const selectAllCheckbox = getSelectAllCheckbox();
        const totalCount = allTicketIds().length;

        bulkActionButtons.forEach(({ button, minSelected = 1 }) => {
            if (button) {
                button.disabled = selectedCount < minSelected;
            }
        });

        if (bulkCountLabel) {
            bulkCountLabel.classList.toggle('hidden', selectedCount === 0);
            bulkCountLabel.textContent = selectedCount === 1 ? '1 selected' : `${selectedCount} selected`;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = totalCount > 0 && selectedCount === totalCount;
            selectAllCheckbox.indeterminate = selectedCount > 0 && !selectAllCheckbox.checked;
        }
    };

    const resetBulkSelection = () => {
        const selectAllCheckbox = getSelectAllCheckbox();
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }

        getRowCheckboxes().forEach((checkbox) => {
            checkbox.checked = false;
        });

        syncBulkSelection();
    };

    return {
        getRowCheckboxes,
        getSelectAllCheckbox,
        selectedTicketIds,
        syncBulkSelection,
        resetBulkSelection,
    };
};
