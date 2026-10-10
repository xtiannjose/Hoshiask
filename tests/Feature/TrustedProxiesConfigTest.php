<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // .env values reach the app through config, so drive the setting from config.
        config(['security.trusted_proxies' => '10.1.2.3']);
        (new AppServiceProvider($this->app))->boot();

        Route::get('/__trusted-proxy-ip', fn (Request $request) => $request->ip());
    }

    public function test_forwarded_client_ip_is_used_when_request_comes_from_a_configured_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('/__trusted-proxy-ip')
            ->assertOk()
            ->assertSeeText('203.0.113.9');
    }

    public function test_forwarded_header_is_ignored_when_request_comes_from_an_unlisted_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('/__trusted-proxy-ip')
            ->assertOk()
            ->assertSeeText('198.51.100.7');
    }
}
