import { beforeEach, describe, expect, it, vi } from 'vitest';

import { PEEK_DURATION_MS, attachPasswordPeek } from './password-peek';

const makeClassList = () => {
    const set = new Set();

    return {
        toggle: (name, force) => (force ? set.add(name) : set.delete(name)),
        contains: (name) => set.has(name),
    };
};

const setup = () => {
    const attrs = {};
    let clickHandler = null;
    const input = { type: 'password' };
    const toggle = {
        setAttribute: (name, value) => { attrs[name] = value; },
        addEventListener: (name, handler) => { if (name === 'click') clickHandler = handler; },
    };
    const eyeOpen = { classList: makeClassList() };
    const eyeClosed = { classList: makeClassList() };
    eyeClosed.classList.toggle('hidden', true);
    const peek = attachPasswordPeek({ input, toggle, eyeOpen, eyeClosed });

    return { input, attrs, eyeOpen, eyeClosed, peek, click: () => clickHandler() };
};

describe('attachPasswordPeek', function () {
    beforeEach(function () {
        vi.useFakeTimers();
        vi.stubGlobal('window', { setTimeout: (cb, ms) => setTimeout(cb, ms), clearTimeout: (id) => clearTimeout(id) });
    });

    it('shows the password for one and a half seconds, then hides it again', function () {
        const { input, attrs, click } = setup();
        expect(PEEK_DURATION_MS).toBe(1500);

        click();
        expect(input.type).toBe('text');
        expect(attrs['aria-pressed']).toBe('true');
        expect(attrs['aria-label']).toBe('Hide password');

        vi.advanceTimersByTime(1499);
        expect(input.type).toBe('text');

        vi.advanceTimersByTime(1);
        expect(input.type).toBe('password');
        expect(attrs['aria-pressed']).toBe('false');
        expect(attrs['aria-label']).toBe('Show password');
    });

    it('swaps the eye icon while the password is showing and back afterwards', function () {
        const { eyeOpen, eyeClosed, click } = setup();

        click();
        expect(eyeOpen.classList.contains('hidden')).toBe(true);
        expect(eyeClosed.classList.contains('hidden')).toBe(false);

        vi.advanceTimersByTime(1500);
        expect(eyeOpen.classList.contains('hidden')).toBe(false);
        expect(eyeClosed.classList.contains('hidden')).toBe(true);
    });

    it('hides immediately when clicked again, and the old timer does nothing later', function () {
        const { input, click } = setup();

        click();
        vi.advanceTimersByTime(500);
        click();
        expect(input.type).toBe('password');

        // A second reveal must get its own full 1.5 seconds, unaffected by the first timer.
        click();
        vi.advanceTimersByTime(1400);
        expect(input.type).toBe('text');
        vi.advanceTimersByTime(100);
        expect(input.type).toBe('password');
    });

    it('can be hidden on demand, for example when the form is sent', function () {
        const { input, peek, click } = setup();

        click();
        peek.hide();
        expect(input.type).toBe('password');

        vi.advanceTimersByTime(5000);
        expect(input.type).toBe('password');
    });
});
