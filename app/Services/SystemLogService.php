<?php

namespace App\Services;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class SystemLogService
{
    public function record(string $eventType, string $description, array $context = []): void
    {
        try {
            $actor = $this->resolveActor($context['actor'] ?? null);
            $request = $this->resolveRequest($context['request'] ?? null);

            SystemLog::create([
                'actor_user_id' => $actor?->id,
                'category' => (string) ($context['category'] ?? 'system'),
                'event_type' => $eventType,
                'target_type' => $context['target_type'] ?? null,
                'target_id' => isset($context['target_id']) ? (int) $context['target_id'] : null,
                'description' => $description,
                'metadata' => $this->sanitizeMetadata($context['metadata'] ?? []),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolveActor(mixed $actor): ?User
    {
        if ($actor instanceof User) {
            return $actor;
        }

        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    private function resolveRequest(mixed $request): Request
    {
        if ($request instanceof Request) {
            return $request;
        }

        $resolved = request();

        if ($resolved instanceof Request) {
            return $resolved;
        }

        return app(Request::class);
    }

    private function sanitizeMetadata(mixed $metadata): mixed
    {
        if (! is_array($metadata)) {
            return $metadata;
        }

        $sanitized = [];
        $sensitiveTokens = ['password', 'secret', 'token', 'authorization', 'cookie', 'session', 'api_key', 'apikey', 'passcode'];
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitiveKey = false;
            foreach ($sensitiveTokens as $token) {
                if (str_contains($normalizedKey, $token)) {
                    $isSensitiveKey = true;
                    break;
                }
            }

            if ($isSensitiveKey) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeMetadata($value)
                : $value;
        }

        return $sanitized;
    }
}
