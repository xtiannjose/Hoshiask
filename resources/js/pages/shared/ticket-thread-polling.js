export const createReplyPolling = ({
    repliesUrl,
    getCursor,
    setCursor,
    syncReplies,
    queueSeenSync,
    intervalMs,
}) => {
    const baseIntervalMs = Number.isFinite(Number(intervalMs)) && Number(intervalMs) > 0
        ? Number(intervalMs)
        : 5000;
    const maxIntervalMs = Math.max(baseIntervalMs, 30000);
    let isPolling = false;
    let timeoutId = 0;
    let hasLoggedError = false;
    let consecutiveIdlePolls = 0;

    const resolvePollUrl = () => {
        if (typeof repliesUrl === 'function') {
            const nextUrl = repliesUrl(getCursor());
            return typeof nextUrl === 'string' ? nextUrl.trim() : '';
        }

        return typeof repliesUrl === 'string' ? repliesUrl.trim() : '';
    };

    const stop = () => {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
            timeoutId = 0;
        }
    };

    const resetBackoff = () => {
        consecutiveIdlePolls = 0;
    };

    const currentIntervalMs = () => Math.min(
        baseIntervalMs * (2 ** Math.min(consecutiveIdlePolls, 3)),
        maxIntervalMs,
    );

    const schedule = () => {
        stop();
        if (resolvePollUrl() === '' || document.visibilityState !== 'visible') return;

        timeoutId = window.setTimeout(() => {
            poll();
        }, currentIntervalMs());
    };

    const poll = async () => {
        const pollUrl = resolvePollUrl();
        if (pollUrl === '' || isPolling || document.visibilityState !== 'visible') return;
        isPolling = true;

        try {
            const response = await fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            if (typeof data.cursor === 'string' && data.cursor !== '') {
                setCursor(data.cursor);
            }

            const replies = Array.isArray(data && data.replies) ? data.replies : [];
            syncReplies(replies);
            queueSeenSync();
            hasLoggedError = false;
            if (replies.length > 0) {
                resetBackoff();
            } else {
                consecutiveIdlePolls += 1;
            }
        } catch (error) {
            consecutiveIdlePolls += 1;
            if (!hasLoggedError && typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('Reply polling failed.', error);
                hasLoggedError = true;
            }
        } finally {
            isPolling = false;
            schedule();
        }
    };

    const bind = () => {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                resetBackoff();
                queueSeenSync();
                poll();
                return;
            }

            stop();
        });

        window.addEventListener('focus', () => {
            resetBackoff();
            queueSeenSync();
            if (document.visibilityState === 'visible') {
                poll();
            }
        });
    };

    return {
        bind,
        poll,
        schedule,
        stop,
    };
};
