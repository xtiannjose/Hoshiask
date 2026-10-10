import { beforeEach, describe, expect, it, vi } from 'vitest';

import { createReplyPolling } from './ticket-thread-polling';

describe('createReplyPolling', function () {
    beforeEach(function () {
        vi.stubGlobal('window', {
            setTimeout: vi.fn(() => 101),
            clearTimeout: vi.fn(),
            addEventListener: vi.fn(),
        });
        vi.stubGlobal('document', {
            visibilityState: 'visible',
            addEventListener: vi.fn(),
        });
        vi.stubGlobal('fetch', vi.fn());
        vi.spyOn(console, 'warn').mockImplementation(() => {});
    });

    it('does not schedule polling when the feed url is blank', function () {
        const polling = createReplyPolling({
            repliesUrl: () => '   ',
            getCursor: () => '',
            setCursor: vi.fn(),
            syncReplies: vi.fn(),
            queueSeenSync: vi.fn(),
            intervalMs: 5000,
        });

        polling.schedule();

        expect(window.setTimeout).not.toHaveBeenCalled();
    });

    it('does not fetch when the resolved feed url is blank', async function () {
        const polling = createReplyPolling({
            repliesUrl: () => '',
            getCursor: () => '',
            setCursor: vi.fn(),
            syncReplies: vi.fn(),
            queueSeenSync: vi.fn(),
            intervalMs: 5000,
        });

        await polling.poll();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('fetches replies and syncs state when the feed url is available', async function () {
        const setCursor = vi.fn();
        const syncReplies = vi.fn();
        const queueSeenSync = vi.fn();
        fetch.mockResolvedValue({
            ok: true,
            json: async () => ({
                cursor: 'cursor-2',
                replies: [{ id: 7 }],
            }),
        });

        const polling = createReplyPolling({
            repliesUrl: () => '/tickets/15/replies?updated_after=cursor-1',
            getCursor: () => 'cursor-1',
            setCursor,
            syncReplies,
            queueSeenSync,
            intervalMs: 5000,
        });

        await polling.poll();

        expect(fetch).toHaveBeenCalledWith('/tickets/15/replies?updated_after=cursor-1', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        expect(setCursor).toHaveBeenCalledWith('cursor-2');
        expect(syncReplies).toHaveBeenCalledWith([{ id: 7 }]);
        expect(queueSeenSync).toHaveBeenCalledTimes(1);
        expect(window.setTimeout).toHaveBeenCalledWith(expect.any(Function), 5000);
    });

    it('logs a polling error once until a successful poll resets the warning state', async function () {
        fetch
            .mockRejectedValueOnce(new Error('network down'))
            .mockRejectedValueOnce(new Error('still down'))
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ cursor: '', replies: [] }),
            })
            .mockRejectedValueOnce(new Error('down again'));

        const polling = createReplyPolling({
            repliesUrl: () => '/tickets/15/replies',
            getCursor: () => '',
            setCursor: vi.fn(),
            syncReplies: vi.fn(),
            queueSeenSync: vi.fn(),
            intervalMs: 5000,
        });

        await polling.poll();
        await polling.poll();
        expect(console.warn).toHaveBeenCalledTimes(1);

        await polling.poll();
        await polling.poll();
        expect(console.warn).toHaveBeenCalledTimes(2);
    });

    it('backs off while idle and resets after new replies arrive', async function () {
        fetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ cursor: 'cursor-1', replies: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ cursor: 'cursor-2', replies: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ cursor: 'cursor-3', replies: [{ id: 9 }] }),
            });

        const polling = createReplyPolling({
            repliesUrl: () => '/tickets/15/replies',
            getCursor: () => '',
            setCursor: vi.fn(),
            syncReplies: vi.fn(),
            queueSeenSync: vi.fn(),
            intervalMs: 5000,
        });

        await polling.poll();
        await polling.poll();
        await polling.poll();

        expect(window.setTimeout).toHaveBeenNthCalledWith(1, expect.any(Function), 10000);
        expect(window.setTimeout).toHaveBeenNthCalledWith(2, expect.any(Function), 20000);
        expect(window.setTimeout).toHaveBeenNthCalledWith(3, expect.any(Function), 5000);
    });
});
