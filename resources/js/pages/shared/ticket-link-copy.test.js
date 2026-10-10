import { describe, expect, it, vi } from 'vitest';

import { copyTicketLink } from './ticket-link-copy';

describe('copyTicketLink', function () {
    it('copies the ticket URL and temporarily marks the button as copied', async function () {
        const resetCallbacks = [];
        const button = {
            dataset: {
                ticketUrl: 'https://example.test/admin/tickets/123',
            },
            textContent: 'Copy link',
            disabled: false,
        };
        const copyText = vi.fn().mockResolvedValue(true);

        await expect(copyTicketLink(button, {
            copyText,
            setTimeoutFn: (callback) => {
                resetCallbacks.push(callback);

                return 10;
            },
        })).resolves.toBe(true);

        expect(copyText).toHaveBeenCalledWith('https://example.test/admin/tickets/123');
        expect(button.textContent).toBe('Copied');
        expect(button.disabled).toBe(true);

        resetCallbacks[0]();

        expect(button.textContent).toBe('Copy link');
        expect(button.disabled).toBe(false);
    });

    it('shows a temporary failure label when copying fails', async function () {
        const resetCallbacks = [];
        const button = {
            dataset: {
                ticketUrl: 'https://example.test/admin/tickets/456',
            },
            textContent: 'Copy link',
            disabled: false,
        };
        const copyText = vi.fn().mockRejectedValue(new Error('Clipboard blocked'));

        await expect(copyTicketLink(button, {
            copyText,
            setTimeoutFn: (callback) => {
                resetCallbacks.push(callback);

                return 11;
            },
        })).resolves.toBe(false);

        expect(button.textContent).toBe('Copy failed');
        expect(button.disabled).toBe(false);

        resetCallbacks[0]();

        expect(button.textContent).toBe('Copy link');
    });
});
