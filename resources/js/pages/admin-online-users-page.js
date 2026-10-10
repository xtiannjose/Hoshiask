import { bootPage } from './shared/boot-page';

const REFRESH_INTERVAL_MS = 20000;

// 423: the monitoring lock expired; 401/419: the session itself ended. Reloading lets the
// server send the browser to the unlock or sign-in page.
const RELOAD_STATUSES = new Set([401, 419, 423]);

const initAdminOnlineUsersPage = () => {
    const pageRoot = document.querySelector('[data-admin-online-users-page]');
    const liveRegion = pageRoot ? pageRoot.querySelector('.js-online-users-live') : null;
    if (!pageRoot || !liveRegion || !pageRoot.dataset.liveUrl) return;

    const liveUrl = new URL(pageRoot.dataset.liveUrl, window.location.origin);
    liveUrl.search = window.location.search;

    let refreshTimeoutId = null;
    let refreshing = false;
    let hasLoggedError = false;

    const clearScheduledRefresh = () => {
        if (refreshTimeoutId !== null) {
            window.clearTimeout(refreshTimeoutId);
            refreshTimeoutId = null;
        }
    };

    const scheduleRefresh = () => {
        clearScheduledRefresh();
        if (document.hidden) return;

        refreshTimeoutId = window.setTimeout(refresh, REFRESH_INTERVAL_MS);
    };

    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;

        try {
            const response = await fetch(liveUrl.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (RELOAD_STATUSES.has(response.status)) {
                window.location.reload();
                return;
            }
            if (!response.ok) return;

            const payload = await response.json();
            // Replacing the markup would drop keyboard focus from a row button; wait for the next cycle.
            if (liveRegion.contains(document.activeElement)) return;

            if (payload && typeof payload.html === 'string') {
                liveRegion.innerHTML = payload.html;
            }
        } catch (error) {
            if (!hasLoggedError && typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('Online users refresh failed.', error);
                hasLoggedError = true;
            }
        } finally {
            refreshing = false;
            scheduleRefresh();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearScheduledRefresh();
            return;
        }

        void refresh();
    });

    const signOutModal = document.getElementById('onlineUserSignOutModal');
    const signOutForm = signOutModal ? signOutModal.querySelector('.js-sign-out-form') : null;
    const signOutUserName = signOutModal ? signOutModal.querySelector('.js-sign-out-user-name') : null;
    const signOutModalController = window.ModalKit && signOutModal ? window.ModalKit.bind(signOutModal) : null;

    // Rows are replaced on every refresh, so listen on the page root. Without the modal the
    // button falls back to submitting its own form directly.
    pageRoot.addEventListener('click', (event) => {
        const trigger = event.target.closest('.js-online-user-sign-out');
        if (!trigger || !trigger.form || !signOutModalController || !signOutForm) return;

        event.preventDefault();
        signOutForm.action = trigger.form.action;
        if (signOutUserName) signOutUserName.textContent = trigger.dataset.userName || 'This user';
        signOutModalController.open();
    });

    scheduleRefresh();
};

bootPage(initAdminOnlineUsersPage);
