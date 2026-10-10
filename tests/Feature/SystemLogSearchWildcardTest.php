<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class SystemLogSearchWildcardTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_percent_and_underscore_in_the_search_box_match_themselves_not_everything(): void
    {
        $shadow = $this->createUser('Wildcard Shadow', 'wildcard-shadow@example.com', User::ROLE_SHADOW);
        $unlocked = [EnsureSystemLogsUnlocked::SESSION_KEY => now()->addHour()->toIso8601String()];

        foreach (['Rate limit reached at 100% load', 'Plain entry about tickets', 'Entry with a_b in it'] as $i => $description) {
            SystemLog::create([
                'category' => 'system',
                'event_type' => "wildcard.test.{$i}",
                'description' => $description,
                'occurred_at' => now(),
            ]);
        }

        $search = fn (string $term) => $this->actingAs($shadow)->withSession($unlocked)
            ->get(route('admin.system-logs.index', ['search' => $term, 'category' => 'system']));

        $search('100%')->assertOk()->assertSee('Rate limit reached at 100% load')->assertDontSee('Plain entry about tickets');
        $search('%')->assertOk()->assertSee('Rate limit reached at 100% load')->assertDontSee('Plain entry about tickets');
        $search('a_b')->assertOk()->assertSee('Entry with a_b in it')->assertDontSee('Plain entry about tickets');
        $search('PLAIN ENTRY')->assertOk()->assertSee('Plain entry about tickets');
    }
}
