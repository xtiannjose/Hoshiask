<?php

namespace App\Support;

/**
 * The SLA minute settings from config/helpdesk.php. A missing value, zero or a negative number falls back
 * to the default, so a typo in .env never turns a threshold into "0 minutes". The two super user reminder
 * thresholds are the exception: they have always treated zero as "as soon as possible" (one minute).
 */
final class SlaSettings
{
    public static function resolutionTargetMinutes(): int
    {
        return self::positive('resolution_target_minutes', 240);
    }

    public static function assignmentReminderMinutes(): int
    {
        return self::positive('assignment_reminder_minutes', 210);
    }

    public static function superUserUncheckedMinutes(): int
    {
        return self::atLeastOneMinute('super_user_unchecked_minutes', 120);
    }

    public static function superUserUnassignedMinutes(): int
    {
        return self::atLeastOneMinute('super_user_unassigned_sla_minutes', 180);
    }

    /**
     * Upper limits of the Resolution Time bands on the SLA Overview.
     *
     * @return array{one_hour: int, four_hours: int, one_day: int}
     */
    public static function resolutionBandMinutes(): array
    {
        return [
            'one_hour' => self::positive('resolution_buckets.under_one_hour_minutes', 60),
            'four_hours' => self::positive('resolution_buckets.under_four_hours_minutes', 240),
            'one_day' => self::positive('resolution_buckets.under_twenty_four_hours_minutes', 1440),
        ];
    }

    private static function atLeastOneMinute(string $key, int $default): int
    {
        return max(1, (int) config('helpdesk.sla.'.$key, $default));
    }

    private static function positive(string $key, int $default): int
    {
        $value = (int) config('helpdesk.sla.'.$key, $default);

        return $value > 0 ? $value : $default;
    }
}
