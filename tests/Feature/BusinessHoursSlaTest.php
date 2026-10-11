<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * With the working-hours setting on, a ticket logged on Friday evening does not breach, and nobody is reminded, over
 * the weekend. The same ticket on the round-the-clock clock does breach.
 */
class BusinessHoursSlaTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $staff;

    private User $technician;

    private Ticket $ticket;

    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel sets the PHP time zone from config('app.timezone') once at boot; do the same for this test.
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Manila');

        config([
            'legal.require_acceptance' => false,
            'app.timezone' => 'Asia/Manila',
            'helpdesk.sla.business_hours.enabled' => true,
            'helpdesk.sla.business_hours.days' => '1,2,3,4,5',
            'helpdesk.sla.business_hours.start' => '08:00',
            'helpdesk.sla.business_hours.end' => '17:00',
        ]);

        // Friday 16:00 in the office; the helpers below move the clock.
        Carbon::setTestNow(Carbon::parse('2026-10-09 16:00', 'Asia/Manila'));

        $this->staff = $this->createSupportUser();
        $this->technician = $this->createAssignedSupportUser();
        $client = $this->createClient();
        $category = $this->createCategory();

        $this->ticket = $this->createTicket($client, $category, [
            'status' => 'in_progress',
            'assigned_to' => $this->technician->id,
            'assigned_at' => now(),
        ]);
        $this->ticket->assignedUsers()->syncWithoutDetaching([$this->technician->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function test_the_ticket_is_not_on_the_breached_tab_while_only_the_weekend_has_passed(): void
    {
        // Monday 10:00: one hour on Friday and two on Monday, so three of the four hours.
        $this->travelToOffice('2026-10-12 10:00');

        $this->assertStringNotContainsString($this->ticket->ticket_number, $this->breachedTab());
    }

    public function test_the_ticket_reaches_the_breached_tab_when_the_fourth_working_hour_has_passed(): void
    {
        $this->travelToOffice('2026-10-12 12:00');

        $this->assertStringContainsString($this->ticket->ticket_number, $this->breachedTab());
    }

    public function test_the_round_the_clock_clock_breaches_over_the_weekend(): void
    {
        config(['helpdesk.sla.business_hours.enabled' => false]);
        $this->travelToOffice('2026-10-10 22:00');

        $this->assertStringContainsString($this->ticket->ticket_number, $this->breachedTab());
    }

    public function test_no_sla_reminder_goes_out_over_the_weekend_and_one_goes_out_after_the_working_hours_pass(): void
    {
        Mail::fake();

        // Assigned Friday 16:00; the reminder is due after 210 working minutes (3.5 hours).
        $this->travelToOffice('2026-10-11 12:00');
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Mail::assertNotQueued(TicketAlertMail::class);

        // Monday 10:00 is 180 working minutes, 09:30 more: still not due.
        $this->travelToOffice('2026-10-12 10:00');
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Mail::assertNotQueued(TicketAlertMail::class);

        // Monday 11:30 is 210 working minutes.
        $this->travelToOffice('2026-10-12 11:30');
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Mail::assertQueued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->hasTo($this->technician->email));
    }

    private function travelToOffice(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, 'Asia/Manila'));
    }

    private function breachedTab(): string
    {
        return (string) $this->actingAs($this->staff)
            ->getJson(route('admin.tickets.index', ['tab' => 'breached', 'partial' => 1]))
            ->assertOk()
            ->json('html');
    }
}
