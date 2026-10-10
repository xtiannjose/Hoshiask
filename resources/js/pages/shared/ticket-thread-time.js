const DAY_MS = 24 * 60 * 60 * 1000;

const parseIso = (value) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

export const parseIsoMs = (value) => {
    const date = parseIso(value);
    return date ? date.getTime() : 0;
};

export const resolveLatestThreadActivityIso = (thread) => {
    if (!thread) return '';

    let latestIso = '';
    let latestMs = 0;

    thread.querySelectorAll('.js-chat-row[data-created-at]').forEach((row) => {
        const rowIso = row.dataset.createdAt || '';
        const rowMs = parseIsoMs(rowIso);
        if (rowMs > latestMs) {
            latestMs = rowMs;
            latestIso = rowIso;
        }
    });

    return latestIso;
};

export const formatThreadTimestampLabel = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    const diffMs = Date.now() - date.getTime();
    if (diffMs > DAY_MS) {
        return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
    }

    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
};

export const shouldInsertTimeSeparator = (previousIso, nextIso, breakMinutes = 15) => {
    const previousDate = parseIso(previousIso);
    const nextDate = parseIso(nextIso);
    if (!previousDate || !nextDate) return false;

    const diffMinutes = Math.floor((nextDate.getTime() - previousDate.getTime()) / 60000);
    return diffMinutes >= breakMinutes;
};

export const createThreadTimeSeparator = (isoDate) => {
    const date = parseIso(isoDate);
    if (!date) return null;

    const separator = document.createElement('div');
    separator.className = 'js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500';
    separator.dataset.time = date.toISOString();
    separator.textContent = formatThreadTimestampLabel(date);
    return separator;
};

export const appendThreadSeparatorIfNeeded = (thread, isoDate, breakMinutes = 15) => {
    if (!thread || !isoDate) return;

    const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
    const lastRow = rows.length ? rows[rows.length - 1] : null;
    if (!lastRow) return;

    const previousIso = lastRow.dataset.createdAt || '';
    if (!shouldInsertTimeSeparator(previousIso, isoDate, breakMinutes)) return;

    const separator = createThreadTimeSeparator(isoDate);
    if (!separator) return;

    thread.appendChild(separator);
};
