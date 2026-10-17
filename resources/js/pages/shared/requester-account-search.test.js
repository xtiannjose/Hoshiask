import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    describeAccountMatches,
    describeListedAccounts,
    fetchRequesterAccounts,
    mergeSearchResults,
} from './requester-account-search';

const option = (value, group = 'client') => ({ value, label: `Account ${value}`, group });

describe('mergeSearchResults', function () {
    it('replaces the group with the results and leaves other groups alone', function () {
        const merged = mergeSearchResults(
            [option('1'), option('2'), option('9', 'support')],
            'client',
            [option('3'), option('4')],
        );

        expect(merged.map((item) => item.value)).toEqual(['9', '3', '4']);
    });

    it('keeps the account that is selected even when the search does not return it', function () {
        const merged = mergeSearchResults(
            [option('1'), option('2')],
            'client',
            [option('3')],
            '2',
        );

        expect(merged.map((item) => item.value)).toEqual(['2', '3']);
    });

    it('does not list the selected account twice when the search returns it as well', function () {
        const merged = mergeSearchResults(
            [option('1'), option('2')],
            'client',
            [option('2'), option('3')],
            '2',
        );

        expect(merged.map((item) => item.value)).toEqual(['2', '3']);
    });

    it('can end up with no accounts of the group', function () {
        expect(mergeSearchResults([option('1')], 'client', [])).toEqual([]);
    });
});

describe('describeAccountMatches', function () {
    it('says it is searching', function () {
        expect(describeAccountMatches({ searching: true })).toBe('Searching…');
    });

    it('says when nothing matches, in the glossary terms', function () {
        const message = describeAccountMatches({ shown: 0, total: 0 });

        expect(message).toContain('No matching accounts');
        expect(message).toContain('department');
        expect(message).not.toContain('office');
    });

    it('says how many of the matches are shown', function () {
        expect(describeAccountMatches({ shown: 50, total: 134 }))
            .toBe('Showing the first 50 of 134 matching accounts. Type more to narrow the list.');
    });

    it('counts a single match in the singular', function () {
        expect(describeAccountMatches({ shown: 1, total: 1 })).toBe('1 matching account.');
        expect(describeAccountMatches({ shown: 3, total: 3 })).toBe('3 matching accounts.');
    });
});

describe('describeListedAccounts', function () {
    it('is empty when every account is listed', function () {
        expect(describeListedAccounts({ shown: 12, total: 12 })).toBe('');
    });

    it('points to the search box when part of the accounts is listed', function () {
        expect(describeListedAccounts({ shown: 50, total: 520 }))
            .toBe(' Showing 50 of 520; use the search box to find the others.');
    });
});

describe('fetchRequesterAccounts', function () {
    afterEach(function () {
        vi.unstubAllGlobals();
    });

    it('asks the search url for the group and the query and reads accounts and total', async function () {
        vi.stubGlobal('window', { location: { origin: 'https://hoshiask.test' } });
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ accounts: [option('7')], total: 12 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const result = await fetchRequesterAccounts({
            url: '/admin/tickets/requester-accounts',
            group: 'client',
            query: 'ana cruz',
        });

        const requested = new URL(fetchMock.mock.calls[0][0]);
        expect(requested.pathname).toBe('/admin/tickets/requester-accounts');
        expect(requested.searchParams.get('group')).toBe('client');
        expect(requested.searchParams.get('q')).toBe('ana cruz');
        expect(fetchMock.mock.calls[0][1].credentials).toBe('same-origin');
        expect(result).toEqual({ accounts: [option('7')], total: 12 });
    });

    it('rejects when the server answers with an error status', async function () {
        vi.stubGlobal('window', { location: { origin: 'https://hoshiask.test' } });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 429 }));

        await expect(fetchRequesterAccounts({ url: '/search', group: 'client', query: 'a' }))
            .rejects.toThrow('status 429');
    });

    it('treats a malformed body as no accounts', async function () {
        vi.stubGlobal('window', { location: { origin: 'https://hoshiask.test' } });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) }));

        await expect(fetchRequesterAccounts({ url: '/search', group: 'support', query: 'a' }))
            .resolves.toEqual({ accounts: [], total: 0 });
    });
});
