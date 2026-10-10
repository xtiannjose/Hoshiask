<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Laravel counts every unnamed `throttle:N,M` limit of one signed-in person in the same bucket, so a page that is
 * polled often used up the small allowance of "create a ticket" (10 a minute). Each limit has its own bucket here.
 */
class ThrottleBucketsTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'throttle:120,1'])->get('/_probe/busy', fn () => 'ok');
        Route::middleware(['web', 'auth', 'throttle:10,1'])->get('/_probe/rare', fn () => 'ok');
    }

    public function test_requests_to_a_busy_route_do_not_use_up_the_allowance_of_a_rarely_used_one(): void
    {
        $client = $this->createClient('Throttle Client', 'throttle-client@example.com');

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($client)->get('/_probe/busy')->assertOk();
        }

        $this->actingAs($client)->get('/_probe/rare')->assertOk();
    }

    public function test_a_limit_still_stops_the_route_it_belongs_to(): void
    {
        $client = $this->createClient('Limit Client', 'limit-client@example.com');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($client)->get('/_probe/rare')->assertOk();
        }

        $this->actingAs($client)->get('/_probe/rare')->assertStatus(429);
        // The busy route has its own, larger allowance and is unaffected.
        $this->actingAs($client)->get('/_probe/busy')->assertOk();
    }

    public function test_two_people_never_share_a_bucket(): void
    {
        $first = $this->createClient('First Client', 'first-client@example.com');
        $second = $this->createClient('Second Client', 'second-client@example.com');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($first)->get('/_probe/rare')->assertOk();
        }

        $this->actingAs($first)->get('/_probe/rare')->assertStatus(429);
        $this->actingAs($second)->get('/_probe/rare')->assertOk();
    }

    public function test_the_headers_count_down_the_allowance_of_the_route_called(): void
    {
        $client = $this->createClient('Header Client', 'header-client@example.com');

        $busy = $this->actingAs($client)->get('/_probe/busy');
        $this->assertSame('120', $busy->headers->get('X-RateLimit-Limit'));
        $this->assertSame('119', $busy->headers->get('X-RateLimit-Remaining'));

        $firstRare = $this->actingAs($client)->get('/_probe/rare');
        $this->assertSame('10', $firstRare->headers->get('X-RateLimit-Limit'));
        $this->assertSame('9', $firstRare->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('8', $this->actingAs($client)->get('/_probe/rare')->headers->get('X-RateLimit-Remaining'));
    }
}
