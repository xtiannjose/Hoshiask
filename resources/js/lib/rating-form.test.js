import { describe, expect, it } from 'vitest';

import { commentRequiredForRating } from './rating-form';

describe('commentRequiredForRating', function () {
    it('asks for a comment only when the rating is 1 or 2 stars', function () {
        expect(commentRequiredForRating('1')).toBe(true);
        expect(commentRequiredForRating('2')).toBe(true);
        expect(commentRequiredForRating(2)).toBe(true);
        expect(commentRequiredForRating('3')).toBe(false);
        expect(commentRequiredForRating('4')).toBe(false);
        expect(commentRequiredForRating('5')).toBe(false);
    });

    it('does not require a comment before a rating is chosen or for junk values', function () {
        expect(commentRequiredForRating('')).toBe(false);
        expect(commentRequiredForRating(null)).toBe(false);
        expect(commentRequiredForRating(undefined)).toBe(false);
        expect(commentRequiredForRating('abc')).toBe(false);
        expect(commentRequiredForRating('0')).toBe(false);
    });
});
