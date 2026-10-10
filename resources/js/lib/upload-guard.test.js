import { describe, expect, it } from 'vitest';

import { findUploadProblems, formatFileSize } from './upload-guard';

const MB = 1024 * 1024;

describe('formatFileSize', function () {
    it('shows megabytes with at most one decimal and small files in KB', function () {
        expect(formatFileSize(2 * MB)).toBe('2 MB');
        expect(formatFileSize(6.25 * MB)).toBe('6.3 MB');
        expect(formatFileSize(300 * 1024)).toBe('300 KB');
        expect(formatFileSize(10)).toBe('1 KB');
    });
});

describe('findUploadProblems with a file count limit', function () {
    it('accepts exactly the limit and complains about one more', function () {
        const files = (n) => Array.from({ length: n }, (_, i) => ({ name: `f${i}.jpg`, size: 1024 }));

        expect(findUploadProblems(files(10), 0, 0, 10)).toEqual([]);

        const problems = findUploadProblems(files(11), 0, 0, 10);
        expect(problems).toHaveLength(1);
        expect(problems[0]).toContain('11 files');
        expect(problems[0]).toContain('at most 10');
    });

    it('has no count limit when none is given', function () {
        expect(findUploadProblems(Array.from({ length: 50 }, () => ({ name: 'a.jpg', size: 1 })), 0, 0)).toEqual([]);
    });
});

describe('findUploadProblems', function () {
    it('accepts files within both limits', function () {
        expect(findUploadProblems([{ name: 'a.jpg', size: MB }, { name: 'b.png', size: MB }], 2 * MB, 8 * MB)).toEqual([]);
    });

    it('names each file over the per-file limit', function () {
        const problems = findUploadProblems([{ name: 'big.jpg', size: 6 * MB }, { name: 'ok.jpg', size: MB }], 2 * MB, 0);

        expect(problems).toHaveLength(1);
        expect(problems[0]).toBe('"big.jpg" is 6 MB. Each file can be at most 2 MB.');
    });

    it('flags a total over the per-request limit even when every file is small enough', function () {
        const files = [1, 2, 3, 4, 5].map((n) => ({ name: `p${n}.jpg`, size: 1.8 * MB }));
        const problems = findUploadProblems(files, 2 * MB, 8 * MB);

        expect(problems).toHaveLength(1);
        expect(problems[0]).toContain('Together your files are 9 MB');
        expect(problems[0]).toContain('at most 8 MB');
    });

    it('treats a zero limit as unlimited', function () {
        expect(findUploadProblems([{ name: 'huge.zip', size: 500 * MB }], 0, 0)).toEqual([]);
    });

    it('handles no files', function () {
        expect(findUploadProblems([], 2 * MB, 8 * MB)).toEqual([]);
    });
});
