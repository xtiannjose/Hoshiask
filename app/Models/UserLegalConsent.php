<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UserLegalConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'terms_version',
        'privacy_version',
        'platform_consent_version',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hasCurrentConsentForUser(User $user): bool
    {
        if (! config('legal.require_acceptance', true)) {
            return true;
        }

        return static::query()
            ->where('user_id', $user->id)
            ->where('terms_version', (string) config('legal.terms_version'))
            ->where('privacy_version', (string) config('legal.privacy_version'))
            ->where('platform_consent_version', (string) config('legal.platform_consent_version'))
            ->exists();
    }

    public static function recordAcceptance(User $user, Request $request): self
    {
        return static::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'terms_version' => (string) config('legal.terms_version'),
                'privacy_version' => (string) config('legal.privacy_version'),
                'platform_consent_version' => (string) config('legal.platform_consent_version'),
            ],
            [
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }
}
