import {
    buildAdminTicketFilterUrl,
    DEFAULT_STATUS_VIEW,
    normalizeAdminTicketResultsUrl,
    resetAdminTicketFilterFieldValue,
} from './admin-ticket-filters';

const TICKET_LIST_POLL_INTERVAL_MS = 10000;

export const createAdminTicketResultsController = ({
    pageRoot,
    filterForm,
    statusView,
    routeBase,
    filterFieldSelectors,
    getResultsContainer,
    hasOpenModal,
    onResultsUpdated,
}) => {
    let snapshotToken = pageRoot.dataset.snapshotToken || '';
    let pageSnapshotToken = pageRoot.dataset.pageSnapshotToken || '';
    let filterSubmitTimeout = null;
    let pollTimeoutId = null;
    let activeRequestId = 0;
    let activeRequestController = null;
    let isResultsLoading = false;
    let isSyncingFilters = false;
    let hasLoggedPollError = false;

    const relativePathForUrl = (url) => `${url.pathname}${url.search}`;

    const updateReturnPathInputs = (path) => {
        document.querySelectorAll('input[name="return_to"]').forEach((input) => {
            input.value = path;
        });
    };

    const clearTicketListPolling = () => {
        if (pollTimeoutId) {
            window.clearTimeout(pollTimeoutId);
            pollTimeoutId = null;
        }
    };

    const scheduleTicketListPolling = (delay = TICKET_LIST_POLL_INTERVAL_MS) => {
        clearTicketListPolling();

        if (!snapshotToken) {
            return;
        }

        pollTimeoutId = window.setTimeout(() => {
            void pollTicketListSnapshot();
        }, delay);
    };

    const syncFilterFormFromUrl = (url) => {
        if (!filterForm) return;

        const params = url.searchParams;
        isSyncingFilters = true;

        filterForm.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            if (field.disabled) return;

            const tagName = field.tagName.toLowerCase();
            const type = (field.getAttribute('type') || '').toLowerCase();
            if (tagName === 'button' || ['button', 'submit', 'reset', 'checkbox', 'radio', 'file'].includes(type)) {
                return;
            }

            const nextValue = resetAdminTicketFilterFieldValue(field.name, params);
            const valueChanged = field.value !== nextValue;
            field.value = nextValue;

            if (valueChanged && tagName === 'select') {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        if (statusView) {
            statusView.value = params.get('status') ?? DEFAULT_STATUS_VIEW;
        }

        isSyncingFilters = false;
    };

    const applyLoadingState = () => {
        const resultsContainer = getResultsContainer();
        if (!resultsContainer) return;

        resultsContainer.classList.toggle('opacity-60', isResultsLoading);
        resultsContainer.classList.toggle('transition-opacity', true);
        resultsContainer.setAttribute('aria-busy', isResultsLoading ? 'true' : 'false');
    };

    const buildFilterUrl = () => {
        const selectedMonth = filterForm?.querySelector('select[name="month"]')?.value?.trim() || '';
        const formEntries = filterForm ? Array.from(new FormData(filterForm).entries()) : [];

        return buildAdminTicketFilterUrl({
            routeBase,
            formEntries,
            selectedMonth,
            statusValue: statusView ? statusView.value : DEFAULT_STATUS_VIEW,
            origin: window.location.origin,
        });
    };

    const loadTicketResults = async (url, { history = 'replace' } = {}) => {
        const normalizedUrl = normalizeAdminTicketResultsUrl(url, window.location.origin);
        const requestUrl = new URL(normalizedUrl.toString());
        requestUrl.searchParams.set('partial', '1');

        const requestId = ++activeRequestId;
        if (activeRequestController) {
            activeRequestController.abort();
        }

        activeRequestController = new AbortController();
        isResultsLoading = true;
        applyLoadingState();

        try {
            const response = await fetch(requestUrl.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: activeRequestController.signal,
            });

            if (!response.ok) {
                throw new Error(`Ticket results request failed with status ${response.status}`);
            }

            const payload = await response.json();
            if (activeRequestId !== requestId) {
                return;
            }

            const resultsContainer = getResultsContainer();
            if (!resultsContainer || typeof payload?.html !== 'string') {
                throw new Error('Ticket results payload was incomplete.');
            }

            resultsContainer.outerHTML = payload.html;
            snapshotToken = payload.token || '';
            pageSnapshotToken = payload.page_token || '';
            pageRoot.dataset.snapshotToken = snapshotToken;
            pageRoot.dataset.pageSnapshotToken = pageSnapshotToken;

            if (history === 'push') {
                window.history.pushState({ adminTickets: true }, '', relativePathForUrl(normalizedUrl));
            } else if (history === 'replace') {
                window.history.replaceState({ adminTickets: true }, '', relativePathForUrl(normalizedUrl));
            }

            syncFilterFormFromUrl(normalizedUrl);
            updateReturnPathInputs(relativePathForUrl(normalizedUrl));
            onResultsUpdated();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            window.location.assign(relativePathForUrl(normalizedUrl));
        } finally {
            if (activeRequestId === requestId) {
                activeRequestController = null;
                isResultsLoading = false;
                applyLoadingState();
                scheduleTicketListPolling();
            }
        }
    };

    const submitFilters = () => {
        if (!filterForm || isSyncingFilters) return;

        if (filterSubmitTimeout) {
            window.clearTimeout(filterSubmitTimeout);
            filterSubmitTimeout = null;
        }

        const targetUrl = buildFilterUrl();
        targetUrl.searchParams.delete('page');
        void loadTicketResults(targetUrl, { history: 'replace' });
    };

    const pollTicketListSnapshot = async () => {
        if (!snapshotToken) {
            return;
        }

        if (document.hidden || hasOpenModal() || isResultsLoading) {
            scheduleTicketListPolling();

            return;
        }

        const heartbeatUrl = normalizeAdminTicketResultsUrl(window.location.href, window.location.origin);
        heartbeatUrl.searchParams.set('heartbeat', '1');

        try {
            const response = await fetch(heartbeatUrl.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            const nextSnapshotToken = payload.token || '';
            const nextPageSnapshotToken = payload.page_token || '';
            if (nextSnapshotToken !== snapshotToken || nextPageSnapshotToken !== pageSnapshotToken) {
                await loadTicketResults(window.location.href, { history: 'none' });

                return;
            }

            snapshotToken = nextSnapshotToken;
            pageSnapshotToken = nextPageSnapshotToken;
            pageRoot.dataset.snapshotToken = snapshotToken;
            pageRoot.dataset.pageSnapshotToken = pageSnapshotToken;
        } catch (error) {
            if (!hasLoggedPollError && typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('Ticket list heartbeat check failed.', error);
                hasLoggedPollError = true;
            }
        } finally {
            scheduleTicketListPolling();
        }
    };

    const bind = () => {
        if (filterForm) {
            filterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                submitFilters();
            });

            filterFieldSelectors.forEach((selector) => {
                const field = filterForm.querySelector(selector);
                if (!field) return;

                field.addEventListener('change', submitFilters);
            });

            const searchInput = filterForm.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    if (filterSubmitTimeout) {
                        window.clearTimeout(filterSubmitTimeout);
                    }

                    filterSubmitTimeout = window.setTimeout(() => {
                        submitFilters();
                    }, 350);
                });
            }
        }

        if (statusView) {
            statusView.addEventListener('change', () => {
                if (isSyncingFilters) return;

                const targetUrl = buildFilterUrl();
                targetUrl.searchParams.delete('page');
                void loadTicketResults(targetUrl, { history: 'replace' });
            });
        }

        window.addEventListener('popstate', () => {
            syncFilterFormFromUrl(normalizeAdminTicketResultsUrl(window.location.href, window.location.origin));
            void loadTicketResults(window.location.href, { history: 'none' });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearTicketListPolling();

                return;
            }

            scheduleTicketListPolling(0);
        });

        window.addEventListener('focus', () => {
            if (!document.hidden) {
                scheduleTicketListPolling(0);
            }
        });

        updateReturnPathInputs(relativePathForUrl(normalizeAdminTicketResultsUrl(window.location.href, window.location.origin)));
        syncFilterFormFromUrl(normalizeAdminTicketResultsUrl(window.location.href, window.location.origin));
        scheduleTicketListPolling();
    };

    return {
        bind,
        loadTicketResults,
    };
};
