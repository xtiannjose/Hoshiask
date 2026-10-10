import { describe, expect, it } from 'vitest';

import { isInternalRole } from './user-role';

describe('isInternalRole', () => {
    it('returns true for internal roles', () => {
        expect(isInternalRole('admin')).toBe(true);
        expect(isInternalRole('super_user')).toBe(true);
        expect(isInternalRole('technical')).toBe(true);
        expect(isInternalRole('shadow')).toBe(true);
    });

    it('returns false for non-internal roles', () => {
        expect(isInternalRole('client')).toBe(false);
        expect(isInternalRole('')).toBe(false);
        expect(isInternalRole(null)).toBe(false);
    });
});
