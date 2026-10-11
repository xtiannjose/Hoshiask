<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The staff ticket search looks in the subject, ticket number, description, department and the requester's name and
 * email, ignoring case, and treats % and _ as plain characters. The query behind it is shaped so that PostgreSQL can
 * use its trigram indexes; these tests keep what it finds the same.
 */
class AdminTicketSearchTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $staff;

    private Ticket $byDescription;

    private Ticket $bySubject;

    private Ticket $byDepartment;

    private Ticket $byRequester;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
        $this->staff = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Maria Santos', 'maria.santos@example.com');
        $other = $this->createClient('Pedro Cruz', 'pedro.cruz@example.com');

        $this->bySubject = $this->createTicket($client, $category, ['subject' => 'Printer jams on every page', 'description' => 'Paper tray is stuck']);
        $this->byDescription = $this->createTicket($other, $category, ['subject' => 'Cannot sign in', 'description' => 'The VPN token expired yesterday']);
        $this->byDepartment = $this->createTicket($other, $category, ['subject' => 'Slow wifi', 'description' => 'Drops often', 'department' => 'Zebrafield']);
        $this->byRequester = $this->createTicket($client, $category, ['subject' => 'Monitor flickers', 'description' => 'Screen goes black']);
    }

    /** @return list<string> subjects found, sorted */
    private function found(string $term): array
    {
        $response = $this->actingAs($this->staff)->getJson(route('admin.tickets.index', ['tab' => 'all', 'search' => $term, 'partial' => 1]));
        $response->assertOk();
        $html = (string) $response->json('html');

        $subjects = [];
        foreach ([$this->bySubject, $this->byDescription, $this->byDepartment, $this->byRequester] as $ticket) {
            if (str_contains($html, $ticket->ticket_number)) {
                $subjects[] = $ticket->subject;
            }
        }
        sort($subjects);

        return $subjects;
    }

    public function test_it_finds_a_ticket_by_subject_ignoring_case(): void
    {
        $this->assertSame(['Printer jams on every page'], $this->found('PRINTER JAMS'));
    }

    public function test_it_finds_a_ticket_by_its_number_in_any_case(): void
    {
        $this->assertSame(['Slow wifi'], $this->found(strtolower($this->byDepartment->ticket_number)));
    }

    public function test_it_finds_a_ticket_by_a_word_in_its_description(): void
    {
        $this->assertSame(['Cannot sign in'], $this->found('vpn token'));
    }

    public function test_it_finds_a_ticket_by_its_department(): void
    {
        $this->assertSame(['Slow wifi'], $this->found('zebra'));
    }

    public function test_it_finds_tickets_by_the_requesters_name_or_email(): void
    {
        $expected = ['Monitor flickers', 'Printer jams on every page'];
        $this->assertSame($expected, $this->found('maria santos'));
        $this->assertSame($expected, $this->found('MARIA.SANTOS@'));
    }

    public function test_percent_and_underscore_match_themselves_not_everything(): void
    {
        $this->assertSame([], $this->found('%'));
        $this->assertSame([], $this->found('_'));
    }

    public function test_a_search_that_matches_nothing_shows_no_tickets(): void
    {
        $this->assertSame([], $this->found('no-such-text-anywhere'));
    }
}
