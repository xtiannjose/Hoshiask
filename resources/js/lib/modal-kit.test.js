import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { registerModalKit } from './modal-kit';

// A stand-in for <dialog>: open/close state, a class list, and a close event that is delivered later, as a browser
// queues it, so a test can deliver it after the dialog has been opened again.
class FakeElement {}

class FakeDialog extends FakeElement {
    constructor() {
        super();
        this.open = false;
        this.classes = new Set();
        this.listeners = {};
        this.pendingCloseEvents = 0;
        this.classList = {
            add: (...names) => names.forEach((name) => this.classes.add(name)),
            remove: (...names) => names.forEach((name) => this.classes.delete(name)),
            contains: (name) => this.classes.has(name),
        };
    }

    addEventListener(type, handler) {
        (this.listeners[type] ||= []).push(handler);
    }

    removeEventListener() {}

    showModal() {
        this.open = true;
    }

    close() {
        if (!this.open) return;
        this.open = false;
        this.pendingCloseEvents += 1;
    }

    deliverCloseEvent() {
        this.pendingCloseEvents -= 1;
        (this.listeners.close || []).forEach((handler) => handler({ target: this }));
    }
}

describe('modal kit', function () {
    let frames;

    beforeEach(function () {
        vi.useFakeTimers();
        frames = [];

        globalThis.Element = FakeElement;
        globalThis.document = { documentElement: {}, getElementById: () => null, querySelector: () => null };
        globalThis.window = {
            getComputedStyle: () => ({ getPropertyValue: () => '200ms' }),
            setTimeout: (...args) => setTimeout(...args),
            clearTimeout: (...args) => clearTimeout(...args),
        };
        globalThis.requestAnimationFrame = (callback) => frames.push(callback);

        registerModalKit();
    });

    afterEach(function () {
        vi.useRealTimers();
        delete globalThis.Element;
        delete globalThis.document;
        delete globalThis.window;
        delete globalThis.requestAnimationFrame;
    });

    const runFrames = () => {
        frames.splice(0).forEach((callback) => callback());
    };

    it('opens with a fade in and closes after the fade out', function () {
        const modal = new FakeDialog();
        const controller = window.ModalKit.bind(modal);

        controller.open();
        runFrames();
        expect(modal.open).toBe(true);
        expect(modal.classes.has('is-open')).toBe(true);

        controller.close();
        expect(modal.classes.has('is-closing')).toBe(true);
        vi.advanceTimersByTime(200);
        expect(modal.open).toBe(false);

        modal.deliverCloseEvent();
        expect([...modal.classes]).toEqual([]);
    });

    it('is not kept open by a close event that arrives after the dialog was opened again', function () {
        const modal = new FakeDialog();
        const onClose = vi.fn();
        const controller = window.ModalKit.bind(modal, { onClose });

        // Open, close (the fade-out ends and the dialog closes), then open again before the close event is delivered.
        controller.open();
        runFrames();
        controller.close();
        vi.advanceTimersByTime(200);
        expect(modal.open).toBe(false);

        controller.open();
        expect(modal.open).toBe(true);

        // Escape right away starts the new fade-out ...
        controller.close();
        expect(modal.classes.has('is-closing')).toBe(true);

        // ... and the late close event of the first close must neither cancel its timer nor strip its classes.
        modal.deliverCloseEvent();
        expect(onClose).not.toHaveBeenCalled();
        expect(modal.classes.has('is-closing')).toBe(true);

        runFrames();
        expect(modal.classes.has('is-open')).toBe(false);

        vi.advanceTimersByTime(200);
        expect(modal.open).toBe(false);

        modal.deliverCloseEvent();
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('does not show a dialog as open when it was closed before the first frame', function () {
        const modal = new FakeDialog();
        const controller = window.ModalKit.bind(modal);

        controller.open();
        controller.close();
        runFrames();

        expect(modal.classes.has('is-open')).toBe(false);
        expect(modal.classes.has('is-closing')).toBe(true);
    });
});
