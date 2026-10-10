export const createDashboardHeartbeatPoller = ({
    pageRoot,
    initialToken,
    heartbeatUrl,
    pollDelayMs = 30000,
    resumeDelayMs = 5000,
    errorLabel = 'Dashboard heartbeat check failed.',
}) => {
    let activeToken = initialToken;
    let checking = false;
    let staleDetected = false;
    let pollTimeoutId = null;
    let hasLoggedError = false;

    const showRefreshPrompt = function () {
        if (document.querySelector('.js-live-refresh-prompt')) return;

        const prompt = document.createElement('div');
        prompt.className = 'js-live-refresh-prompt mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800';
        prompt.innerHTML = 'New ticket updates are available. <button type="button" class="ml-2 font-semibold underline js-live-refresh-now">Refresh now</button>';
        pageRoot.prepend(prompt);

        const refreshButton = prompt.querySelector('.js-live-refresh-now');
        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                window.location.reload();
            });
        }
    };

    const scheduleNextPoll = function (delay = pollDelayMs) {
        if (pollTimeoutId !== null) {
            window.clearTimeout(pollTimeoutId);
        }

        if (staleDetected || document.hidden) {
            pollTimeoutId = null;

            return;
        }

        pollTimeoutId = window.setTimeout(pollSnapshot, delay);
    };

    const pollSnapshot = async function () {
        if (checking || document.hidden || staleDetected) return;
        checking = true;

        try {
            const response = await fetch(heartbeatUrl.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== activeToken) {
                staleDetected = true;
                showRefreshPrompt();
                scheduleNextPoll();

                return;
            }

            activeToken = payload.token;
        } catch (error) {
            if (!hasLoggedError && typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn(errorLabel, error);
                hasLoggedError = true;
            }
        } finally {
            checking = false;
            scheduleNextPoll();
        }
    };

    const start = function () {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden || staleDetected) {
                scheduleNextPoll();

                return;
            }

            scheduleNextPoll(resumeDelayMs);
        });

        scheduleNextPoll();
    };

    return { start };
};
