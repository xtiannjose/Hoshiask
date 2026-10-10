<?php

namespace App\Http\Controllers;

use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request, HelpdeskOperationsStatusService $opsStatus): JsonResponse
    {
        $showDetails = $this->shouldShowDetails($request);
        $status = $opsStatus->buildStatus($showDetails);
        $payload = [
            'status' => $status['status'],
            'checked_at' => $status['checked_at'],
        ];

        if ($showDetails) {
            $payload += [
                'checks' => [
                    'database' => $status['database_reachable'] ? 'ok' : 'failed',
                    'php' => $status['php_supported'] ? 'ok' : 'failed',
                    'queue' => $status['queue_worker_required'] && $status['queue_worker_running'] !== true
                        ? 'degraded'
                        : 'ok',
                ],
                'queue' => [
                    'connection' => $status['queue_connection'],
                    'worker_required' => $status['queue_worker_required'],
                    'worker_running' => $status['queue_worker_running'],
                    'pending_jobs' => $status['pending_jobs'],
                    'failed_jobs' => $status['failed_jobs'],
                ],
                'warnings' => $status['warnings'],
                'advisories' => $status['advisories'],
            ];
        }

        return response()->json($payload, $status['status'] === 'failed' ? 503 : 200);
    }

    private function shouldShowDetails(Request $request): bool
    {
        $configuredToken = config('helpdesk.health.detail_token');
        if (! is_string($configuredToken) || trim($configuredToken) === '') {
            return false;
        }

        $providedToken = $request->bearerToken()
            ?: $request->header('X-Health-Token');

        return is_string($providedToken) && hash_equals($configuredToken, $providedToken);
    }
}
