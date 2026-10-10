<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The same new-ticket form sent twice (a double click, the back button, a retry on a weak connection)
 * makes one ticket, one set of emails and one audit entry.
 */
class DuplicateTicketSubmissionTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const REPEAT_NOTICE = 'This ticket was already sent a moment ago, so it was not created again.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
        Mail::fake();
        Cache::flush();
        DuplicateSubmissionGuard::$waitSeconds = 20;
    }

    protected function tearDown(): void
    {
        DuplicateSubmissionGuard::$waitSeconds = 20;

        parent::tearDown();
    }

    public function test_the_same_client_form_sent_twice_makes_one_ticket(): void
    {
        $client = $this->createClient('Double Click', 'double-click@example.com');
        $payload = $this->clientPayload();

        $this->actingAs($client)->post(route('client.tickets.store'), $payload)
            ->assertSessionHas('success', 'Ticket created successfully!');
        $ticket = Ticket::query()->where('subject', 'Printer is jammed')->firstOrFail();

        $this->actingAs($client)->post(route('client.tickets.store'), $payload)
            ->assertRedirect(route('client.tickets.show', $ticket))
            ->assertSessionHas('success', self::REPEAT_NOTICE);

        $this->assertSame(1, Ticket::query()->where('subject', 'Printer is jammed')->count());
    }

    public function test_the_emails_and_the_audit_entry_happen_only_once(): void
    {
        $client = $this->createClient('Once Only', 'once-only@example.com');
        $payload = $this->clientPayload();

        $this->actingAs($client)->post(route('client.tickets.store'), $payload);
        $this->actingAs($client)->post(route('client.tickets.store'), $payload);
        $this->actingAs($client)->post(route('client.tickets.store'), $payload);

        $this->assertCount(1, Mail::queued(TicketAlertMail::class, fn (TicketAlertMail $mail): bool => $mail->hasTo('once-only@example.com')));
        $this->assertSame(1, SystemLog::query()->where('event_type', 'ticket.created')->count());
    }

    public function test_different_content_makes_a_second_ticket(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());
        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload(['description' => 'A different problem altogether.']));

        $this->assertSame(2, Ticket::query()->where('subject', 'Printer is jammed')->count());
    }

    public function test_the_same_files_count_as_a_repeat_and_different_files_do_not(): void
    {
        $client = $this->createClient();
        $withFile = fn (string $name, int $kilobytes): array => $this->clientPayload(['attachments' => [UploadedFile::fake()->create($name, $kilobytes, 'application/pdf')]]);

        $this->actingAs($client)->post(route('client.tickets.store'), $withFile('screenshot.pdf', 12));
        $this->actingAs($client)->post(route('client.tickets.store'), $withFile('screenshot.pdf', 12));
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Attachment::query()->count(), 'the repeat must not store its files again');

        $this->actingAs($client)->post(route('client.tickets.store'), $withFile('other-photo.pdf', 12));
        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_two_different_people_sending_the_same_words_each_get_a_ticket(): void
    {
        $alice = $this->createClient('Alice', 'alice@example.com');
        $bob = $this->createClient('Bob', 'bob@example.com');

        $this->actingAs($alice)->post(route('client.tickets.store'), $this->clientPayload());
        $this->actingAs($bob)->post(route('client.tickets.store'), $this->clientPayload());

        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame([$alice->id, $bob->id], Ticket::query()->orderBy('id')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_a_repeat_after_ten_minutes_is_a_new_ticket(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());
        $this->travel(11)->minutes();
        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());

        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_a_repeat_just_inside_ten_minutes_is_still_a_repeat(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());
        $this->travel(9)->minutes();
        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_repeat_after_the_first_ticket_was_deleted_creates_a_new_one(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());
        Ticket::query()->firstOrFail()->delete();
        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(2, SystemLog::query()->where('event_type', 'ticket.created')->count());
    }

    public function test_an_invalid_form_is_not_remembered(): void
    {
        $client = $this->createClient();
        $invalid = $this->clientPayload(['subject' => '']);

        $this->actingAs($client)->post(route('client.tickets.store'), $invalid)->assertSessionHasErrors('subject');
        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_request_that_waits_too_long_for_the_first_one_creates_nothing_and_says_so(): void
    {
        $client = $this->createClient();
        $payload = $this->clientPayload();
        DuplicateSubmissionGuard::$waitSeconds = 0;

        $fingerprint = DuplicateSubmissionGuard::fingerprint('client', $client->id, Request::create('/', 'POST', $payload));
        $firstRequestStillWorking = Cache::lock('ticket-submission:'.$fingerprint.':lock', 60);
        $this->assertTrue($firstRequestStillWorking->get());

        $this->actingAs($client)->from(route('client.tickets.create'))->post(route('client.tickets.store'), $payload)
            ->assertRedirect(route('client.tickets.create'))
            ->assertSessionHas('error', 'Your ticket is still being sent. Please wait a moment and check My Tickets before sending it again.');

        $this->assertSame(0, Ticket::query()->count());

        $firstRequestStillWorking->release();
        $this->actingAs($client)->post(route('client.tickets.store'), $payload);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_the_staff_form_sent_twice_makes_one_ticket(): void
    {
        $admin = $this->createUser('Staff Admin', 'staff-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Phone Caller', 'phone-caller@example.com');
        $payload = $this->staffPayload($client);

        $this->actingAs($admin)->post(route('admin.tickets.store'), $payload)
            ->assertSessionHas('success', 'Ticket created successfully.');
        $ticket = Ticket::query()->where('subject', 'Printer is jammed')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.tickets.store'), $payload)
            ->assertRedirect(route('admin.tickets.show', $ticket))
            ->assertSessionHas('success', 'This ticket was already created a moment ago, so it was not created again.');

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, SystemLog::query()->where('event_type', 'ticket.created_by_support_user')->count());
        $this->assertCount(1, Mail::queued(TicketAlertMail::class, fn (TicketAlertMail $mail): bool => $mail->hasTo('phone-caller@example.com')));
    }

    public function test_the_staff_form_for_another_requester_is_a_new_ticket(): void
    {
        $admin = $this->createUser('Staff Admin', 'staff-admin@example.com', User::ROLE_ADMIN);
        $one = $this->createClient('Caller One', 'caller-one@example.com');
        $two = $this->createClient('Caller Two', 'caller-two@example.com');

        $this->actingAs($admin)->post(route('admin.tickets.store'), $this->staffPayload($one));
        $this->actingAs($admin)->post(route('admin.tickets.store'), $this->staffPayload($two));

        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_a_client_and_the_staff_form_do_not_swallow_each_other(): void
    {
        $admin = $this->createUser('Staff Admin', 'staff-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Both Ways', 'both-ways@example.com');

        $this->actingAs($client)->post(route('client.tickets.store'), $this->clientPayload());
        $this->actingAs($admin)->post(route('admin.tickets.store'), $this->staffPayload($client));

        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_a_failure_while_creating_is_not_remembered_and_the_lock_is_released(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            DuplicateSubmissionGuard::once('same-form', fn (): Ticket => throw new RuntimeException('disk full'));
        } finally {
            $created = DuplicateSubmissionGuard::once('same-form', fn (): Ticket => $this->createTicket($this->createClient(), $this->createCategory()));
            $this->assertFalse($created['repeat'], 'the failed attempt left nothing behind');
            $this->assertSame(1, Ticket::query()->count());
        }
    }

    public function test_the_guard_itself_waits_and_then_gives_up_when_the_lock_stays_taken(): void
    {
        DuplicateSubmissionGuard::$waitSeconds = 0;
        $lock = Cache::lock('ticket-submission:busy:lock', 60);
        $lock->get();

        $this->expectException(LockTimeoutException::class);

        DuplicateSubmissionGuard::once('busy', fn (): Ticket => $this->createTicket($this->createClient(), $this->createCategory()));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function clientPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Double Click',
            'contact_number' => '09171234567',
            'email' => 'double-click@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Printer is jammed',
            'description' => 'It jams on every page.',
            'category_id' => $this->category()->id,
            'ticket_consent' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(User $client): array
    {
        return [
            'user_id' => $client->id,
            'name' => $client->name,
            'contact_number' => '09170000000',
            'email' => $client->email,
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Printer is jammed',
            'description' => 'It jams on every page.',
            'category_id' => $this->category()->id,
            'ticket_type' => 'external',
        ];
    }

    private function category(): Category
    {
        return Category::query()->first() ?? $this->createCategory();
    }
}
