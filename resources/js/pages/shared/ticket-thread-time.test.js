import { describe, expect, it } from 'vitest';

import {
    formatThreadTimestampLabel,
    parseIsoMs,
    resolveLatestThreadActivityIso,
    shouldInsertTimeSeparator,
} from './ticket-thread-time';

describe('parseIsoMs', () => {
    it('returns zero for invalid ISO values', () => {
        expect(parseIsoMs('not-a-date')).toBe(0);
        expect(parseIsoMs('')).toBe(0);
    });

    it('returns a timestamp for valid ISO values', () => {
        expect(parseIsoMs('2026-01-01T00:00:00.000Z')).toBeGreaterThan(0);
    });
});

describe('resolveLatestThreadActivityIso', () => {
    it('returns an empty string when thread is missing', () => {
        expect(resolveLatestThreadActivityIso(null)).toBe('');
    });

    it('returns latest activity from reply rows', () => {
        const thread = {
            querySelectorAll: () => ([
                { dataset: { createdAt: '2026-03-01T00:00:00.000Z' } },
                { dataset: { createdAt: '2026-03-01T01:00:00.000Z' } },
                { dataset: { createdAt: '2026-03-01T00:30:00.000Z' } },
            ]),
        };

        expect(resolveLatestThreadActivityIso(thread)).toBe('2026-03-01T01:00:00.000Z');
    });
});

describe('shouldInsertTimeSeparator', () => {
    it('returns true when gap is at least the break window', () => {
        expect(
            shouldInsertTimeSeparator(
                '2026-03-01T00:00:00.000Z',
                '2026-03-01T00:20:00.000Z',
                15,
            ),
        ).toBe(true);
    });

    it('returns false when gap is below break window or values are invalid', () => {
        expect(
            shouldInsertTimeSeparator(
                '2026-03-01T00:00:00.000Z',
                '2026-03-01T00:05:00.000Z',
                15,
            ),
        ).toBe(false);
        expect(shouldInsertTimeSeparator('invalid', '2026-03-01T00:05:00.000Z', 15)).toBe(false);
    });
});

describe('formatThreadTimestampLabel', () => {
    it('returns empty string for invalid dates', () => {
        expect(formatThreadTimestampLabel(new Date('invalid'))).toBe('');
    });
});
