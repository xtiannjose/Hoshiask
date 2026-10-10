<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\CredentialHandoff;
use App\Models\User;
use App\Services\Admin\ManagedUserAccessService;
use App\Services\Admin\SignInActivityService;
use App\Services\Admin\UserDirectoryService;
use App\Services\Admin\UserPresenceService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ManagedUserProfileController extends Controller
{
    public function __construct(
        private ManagedUserAccessService $managedUserAccess,
        private SystemLogService $systemLogs,
        private UserDirectoryService $userDirectory,
        private UserPresenceService $presence,
        private SignInActivityService $signInActivity,
    ) {}

    public function show(Request $request, User $user)
    {
        $currentUser = auth()->user();

        $error = $this->managedUserAccess->showError($currentUser, $user);
        if ($error !== null) {
            return redirect()->route('admin.users.index')
                ->with('error', $error);
        }

        // Presence and sign-in history are shadow-only; the history also sits behind the
        // monitoring password lock because it exposes IP addresses.
        $isOnline = $currentUser->isShadow() && $this->presence->isOnline($user);
        $signInActivity = $currentUser->isShadow() && EnsureSystemLogsUnlocked::isUnlocked($request)
            ? $this->signInActivity->historyForUser($user)
            : null;

        $statistics = $this->userDirectory->buildUserTicketStatistics($user);
        $statisticsLinks = $this->userDirectory->buildUserStatisticsLinks($user, $statistics['show_assigned']);
        $recentTickets = $this->userDirectory->recentTicketsForUser($user);
        $canRevealManagedPassword = $currentUser->can('revealManagedPassword', $user);
        $activeCredentialHandoff = null;

        if ($canRevealManagedPassword) {
            $activeCredentialHandoff = CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->first();
        }

        $revealedManagedPassword = session('managed_password_reveal');

        return view('admin.users.show', compact(
            'user',
            'statistics',
            'statisticsLinks',
            'recentTickets',
            'canRevealManagedPassword',
            'activeCredentialHandoff',
            'revealedManagedPassword',
            'isOnline',
            'signInActivity'
        ));
    }

    public function edit(Request $request, User $user)
    {
        $currentUser = auth()->user();

        $error = $this->managedUserAccess->editError($currentUser, $user);
        if ($error !== null) {
            return redirect()->route('admin.users.index')
                ->with('error', $error);
        }

        $availableRoles = $currentUser->manageableUserRoleOptions($user);
        $canEditPassword = $currentUser->canEditManagedUserPassword($user);
        $returnTo = $this->managedUserAccess->resolveReturnUrl($request->query('return_to'), $user);

        return view('admin.users.edit', compact('user', 'availableRoles', 'canEditPassword', 'returnTo'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $currentUser = auth()->user();
        $stayOnEdit = $request->boolean('stay_on_edit');
        $returnToInput = $request->input('return_to');
        $hasReturnTo = is_string($returnToInput) && trim($returnToInput) !== '';
        $returnTo = $hasReturnTo
            ? $this->managedUserAccess->resolveReturnUrl($returnToInput, $user)
            : route('admin.users.index', absolute: false);

        $error = $this->managedUserAccess->updateError($currentUser, $user);
        if ($error !== null) {
            return redirect()->route('admin.users.index')
                ->with('error', $error);
        }

        $requestedRole = User::normalizeRole($request->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;
        $canManageClientNotes = $currentUser->isShadow() && $willBeClientRole;

        $role = $request->string('role')->toString();
        $department = $this->userDirectory->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->userDirectory->normalizeRoleForPersistence($role);
        $requestedIsProfileLocked = $request->boolean('is_profile_locked');
        $requestedUsername = $request->filled('username')
            ? $request->string('username')->toString()
            : (string) $user->username;

        if ($user->is_profile_locked) {
            $isChangingProfileFieldsWhileLocked = (
                $requestedUsername !== (string) ($user->username ?? '')
                || (string) $request->string('name')->toString() !== (string) $user->name
                || (string) $request->string('email')->toString() !== (string) $user->email
                || (string) $request->string('phone')->toString() !== (string) ($user->phone ?? '')
                || (string) $department !== (string) $user->department
                || (string) $persistedRole !== (string) $user->normalizedRole()
                || ($canManageClientNotes && (string) trim($request->string('client_notes')->toString()) !== (string) ($user->client_notes ?? ''))
                || $request->boolean('is_active') !== (bool) $user->is_active
                || $request->filled('password')
            );

            if ($isChangingProfileFieldsWhileLocked) {
                return back()
                    ->withInput()
                    ->with('error', 'Profile editing is locked. Unlock it first before changing other fields.');
            }
        }

        $updateData = [
            'username' => $requestedUsername,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'role' => $persistedRole,
            'is_active' => $request->boolean('is_active'),
            'is_profile_locked' => $requestedIsProfileLocked,
        ];

        if ($persistedRole !== User::ROLE_CLIENT) {
            $updateData['client_notes'] = null;
        } elseif ($currentUser->isShadow()) {
            $updateData['client_notes'] = trim($request->string('client_notes')->toString()) ?: null;
        } else {
            $updateData['client_notes'] = $user->client_notes;
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
            return $this->redirectAfterManagedUserUpdate($user, $stayOnEdit, 'No changes were detected.', $returnTo, $hasReturnTo);
        }

        $changedFields = array_keys($user->getDirty());
        $nonSensitiveChangedFields = array_values(array_filter(
            $changedFields,
            static fn (string $field): bool => $field !== 'password'
        ));

        $user->save();

        // A new password or a new role means the old sessions (and remember-me cookie) must not keep working.
        // The password itself is never logged, only the fact that it changed.
        $passwordChanged = in_array('password', $changedFields, true);
        $sessionsEnded = 0;
        if ($passwordChanged || in_array('role', $changedFields, true)) {
            $sessionsEnded = $user->is($currentUser)
                ? $user->invalidateSessions($request->session()->getId())
                : $user->forceSignOut();
        }

        $this->systemLogs->record(
            'user.updated',
            'Updated a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'changed_fields' => $nonSensitiveChangedFields,
                    'credentials_changed' => $passwordChanged,
                    'sign_ins_ended' => $sessionsEnded,
                ],
                'request' => $request,
            ]
        );

        return $this->redirectAfterManagedUserUpdate($user, $stayOnEdit, 'User updated successfully.', $returnTo, $hasReturnTo);
    }

    private function redirectAfterManagedUserUpdate(User $user, bool $stayOnEdit, string $message, string $returnTo, bool $hasReturnTo)
    {
        if ($stayOnEdit) {
            $routeParameters = ['user' => $user];
            if ($hasReturnTo) {
                $routeParameters['return_to'] = $returnTo;
            }

            return redirect()->route('admin.users.edit', $routeParameters)
                ->with('success', $message);
        }

        return redirect()->to($returnTo)
            ->with('success', $message);
    }
}
