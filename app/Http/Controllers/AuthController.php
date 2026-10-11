<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateAccountSettingsRequest;
use App\Models\CredentialHandoff;
use App\Models\User;
use App\Services\SystemLogService;
use App\Support\LoggedLoginText;
use App\Support\LoginThrottle;
use App\Support\PasswordHelp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * A syntactically valid bcrypt hash with no corresponding real password.
     * Used to keep Hash::check()'s timing constant whether or not the
     * submitted login matches a real account, preventing user enumeration
     * via response-time analysis.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /**
     * Set at sign-in and cleared at sign-out. It carries no data: its presence on a
     * signed-out visit means the session ran out rather than the person never signing in.
     */
    public const SIGNED_IN_HINT_COOKIE = 'helpdesk_signed_in';

    /** How long "stay signed in" lasts for staff: about two months, then they sign in again. Clients keep Laravel's default. */
    private const STAFF_REMEMBER_MINUTES = 60 * 24 * 60;

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->dashboardPath(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $loginInput = trim($request->string('login')->toString());
        $remember = $request->boolean('remember');
        $isEmailLogin = filter_var($loginInput, FILTER_VALIDATE_EMAIL) !== false;
        $accountLockKey = LoginThrottle::accountKey($loginInput, $request->ip());
        $addressKey = LoginThrottle::ipKey($request->ip());

        if (RateLimiter::tooManyAttempts($addressKey, LoginThrottle::IP_MAX_FAILURES_PER_MINUTE)) {
            return back()->withErrors([
                'login' => 'Too many failed sign-in attempts from this network. Wait a minute and try again.',
            ])->onlyInput('login');
        }

        if (RateLimiter::tooManyAttempts($accountLockKey, LoginThrottle::MAX_ATTEMPTS)) {
            $secondsUntilRetry = RateLimiter::availableIn($accountLockKey);

            return back()->withErrors([
                'login' => $this->loginLockoutErrorMessage($secondsUntilRetry),
            ])->onlyInput('login');
        }

        $matchedUser = $isEmailLogin
            ? User::query()->whereRaw('LOWER(email) = ?', [strtolower($loginInput)])->first()
            : User::query()->whereRaw('LOWER(username) = ?', [strtolower($loginInput)])->first();

        $passwordMatches = Hash::check(
            $request->password,
            $matchedUser->password ?? self::DUMMY_PASSWORD_HASH
        );

        if ($matchedUser && $passwordMatches) {
            if (! $matchedUser->is_active) {
                RateLimiter::hit($accountLockKey, LoginThrottle::LOCKOUT_SECONDS);
                RateLimiter::hit($addressKey, LoginThrottle::IP_WINDOW_SECONDS);
                $this->systemLogs->record(
                    'auth.login_failed',
                    'Failed login attempt: account inactive.',
                    [
                        'category' => 'security',
                        'target_type' => User::class,
                        'target_id' => $matchedUser->id,
                        'metadata' => [
                            'login' => $loginInput,
                            'method' => $isEmailLogin ? 'email' : 'username',
                            'reason' => 'inactive_account',
                        ],
                        'request' => $request,
                    ]
                );

                return back()->withErrors([
                    'login' => 'Your account is inactive. Please contact an administrator.',
                ])->onlyInput('login');
            }

            if ($remember && $matchedUser->canAccessAdminTickets()) {
                Auth::guard()->setRememberDuration(self::STAFF_REMEMBER_MINUTES);
            }

            Auth::login($matchedUser, $remember);
            $request->session()->regenerate();
            RateLimiter::clear($accountLockKey);
            cookie()->queue(cookie(self::SIGNED_IN_HINT_COOKIE, '1', 60 * 24 * 30, httpOnly: true, sameSite: 'lax'));
            $this->systemLogs->record(
                'auth.login',
                'User signed in.',
                [
                    'category' => 'auth',
                    'actor' => $matchedUser,
                    'target_type' => User::class,
                    'target_id' => $matchedUser->id,
                    'metadata' => [
                        'method' => $isEmailLogin ? 'email' : 'username',
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->to($this->resolvePostLoginRedirectPath($request, $matchedUser));
        }

        RateLimiter::hit($accountLockKey, LoginThrottle::LOCKOUT_SECONDS);
        RateLimiter::hit($addressKey, LoginThrottle::IP_WINDOW_SECONDS);
        $this->systemLogs->record(
            'auth.login_failed',
            'Failed login attempt: invalid credentials.',
            [
                'category' => 'security',
                'target_type' => $matchedUser ? User::class : null,
                'target_id' => $matchedUser?->id,
                'metadata' => [
                    // A password typed into this box by mistake must not end up in the log.
                    'login' => LoggedLoginText::forLog($loginInput, $matchedUser !== null),
                    'method' => $isEmailLogin ? 'email' : 'username',
                    'reason' => 'invalid_credentials',
                ],
                'request' => $request,
            ]
        );

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->systemLogs->record(
                'auth.logout',
                'User signed out.',
                [
                    'category' => 'auth',
                    'actor' => $user,
                    'target_type' => User::class,
                    'target_id' => $user->id,
                    'request' => $request,
                ]
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        cookie()->queue(cookie()->forget(self::SIGNED_IN_HINT_COOKIE));

        return redirect('/');
    }

    public function accountSettings()
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        $departmentOptions = User::allowedDepartments();

        return view('account.settings', compact('user', 'departmentOptions'));
    }

    public function updateAccountSettings(UpdateAccountSettingsRequest $request)
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        $wasForcedToChangePassword = $user->mustChangePassword();

        // A locked profile cannot be edited, but a required password change must still go through.
        if ($user->is_profile_locked && ! ($wasForcedToChangePassword && $request->filled('password'))) {
            return redirect()->route('account.settings')
                ->with('error', 'Your profile editing is locked. Please contact an administrator.');
        }

        $normalizedRole = $user->normalizedRole();
        $isSuperAdmin = in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
        $isUsernameLocked = in_array($normalizedRole, [User::ROLE_SUPER_USER, User::ROLE_TECHNICAL], true);
        $username = $isUsernameLocked
            ? (string) $user->username
            : $request->string('username')->toString();
        $email = $isSuperAdmin
            ? $request->input('email')
            : $user->email;

        $updateData = [];

        if (! $user->is_profile_locked) {
            $updateData = [
                'username' => $username !== '' ? $username : (string) $user->username,
                'name' => $request->string('name')->toString(),
                'phone' => $request->string('phone')->toString(),
            ];

            if ($isSuperAdmin) {
                $updateData['email'] = $email;
                $updateData['department'] = $request->string('department')->toString();
            }
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            $updateData['must_change_password'] = false;
            CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->delete();
        }

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return redirect()->route('account.settings')
                ->with('success', 'No changes were detected.');
        }

        $changedFields = array_keys($user->getDirty());
        $nonSensitiveChangedFields = array_values(array_filter(
            $changedFields,
            static fn (string $field): bool => $field !== 'password'
        ));

        $user->save();

        if ($request->filled('password')) {
            $user->invalidateSessions(session()->getId());
        }

        $this->systemLogs->record(
            'account.settings.updated',
            'Updated account settings.',
            [
                'category' => 'account',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'changed_fields' => $nonSensitiveChangedFields,
                ],
                'request' => $request,
            ]
        );

        if ($wasForcedToChangePassword && $request->filled('password')) {
            return redirect()->to($this->dashboardPath($user->fresh()))
                ->with('success', 'Password changed. You can now use the system.');
        }

        return redirect()->route('account.settings')
            ->with('success', 'Account settings updated successfully.');
    }

    private function dashboardPath(User $user): string
    {
        if ($user->canAccessAdminTickets() && $user->mustChangePassword()) {
            return '/account/settings';
        }

        if ($user->canAccessAdminTickets()) {
            return '/admin/dashboard';
        }

        return '/client/dashboard';
    }

    private function resolvePostLoginRedirectPath(Request $request, User $user): string
    {
        $fallbackPath = $this->dashboardPath($user);
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl)) {
            return $fallbackPath;
        }

        $intendedUrl = trim($intendedUrl);
        if ($intendedUrl === '') {
            return $fallbackPath;
        }

        $parsedUrl = parse_url($intendedUrl);
        if ($parsedUrl === false) {
            return $fallbackPath;
        }

        if (
            isset($parsedUrl['host'])
            && strcasecmp((string) $parsedUrl['host'], (string) $request->getHost()) !== 0
        ) {
            return $fallbackPath;
        }

        if (isset($parsedUrl['scheme']) && ! isset($parsedUrl['host'])) {
            return $fallbackPath;
        }

        $path = '/'.ltrim((string) ($parsedUrl['path'] ?? ''), '/');
        $query = isset($parsedUrl['query']) && $parsedUrl['query'] !== ''
            ? '?'.$parsedUrl['query']
            : '';

        if (! $this->userCanAccessPostLoginPath($user, $path)) {
            return $fallbackPath;
        }

        return $path.$query;
    }

    private function userCanAccessPostLoginPath(User $user, string $path): bool
    {
        if (in_array($path, ['/', '/login', '/logout', '/register'], true)) {
            return false;
        }

        if (str_starts_with($path, '/legal/acceptance')) {
            return true;
        }

        if (str_starts_with($path, '/attachments/')) {
            return true;
        }

        if (str_starts_with($path, '/account/settings')) {
            return ! $user->isClient();
        }

        if ($user->canAccessAdminTickets()) {
            return str_starts_with($path, '/admin/');
        }

        return str_starts_with($path, '/client/');
    }

    private function loginLockoutErrorMessage(int $secondsUntilRetry): string
    {
        $retryAfterSeconds = max(1, $secondsUntilRetry);
        $retryAfterMinutes = (int) ceil($retryAfterSeconds / 60);
        $unit = $retryAfterMinutes === 1 ? 'minute' : 'minutes';

        return "Too many sign-in attempts for this account. Try again in {$retryAfterMinutes} {$unit}. ".PasswordHelp::lockoutSentence();
    }
}
