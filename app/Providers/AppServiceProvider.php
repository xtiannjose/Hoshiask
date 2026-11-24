<?php

namespace App\Providers;

use App\Services\SupportBrandingService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SupportBrandingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies are auto-discovered by name (App\Models\X => App\Policies\XPolicy).

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureTrustedProxies();
        $this->logSlowQueries();

        // Only `npm run dev` creates the hot file, so there's nothing to check elsewhere.
        if ($this->app->isLocal()) {
            $this->deleteStaleViteHotFile();
        }
    }

    /**
     * Logs a warning for each query slower than the threshold. A blank OBSERVABILITY_SLOW_QUERY_ENABLED means on in
     * staging and production only. The SQL is logged without its values, which can hold personal data.
     */
    private function logSlowQueries(): void
    {
        $enabled = config('observability.slow_queries.enabled');
        $thresholdMs = (int) config('observability.slow_queries.threshold_ms');

        if (! (is_bool($enabled) ? $enabled : $this->app->environment(['staging', 'production'])) || $thresholdMs < 1) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
            if ($query->time >= $thresholdMs) {
                Log::warning('Slow query detected.', [
                    'connection' => $query->connectionName,
                    'duration_ms' => round((float) $query->time, 2),
                    'sql' => Str::limit($query->sql, 4000),
                ]);
            }
        });
    }

    private function configureTrustedProxies(): void
    {
        $configuredProxies = trim((string) config('security.trusted_proxies'));
        $proxies = $configuredProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $configuredProxies))));

        TrustProxies::at($proxies === [] ? ['127.0.0.1', '::1'] : $proxies);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    private function deleteStaleViteHotFile(): void
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) @file_get_contents($hotFile));
        if ($hotUrl === '') {
            @unlink($hotFile);

            return;
        }

        $parsedUrl = parse_url($hotUrl);
        if (! is_array($parsedUrl) || empty($parsedUrl['host'])) {
            @unlink($hotFile);

            return;
        }

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = (string) $parsedUrl['host'];
        $port = (int) ($parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80));

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
        if ($socket === false) {
            @unlink($hotFile);

            return;
        }

        fclose($socket);
    }
}
