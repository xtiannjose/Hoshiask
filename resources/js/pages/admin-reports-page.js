import { bootPage } from './shared/boot-page';

let activeRequestId = 0;
let activeRequestController = null;
let reportsPopstateBound = false;
let reportsUiState = {
    slaOverviewOpen: false,
};

const relativePathForUrl = (url) => `${url.pathname}${url.search}`;

const normalizeReportsUrl = (url, routeBase) => {
    const normalized = new URL(url, window.location.origin);
    const routeUrl = new URL(routeBase || normalized.pathname, window.location.origin);

    normalized.pathname = routeUrl.pathname;
    normalized.searchParams.delete('partial');

    return normalized;
};

const buildRequestUrlFromForm = (form, routeBase) => {
    const targetUrl = new URL(routeBase, window.location.origin);
    const formData = new FormData(form);

    for (const [key, rawValue] of formData.entries()) {
        const value = String(rawValue).trim();
        if (value === '') {
            continue;
        }

        targetUrl.searchParams.append(key, value);
    }

    return targetUrl;
};

const initAdminReportsPage = () => {
    const shell = document.querySelector('[data-admin-reports-shell]');
    const pageRoot = shell?.querySelector('[data-admin-reports-page]');
    if (!shell || !pageRoot) return;

    const routeBase = pageRoot.dataset.routeBase || window.location.pathname;
    const slaDetails = shell.querySelector('[data-reports-sla-details]');

    if (slaDetails && reportsUiState.slaOverviewOpen) {
        slaDetails.open = true;
    }

    const bindVolumeModal = () => {
        if (!window.ModalKit) return;

        const modal = shell.querySelector('#volume-chart-modal');
        const modalTitle = shell.querySelector('#volume-chart-modal-title');
        const modalContent = shell.querySelector('#volume-chart-modal-content');
        const openButtons = shell.querySelectorAll('.js-open-volume-chart');

        if (!modal || !modalTitle || !modalContent || openButtons.length === 0) {
            return;
        }

        const clearModalContent = () => {
            modalContent.innerHTML = '';
        };

        const modalController = window.ModalKit.bind(modal, {
            onClose: clearModalContent,
        });

        if (!modalController) return;

        const tuneModalChartLayout = (clone) => {
            clone.querySelectorAll('.js-total-pie-chart').forEach((pie) => {
                pie.classList.remove('h-40', 'w-40', 'h-44', 'w-44');
                pie.classList.add('h-72', 'w-72');
            });
        };

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const sourceId = button.dataset.chartSource || '';
                const source = shell.querySelector(`#${sourceId}`);
                if (!source) {
                    return;
                }

                modalTitle.textContent = button.dataset.chartTitle || 'Volume Chart';
                clearModalContent();

                const clone = source.cloneNode(true);
                clone.removeAttribute('id');
                tuneModalChartLayout(clone);

                modalContent.appendChild(clone);
                modalController.open();
            });
        });
    };

    const replaceReportsShell = (html) => {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const nextShell = template.content.querySelector('[data-admin-reports-shell]');

        if (!nextShell) {
            throw new Error('Reports partial payload was incomplete.');
        }

        const currentSlaDetails = shell.querySelector('[data-reports-sla-details]');
        reportsUiState.slaOverviewOpen = Boolean(currentSlaDetails?.open);

        shell.replaceWith(nextShell);
        initAdminReportsPage();
    };

    const loadReports = async (url, { history = 'replace' } = {}) => {
        const normalizedUrl = normalizeReportsUrl(url, routeBase);
        const requestUrl = new URL(normalizedUrl.toString());
        requestUrl.searchParams.set('partial', '1');

        const requestId = ++activeRequestId;
        if (activeRequestController) {
            activeRequestController.abort();
        }

        activeRequestController = new AbortController();

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
                throw new Error(`Reports request failed with status ${response.status}`);
            }

            const payload = await response.json();
            if (activeRequestId !== requestId) {
                return;
            }

            if (typeof payload?.html !== 'string') {
                throw new Error('Reports partial payload was incomplete.');
            }

            replaceReportsShell(payload.html);

            if (history === 'push') {
                window.history.pushState({ adminReports: true }, '', relativePathForUrl(normalizedUrl));
            } else if (history === 'replace') {
                window.history.replaceState({ adminReports: true }, '', relativePathForUrl(normalizedUrl));
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            window.location.assign(relativePathForUrl(normalizeReportsUrl(url, routeBase)));
        } finally {
            if (activeRequestId === requestId) {
                activeRequestController = null;
            }
        }
    };

    const bindFilterForms = () => {
        const filterForms = shell.querySelectorAll('form[data-reports-filter-form]');
        filterForms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const targetUrl = buildRequestUrlFromForm(form, routeBase);
                void loadReports(targetUrl, { history: 'replace' });
            });
        });
    };

    const bindAutoSubmitControls = () => {
        shell.querySelectorAll('select[data-auto-submit-change]').forEach((input) => {
            input.addEventListener('change', () => {
                const form = input.closest('form[data-reports-filter-form]');
                if (!form) {
                    return;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();

                    return;
                }

                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            });
        });
    };

    bindVolumeModal();
    bindFilterForms();
    bindAutoSubmitControls();

    if (slaDetails) {
        reportsUiState.slaOverviewOpen = slaDetails.open;
        slaDetails.addEventListener('toggle', () => {
            reportsUiState.slaOverviewOpen = slaDetails.open;
        });
    }

    if (!reportsPopstateBound) {
        window.addEventListener('popstate', () => {
            if (!document.querySelector('[data-admin-reports-page]')) {
                return;
            }

            void loadReports(window.location.href, { history: 'none' });
        });
        reportsPopstateBound = true;
    }
};

bootPage(initAdminReportsPage, { defer: false });
