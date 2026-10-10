<?php

namespace App\Support;

/**
 * Province → city/municipality lookup sourced from the Philippine Statistics
 * Authority's PSGC publication (see resources/data/philippine-locations.json).
 * "NCR" is kept as a pseudo-province (matching how this app already stores
 * Metro Manila tickets), holding its constituent cities plus Pateros, NCR's
 * one PSGC-classified municipality.
 */
final class PhilippineLocations
{
    /**
     * Common real-world names people use that don't match the official PSGC
     * spelling exactly. Checked before falling back to an exact list lookup.
     *
     * @var array<string, string>
     */
    private const PROVINCE_ALIASES = [
        'metro manila' => 'NCR',
        'national capital region' => 'NCR',
        'metro cebu' => 'Cebu',
    ];

    /**
     * @var array<string, array<int, string>>|null
     */
    private static ?array $data = null;

    /**
     * @return array<string, array<int, string>>
     */
    private static function data(): array
    {
        if (self::$data === null) {
            $path = resource_path('data/philippine-locations.json');
            self::$data = json_decode(file_get_contents($path), true) ?? [];
        }

        return self::$data;
    }

    /**
     * @return array<int, string>
     */
    public static function provinces(): array
    {
        return array_keys(self::data());
    }

    public static function isValidProvince(string $province): bool
    {
        return self::resolveProvince($province) !== null;
    }

    /**
     * @return array<int, string>
     */
    public static function municipalitiesFor(string $province): array
    {
        $matchedProvince = self::resolveProvince($province);

        return $matchedProvince === null ? [] : self::data()[$matchedProvince];
    }

    public static function isValidPair(string $province, string $municipality): bool
    {
        return self::matchCaseInsensitive(self::municipalitiesFor($province), $municipality) !== null;
    }

    private static function resolveProvince(string $province): ?string
    {
        $province = trim($province);
        if ($province === '') {
            return null;
        }

        $alias = self::PROVINCE_ALIASES[mb_strtolower($province)] ?? null;
        if ($alias !== null) {
            return $alias;
        }

        return self::matchCaseInsensitive(self::provinces(), $province);
    }

    /**
     * @param  array<int, string>  $haystack
     */
    private static function matchCaseInsensitive(array $haystack, string $needle): ?string
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        foreach ($haystack as $candidate) {
            if (strcasecmp($candidate, $needle) === 0) {
                return $candidate;
            }
        }

        // Many people redundantly add/omit a trailing "City" (e.g. "Pasig City"
        // for the official "Pasig", or "Davao" for the official "Davao City").
        // Retry with that suffix toggled before giving up.
        $withoutCitySuffix = preg_replace('/\s+city$/i', '', $needle);
        $withCitySuffix = $needle.' City';

        foreach ($haystack as $candidate) {
            if (strcasecmp($candidate, $withoutCitySuffix) === 0 || strcasecmp($candidate, $withCitySuffix) === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
