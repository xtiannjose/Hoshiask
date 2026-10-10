<?php

namespace App\Services\Observability;

use App\Services\SystemLogService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlowQueryTelemetryService
{
    private bool $recordingSystemLog = false;

    public function register(): void
    {
        $configuredEnabledFlag = config('observability.slow_queries.enabled');
        $slowQueryTelemetryEnabled = is_bool($configuredEnabledFlag)
            ? $configuredEnabledFlag
            : app()->environment(['staging', 'production']);

        if (! $slowQueryTelemetryEnabled) {
            return;
        }

        $thresholdMs = (int) config('observability.slow_queries.threshold_ms', 250);
        if ($thresholdMs < 1) {
            return;
        }

        $includeBindings = (bool) config('observability.slow_queries.include_bindings', false);
        $logToSystemLogs = (bool) config('observability.slow_queries.log_to_system_logs', false);

        DB::listen(function (QueryExecuted $query) use ($thresholdMs, $includeBindings, $logToSystemLogs): void {
            if ($query->time < $thresholdMs) {
                return;
            }

            $payload = [
                'connection' => $query->connectionName,
                'duration_ms' => round((float) $query->time, 2),
                'sql' => Str::limit($includeBindings ? (string) $query->toRawSql() : $query->sql, 4000),
            ];

            if ($includeBindings) {
                $payload['bindings'] = $this->sanitizeBindings($query->bindings);
            }

            Log::warning('Slow query detected.', $payload);

            if (! $logToSystemLogs || $this->recordingSystemLog) {
                return;
            }

            try {
                $this->recordingSystemLog = true;
                app(SystemLogService::class)->record(
                    'database.query.slow',
                    'Slow database query exceeded configured threshold.',
                    [
                        'category' => 'performance',
                        'metadata' => $payload,
                    ]
                );
            } catch (\Throwable $exception) {
                report($exception);
            } finally {
                $this->recordingSystemLog = false;
            }
        });
    }

    private function sanitizeBindings(array $bindings): array
    {
        return array_map(function (mixed $binding): mixed {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format(DATE_ATOM);
            }

            if (is_scalar($binding) || $binding === null) {
                if (is_string($binding)) {
                    return Str::limit($binding, 250);
                }

                return $binding;
            }

            return '['.get_debug_type($binding).']';
        }, $bindings);
    }
}
