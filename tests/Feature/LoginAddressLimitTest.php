<?php

namespace Tests\Feature;

use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * One office often shares one network address. Only failed sign-ins count against an address, so a whole office
 * signing in at 8 o'clock is never locked out, while one address trying many logins is stopped.
 */
class LoginAddressLimitTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const OFFICE = '198.51.100.20';

    private function attempt(string $login, string $password, string $ip = self::OFFICE)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('login'), ['login' => $login, 'password' => $password]);
    }

    public function test_the_sign_in_route_does_not_count_every_request_against_a_tiny_limit(): void
    {
        $middleware = Route::getRoutes()->getByName('login.attempt')->gatherMiddleware();

        $this->assertNotContains('throttle:5,1', $middleware);
        $this->assertContains('throttle:60,1', $middleware);
    }

    public function test_many_successful_sign_ins_from_one_address_are_never_refused(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createClient("Office Person {$i}", "office-{$i}@example.com", ['username' => "office.{$i}"]);
        }

        for ($i = 1; $i <= 12; $i++) {
            $this->attempt("office.{$i}", 'password123')->assertSessionHasNoErrors();
            $this->assertAuthenticated();
            $this->post(route('logout'));
        }
    }

    public function test_one_address_trying_many_logins_is_stopped_after_its_failures_pile_up(): void
    {
        $this->createClient('Real Person', 'real@example.com', ['username' => 'real.person']);

        for ($i = 1; $i <= LoginThrottle::IP_MAX_FAILURES_PER_MINUTE; $i++) {
            $this->attempt("guess.{$i}", 'wrong-guess');
        }

        // Even the right password is refused from this address for the rest of the minute...
        $this->attempt('real.person', 'password123')->assertSessionHasErrors('login');
        $this->assertStringContainsString('from this network', session('errors')->first('login'));
        $this->assertGuest();

        // ...and another address is not affected.
        $this->attempt('real.person', 'password123', '203.0.113.99')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }
}
