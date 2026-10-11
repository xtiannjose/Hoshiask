<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Support\BusinessHours;
use App\Support\TicketSla;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    private const ZONE = 'Asia/Manila';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => self::ZONE,
            'helpdesk.sla.business_hours.enabled' => true,
            'helpdesk.sla.business_hours.days' => '1,2,3,4,5',
            'helpdesk.sla.business_hours.start' => '08:00',
            'helpdesk.sla.business_hours.end' => '17:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_switched_off_every_minute_counts(): void
    {
        config(['helpdesk.sla.business_hours.enabled' => false]);

        $this->assertFalse(BusinessHours::enabled());
        $this->assertSame(180, BusinessHours::elapsedMinutes($this->at('2026-10-09 22:00')->getTimestamp(), $this->at('2026-10-10 01:00')->getTimestamp()));
        $this->assertSame('2026-10-12 06:00:00', BusinessHours::cutoff(240, $this->at('2026-10-12 10:00'))->format('Y-m-d H:i:s'));
    }

    public function test_the_weekend_and_the_night_are_skipped(): void
    {
        // Friday 16:00 to Monday 10:00 is one hour on Friday and two on Monday.
        $this->assertSame(180, $this->between('2026-10-09 16:00', '2026-10-12 10:00'));
        // Saturday to Sunday has no working time at all.
        $this->assertSame(0, $this->between('2026-10-10 08:00', '2026-10-11 17:00'));
        // A ticket logged before opening starts at opening.
        $this->assertSame(60, $this->between('2026-10-12 06:00', '2026-10-12 09:00'));
        // A ticket logged after closing starts the next working day.
        $this->assertSame(30, $this->between('2026-10-12 18:00', '2026-10-13 08:30'));
        // Inside one day.
        $this->assertSame(150, $this->between('2026-10-12 09:00', '2026-10-12 11:30'));
        // A whole working week is five nine-hour days.
        $this->assertSame(5 * 9 * 60, $this->between('2026-10-12 08:00', '2026-10-19 08:00'));
    }

    public function test_a_different_set_of_days_and_hours_is_followed(): void
    {
        config([
            'helpdesk.sla.business_hours.days' => '1,3,6',
            'helpdesk.sla.business_hours.start' => '09:30',
            'helpdesk.sla.business_hours.end' => '12:00',
        ]);

        // Monday (working) 09:00 to Tuesday 12:00: only Monday 09:30-12:00 counts.
        $this->assertSame(150, $this->between('2026-10-12 09:00', '2026-10-13 12:00'));
        // Tuesday to Wednesday 11:00: Wednesday 09:30-11:00.
        $this->assertSame(90, $this->between('2026-10-13 00:00', '2026-10-14 11:00'));
        // Saturday is working here.
        $this->assertSame(150, $this->between('2026-10-17 00:00', '2026-10-17 23:00'));
    }

    public function test_the_cutoff_walks_back_over_the_night_and_the_weekend(): void
    {
        // Monday 10:00, four hours back: two on Monday morning, two before Friday's close.
        $this->assertSame('2026-10-09 15:00:00', $this->cutoff(240, '2026-10-12 10:00'));
        // During the weekend the clock stands still at Friday 17:00.
        $this->assertSame('2026-10-09 13:00:00', $this->cutoff(240, '2026-10-10 03:00'));
        // Exactly at opening time, the four hours were all on the previous working day.
        $this->assertSame('2026-10-09 13:00:00', $this->cutoff(240, '2026-10-12 08:00'));
        // Further back than the start of the calendar there is nothing to find.
        $this->assertSame('1970-01-01', substr($this->cutoff(100000000, '2026-10-12 10:00'), 0, 10));
    }

    public function test_the_cutoff_agrees_with_the_elapsed_minutes(): void
    {
        foreach (['2026-10-12 10:00', '2026-10-10 03:00', '2026-10-14 16:59', '2026-10-16 17:00', '2026-10-18 12:00'] as $local) {
            foreach ([1, 59, 60, 210, 240, 540, 541, 1500, 4000] as $minutes) {
                $cutoff = Carbon::parse($this->cutoff($minutes, $local), self::ZONE);
                $now = $this->at($local)->getTimestamp();

                // A ticket logged at the cutoff has used exactly the target; one logged a minute later has not.
                $this->assertSame($minutes, BusinessHours::elapsedMinutes($cutoff->getTimestamp(), $now));
                $this->assertSame($minutes - 1, BusinessHours::elapsedMinutes($cutoff->copy()->addMinute()->getTimestamp(), $now));
            }
        }
    }

    public function test_the_ticket_badge_and_breach_follow_working_time(): void
    {
        // Logged Friday 16:00, looked at on Monday 10:00: three counted hours, so on track; a calendar clock says breached.
        Carbon::setTestNow($this->at('2026-10-12 10:00'));
        $ticket = (new Ticket)->forceFill(['status' => 'open', 'created_at' => $this->at('2026-10-09 16:00')]);

        $sla = new TicketSla($ticket);
        $this->assertSame(180, $sla->elapsedMinutes());
        $this->assertFalse($sla->hasBreachedResolutionTarget());

        // Two hours later it is past the four-hour target.
        Carbon::setTestNow($this->at('2026-10-12 12:00'));
        $this->assertTrue($sla->hasBreachedResolutionTarget());
    }

    public function test_resolution_time_for_the_reports_counts_working_minutes(): void
    {
        $created = $this->at('2026-10-09 16:00')->getTimestamp();
        $resolved = $this->at('2026-10-12 09:00')->getTimestamp();

        $this->assertSame(120, TicketSla::minutesToCompletion($created, $resolved, null));

        config(['helpdesk.sla.business_hours.enabled' => false]);
        // The same ticket on the calendar clock: Friday 16:00 to Monday 09:00 is 65 hours.
        $this->assertSame(65 * 60, TicketSla::minutesToCompletion($created, $resolved, null));
    }

    public function test_unusable_days_or_hours_fall_back_to_the_round_the_clock_clock(): void
    {
        foreach ([['days' => 'x', 'start' => '08:00', 'end' => '17:00'], ['days' => '1,2', 'start' => '17:00', 'end' => '08:00'], ['days' => '1,2', 'start' => 'soon', 'end' => '17:00'], ['days' => '8,0', 'start' => '08:00', 'end' => '17:00']] as $bad) {
            config(['helpdesk.sla.business_hours.days' => $bad['days'], 'helpdesk.sla.business_hours.start' => $bad['start'], 'helpdesk.sla.business_hours.end' => $bad['end']]);

            $this->assertFalse(BusinessHours::enabled());
            $this->assertTrue(BusinessHours::misconfigured());
            $this->assertSame(180, $this->between('2026-10-09 22:00', '2026-10-10 01:00'));
        }
    }

    public function test_closed_dates_count_like_a_weekend(): void
    {
        // Monday 12 October 2026 is closed: Friday 16:00 to Tuesday 10:00 is one hour on Friday and two on Tuesday.
        config(['helpdesk.sla.business_hours.closed_dates' => '2026-10-12']);

        $this->assertSame(180, $this->between('2026-10-09 16:00', '2026-10-13 10:00'));
        $this->assertSame(0, $this->between('2026-10-12 08:00', '2026-10-12 17:00'));
        // The same span without the closed date is a whole day longer.
        config(['helpdesk.sla.business_hours.closed_dates' => '']);
        $this->assertSame(180 + 9 * 60, $this->between('2026-10-09 16:00', '2026-10-13 10:00'));
    }

    public function test_the_cutoff_skips_a_closed_date_and_still_agrees_with_the_elapsed_minutes(): void
    {
        config(['helpdesk.sla.business_hours.closed_dates' => '2026-10-12, 2026-10-14']);

        // Tuesday 13 October 10:00, four hours back: two on Tuesday, then Friday (Monday is closed).
        $this->assertSame('2026-10-09 15:00:00', $this->cutoff(240, '2026-10-13 10:00'));

        foreach (['2026-10-13 10:00', '2026-10-15 09:00', '2026-10-14 12:00', '2026-10-17 12:00'] as $local) {
            foreach ([1, 60, 240, 540, 1500, 3000] as $minutes) {
                $cutoff = Carbon::parse($this->cutoff($minutes, $local), self::ZONE);
                $now = $this->at($local)->getTimestamp();

                $this->assertSame($minutes, BusinessHours::elapsedMinutes($cutoff->getTimestamp(), $now), "$minutes min before $local");
                $this->assertSame($minutes - 1, BusinessHours::elapsedMinutes($cutoff->copy()->addMinute()->getTimestamp(), $now), "$minutes min before $local");
            }
        }
    }

    public function test_a_closed_date_on_a_day_that_is_not_worked_changes_nothing_and_a_bad_entry_is_named(): void
    {
        // Saturday 10 October is not a working day anyway; the other two entries are not dates.
        config(['helpdesk.sla.business_hours.closed_dates' => '2026-10-10, soon, 2026-02-30, 2026-10-13']);

        $this->assertSame(['soon', '2026-02-30'], BusinessHours::unreadableClosedDates());
        $this->assertSame(180, $this->between('2026-10-09 16:00', '2026-10-12 10:00'));
        // Tuesday 13 October (a real, working, closed date) counts nothing.
        $this->assertSame(0, $this->between('2026-10-13 08:00', '2026-10-13 17:00'));
    }

    public function test_the_example_environment_file_lists_the_nationwide_holidays_as_readable_dates(): void
    {
        $env = (string) file_get_contents(base_path('.env.example'));
        $this->assertSame(1, preg_match('/^HELPDESK_SLA_BUSINESS_HOURS_CLOSED_DATES=(.+)$/m', $env, $match));

        config(['helpdesk.sla.business_hours.closed_dates' => trim($match[1])]);

        $this->assertSame([], BusinessHours::unreadableClosedDates());
        $this->assertCount(18, array_unique(array_map('trim', explode(',', trim($match[1])))));
        // Holy Week: Wednesday 1 April 16:00 to Monday 6 April 09:00 is one hour on Wednesday and one on Monday,
        // because Maundy Thursday and Good Friday (2 and 3 April) are closed. Without the list it is 20 hours.
        $this->assertSame(120, $this->between('2026-04-01 16:00', '2026-04-06 09:00'));
    }

    private function at(string $local): Carbon
    {
        return Carbon::parse($local, self::ZONE);
    }

    private function between(string $from, string $to): int
    {
        return BusinessHours::elapsedMinutes($this->at($from)->getTimestamp(), $this->at($to)->getTimestamp());
    }

    private function cutoff(int $minutes, string $now): string
    {
        return BusinessHours::cutoff($minutes, $this->at($now))->format('Y-m-d H:i:s');
    }
}
