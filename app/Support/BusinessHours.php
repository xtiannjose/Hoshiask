<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * The clock the SLA minutes run on. Off (the default) every minute counts, day and night. On, only the working
 * days and hours in `helpdesk.sla.business_hours` count, in the application time zone: a ticket logged at 17:30 on
 * Friday starts its four hours on Monday at the opening time.
 *
 * Two questions are answered, both without looping over days:
 *   - elapsedMinutes(): how many counted minutes lie between two moments (the SLA badge, the reports).
 *   - cutoff(): the moment that is N counted minutes before now. A ticket logged at or before it has used at
 *     least N minutes, so the lists and reminders keep one `created_at <= cutoff` condition in SQL.
 *
 * A calendar day is a number of days since 1970-01-01 (a Thursday). Counted seconds up to a moment are the
 * counted days before it times the length of a working day, plus the part of its own day that has passed.
 *
 * shortcut: public holidays are not skipped and a daylight-saving change shifts one day by an hour; add a
 * holiday list here if the office wants closed days to stop the clock.
 */
final class BusinessHours
{
    private const SECONDS_PER_DAY = 86400;

    /** @var array<string, array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, offsets: list<int>}|null> */
    private static array $schedules = [];

    public static function enabled(): bool
    {
        return self::flagOn() && self::schedule() !== null;
    }

    /** The switch is on but the days or hours cannot be used, so the SLA minutes still run around the clock. */
    public static function misconfigured(): bool
    {
        return self::flagOn() && self::schedule() === null;
    }

    /** Counted minutes from one moment to a later one (calendar minutes while the setting is off). */
    public static function elapsedMinutes(int $fromTimestamp, int $toTimestamp): int
    {
        if ($toTimestamp <= $fromTimestamp) {
            return 0;
        }

        $schedule = self::flagOn() ? self::schedule() : null;
        if ($schedule === null) {
            return intdiv($toTimestamp - $fromTimestamp, 60);
        }

        return intdiv(self::countedSecondsUpTo($toTimestamp, $schedule) - self::countedSecondsUpTo($fromTimestamp, $schedule), 60);
    }

    /** The moment that is $minutes counted minutes before $now (before the current time when null). */
    public static function cutoff(int $minutes, ?CarbonInterface $now = null): Carbon
    {
        $now = Carbon::instance($now ?? now());
        $schedule = self::flagOn() ? self::schedule() : null;
        if ($schedule === null) {
            return $now->copy()->subMinutes($minutes);
        }

        $target = self::countedSecondsUpTo($now->getTimestamp(), $schedule) - $minutes * 60;
        if ($target < 0) {
            return Carbon::createFromTimestamp(0, self::timezone()->getName());
        }

        // The working day that holds the target, and how far into its hours the target lies. A target at the very
        // end of a day lies at that day's closing time, which is the same counted instant as the next opening.
        $workingDay = intdiv($target, $schedule['length']);
        $into = $target - $workingDay * $schedule['length'];
        $dayNumber = intdiv($workingDay, $schedule['per_week']) * 7 + $schedule['offsets'][$workingDay % $schedule['per_week']];
        [$year, $month, $day] = array_map('intval', explode('-', gmdate('Y-n-j', $dayNumber * self::SECONDS_PER_DAY)));

        return Carbon::create($year, $month, $day, 0, 0, 0, self::timezone()->getName())
            ->addSeconds($schedule['start'] + $into);
    }

    /**
     * @param  array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, offsets: list<int>}  $schedule
     */
    private static function countedSecondsUpTo(int $timestamp, array $schedule): int
    {
        $local = max(0, $timestamp + self::timezone()->getOffset(new DateTimeImmutable('@'.$timestamp)));
        $dayNumber = intdiv($local, self::SECONDS_PER_DAY);
        $positionInWeek = $dayNumber % 7;

        $seconds = (intdiv($dayNumber, 7) * $schedule['per_week'] + $schedule['before'][$positionInWeek]) * $schedule['length'];

        if ($schedule['is_working'][$positionInWeek]) {
            $passed = $local - $dayNumber * self::SECONDS_PER_DAY - $schedule['start'];
            $seconds += min(max($passed, 0), $schedule['length']);
        }

        return $seconds;
    }

    private static function flagOn(): bool
    {
        return (bool) config('helpdesk.sla.business_hours.enabled', false);
    }

    private static function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('app.timezone', 'UTC'));
    }

    /**
     * @return array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, offsets: list<int>}|null
     *                                                                                                                           Null when the days or hours cannot be read, or leave no working time.
     */
    private static function schedule(): ?array
    {
        $days = config('helpdesk.sla.business_hours.days', '1,2,3,4,5');
        $start = (string) config('helpdesk.sla.business_hours.start', '08:00');
        $end = (string) config('helpdesk.sla.business_hours.end', '17:00');
        $key = json_encode([$days, $start, $end]);

        if (! array_key_exists($key, self::$schedules)) {
            self::$schedules[$key] = self::buildSchedule($days, $start, $end);
        }

        return self::$schedules[$key];
    }

    /**
     * @return array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, offsets: list<int>}|null
     */
    private static function buildSchedule(mixed $days, string $start, string $end): ?array
    {
        $startSeconds = self::secondsOfDay($start);
        $endSeconds = self::secondsOfDay($end);
        $isoDays = array_unique(array_filter(
            array_map('intval', is_array($days) ? $days : explode(',', (string) $days)),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        ));

        if ($startSeconds === null || $endSeconds === null || $endSeconds <= $startSeconds || $isoDays === []) {
            return null;
        }

        // Position 0 is a Thursday, the weekday of 1970-01-01, which is ISO day 4.
        $isWorking = [];
        $before = [];
        $offsets = [];
        $count = 0;
        for ($position = 0; $position < 7; $position++) {
            $working = in_array(($position + 3) % 7 + 1, $isoDays, true);
            $before[$position] = $count;
            $isWorking[$position] = $working;
            if ($working) {
                $offsets[] = $position;
                $count++;
            }
        }

        return [
            'start' => $startSeconds,
            'length' => $endSeconds - $startSeconds,
            'per_week' => $count,
            'is_working' => $isWorking,
            'before' => $before,
            'offsets' => $offsets,
        ];
    }

    /** "08:30" as seconds after midnight; "24:00" is allowed as an end time. Null when it is not a time. */
    private static function secondsOfDay(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $parts)) {
            return null;
        }

        $seconds = ((int) $parts[1] * 60 + (int) $parts[2]) * 60;

        return (int) $parts[2] < 60 && $seconds <= self::SECONDS_PER_DAY ? $seconds : null;
    }
}
