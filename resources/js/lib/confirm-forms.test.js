import { describe, expect, it, vi } from 'vitest';

import { shouldBlockSubmit } from './confirm-forms';

describe('shouldBlockSubmit', function () {
    it('never blocks a form without a data-confirm question', function () {
        const confirmFn = vi.fn();

        expect(shouldBlockSubmit({ dataset: {} }, confirmFn)).toBe(false);
        expect(confirmFn).not.toHaveBeenCalled();
    });

    it('asks the question and blocks the form when the answer is no', function () {
        const confirmFn = vi.fn().mockReturnValue(false);

        expect(shouldBlockSubmit({ dataset: { confirm: 'Delete this article?' } }, confirmFn)).toBe(true);
        expect(confirmFn).toHaveBeenCalledWith('Delete this article?');
    });

    it('lets the form go through when the answer is yes', function () {
        expect(shouldBlockSubmit({ dataset: { confirm: 'Delete?' } }, () => true)).toBe(false);
    });
});
