import { describe, expect, it } from 'vitest';
import { isBlockedTrigger, readDeleteTrigger } from './delete-confirm';

describe('readDeleteTrigger', () => {
    it('reads the title, name, note and address from the data attributes', () => {
        const trigger = {
            dataset: {
                deleteAction: '/admin/departments/4',
                deleteTitle: 'Delete Department',
                deleteName: 'Meadow',
                deleteNote: 'Its logo is removed.',
            },
        };

        expect(readDeleteTrigger(trigger)).toEqual({
            action: '/admin/departments/4',
            title: 'Delete Department',
            name: 'Meadow',
            note: 'Its logo is removed.',
        });
    });

    it('falls back to a plain Delete title and empty text when attributes are missing', () => {
        expect(readDeleteTrigger({ dataset: {} })).toEqual({ action: '', title: 'Delete', name: '', note: '' });
        expect(readDeleteTrigger(null)).toEqual({ action: '', title: 'Delete', name: '', note: '' });
    });
});

describe('isBlockedTrigger', () => {
    const triggerWith = (ariaDisabled) => ({ getAttribute: (name) => (name === 'aria-disabled' ? ariaDisabled : null) });

    it('is blocked only when the button says it is aria-disabled', () => {
        expect(isBlockedTrigger(triggerWith('true'))).toBe(true);
        expect(isBlockedTrigger(triggerWith('false'))).toBe(false);
        expect(isBlockedTrigger(triggerWith(null))).toBe(false);
    });

    it('treats a missing trigger as blocked, so a stray click never opens the dialog', () => {
        expect(isBlockedTrigger(null)).toBe(true);
    });
});
