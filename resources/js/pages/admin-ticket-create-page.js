import { bootPage } from './shared/boot-page';
import { normalizeLeadingUppercase } from '../lib/html-utils';
import {
    describeAccountMatches,
    describeListedAccounts,
    fetchRequesterAccounts,
    mergeSearchResults,
} from './shared/requester-account-search';

const SEARCH_DELAY_MS = 250;

const initAdminTicketCreatePage = () => {
    const pageRoot = document.querySelector('[data-admin-ticket-create-page]');
    if (!pageRoot) return;

    const form = document.getElementById('admin-ticket-create-form');
    const requesterSelect = pageRoot.querySelector('[data-client-account-select]');
    const requesterSearchInput = pageRoot.querySelector('[data-requester-search]');
    const requesterSearchUrl = String(pageRoot.dataset.requesterSearchUrl || '').trim();
    const ticketTypeInputs = Array.from(pageRoot.querySelectorAll('[data-ticket-type-input]'))
        .filter((input) => input instanceof HTMLInputElement);
    const requesterAccountsElement = pageRoot.querySelector('[data-requester-accounts]');
    const requesterAccountTotalsElement = pageRoot.querySelector('[data-requester-account-totals]');
    const requesterAccountLabel = pageRoot.querySelector('[data-requester-account-label]');
    const requesterAccountHelp = pageRoot.querySelector('[data-requester-account-help]');
    const requesterSnapshotNote = pageRoot.querySelector('[data-requester-snapshot-note]');
    const staffAssignmentWrap = pageRoot.querySelector('[data-staff-assignment-wrap]');
    const staffAssignmentSelect = pageRoot.querySelector('[data-staff-assignment-select]');
    const descriptionCopy = pageRoot.querySelector('[data-ticket-create-description]');
    const nameInput = document.getElementById('name');
    const contactInput = document.getElementById('contact_number');
    const emailInput = document.getElementById('email');
    if (!form) return;

    const currentSupportUserId = String(pageRoot.dataset.currentSupportUserId || '').trim();
    let accountOptions = [];
    if (requesterAccountsElement instanceof HTMLScriptElement) {
        try {
            const parsedAccounts = JSON.parse(requesterAccountsElement.textContent || '{}');
            accountOptions = ['client', 'support'].flatMap((group) => (
                Array.isArray(parsedAccounts[group]) ? parsedAccounts[group] : []
            ));
        } catch {
            accountOptions = [];
        }
    }

    // The page lists only the first accounts of each kind; the search box asks the server for the rest.
    const initialAccountOptions = [...accountOptions];
    let accountTotals = { client: 0, support: 0 };
    if (requesterAccountTotalsElement instanceof HTMLScriptElement) {
        try {
            accountTotals = { ...accountTotals, ...JSON.parse(requesterAccountTotalsElement.textContent || '{}') };
        } catch {
            // Without totals the page simply does not mention that the list is partial.
        }
    }
    let searchTimerId = 0;
    let searchController = null;

    const fields = [
        document.getElementById('province'),
        document.getElementById('municipality'),
        document.getElementById('subject'),
    ].filter(Boolean);

    const normalizeField = (field) => {
        field.value = normalizeLeadingUppercase(field.value);
    };

    const activeTicketType = () => {
        const checkedInput = ticketTypeInputs.find((input) => input.checked);
        return checkedInput ? checkedInput.value : 'external';
    };

    const activeRequesterGroup = () => (
        activeTicketType() === 'internal'
            ? 'support'
            : 'client'
    );

    const selectedAccountRecord = () => {
        if (!(requesterSelect instanceof HTMLSelectElement)) return null;
        const selectedValue = String(requesterSelect.value || '').trim();
        return accountOptions.find((option) => option.value === selectedValue) || null;
    };

    const updateRequesterCopy = () => {
        const isInternal = activeRequesterGroup() === 'support';

        if (requesterAccountLabel) {
            requesterAccountLabel.innerHTML = `${isInternal ? 'Staff Requester Account' : 'Client Requester Account'} <span class="text-red-600">*</span>`;
        }

        if (requesterAccountHelp) {
            const group = isInternal ? 'support' : 'client';
            const listed = initialAccountOptions.filter((option) => option.group === group).length;

            requesterAccountHelp.textContent = (isInternal
                ? 'Staff tickets show only active staff requester accounts.'
                : 'Client tickets show only active client requester accounts.')
                + describeListedAccounts({ shown: listed, total: Number(accountTotals[group]) || 0 });
        }

        if (requesterSnapshotNote) {
            requesterSnapshotNote.textContent = isInternal
                ? 'This staff ticket will be linked to the selected staff account, while the contact details below capture the request snapshot used for follow-up.'
                : 'This ticket will be linked to the selected client account, while the contact details below capture the request snapshot used for support follow-up.';
        }

        if (descriptionCopy) {
            descriptionCopy.textContent = isInternal
                ? 'Use this form to log an internal staff-to-staff support request.'
                : 'Use this form when a client contacts support directly and the ticket needs to be logged by a support user.';
        }

        if (nameInput) {
            nameInput.placeholder = isInternal ? 'Staff full name' : 'Client full name';
        }

        if (emailInput) {
            emailInput.placeholder = isInternal ? 'staff@example.com' : 'client@example.com';
        }
    };

    const updateAssignmentVisibility = () => {
        const isInternal = activeRequesterGroup() === 'support';

        if (staffAssignmentWrap) {
            staffAssignmentWrap.classList.toggle('hidden', !isInternal);
        }

        if (staffAssignmentSelect instanceof HTMLSelectElement) {
            staffAssignmentSelect.disabled = !isInternal;
        }
    };

    const rebuildRequesterOptions = ({ forceSelection = false, autoSelect = true } = {}) => {
        if (!(requesterSelect instanceof HTMLSelectElement)) return;

        const isInternal = activeRequesterGroup() === 'support';
        const group = isInternal ? 'support' : 'client';
        const previousValue = String(requesterSelect.value || '').trim();
        const availableOptions = accountOptions.filter((option) => option.group === group);
        let nextValue = previousValue;

        if (!availableOptions.some((option) => option.value === nextValue) || forceSelection) {
            nextValue = '';
        }

        if (nextValue === '' && isInternal && currentSupportUserId !== '') {
            nextValue = availableOptions.some((option) => option.value === currentSupportUserId)
                ? currentSupportUserId
                : '';
        }

        // A search that finds one account must not choose it: the person has not picked it yet.
        if (autoSelect && nextValue === '' && availableOptions.length === 1) {
            nextValue = availableOptions[0].value;
        }

        requesterSelect.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = isInternal
            ? 'Select a staff requester account'
            : 'Select a client requester account';
        requesterSelect.appendChild(placeholderOption);

        availableOptions.forEach((account) => {
            const option = document.createElement('option');
            option.value = account.value;
            option.textContent = account.label;
            option.dataset.name = account.name;
            option.dataset.email = account.email;
            option.dataset.phone = account.phone;
            option.dataset.accountGroup = account.group;
            option.dataset.role = account.role;
            if (account.value === nextValue) {
                option.selected = true;
            }
            requesterSelect.appendChild(option);
        });

        requesterSelect.value = nextValue;
    };

    const syncSelectedClientDetails = ({ force = false } = {}) => {
        if (!(requesterSelect instanceof HTMLSelectElement)) return;

        const option = selectedAccountRecord();
        if (!option || option.value === '') return;

        const fieldPairs = [
            [nameInput, option.name || ''],
            [contactInput, option.phone || ''],
            [emailInput, option.email || ''],
        ];

        fieldPairs.forEach(([field, value]) => {
            if (!field) return;
            if (!force && String(field.value || '').trim() !== '') return;
            field.value = value;
        });
    };

    const resetRequesterSearch = () => {
        window.clearTimeout(searchTimerId);
        if (searchController) {
            searchController.abort();
            searchController = null;
        }
        accountOptions = [...initialAccountOptions];
        if (requesterSearchInput instanceof HTMLInputElement) {
            requesterSearchInput.value = '';
        }
    };

    const runRequesterSearch = async () => {
        if (!(requesterSearchInput instanceof HTMLInputElement) || requesterSearchUrl === '') return;

        const group = activeRequesterGroup();
        const query = requesterSearchInput.value.trim();
        const selectedValue = requesterSelect instanceof HTMLSelectElement
            ? String(requesterSelect.value || '').trim()
            : '';

        if (searchController) {
            searchController.abort();
            searchController = null;
        }

        if (query === '') {
            // Back to the accounts listed with the page.
            accountOptions = mergeSearchResults(
                accountOptions,
                group,
                initialAccountOptions.filter((option) => option.group === group),
                selectedValue,
            );
            rebuildRequesterOptions({ autoSelect: false });
            updateRequesterCopy();
            return;
        }

        const controller = new AbortController();
        searchController = controller;
        if (requesterAccountHelp) {
            requesterAccountHelp.textContent = describeAccountMatches({ searching: true });
        }

        try {
            const { accounts, total } = await fetchRequesterAccounts({
                url: requesterSearchUrl,
                group,
                query,
                signal: controller.signal,
            });

            // The person kept typing or switched the ticket type while this answer was on its way.
            if (controller.signal.aborted) return;

            accountOptions = mergeSearchResults(accountOptions, group, accounts, selectedValue);
            rebuildRequesterOptions({ autoSelect: false });
            if (requesterAccountHelp) {
                requesterAccountHelp.textContent = describeAccountMatches({ shown: accounts.length, total });
            }
        } catch {
            if (controller.signal.aborted) return;

            if (requesterAccountHelp) {
                requesterAccountHelp.textContent = 'The account search is unavailable right now. Pick from the list, or try again.';
            }
        } finally {
            if (searchController === controller) {
                searchController = null;
            }
        }
    };

    if (requesterSearchInput instanceof HTMLInputElement) {
        requesterSearchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimerId);
            searchTimerId = window.setTimeout(runRequesterSearch, SEARCH_DELAY_MS);
        });

        // Enter would otherwise send the whole ticket form.
        requesterSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(searchTimerId);
                runRequesterSearch();
            }
        });
    }

    fields.forEach((field) => {
        field.addEventListener('blur', () => {
            normalizeField(field);
        });
    });

    if (requesterSelect instanceof HTMLSelectElement) {
        requesterSelect.addEventListener('change', () => {
            syncSelectedClientDetails({ force: true });
        });

        rebuildRequesterOptions();
        updateRequesterCopy();
        updateAssignmentVisibility();
        syncSelectedClientDetails();
    }

    if (ticketTypeInputs.length > 0) {
        ticketTypeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                resetRequesterSearch();
                rebuildRequesterOptions({ forceSelection: true });
                updateRequesterCopy();
                updateAssignmentVisibility();
                syncSelectedClientDetails({ force: true });
            });
        });
    }

    form.addEventListener('submit', () => {
        fields.forEach(normalizeField);
    });
};

bootPage(initAdminTicketCreatePage);
