<?php

namespace App\Providers;

use App\Services\Observability\SlowQueryTelemetryService;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(SlowQueryTelemetryService $slowQueryTelemetry): void
    {
        $slowQueryTelemetry->register();
    }
}
