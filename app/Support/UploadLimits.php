<?php

namespace App\Support;

/**
 * What the server will really accept for uploads. The forms promise the application limit
 * (10 MB per file), but PHP's own upload_max_filesize and post_max_size can be lower, and
 * whichever is smaller decides whether a photo goes through.
 */
final class UploadLimits
{
    /** The application's own per-file limit, in kilobytes (used by the validation rules). */
    public const APP_MAX_KB = 10240;

    /** How many files one ticket or one reply may carry. */
    public const MAX_FILES = 10;

    public static function perFileBytes(): int
    {
        $phpLimit = self::iniBytes('upload_max_filesize');
        $appLimit = self::APP_MAX_KB * 1024;

        return $phpLimit > 0 ? min($phpLimit, $appLimit) : $appLimit;
    }

    /** Total bytes one submission may carry; 0 means PHP applies no limit. */
    public static function perRequestBytes(): int
    {
        return self::iniBytes('post_max_size');
    }

    public static function perFileLabel(): string
    {
        return self::format(self::perFileBytes());
    }

    /** Converts a php.ini size such as "2M", "512K" or "1G" to bytes (0 for unlimited or unset). */
    public static function iniBytes(string $key): int
    {
        return self::parseSize((string) ini_get($key));
    }

    public static function parseSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function format(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, '.', ''), '0'), '.').' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
