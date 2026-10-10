<?php

namespace Tests\Unit;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use PHPUnit\Framework\TestCase;

class TicketPriorityPresentationTest extends TestCase
{
    public function test_severity_badges_follow_urgency_order(): void
    {
        $severityOne = new Ticket(['priority' => 'severity_1']);
        $severityTwo = new Ticket(['priority' => 'severity_2']);
        $severityThree = new Ticket(['priority' => 'severity_3']);

        $this->assertSame('bg-red-100 text-red-800', $severityOne->priority_badge_class);
        $this->assertSame('bg-amber-100 text-amber-800', $severityTwo->priority_badge_class);
        $this->assertSame('bg-emerald-100 text-emerald-800', $severityThree->priority_badge_class);
    }

    public function test_report_chart_colours_follow_the_same_urgency_order_as_badges(): void
    {
        // Severity 1 is the most urgent: red in charts as well as on badges.
        $this->assertSame('#ef4444', TicketPriority::Severity1->chartColor());
        $this->assertSame('#f59e0b', TicketPriority::Severity2->chartColor());
        $this->assertSame('#10b981', TicketPriority::Severity3->chartColor());
    }

    public function test_tickets_without_a_severity_share_one_pending_review_label(): void
    {
        $this->assertSame('Severity not set', (new Ticket(['priority' => null]))->priority_label);
        $this->assertSame('Severity not set', TicketPriority::labelFor(null));
        $this->assertSame('Severity 2', TicketPriority::labelFor('severity_2'));
    }

    public function test_status_lists_and_labels_come_from_the_status_enum(): void
    {
        $this->assertSame(['open', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled'], Ticket::STATUSES);
        $this->assertSame(['open', 'in_progress', 'pending'], Ticket::OPEN_STATUSES);
        $this->assertSame(['resolved', 'closed'], Ticket::CLOSED_STATUSES);
        $this->assertSame(['cancelled'], Ticket::CANCELLED_STATUSES);
        $this->assertSame(array_column(TicketStatus::cases(), 'value'), Ticket::STATUSES);
        $this->assertSame('In Progress', (new Ticket(['status' => 'in_progress']))->status_label);
    }
}
