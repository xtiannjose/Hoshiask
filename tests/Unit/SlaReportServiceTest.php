<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Services\Admin\Reports\SlaReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class SlaReportServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_it_counts_every_ticket_across_the_chunks_it_reads(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $created = Carbon::create(2026, 3, 10, 9, 0, 0);

        // 2,100 tickets is more than one chunk (2,000). Each hour-length is used by a known number of tickets.
        $hoursToTickets = [0 => 700, 2 => 600, 10 => 500, 30 => 300];
        $rows = [];
        foreach ($hoursToTickets as $hours => $count) {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'ticket_number' => sprintf('TK-%d-%04d', $hours, $i),
                    'name' => 'Requester', 'contact_number' => '09180000000', 'email' => 'r@example.com',
                    'province' => 'NCR', 'municipality' => 'Pasig', 'subject' => 'Bulk', 'description' => 'Bulk',
                    'status' => 'closed', 'user_id' => $client->id, 'category_id' => $category->id,
                    'ticket_type' => 'external', 'created_at' => $created, 'updated_at' => $created,
                    'resolved_at' => $created->copy()->addHours($hours)->addMinutes(5),
                    'closed_at' => $created->copy()->addHours($hours)->addMinutes(10),
                ];
            }
        }
        foreach (array_chunk($rows, 50) as $batch) {
            DB::table('tickets')->insert($batch);
        }

        $report = app(SlaReportService::class)->build(
            Ticket::query(),
            $created->copy()->startOfDay(),
            $created->copy()->endOfDay(),
            'March 2026'
        );

        $counts = collect($report['resolution_buckets'])->pluck('count', 'label')->all();
        $this->assertSame(
            ['Under 1 Hour' => 700, 'Under 4 Hours' => 600, 'Under 24 Hours' => 500, 'Above 24 Hours' => 300],
            $counts
        );
        $this->assertSame(33.3, collect($report['resolution_buckets'])->firstWhere('label', 'Under 1 Hour')['rate']);
    }
}
