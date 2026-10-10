// Search for the requester picker on the new-ticket form: the page lists the first accounts and the server
// searches the rest (see TicketRequesterAccountController).

/**
 * Keeps every other group's options, and replaces this group's with the search results plus the account that is
 * currently selected, so a search never drops the person already chosen.
 */
export const mergeSearchResults = (accountOptions, group, results, selectedValue = '') => {
    const kept = accountOptions.filter((option) => option.group !== group
        || (selectedValue !== '' && option.value === selectedValue));
    const keptValues = new Set(kept.map((option) => option.value));

    return [...kept, ...results.filter((option) => !keptValues.has(option.value))];
};

/** The line under the picker that says how many accounts the search found. */
export const describeAccountMatches = ({ searching = false, shown = 0, total = 0 } = {}) => {
    if (searching) return 'Searching…';
    if (total === 0) return 'No matching accounts. Try another name, email, department or phone.';
    if (total > shown) return `Showing the first ${shown} of ${total} matching accounts. Type more to narrow the list.`;

    return `${total} matching ${total === 1 ? 'account' : 'accounts'}.`;
};

/**
 * The sentence added while the search box is empty: only needed when the page lists part of the accounts. It says
 * "showing", not "the first": the list may also carry the account chosen before an error or the signed-in staff member.
 */
export const describeListedAccounts = ({ shown = 0, total = 0 } = {}) => (
    total > shown
        ? ` Showing ${shown} of ${total}; use the search box to find the others.`
        : ''
);

export const fetchRequesterAccounts = async ({ url, group, query, signal }) => {
    const target = new URL(url, window.location.origin);
    target.searchParams.set('group', group);
    target.searchParams.set('q', query);

    const response = await fetch(target.toString(), {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error(`Account search failed with status ${response.status}.`);
    }

    const payload = await response.json();
    const total = Number(payload && payload.total);

    return {
        accounts: Array.isArray(payload && payload.accounts) ? payload.accounts : [],
        total: Number.isFinite(total) ? total : 0,
    };
};
