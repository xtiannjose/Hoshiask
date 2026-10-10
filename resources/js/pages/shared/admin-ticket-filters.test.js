import { describe, expect, it } from 'vitest';

import {
    buildAdminTicketFilterUrl,
    normalizeAdminTicketResultsUrl,
    parseAssignedIds,
    resetAdminTicketFilterFieldValue,
} from './admin-ticket-filters';

describe('parseAssignedIds', function () {
    it('returns ids from json arrays', function () {
        expect(parseAssignedIds('[1,"2",3]')).toEqual(['1', '2', '3']);
    });

    it('falls back to comma-delimited values', function () {
        expect(parseAssignedIds(' 4, 5 ,6 ')).toEqual(['4', '5', '6']);
    });

    it('returns an empty array for blank values', function () {
        expect(parseAssignedIds('')).toEqual([]);
    });
});

describe('normalizeAdminTicketResultsUrl', function () {
    it('removes partial and heartbeat params without touching the rest', function () {
        const normalized = normalizeAdminTicketResultsUrl(
            'https://example.test/admin/tickets?partial=1&heartbeat=1&tab=all'
        );

        expect(normalized.toString()).toBe('https://example.test/admin/tickets?tab=all');
    });
});

describe('resetAdminTicketFilterFieldValue', function () {
    it('returns defaults for missing fields', function () {
        const params = new URLSearchParams();

        expect(resetAdminTicketFilterFieldValue('tab', params)).toBe('tickets');
        expect(resetAdminTicketFilterFieldValue('search', params)).toBe('');
        expect(resetAdminTicketFilterFieldValue('priority', params)).toBe('all');
    });
});

describe('buildAdminTicketFilterUrl', function () {
    it('keeps meaningful filters and drops empty values', function () {
        const url = buildAdminTicketFilterUrl({
            routeBase: '/admin/tickets',
            origin: 'https://example.test',
            formEntries: [
                ['tab', 'history'],
                ['search', 'printer'],
                ['priority', 'all'],
                ['assigned_to', '14'],
                ['month', ''],
            ],
        });

        expect(url.toString()).toBe('https://example.test/admin/tickets?tab=history&search=printer&assigned_to=14');
    });

    it('drops date range params when month filtering is active and applies status override', function () {
        const url = buildAdminTicketFilterUrl({
            routeBase: '/admin/tickets',
            origin: 'https://example.test',
            selectedMonth: '2026-04',
            statusValue: 'pending',
            formEntries: [
                ['tab', 'tickets'],
                ['month', '2026-04'],
                ['created_from', '2026-04-01'],
                ['created_to', '2026-04-30'],
                ['report_scope', 'daily'],
            ],
        });

        expect(url.toString()).toBe('https://example.test/admin/tickets?tab=tickets&month=2026-04&status=pending');
    });
});
