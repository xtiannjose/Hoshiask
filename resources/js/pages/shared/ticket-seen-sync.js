const defaultParseIsoMs = (value) => {
    const parsed = Date.parse(value || '');
    return Number.isNaN(parsed) ? 0 : parsed;
};

export const createTicketSeenSync = ({
    seenUrl,
    csrfToken,
    ticketId,
    getLatestActivityIso,
    parseIsoMs = defaultParseIsoMs,
}) => {
    let isSyncingSeen = false;
    let queuedSeenAtIso = '';
    let latestSeenAtMs = 0;

    const queueMoreRecentSeenAt = (activityAtIso) => {
        const queuedMs = parseIsoMs(queuedSeenAtIso);
        const activityMs = parseIsoMs(activityAtIso);
        if (activityMs > queuedMs) {
            queuedSeenAtIso = activityAtIso;
        }
    };

    const flushSeenSync = async () => {
        if (!seenUrl || isSyncingSeen || !queuedSeenAtIso || document.visibilityState === 'hidden') {
            return;
        }

        const activityAt = queuedSeenAtIso;
        queuedSeenAtIso = '';
        isSyncingSeen = true;

        try {
            const response = await fetch(seenUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ activity_at: activityAt }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                queueMoreRecentSeenAt(activityAt);
                return;
            }

            const payload = await response.json().catch(function () { return {}; });
            const acknowledgedIso = (payload && payload.seen_at) ? payload.seen_at : activityAt;
            latestSeenAtMs = Math.max(latestSeenAtMs, parseIsoMs(acknowledgedIso));

            window.dispatchEvent(new CustomEvent('ticket-notification-seen', {
                detail: {
                    ticketId: Number(ticketId || 0),
                    seenAt: acknowledgedIso,
                },
            }));
        } catch (error) {
            queueMoreRecentSeenAt(activityAt);
        } finally {
            isSyncingSeen = false;
            if (queuedSeenAtIso) {
                flushSeenSync();
            }
        }
    };

    const queueSeenSync = (activityAtIso) => {
        if (!seenUrl || document.visibilityState === 'hidden') {
            return;
        }

        const candidateIso = activityAtIso || (typeof getLatestActivityIso === 'function'
            ? getLatestActivityIso()
            : '');
        const candidateMs = parseIsoMs(candidateIso);
        if (!candidateMs || candidateMs <= latestSeenAtMs) {
            return;
        }

        queueMoreRecentSeenAt(candidateIso);
        flushSeenSync();
    };

    return { queueSeenSync };
};
