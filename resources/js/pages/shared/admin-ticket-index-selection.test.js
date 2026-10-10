import { describe, expect, it } from 'vitest';
import { createAdminTicketBulkSelection } from './admin-ticket-index-selection';

// Each ticket has two checkboxes with the same value: the phone card and the desktop table row.
const makePage = (ids) => {
    const checkboxes = ids.flatMap((id) => [
        { className: 'js-ticket-checkbox', value: String(id), checked: false },
        { className: 'js-ticket-checkbox', value: String(id), checked: false },
    ]);
    const selectAll = { checked: false, indeterminate: false };
    const pageRoot = {
        querySelectorAll: () => checkboxes,
        querySelector: () => selectAll,
    };
    const label = { textContent: '', classList: { toggle: () => {} } };
    const mergeButton = { disabled: true };
    const selection = createAdminTicketBulkSelection({
        pageRoot,
        bulkCountLabel: label,
        bulkActionButtons: [{ button: mergeButton, minSelected: 2 }],
    });

    return { checkboxes, selectAll, label, mergeButton, selection };
};

describe('bulk ticket selection', () => {
    it('counts a ticket once when both of its checkboxes are ticked', () => {
        const { checkboxes, label, selection } = makePage([1, 2, 3]);
        checkboxes.forEach((checkbox) => { checkbox.checked = true; });

        selection.syncBulkSelection();

        expect(selection.selectedTicketIds()).toEqual(['1', '2', '3']);
        expect(label.textContent).toBe('3 selected');
    });

    it('counts a ticket as selected when only one of its two checkboxes is ticked', () => {
        const { checkboxes, label, selection } = makePage([1, 2, 3]);
        checkboxes[0].checked = true;
        checkboxes[3].checked = true;

        selection.syncBulkSelection();

        expect(selection.selectedTicketIds()).toEqual(['1', '2']);
        expect(label.textContent).toBe('2 selected');
    });

    it('ticks select-all once every ticket is selected by hand through either checkbox', () => {
        const { checkboxes, selectAll, selection } = makePage([1, 2]);
        checkboxes[0].checked = true; // ticket 1, first copy
        checkboxes[3].checked = true; // ticket 2, second copy

        selection.syncBulkSelection();

        expect(selectAll.checked).toBe(true);
        expect(selectAll.indeterminate).toBe(false);
    });

    it('leaves select-all unticked but indeterminate for a partial selection, and enables merge only from two tickets', () => {
        const { checkboxes, selectAll, mergeButton, selection } = makePage([1, 2, 3]);
        checkboxes[0].checked = true;

        selection.syncBulkSelection();
        expect(selectAll.checked).toBe(false);
        expect(selectAll.indeterminate).toBe(true);
        expect(mergeButton.disabled).toBe(true);

        checkboxes[2].checked = true;
        selection.syncBulkSelection();
        expect(mergeButton.disabled).toBe(false);
    });
});
