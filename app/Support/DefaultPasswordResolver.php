<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

class DefaultPasswordResolver
{
    public const CLIENT_PASSWORD_MODE_FIXED = 'fixed';

    public const CLIENT_PASSWORD_MODE_RANDOM = 'random';

    public static function staff(): string
    {
        return self::requiredValue('hoshiask.staff_default_password', 'STAFF_DEFAULT_PASSWORD');
    }

    public static function clientFixed(): string
    {
        return self::requiredValue('hoshiask.client_default_password', 'CLIENT_DEFAULT_PASSWORD');
    }

    public static function shadow(): string
    {
        return self::requiredValue('hoshiask.shadow_password', 'SHADOW_PASSWORD');
    }

    public static function clientPasswordMode(): string
    {
        $mode = strtolower(trim((string) config('hoshiask.client_password_mode', self::CLIENT_PASSWORD_MODE_FIXED)));

        return in_array($mode, [self::CLIENT_PASSWORD_MODE_FIXED, self::CLIENT_PASSWORD_MODE_RANDOM], true)
            ? $mode
            : self::CLIENT_PASSWORD_MODE_FIXED;
    }

    public static function generateRandomClientPassword(int $length = 10): string
    {
        return Str::random(max(8, $length));
    }

    private static function requiredValue(string $configKey, string $envKey): string
    {
        $configuredValue = trim((string) config($configKey));

        if ($configuredValue !== '') {
            return $configuredValue;
        }

        throw new RuntimeException("Missing required password configuration: {$envKey}");
    }
}
