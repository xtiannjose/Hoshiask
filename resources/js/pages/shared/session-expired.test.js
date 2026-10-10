import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    isSessionExpiredStatus,
    restoreReplyDraft,
    saveReplyDraft,
    showSessionExpiredNotice,
} from './session-expired';

const makeStorage = () => {
    const values = new Map();

    return {
        getItem: (key) => (values.has(key) ? values.get(key) : null),
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
        get size() {
            return values.size;
        },
    };
};

const makeElement = () => {
    const classes = new Set(['hidden']);

    return {
        textContent: '',
        className: '',
        children: [],
        classList: {
            remove: (name) => classes.delete(name),
            contains: (name) => classes.has(name),
        },
        append(...nodes) {
            this.children.push(...nodes);
        },
    };
};

describe('session expiry handling', function () {
    let storage;

    beforeEach(function () {
        storage = makeStorage();
        vi.stubGlobal('window', {
            sessionStorage: storage,
            location: { pathname: '/client/tickets/9', href: 'https://example.test/client/tickets/9' },
        });
        vi.stubGlobal('document', { createElement: () => makeElement() });
    });

    afterEach(function () {
        vi.unstubAllGlobals();
    });

    it('recognises the statuses Laravel returns when a session has ended', function () {
        expect(isSessionExpiredStatus(401)).toBe(true);
        expect(isSessionExpiredStatus(419)).toBe(true);
        expect(isSessionExpiredStatus(422)).toBe(false);
        expect(isSessionExpiredStatus(500)).toBe(false);
    });

    it('restores a saved draft once, into an empty box only', function () {
        saveReplyDraft('Please call me back', '/client/tickets/1');

        const input = { value: '' };
        expect(restoreReplyDraft(input, '/client/tickets/1')).toBe(true);
        expect(input.value).toBe('Please call me back');

        // Already consumed.
        const another = { value: '' };
        expect(restoreReplyDraft(another, '/client/tickets/1')).toBe(false);
    });

    it('never overwrites text the user has already typed', function () {
        saveReplyDraft('old draft', '/client/tickets/2');

        const input = { value: 'newer text' };
        expect(restoreReplyDraft(input, '/client/tickets/2')).toBe(false);
        expect(input.value).toBe('newer text');
        // The stale draft is dropped rather than resurfacing later.
        expect(storage.size).toBe(0);
    });

    it('keeps drafts separate per ticket', function () {
        saveReplyDraft('for ticket 3', '/client/tickets/3');

        const input = { value: '' };
        expect(restoreReplyDraft(input, '/client/tickets/4')).toBe(false);
        expect(input.value).toBe('');
    });

    it('does nothing when session storage is unavailable', function () {
        vi.stubGlobal('window', {
            get sessionStorage() {
                throw new Error('blocked');
            },
            location: { pathname: '/x', href: 'https://example.test/x' },
        });

        expect(() => saveReplyDraft('text')).not.toThrow();
        expect(restoreReplyDraft({ value: '' })).toBe(false);
    });

    it('explains the expiry, saves the text and links back to the ticket', function () {
        const errorElement = makeElement();

        showSessionExpiredNotice(errorElement, 'my unsent reply', 'https://example.test/client/tickets/5');

        expect(errorElement.classList.contains('hidden')).toBe(false);
        expect(errorElement.children[0]).toContain('Your session expired');
        expect(errorElement.children[1].href).toBe('https://example.test/client/tickets/5');
        expect(errorElement.children[1].textContent).toBe('Sign in again');
        expect(storage.getItem('helpdesk:reply-draft:/client/tickets/9')).toBe('my unsent reply');
    });
});
