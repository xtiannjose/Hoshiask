<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\TicketCategoryBuckets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCategoryBucketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buckets_no_longer_include_the_retired_security_category(): void
    {
        $this->assertSame(
            ['Hardware', 'Software', 'Network', 'Access / Permissions', 'Other'],
            TicketCategoryBuckets::labels()
        );
        $this->assertSame('Other', TicketCategoryBuckets::forCategoryName('Security'));
    }

    public function test_seeded_categories_map_to_the_expected_buckets(): void
    {
        $this->assertSame('Hardware', TicketCategoryBuckets::forCategoryName('Hardware'));
        $this->assertSame('Software', TicketCategoryBuckets::forCategoryName('Software'));
        $this->assertSame('Network', TicketCategoryBuckets::forCategoryName('Network & Connectivity'));
        $this->assertSame('Access / Permissions', TicketCategoryBuckets::forCategoryName('Account & Access'));
        $this->assertSame('Other', TicketCategoryBuckets::forCategoryName('Other'));
        $this->assertSame('Other', TicketCategoryBuckets::forCategoryName(null));
    }

    public function test_filter_keys_round_trip_and_legacy_security_key_maps_to_other(): void
    {
        foreach (TicketCategoryBuckets::labels() as $label) {
            $this->assertSame($label, TicketCategoryBuckets::labelForFilterKey(TicketCategoryBuckets::filterKey($label)));
        }

        $this->assertSame('access_permissions', TicketCategoryBuckets::filterKey('Access / Permissions'));
        $this->assertSame('Access / Permissions', TicketCategoryBuckets::labelForFilterKey('access/permissions'));
        $this->assertSame('Other', TicketCategoryBuckets::labelForFilterKey('security'));
        $this->assertNull(TicketCategoryBuckets::labelForFilterKey('not-a-bucket'));
        $this->assertNull(TicketCategoryBuckets::labelForFilterKey(''));
    }

    public function test_sql_bucket_filter_matches_php_bucketing_for_every_category(): void
    {
        foreach (['Hardware & Software', 'Software', 'Network & Connectivity', 'Account & Access', 'Printer', 'Other'] as $name) {
            Category::create(['name' => $name, 'description' => $name, 'color' => '#000000', 'is_active' => true]);
        }

        foreach (TicketCategoryBuckets::labels() as $label) {
            $sqlMatches = Category::query()
                ->where(fn ($query) => TicketCategoryBuckets::constrainCategoryQuery($query, $label))
                ->orderBy('name')
                ->pluck('name')
                ->all();

            $phpMatches = Category::query()
                ->orderBy('name')
                ->pluck('name')
                ->filter(fn (string $name) => TicketCategoryBuckets::forCategoryName($name) === $label)
                ->values()
                ->all();

            $this->assertSame($phpMatches, $sqlMatches, "Bucket {$label} must match the same categories in SQL and PHP.");
        }
    }
}
