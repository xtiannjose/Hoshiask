<?php

namespace App\Support;

/**
 * Turns a raw user-agent string into a short "Browser on Platform" label for monitoring views.
 */
class DeviceLabel
{
    /**
     * Checked in order: most Chromium-based browsers also claim "Chrome" and "Safari",
     * so the more specific names must come first.
     *
     * @var array<string, string>
     */
    private const BROWSERS = [
        'Edge' => '/Edg(e|A|iOS)?\//',
        'Opera' => '/OPR\/|Opera/',
        'Samsung Internet' => '/SamsungBrowser\//',
        'Firefox' => '/Firefox\/|FxiOS\//',
        'Chrome' => '/Chrome\/|CriOS\//',
        'Safari' => '/Safari\//',
    ];

    /**
     * iOS and Android user agents also mention "Mac OS X" and "Linux", so they come first.
     *
     * @var array<string, string>
     */
    private const PLATFORMS = [
        'iPhone' => '/iPhone/',
        'iPad' => '/iPad/',
        'Android' => '/Android/',
        'Windows' => '/Windows/',
        'ChromeOS' => '/CrOS/',
        'macOS' => '/Macintosh|Mac OS X/',
        'Linux' => '/Linux/',
    ];

    public static function fromUserAgent(?string $userAgent): string
    {
        $userAgent = trim((string) $userAgent);
        if ($userAgent === '') {
            return 'Unknown device';
        }

        $browser = self::firstMatch(self::BROWSERS, $userAgent);
        $platform = self::firstMatch(self::PLATFORMS, $userAgent);

        return match (true) {
            $browser !== null && $platform !== null => "{$browser} on {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => 'Unknown device',
        };
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function firstMatch(array $patterns, string $userAgent): ?string
    {
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $label;
            }
        }

        return null;
    }
}
