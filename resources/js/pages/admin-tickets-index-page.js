import { bootPage } from './shared/boot-page';
import { createAdminTicketResultsController } from './shared/admin-ticket-index-results';
import { createAdminTicketBulkSelection, populateHiddenSelectedIds } from './shared/admin-ticket-index-selection';
import { parseAssignedIds } from './shared/admin-ticket-filters';
import { copyTicketLink } from './shared/ticket-link-copy';
import {
    hasOpenModal,
    populateAdminTicketEditModal,
    setMultiSelectValues,
    syncCheckboxControlledSubmitState,
    syncEditCloseReasonVisibility,
} from './shared/admin-ticket-index-ui';

const initAdminTicketsIndexPage = () => {
    const pageRoot = document.querySelector('[data-admin-tickets-index-page]');
    if (!pageRoot) return;

    const filterForm = pageRoot.querySelector('form[data-search-history-form]');
    const statusView = document.getElementById('admin-status-view');
    const assignForm = document.getElementById('assign-ticket-form');
    const assignModal = document.getElementById('assign-ticket-modal');
    const assignTicketText = document.getElementById('assign-modal-ticket');
    const assignAssigneeSelect = document.getElementById('assign-modal-select');
    const revertForm = document.getElementById('revert-ticket-form');
    const revertModal = document.getElementById('revert-ticket-modal');
    const revertTicketText = document.getElementById('revert-modal-ticket');
    const revertConfirmCheckbox = document.getElementById('revert-confirm-checkbox');
    const revertSubmitButton = document.getElementById('revert-submit-btn');
    const editForm = document.getElementById('edit-ticket-form');
    const editModal = document.getElementById('edit-ticket-modal');
    const editTicketText = document.getElementById('edit-modal-ticket');
    const editAssignedSelect = document.getElementById('edit-modal-assigned');
    const editStatusSelect = document.getElementById('edit-modal-status');
    const editCloseReasonWrap = document.getElementById('edit-modal-close-reason-wrap');
    const editCloseReasonInput = document.getElementById('edit-modal-close-reason');
    const editCloseHint = document.getElementById('edit-modal-close-hint');
    const editPrioritySelect = document.getElementById('edit-modal-priority');
    const editTicketTypeSelect = document.getElementById('edit-modal-ticket-type');
    const editDeleteButton = document.getElementById('edit-modal-delete-btn');
    const deleteForm = document.getElementById('delete-ticket-form');
    const deleteModal = document.getElementById('delete-ticket-modal');
    const deleteTicketText = document.getElementById('delete-modal-ticket');
    const deleteConfirmCheckbox = document.getElementById('delete-confirm-checkbox');
    const deleteConfirmSubmit = document.getElementById('delete-confirm-submit');
    const bulkDeleteForm = document.getElementById('bulk-ticket-delete-form');
    const bulkDeleteButton = document.getElementById('bulk-delete-submit');
    const bulkSelectedIds = document.getElementById('bulk-selected-ids');
    const bulkDeleteConfirmModal = document.getElementById('bulk-delete-confirm-modal');
    const bulkDeleteConfirmCheckbox = document.getElementById('bulk-delete-confirm-checkbox');
    const bulkDeleteConfirmSubmit = document.getElementById('bulk-delete-confirm-submit');
    const bulkCountLabel = document.getElementById('bulk-selected-count');
    const bulkAssignButton = document.getElementById('bulk-assign-submit');
    const bulkAssignModal = document.getElementById('bulk-assign-modal');
    const bulkAssignSelect = document.getElementById('bulk-assign-select');
    const bulkAssignSelectedIds = document.getElementById('bulk-assign-selected-ids');
    const bulkAssignModalCount = document.getElementById('bulk-assign-modal-count');
    const bulkStatusButton = document.getElementById('bulk-status-submit');
    const bulkStatusModal = document.getElementById('bulk-status-modal');
    const bulkStatusSelect = document.getElementById('bulk-status-select');
    const bulkStatusCloseReasonWrap = document.getElementById('bulk-status-close-reason-wrap');
    const bulkStatusCloseReasonInput = document.getElementById('bulk-status-close-reason');
    const bulkStatusSelectedIds = document.getElementById('bulk-status-selected-ids');
    const bulkStatusModalCount = document.getElementById('bulk-status-modal-count');
    const bulkPriorityButton = document.getElementById('bulk-priority-submit');
    const bulkPriorityModal = document.getElementById('bulk-priority-modal');
    const bulkPrioritySelect = document.getElementById('bulk-priority-select');
    const bulkPrioritySelectedIds = document.getElementById('bulk-priority-selected-ids');
    const bulkPriorityModalCount = document.getElementById('bulk-priority-modal-count');
    const bulkMergeButton = document.getElementById('bulk-merge-submit');
    const bulkMergeConfirmModal = document.getElementById('bulk-merge-confirm-modal');
    const bulkMergeForm = document.getElementById('bulk-merge-form');
    const bulkMergeConfirmCheckbox = document.getElementById('bulk-merge-confirm-checkbox');
    const bulkMergeConfirmSubmit = document.getElementById('bulk-merge-confirm-submit');
    const bulkMergeSelectedIds = document.getElementById('bulk-merge-selected-ids');
    const bulkMergeModalCount = document.getElementById('bulk-merge-modal-count');
    const routeBase = pageRoot.dataset.routeBase || window.location.pathname;
    const assignRouteTemplate = pageRoot.dataset.assignRouteTemplate || '';
    const statusRouteTemplate = pageRoot.dataset.statusRouteTemplate || '';
    const quickUpdateRouteTemplate = pageRoot.dataset.quickUpdateRouteTemplate || '';
    const deleteRouteTemplate = pageRoot.dataset.deleteRouteTemplate || '';
    const modalNodes = [
        assignModal,
        revertModal,
        editModal,
        deleteModal,
        bulkDeleteConfirmModal,
        bulkAssignModal,
        bulkStatusModal,
        bulkPriorityModal,
        bulkMergeConfirmModal,
    ];

    const assignModalController = window.ModalKit ? window.ModalKit.bind(assignModal) : null;
    const revertModalController = window.ModalKit && revertModal ? window.ModalKit.bind(revertModal) : null;
    const editModalController = window.ModalKit ? window.ModalKit.bind(editModal) : null;
    const deleteModalController = window.ModalKit && deleteModal ? window.ModalKit.bind(deleteModal) : null;
    const bulkDeleteConfirmModalController = window.ModalKit && bulkDeleteConfirmModal ? window.ModalKit.bind(bulkDeleteConfirmModal) : null;
    const bulkAssignModalController = window.ModalKit && bulkAssignModal ? window.ModalKit.bind(bulkAssignModal) : null;
    const bulkStatusModalController = window.ModalKit && bulkStatusModal ? window.ModalKit.bind(bulkStatusModal) : null;
    const bulkPriorityModalController = window.ModalKit && bulkPriorityModal ? window.ModalKit.bind(bulkPriorityModal) : null;
    const bulkMergeConfirmModalController = window.ModalKit && bulkMergeConfirmModal ? window.ModalKit.bind(bulkMergeConfirmModal) : null;

    let allowBulkDeleteSubmit = false;

    const syncEditStatusState = () => {
        syncEditCloseReasonVisibility({
            statusSelect: editStatusSelect,
            closeReasonWrap: editCloseReasonWrap,
            closeReasonInput: editCloseReasonInput,
        });
    };

    const syncRevertSubmitState = () => {
        syncCheckboxControlledSubmitState({
            checkbox: revertConfirmCheckbox,
            submitButton: revertSubmitButton,
        });
    };

    const syncBulkDeleteConfirmState = () => {
        syncCheckboxControlledSubmitState({
            checkbox: bulkDeleteConfirmCheckbox,
            submitButton: bulkDeleteConfirmSubmit,
        });
    };

    const syncDeleteConfirmState = () => {
        syncCheckboxControlledSubmitState({
            checkbox: deleteConfirmCheckbox,
            submitButton: deleteConfirmSubmit,
        });
    };

    const bulkSelection = createAdminTicketBulkSelection({
        pageRoot,
        bulkActionButtons: [
            { button: bulkDeleteButton },
            { button: bulkAssignButton },
            { button: bulkStatusButton },
            { button: bulkPriorityButton },
            { button: bulkMergeButton, minSelected: 2 },
        ],
        bulkCountLabel,
    });

    const syncBulkStatusState = () => {
        syncEditCloseReasonVisibility({
            statusSelect: bulkStatusSelect,
            closeReasonWrap: bulkStatusCloseReasonWrap,
            closeReasonInput: bulkStatusCloseReasonInput,
        });
    };

    const syncBulkMergeConfirmState = () => {
        syncCheckboxControlledSubmitState({
            checkbox: bulkMergeConfirmCheckbox,
            submitButton: bulkMergeConfirmSubmit,
        });
    };

    const bulkSelectionCountText = () => {
        const count = bulkSelection.selectedTicketIds().length;

        return count === 1 ? '1 ticket selected' : `${count} tickets selected`;
    };

    const resultsController = createAdminTicketResultsController({
        pageRoot,
        filterForm,
        statusView,
        routeBase,
        filterFieldSelectors: [
            'select[name="priority"]',
            'select[name="category"]',
            'select[name="province"]',
            'select[name="municipality"]',
            'select[name="month"]',
            'select[name="ticket_type"]',
            'select[name="assigned_to"]',
            'select[name="account_id"]',
            'select[name="department"]',
        ],
        getResultsContainer: () => pageRoot.querySelector('[data-admin-tickets-results]'),
        hasOpenModal: () => hasOpenModal(modalNodes),
        onResultsUpdated: () => bulkSelection.resetBulkSelection(),
    });

    if (revertConfirmCheckbox) {
        revertConfirmCheckbox.addEventListener('change', syncRevertSubmitState);
    }

    if (bulkDeleteConfirmCheckbox) {
        bulkDeleteConfirmCheckbox.addEventListener('change', syncBulkDeleteConfirmState);
    }

    if (deleteConfirmCheckbox) {
        deleteConfirmCheckbox.addEventListener('change', syncDeleteConfirmState);
    }

    if (revertForm) {
        revertForm.addEventListener('submit', (event) => {
            if (!revertConfirmCheckbox || revertConfirmCheckbox.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', (event) => {
            if (!deleteConfirmCheckbox || deleteConfirmCheckbox.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    if (editStatusSelect) {
        editStatusSelect.addEventListener('change', syncEditStatusState);
    }

    pageRoot.addEventListener('click', (event) => {
        const clearLink = event.target.closest('[data-admin-ticket-clear]');
        if (clearLink) {
            event.preventDefault();
            void resultsController.loadTicketResults(clearLink.href, { history: 'replace' });
            return;
        }

        const copyLinkButton = event.target.closest('[data-ticket-copy-link]');
        if (copyLinkButton) {
            event.preventDefault();
            void copyTicketLink(copyLinkButton);
            return;
        }

        const selectAllCheckbox = event.target.closest('#select-all-tickets');
        if (selectAllCheckbox) {
            bulkSelection.getRowCheckboxes().forEach((checkbox) => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            bulkSelection.syncBulkSelection();
            return;
        }

        const ticketCheckbox = event.target.closest('.js-ticket-checkbox');
        if (ticketCheckbox) {
            bulkSelection.syncBulkSelection();
            return;
        }

        const paginationLink = event.target.closest('[data-admin-ticket-pagination] a');
        if (paginationLink) {
            event.preventDefault();
            void resultsController.loadTicketResults(paginationLink.href, { history: 'push' });
            return;
        }

        const assignButton = event.target.closest('.js-open-assign-modal');
        if (assignButton) {
            const ticketId = assignButton.dataset.ticketId;
            if (!ticketId || !assignForm) return;

            assignForm.action = assignRouteTemplate.replace('__TICKET__', ticketId);
            if (assignTicketText) {
                assignTicketText.textContent = 'Ticket #' + (assignButton.dataset.ticketNumber || '');
            }
            setMultiSelectValues(assignAssigneeSelect, parseAssignedIds(assignButton.dataset.assignedTo || '[]'));
            if (assignModalController) assignModalController.open();

            return;
        }

        const editButton = event.target.closest('.js-open-edit-modal');
        if (editButton) {
            populateAdminTicketEditModal({
                button: editButton,
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
                onStatusStateSync: syncEditStatusState,
            });
            if (editModalController) editModalController.open();

            return;
        }

        const revertButton = event.target.closest('.js-open-revert-modal');
        if (revertButton) {
            const ticketId = revertButton.dataset.ticketId;
            if (!ticketId || !revertForm) return;

            revertForm.action = statusRouteTemplate.replace('__TICKET__', ticketId);
            if (revertTicketText) {
                revertTicketText.textContent = `Ticket #${revertButton.dataset.ticketNumber || ''} will be reverted to In Progress.`;
            }
            if (revertConfirmCheckbox) {
                revertConfirmCheckbox.checked = false;
            }
            syncRevertSubmitState();
            if (revertModalController) revertModalController.open();
        }
    });

    if (editDeleteButton) {
        editDeleteButton.addEventListener('click', () => {
            const ticketId = editDeleteButton.dataset.ticketId;
            if (!ticketId || !deleteForm) return;

            deleteForm.action = deleteRouteTemplate.replace('__TICKET__', ticketId);
            if (deleteTicketText) {
                deleteTicketText.textContent = 'Ticket #' + (editDeleteButton.dataset.ticketNumber || '');
            }
            if (deleteConfirmCheckbox) {
                deleteConfirmCheckbox.checked = false;
            }
            syncDeleteConfirmState();
            if (editModalController) editModalController.close();
            if (deleteModalController) deleteModalController.open();
        });
    }

    if (bulkDeleteButton) {
        bulkDeleteButton.addEventListener('click', () => {
            if (bulkSelection.selectedTicketIds().length === 0) {
                return;
            }

            allowBulkDeleteSubmit = false;
            if (bulkDeleteConfirmCheckbox) {
                bulkDeleteConfirmCheckbox.checked = false;
            }
            syncBulkDeleteConfirmState();

            if (bulkDeleteConfirmModalController) {
                bulkDeleteConfirmModalController.open();
            }
        });
    }

    if (bulkDeleteConfirmSubmit && bulkDeleteForm) {
        bulkDeleteConfirmSubmit.addEventListener('click', () => {
            if (!bulkDeleteConfirmCheckbox || !bulkDeleteConfirmCheckbox.checked) {
                return;
            }

            allowBulkDeleteSubmit = true;
            if (bulkDeleteConfirmModalController) {
                bulkDeleteConfirmModalController.close();
            }
            bulkDeleteForm.requestSubmit();
        });
    }

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', (event) => {
            const selectedIds = bulkSelection.selectedTicketIds();
            if (selectedIds.length === 0) {
                event.preventDefault();
                return;
            }

            if (!allowBulkDeleteSubmit) {
                event.preventDefault();
                return;
            }

            allowBulkDeleteSubmit = false;
            populateHiddenSelectedIds(bulkSelectedIds, selectedIds);
        });
    }

    if (bulkAssignButton) {
        bulkAssignButton.addEventListener('click', () => {
            const selectedIds = bulkSelection.selectedTicketIds();
            if (selectedIds.length === 0) return;

            populateHiddenSelectedIds(bulkAssignSelectedIds, selectedIds);
            if (bulkAssignModalCount) bulkAssignModalCount.textContent = bulkSelectionCountText();
            setMultiSelectValues(bulkAssignSelect, []);
            if (bulkAssignModalController) bulkAssignModalController.open();
        });
    }

    if (bulkStatusButton) {
        bulkStatusButton.addEventListener('click', () => {
            const selectedIds = bulkSelection.selectedTicketIds();
            if (selectedIds.length === 0) return;

            populateHiddenSelectedIds(bulkStatusSelectedIds, selectedIds);
            if (bulkStatusModalCount) bulkStatusModalCount.textContent = bulkSelectionCountText();
            if (bulkStatusSelect) bulkStatusSelect.value = 'open';
            if (bulkStatusCloseReasonInput) bulkStatusCloseReasonInput.value = '';
            syncBulkStatusState();
            if (bulkStatusModalController) bulkStatusModalController.open();
        });
    }

    if (bulkPriorityButton) {
        bulkPriorityButton.addEventListener('click', () => {
            const selectedIds = bulkSelection.selectedTicketIds();
            if (selectedIds.length === 0) return;

            populateHiddenSelectedIds(bulkPrioritySelectedIds, selectedIds);
            if (bulkPriorityModalCount) bulkPriorityModalCount.textContent = bulkSelectionCountText();
            if (bulkPrioritySelect) bulkPrioritySelect.value = '';
            if (bulkPriorityModalController) bulkPriorityModalController.open();
        });
    }

    if (bulkMergeButton) {
        bulkMergeButton.addEventListener('click', () => {
            const selectedIds = bulkSelection.selectedTicketIds();
            if (selectedIds.length < 2) return;

            populateHiddenSelectedIds(bulkMergeSelectedIds, selectedIds);
            if (bulkMergeModalCount) bulkMergeModalCount.textContent = bulkSelectionCountText();
            if (bulkMergeConfirmCheckbox) bulkMergeConfirmCheckbox.checked = false;
            syncBulkMergeConfirmState();
            if (bulkMergeConfirmModalController) bulkMergeConfirmModalController.open();
        });
    }

    if (bulkMergeConfirmCheckbox) {
        bulkMergeConfirmCheckbox.addEventListener('change', syncBulkMergeConfirmState);
    }

    if (bulkStatusSelect) {
        bulkStatusSelect.addEventListener('change', syncBulkStatusState);
    }

    if (bulkMergeForm) {
        bulkMergeForm.addEventListener('submit', (event) => {
            if (!bulkMergeConfirmCheckbox || bulkMergeConfirmCheckbox.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    resultsController.bind();
    syncEditStatusState();
    syncRevertSubmitState();
    syncDeleteConfirmState();
    syncBulkDeleteConfirmState();
    syncBulkStatusState();
    syncBulkMergeConfirmState();
    bulkSelection.syncBulkSelection();
};

bootPage(initAdminTicketsIndexPage);
