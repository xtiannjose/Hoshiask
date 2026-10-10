<?php

namespace App\Services\Admin;

use App\Models\SystemLog;
use App\Models\User;
use App\Support\DeviceLabel;
use App\Support\LoginThrottle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sign-in history and failed sign-in monitoring for the shadow account, read from the auth
 * events AuthController already writes to the system log. Lockout status comes from the
 * live rate limiter rather than the log, so it always matches what the login form enforces.
 */
class SignInActivityService
{
    public const FAILED_SIGN_IN_WINDOW_HOURS = 24;

    /** Upper bound on failed attempts scanned, so a brute-force burst cannot slow the page down. */
    private const FAILED_SIGN_IN_SCAN_LIMIT = 1000;

    private const TOP_SOURCE_IP_LIMIT = 5;

    private const HISTORY_LIMIT = 15;

    /** Events recorded by the account itself; the others are admin actions taken on it. */
    private const SELF_HISTORY_EVENTS = [
        'auth.login',
        'auth.logout',
        'auth.login_failed',
    ];

    private const ADMIN_HISTORY_EVENTS = [
        'auth.lockout_cleared',
        'user.sessions.revoked',
    ];

    /**
     * Failed sign-ins over the last day, grouped by the login name that was tried and by source IP.
     *
     * @return array{
     *     total: int,
     *     by_login: array<int, array{login: string, account: ?User, attempts: int, ip_addresses: array<int, string>, last_attempt_at: Carbon, reason: string, locked_for_seconds: ?int}>,
     *     by_ip: array<int, array{ip_address: string, attempts: int, logins: int, last_attempt_at: Carbon}>
     * }
     */
    public function failedSignInSummary(): array
    {
        $attempts = SystemLog::query()
            ->where('event_type', 'auth.login_failed')
            ->where('occurred_at', '>=', now()->subHours(self::FAILED_SIGN_IN_WINDOW_HOURS))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::FAILED_SIGN_IN_SCAN_LIMIT)
            ->get(['id', 'target_id', 'metadata', 'ip_address', 'occurred_at']);

        // Attempts arrive newest first, so the first attempt in each group is the latest one.
        $attemptsByLogin = [];
        $attemptsByIp = [];
        foreach ($attempts as $attempt) {
            $login = $this->attemptedLogin($attempt);
            if ($login !== '') {
                $attemptsByLogin[$login][] = $attempt;
            }
            if (filled($attempt->ip_address)) {
                $attemptsByIp[$attempt->ip_address][] = $attempt;
            }
        }

        $accounts = User::query()
            ->whereIn('id', $attempts->pluck('target_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $byLogin = [];
        foreach ($attemptsByLogin as $login => $loginAttempts) {
            // Array keys turn numeric-looking logins into ints.
            $login = (string) $login;
            $latest = $loginAttempts[0];
            $accountId = collect($loginAttempts)->map(fn (SystemLog $attempt) => $attempt->target_id)->filter()->first();
            $account = $accountId ? $accounts->get($accountId) : null;

            $byLogin[] = [
                'login' => $login,
                'account' => $account,
                'attempts' => count($loginAttempts),
                'ip_addresses' => collect($loginAttempts)->map(fn (SystemLog $attempt) => $attempt->ip_address)->filter()->unique()->values()->all(),
                'last_attempt_at' => $latest->occurred_at,
                'reason' => $this->failureReasonLabel($latest, $account !== null),
                'locked_for_seconds' => $this->lockedForSeconds($login, $this->addressesOf($loginAttempts)),
            ];
        }
        // Locked login names first, then the most recent attempts.
        usort($byLogin, fn (array $left, array $right) => [$right['locked_for_seconds'] !== null, $right['last_attempt_at']->getTimestamp()]
            <=> [$left['locked_for_seconds'] !== null, $left['last_attempt_at']->getTimestamp()]);

        $byIp = [];
        foreach ($attemptsByIp as $ipAddress => $ipAttempts) {
            $byIp[] = [
                'ip_address' => (string) $ipAddress,
                'attempts' => count($ipAttempts),
                'logins' => collect($ipAttempts)->map(fn (SystemLog $attempt) => $this->attemptedLogin($attempt))->filter()->unique()->count(),
                'last_attempt_at' => $ipAttempts[0]->occurred_at,
            ];
        }
        usort($byIp, fn (array $left, array $right) => $right['attempts'] <=> $left['attempts']);

        return [
            'total' => $attempts->count(),
            'by_login' => $byLogin,
            'by_ip' => array_slice($byIp, 0, self::TOP_SOURCE_IP_LIMIT),
        ];
    }

    /**
     * Seconds left on a login name's sign-in lockout, or null when it is not locked. The lock is kept per address,
     * so this looks at every address the login failed from and reports the longest wait.
     *
     * @param  array<int, string>  $ipAddresses
     */
    public function lockedForSeconds(string $login, array $ipAddresses): ?int
    {
        $longest = null;

        foreach ($this->lockKeys($login, $ipAddresses) as $key) {
            if (RateLimiter::tooManyAttempts($key, LoginThrottle::MAX_ATTEMPTS)) {
                $longest = max($longest ?? 0, max(1, RateLimiter::availableIn($key)));
            }
        }

        return $longest;
    }

    /**
     * Lift a login name's sign-in lockout early, from every address. Returns false when it was not locked.
     */
    public function clearLockout(string $login): bool
    {
        $addresses = $this->failedFromAddresses($login);

        if ($this->lockedForSeconds($login, $addresses) === null) {
            return false;
        }

        foreach ($this->lockKeys($login, $addresses) as $key) {
            RateLimiter::clear($key);
        }

        return true;
    }

    /**
     * The addresses a login name failed to sign in from over the last day.
     *
     * @return array<int, string>
     */
    public function failedFromAddresses(string $login): array
    {
        $login = mb_strtolower(trim($login));

        return SystemLog::query()
            ->where('event_type', 'auth.login_failed')
            ->where('occurred_at', '>=', now()->subHours(self::FAILED_SIGN_IN_WINDOW_HOURS))
            ->orderByDesc('id')
            ->limit(self::FAILED_SIGN_IN_SCAN_LIMIT)
            ->get(['metadata', 'ip_address'])
            ->filter(fn (SystemLog $attempt) => $this->attemptedLogin($attempt) === $login)
            ->pluck('ip_address')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, SystemLog>  $attempts
     * @return array<int, string>
     */
    private function addressesOf(array $attempts): array
    {
        return collect($attempts)->pluck('ip_address')->filter()->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $ipAddresses
     * @return array<int, string>
     */
    private function lockKeys(string $login, array $ipAddresses): array
    {
        return array_values(array_unique([
            LoginThrottle::accountKey($login),
            ...array_map(fn (string $ip): string => LoginThrottle::accountKey($login, $ip), $ipAddresses),
        ]));
    }

    /**
     * The account a login name signs in to, matched the same way the login form does.
     */
    public function accountForLogin(string $login): ?User
    {
        $login = trim($login);
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'username';

        return User::query()
            ->whereRaw("LOWER({$column}) = ?", [mb_strtolower($login)])
            ->first();
    }

    /**
     * Recent sign-in events for one account, newest first.
     *
     * @return array<int, array{label: string, tone: string, occurred_at: Carbon, ip_address: ?string, device: ?string, actor_name: ?string}>
     */
    public function historyForUser(User $user): array
    {
        $logs = SystemLog::query()
            ->with('actor')
            ->where('target_type', User::class)
            ->where('target_id', $user->id)
            ->whereIn('event_type', [...self::SELF_HISTORY_EVENTS, ...self::ADMIN_HISTORY_EVENTS])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return array_values($logs->map(fn (SystemLog $log) => $this->historyRow($log))->all());
    }

    /**
     * @return array{label: string, tone: string, occurred_at: Carbon, ip_address: ?string, device: ?string, actor_name: ?string}
     */
    private function historyRow(SystemLog $log): array
    {
        // Admin actions are recorded from the admin's own browser, so their IP and device
        // say nothing about this account; show who took the action instead.
        $isSelfEvent = in_array($log->event_type, self::SELF_HISTORY_EVENTS, true);

        [$label, $tone] = match ($log->event_type) {
            'auth.login' => ['Signed in', 'success'],
            'auth.logout' => ['Signed out', 'neutral'],
            'auth.login_failed' => ['Failed sign-in: '.strtolower($this->failureReasonLabel($log, true)), 'danger'],
            'auth.lockout_cleared' => ['Sign-in lockout cleared', 'warning'],
            'user.sessions.revoked' => ['Signed out of all devices', 'warning'],
            default => [(string) $log->description, 'neutral'],
        };

        return [
            'label' => $label,
            'tone' => $tone,
            'occurred_at' => $log->occurred_at,
            'ip_address' => $isSelfEvent ? $log->ip_address : null,
            'device' => $isSelfEvent ? DeviceLabel::fromUserAgent($log->user_agent) : null,
            'actor_name' => $isSelfEvent ? null : $log->actor?->name,
        ];
    }

    private function failureReasonLabel(SystemLog $attempt, bool $accountExists): string
    {
        return match (true) {
            ($attempt->metadata['reason'] ?? null) === 'inactive_account' => 'Account deactivated',
            $accountExists => 'Wrong password',
            default => 'Unknown account',
        };
    }

    private function attemptedLogin(SystemLog $attempt): string
    {
        return mb_strtolower(trim((string) ($attempt->metadata['login'] ?? '')));
    }
}
