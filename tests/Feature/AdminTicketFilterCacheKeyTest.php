<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TicketController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminTicketFilterCacheKeyTest extends TestCase
{
    public function test_a_very_long_search_still_gives_a_short_cache_key_and_other_searches_give_other_keys(): void
    {
        $controller = $this->app->make(TicketController::class);
        $method = new \ReflectionMethod($controller, 'filterOptionsCacheKey');

        $key = fn (string $search): string => $method->invoke(
            $controller,
            Request::create('/admin/tickets', 'GET', ['search' => $search]),
            null,
            'tickets',
            '',
            null,
        );

        $long = $key(str_repeat('a very long search ', 100));

        $this->assertLessThan(200, strlen($long));
        $this->assertNotSame($long, $key('short'));
        $this->assertSame($key('same'), $key('same'));
    }
}
