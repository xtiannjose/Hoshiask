import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { buildMunicipalitiesUrl } from './location-suggestions';

describe('buildMunicipalitiesUrl', function () {
    beforeEach(function () {
        vi.stubGlobal('window', { location: { origin: 'https://helpdesk.example.test' } });
    });

    afterEach(function () {
        vi.unstubAllGlobals();
    });

    it('adds the province as an encoded query parameter', function () {
        expect(buildMunicipalitiesUrl('/locations/municipalities', 'Davao del Sur'))
            .toBe('https://helpdesk.example.test/locations/municipalities?province=Davao+del+Sur');
    });

    it('keeps an absolute base URL as it is', function () {
        expect(buildMunicipalitiesUrl('https://helpdesk.example.test/locations/municipalities', 'NCR'))
            .toBe('https://helpdesk.example.test/locations/municipalities?province=NCR');
    });
});
