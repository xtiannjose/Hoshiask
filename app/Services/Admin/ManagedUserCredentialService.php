<?php

namespace App\Services\Admin;

use App\Models\CredentialHandoff;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class ManagedUserCredentialService
{
    private const CACHE_KEY_PREFIX = 'managed-user-password-handoff:';

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function issueTemporaryPassword(User $targetUser, User $actor): void
    {
        $temporaryPassword = $this->generateTemporaryManagedPassword();
        $expiresAt = now()->addMinutes(10);
        $cacheToken = (string) Str::uuid();

        $existingHandoff = CredentialHandoff::query()
            ->where('target_user_id', $targetUser->id)
            ->first();

        $targetUser->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => ! $targetUser->isClient(),
        ])->save();
        $targetUser->invalidateSessions();

        if ($existingHandoff) {
            Cache::forget($this->secretCacheKey((string) $existingHandoff->temporary_password));
        }

        Cache::put(
            $this->secretCacheKey($cacheToken),
            Crypt::encryptString($temporaryPassword),
            $expiresAt
        );

        CredentialHandoff::query()->updateOrCreate(
            ['target_user_id' => $targetUser->id],
            [
                'issued_by_user_id' => $actor->id,
                'temporary_password' => $cacheToken,
                'expires_at' => $expiresAt,
                'revealed_at' => null,
                'consumed_at' => null,
            ]
        );

        $this->systemLogs->record(
            'user.password.handoff_issued',
            'Issued a one-time managed password handoff.',
            [
                'category' => 'security',
                'target_type' => User::class,
                'target_id' => $targetUser->id,
                'metadata' => [
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ]
        );
    }

    public function revealTemporaryPassword(User $targetUser): ?string
    {
        $handoff = CredentialHandoff::query()
            ->where('target_user_id', $targetUser->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $handoff) {
            return null;
        }

        $encryptedTemporaryPassword = Cache::pull(
            $this->secretCacheKey((string) $handoff->temporary_password)
        );

        if (! is_string($encryptedTemporaryPassword) || $encryptedTemporaryPassword === '') {
            $handoff->forceFill([
                'consumed_at' => now(),
            ])->save();

            return null;
        }

        try {
            $temporaryPassword = Crypt::decryptString($encryptedTemporaryPassword);
        } catch (Throwable) {
            $handoff->forceFill([
                'consumed_at' => now(),
            ])->save();

            return null;
        }

        $handoff->forceFill([
            'revealed_at' => $handoff->revealed_at ?? now(),
            'consumed_at' => now(),
        ])->save();

        $this->systemLogs->record(
            'user.password.handoff_revealed',
            'Revealed a one-time managed password handoff.',
            [
                'category' => 'security',
                'target_type' => User::class,
                'target_id' => $targetUser->id,
            ]
        );

        return $temporaryPassword;
    }

    private function generateTemporaryManagedPassword(): string
    {
        return strtoupper(Str::random(4)).'-'.Str::random(4).'-'.Str::random(8);
    }

    private function secretCacheKey(string $cacheToken): string
    {
        return self::CACHE_KEY_PREFIX.$cacheToken;
    }
}
