export const DEFAULT_STATUS_VIEW = 'all';

export const parseAssignedIds = function (rawValue) {
    if (!rawValue) {
        return [];
    }

    try {
        const parsed = JSON.parse(rawValue);
        if (Array.isArray(parsed)) {
            return parsed.map(function (value) {
                return String(value);
            });
        }
    } catch (error) {
        if (typeof console !== 'undefined' && typeof console.warn === 'function') {
            console.warn('Failed to parse assigned-user filter value.', error);
        }
    }

    return String(rawValue)
        .split(',')
        .map(function (value) { return value.trim(); })
        .filter(function (value) { return value !== ''; });
};

export const normalizeAdminTicketResultsUrl = function (url, origin = 'http://localhost') {
    const normalized = new URL(url, origin);
    normalized.searchParams.delete('partial');
    normalized.searchParams.delete('heartbeat');

    return normalized;
};

export const resetAdminTicketFilterFieldValue = function (fieldName, params) {
    const paramValue = params.get(fieldName);
    if (paramValue !== null) {
        return paramValue;
    }

    if (fieldName === 'tab') {
        return 'tickets';
    }

    if (['search', 'month', 'created_from', 'created_to', 'report_scope'].includes(fieldName)) {
        return '';
    }

    return 'all';
};

export const buildAdminTicketFilterUrl = function ({
    routeBase,
    formEntries = [],
    selectedMonth = '',
    statusValue = DEFAULT_STATUS_VIEW,
    origin = 'http://localhost',
}) {
    const targetUrl = new URL(routeBase, origin);

    formEntries.forEach(function ([key, rawValue]) {
        const value = String(rawValue).trim();
        if (value === '') {
            return;
        }

        if (key !== 'tab' && value === 'all') {
            return;
        }

        if (selectedMonth !== '' && ['created_from', 'created_to', 'report_scope'].includes(key)) {
            return;
        }

        targetUrl.searchParams.append(key, value);
    });

    if (statusValue !== DEFAULT_STATUS_VIEW) {
        targetUrl.searchParams.set('status', statusValue);
    }

    return targetUrl;
};
