import { beforeEach, describe, expect, it, vi } from 'vitest';

import { createAdminTicketResultsController } from './admin-ticket-index-results';

describe('createAdminTicketResultsController', function () {
    beforeEach(function () {
        vi.stubGlobal('window', {
            location: {
                href: 'https://example.test/admin/tickets?tab=tickets',
                origin: 'https://example.test',
                pathname: '/admin/tickets',
                search: '?tab=tickets',
            },
            history: {
                pushState: vi.fn(),
                replaceState: vi.fn(),
            },
            setTimeout: vi.fn(),
            clearTimeout: vi.fn(),
            addEventListener: vi.fn(),
        });
        vi.stubGlobal('document', {
            hidden: false,
            addEventListener: vi.fn(),
            querySelectorAll: vi.fn(() => []),
        });
        vi.stubGlobal('fetch', vi.fn());
    });

    it('reloads results when only the page snapshot token changes', async function () {
        let scheduledPoll = null;
        window.setTimeout.mockImplementation((callback) => {
            scheduledPoll = callback;

            return 101;
        });

        const pageRoot = {
            dataset: {
                snapshotToken: 'snapshot-1',
                pageSnapshotToken: 'page-1',
            },
        };
        const resultsContainer = {
            outerHTML: '<div data-admin-tickets-results></div>',
            classList: {
                toggle: vi.fn(),
            },
            setAttribute: vi.fn(),
        };
        const onResultsUpdated = vi.fn();

        fetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    token: 'snapshot-1',
                    page_token: 'page-2',
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    html: '<div data-admin-tickets-results>Updated</div>',
                    token: 'snapshot-1',
                    page_token: 'page-2',
                }),
            });

        const controller = createAdminTicketResultsController({
            pageRoot,
            filterForm: null,
            statusView: null,
            routeBase: '/admin/tickets',
            filterFieldSelectors: [],
            getResultsContainer: () => resultsContainer,
            hasOpenModal: () => false,
            onResultsUpdated,
        });

        controller.bind();
        expect(scheduledPoll).toEqual(expect.any(Function));

        scheduledPoll();
        await vi.waitFor(() => {
            expect(fetch).toHaveBeenCalledTimes(2);
            expect(pageRoot.dataset.pageSnapshotToken).toBe('page-2');
        });

        expect(fetch).toHaveBeenNthCalledWith(1, 'https://example.test/admin/tickets?tab=tickets&heartbeat=1', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        expect(fetch).toHaveBeenNthCalledWith(2, 'https://example.test/admin/tickets?tab=tickets&partial=1', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: expect.any(Object),
        });
        expect(pageRoot.dataset.snapshotToken).toBe('snapshot-1');
        expect(onResultsUpdated).toHaveBeenCalledTimes(1);
        expect(window.history.replaceState).not.toHaveBeenCalled();
    });
});
