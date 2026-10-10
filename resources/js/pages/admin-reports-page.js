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

        const sourceChartHeight = 192;
        const enlargedChartHeight = 416;
        const barScale = enlargedChartHeight / sourceChartHeight;

        const clearModalContent = () => {
            modalContent.innerHTML = '';
        };

        const modalController = window.ModalKit.bind(modal, {
            onClose: clearModalContent,
        });

        if (!modalController) return;

        const tuneModalChartLayout = (clone) => {
            const chartArea = clone.querySelector('.h-48');
            if (chartArea) {
                chartArea.classList.remove('h-48', 'overflow-x-auto');
                chartArea.classList.add('h-[26rem]', 'overflow-hidden');
            }

            const pieCharts = clone.querySelectorAll('.js-total-pie-chart');
            pieCharts.forEach((pie) => {
                pie.classList.remove('h-40', 'w-40', 'h-44', 'w-44');
                pie.classList.add('h-72', 'w-72');
            });

            const charts = clone.querySelectorAll('.js-volume-bars');
            charts.forEach((chart) => {
                chart.classList.remove('min-w-[760px]', 'min-w-[720px]', 'pb-6');
                chart.classList.add('w-full', 'min-w-0', 'justify-between', 'pb-10');

                const groups = chart.querySelectorAll('.group');
                groups.forEach((group) => {
                    group.classList.remove('min-w-[16px]', 'min-w-[34px]');
                    group.classList.add('flex-1', 'min-w-0');
                });

                const bars = chart.querySelectorAll('.js-volume-bar');
                bars.forEach((bar) => {
                    const originalHeight = Number.parseFloat(bar.style.height || '0');
                    if (!Number.isFinite(originalHeight) || originalHeight <= 0) {
                        return;
                    }

                    const scaledHeight = Math.round(originalHeight * barScale);
                    bar.style.height = `${Math.min(340, Math.max(8, scaledHeight))}px`;
                });

                const labels = chart.querySelectorAll('span.text-\\[10px\\]');
                labels.forEach((label) => {
                    label.classList.add('text-xs');
                });
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

    const bindClearLink = () => {
        shell.querySelectorAll('[data-admin-reports-clear]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                void loadReports(link.href, { history: 'replace' });
            });
        });
    };

    bindVolumeModal();
    bindFilterForms();
    bindAutoSubmitControls();
    bindClearLink();

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
