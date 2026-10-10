<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnlineUsers\ClearSignInLockoutRequest;
use App\Models\User;
use App\Services\Admin\SignInActivityService;
use App\Services\Admin\UserPresenceService;
use App\Services\SystemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineUserController extends Controller
{
    public function __construct(
        private UserPresenceService $presence,
        private SignInActivityService $signInActivity,
        private SystemLogService $systemLogs,
    ) {}

    public function index(Request $request)
    {
        $this->systemLogs->record(
            'online_users.viewed',
            'Viewed the online users monitor.',
            ['category' => 'system']
        );

        return view('admin.online-users.index', $this->liveViewData($request));
    }

    /**
     * The live section of the page, re-rendered for the page's auto-refresh.
     */
    public function live(Request $request): JsonResponse
    {
        return response()->json([
            'html' => view('admin.online-users.partials.live', $this->liveViewData($request))->render(),
        ]);
    }

    public function signOut(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back(fallback: route('admin.online-users.index'))->with('error', 'Use Sign out in the account menu to end your own session.');
        }

        $endedCount = $user->forceSignOut();

        $this->systemLogs->record(
            'user.sessions.revoked',
            'Signed a user out of all devices.',
            [
                'category' => 'security',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'ended_count' => $endedCount,
                ],
            ]
        );

        return back(fallback: route('admin.online-users.index'))->with('success', "{$user->name} has been signed out of all devices.");
    }

    public function clearLockout(ClearSignInLockoutRequest $request)
    {
        $login = trim($request->string('login')->toString());

        if (! $this->signInActivity->clearLockout($login)) {
            return back(fallback: route('admin.online-users.index'))->with('error', "\"{$login}\" is no longer locked out.");
        }

        $account = $this->signInActivity->accountForLogin($login);

        $this->systemLogs->record(
            'auth.lockout_cleared',
            'Cleared a sign-in lockout.',
            [
                'category' => 'security',
                'target_type' => $account ? User::class : null,
                'target_id' => $account?->id,
                'metadata' => [
                    'login' => $login,
                ],
            ]
        );

        return back(fallback: route('admin.online-users.index'))->with('success', "Sign-in lockout cleared for \"{$login}\".");
    }

    private function liveViewData(Request $request): array
    {
        $segment = $request->query('segment');
        $segment = is_string($segment) && in_array($segment, UserPresenceService::SEGMENTS, true) ? $segment : 'all';

        $onlineUsers = $this->presence->onlineUsers();

        return [
            'segment' => $segment,
            'summary' => $this->presence->summarize($onlineUsers),
            'onlineUsers' => $this->presence->filterBySegment($onlineUsers, $segment),
            'onlineWindowMinutes' => $this->presence->onlineWindowMinutes(),
            'failedSignIns' => $this->signInActivity->failedSignInSummary(),
            'failedSignInWindowHours' => SignInActivityService::FAILED_SIGN_IN_WINDOW_HOURS,
            'refreshedAt' => now(),
        ];
    }
}
