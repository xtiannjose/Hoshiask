import { describe, expect, it, vi } from 'vitest';

import { isPlainPrimaryClick, showArticle } from './knowledge-base-modal';

describe('isPlainPrimaryClick', function () {
    it('accepts an ordinary left click', function () {
        expect(isPlainPrimaryClick({ button: 0 })).toBe(true);
    });

    it('leaves middle clicks and new-tab gestures to the browser', function () {
        expect(isPlainPrimaryClick({ button: 1 })).toBe(false);
        expect(isPlainPrimaryClick({ button: 0, ctrlKey: true })).toBe(false);
        expect(isPlainPrimaryClick({ button: 0, metaKey: true })).toBe(false);
        expect(isPlainPrimaryClick({ button: 0, shiftKey: true })).toBe(false);
        expect(isPlainPrimaryClick({ button: 0, altKey: true })).toBe(false);
    });
});

describe('showArticle', function () {
    const build = () => {
        const copy = { copy: true };

        return {
            copy,
            template: { content: { cloneNode: vi.fn().mockReturnValue(copy) } },
            content: { replaceChildren: vi.fn(), scrollTop: 120 },
            controller: { open: vi.fn() },
        };
    };

    it('puts a copy of the article in the popup, scrolls it to the top and opens it', function () {
        const { copy, template, content, controller } = build();

        expect(showArticle({ template, content, controller })).toBe(true);

        expect(template.content.cloneNode).toHaveBeenCalledWith(true);
        expect(content.replaceChildren).toHaveBeenCalledWith(copy);
        expect(content.scrollTop).toBe(0);
        expect(controller.open).toHaveBeenCalledTimes(1);
    });

    it('does nothing when the article has no content, so the link still goes to its page', function () {
        const { content, controller } = build();

        expect(showArticle({ template: null, content, controller })).toBe(false);
        expect(content.replaceChildren).not.toHaveBeenCalled();
        expect(controller.open).not.toHaveBeenCalled();
    });
});
