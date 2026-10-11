<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * The clock the SLA minutes run on. Off (the default) every minute counts, day and night. On, only the working
 * days and hours in `helpdesk.sla.business_hours` count, in the application time zone: a ticket logged at 17:30 on
 * Friday starts its four hours on Monday at the opening time. Dates listed as closed (public holidays) count like a
 * weekend.
 *
 * Two questions are answered:
 *   - elapsedMinutes(): how many counted minutes lie between two moments (the SLA badge, the reports).
 *   - cutoff(): the moment that is N counted minutes before now. A ticket logged at or before it has used at
 *     least N minutes, so the lists and reminders keep one `created_at <= cutoff` condition in SQL.
 *
 * A calendar day is a number of days since 1970-01-01 (a Thursday). Counted seconds up to a moment are the
 * counted days before it times the length of a working day, plus the part of its own day that has passed, minus
 * the closed dates. That is plain arithmetic with no loop over days, so a report over thousands of tickets stays
 * fast; cutoff() finds its moment by halving the time span, about thirty steps.
 *
 * shortcut: a daylight-saving change shifts the hours of its own day by one hour; the offices here do not use it.
 */
final class BusinessHours
{
    private const SECONDS_PER_DAY = 86400;

    /** @var array<string, array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, closed: list<int>, unreadable: list<string>}|null> */
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

    /**
     * Entries of the closed-dates setting that are not a real `YYYY-MM-DD` date. They are ignored.
     *
     * @return list<string>
     */
    public static function unreadableClosedDates(): array
    {
        $schedule = self::flagOn() ? self::schedule() : null;

        return $schedule['unreadable'] ?? [];
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

        $timezone = self::timezone()->getName();
        $upper = $now->getTimestamp();
        $target = self::countedSecondsUpTo($upper, $schedule) - max(0, $minutes) * 60;

        if ($target < 0) {
            return Carbon::createFromTimestamp(0, $timezone);
        }

        if ($target >= self::countedSecondsUpTo($upper, $schedule)) {
            return $now->copy();
        }

        // The last second whose counted total is still at or below the target: the opening time that the target
        // falls on, plus the counted seconds into that day. Invariant: total(lower) <= target < total(upper).
        $lower = 0;
        while ($upper - $lower > 1) {
            $middle = intdiv($lower + $upper, 2);
            if (self::countedSecondsUpTo($middle, $schedule) <= $target) {
                $lower = $middle;
            } else {
                $upper = $middle;
            }
        }

        return Carbon::createFromTimestamp($lower, $timezone);
    }

    /**
     * @param  array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, closed: list<int>, unreadable: list<string>}  $schedule
     */
    private static function countedSecondsUpTo(int $timestamp, array $schedule): int
    {
        $local = max(0, $timestamp + self::timezone()->getOffset(new DateTimeImmutable('@'.$timestamp)));
        $dayNumber = intdiv($local, self::SECONDS_PER_DAY);
        $positionInWeek = $dayNumber % 7;

        $seconds = (intdiv($dayNumber, 7) * $schedule['per_week'] + $schedule['before'][$positionInWeek]) * $schedule['length'];

        if ($schedule['is_working'][$positionInWeek]) {
            $seconds += self::passedOfDay($local, $dayNumber, $schedule);
        }

        // A closed date takes back the working time it would have added up to this moment.
        foreach ($schedule['closed'] as $closedDayNumber) {
            if ($closedDayNumber > $dayNumber) {
                break;
            }

            $seconds -= self::passedOfDay($local, $closedDayNumber, $schedule);
        }

        return $seconds;
    }

    /**
     * Working seconds of one calendar day that have passed at local time $local.
     *
     * @param  array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, closed: list<int>, unreadable: list<string>}  $schedule
     */
    private static function passedOfDay(int $local, int $dayNumber, array $schedule): int
    {
        $passed = $local - $dayNumber * self::SECONDS_PER_DAY - $schedule['start'];

        return min(max($passed, 0), $schedule['length']);
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
     * @return array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, closed: list<int>, unreadable: list<string>}|null
     *                                                                                                                                                    Null when the days or hours cannot be read, or leave no working time.
     */
    private static function schedule(): ?array
    {
        $days = config('helpdesk.sla.business_hours.days', '1,2,3,4,5');
        $start = (string) config('helpdesk.sla.business_hours.start', '08:00');
        $end = (string) config('helpdesk.sla.business_hours.end', '17:00');
        $closed = config('helpdesk.sla.business_hours.closed_dates', '');
        $key = json_encode([$days, $start, $end, $closed]);

        if (! array_key_exists($key, self::$schedules)) {
            self::$schedules[$key] = self::buildSchedule($days, $start, $end, $closed);
        }

        return self::$schedules[$key];
    }

    /**
     * @return array{start: int, length: int, per_week: int, is_working: list<bool>, before: list<int>, closed: list<int>, unreadable: list<string>}|null
     */
    private static function buildSchedule(mixed $days, string $start, string $end, mixed $closedDates): ?array
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
        $count = 0;
        for ($position = 0; $position < 7; $position++) {
            $working = in_array(($position + 3) % 7 + 1, $isoDays, true);
            $before[$position] = $count;
            $isWorking[$position] = $working;
            if ($working) {
                $count++;
            }
        }

        $closed = [];
        $unreadable = [];
        foreach (is_array($closedDates) ? $closedDates : explode(',', (string) $closedDates) as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            $dayNumber = self::dayNumberOf($entry);
            if ($dayNumber === null) {
                $unreadable[] = $entry;

                continue;
            }

            // A closed date on a weekday that is not worked anyway changes nothing.
            if ($isWorking[$dayNumber % 7]) {
                $closed[$dayNumber] = $dayNumber;
            }
        }
        sort($closed);

        return [
            'start' => $startSeconds,
            'length' => $endSeconds - $startSeconds,
            'per_week' => $count,
            'is_working' => $isWorking,
            'before' => $before,
            'closed' => array_values($closed),
            'unreadable' => $unreadable,
        ];
    }

    /** Days since 1970-01-01 of a `YYYY-MM-DD` date, or null when it is not a real date on or after 1970-01-01. */
    private static function dayNumberOf(string $date): ?int
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || (int) $parts[1] < 1970) {
            return null;
        }

        return intdiv(gmmktime(0, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1]), self::SECONDS_PER_DAY);
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
