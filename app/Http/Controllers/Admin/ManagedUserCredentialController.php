<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\ManagedUserAccessService;
use App\Services\Admin\ManagedUserCredentialService;

class ManagedUserCredentialController extends Controller
{
    public function __construct(
        private ManagedUserAccessService $managedUserAccess,
        private ManagedUserCredentialService $managedCredentials,
    ) {}

    public function resetManagedUserPassword(User $user)
    {
        $currentUser = auth()->user();

        $guardRedirect = $this->guardManagedCredentialAccess($currentUser, $user, 'resetManagedPassword');
        if ($guardRedirect !== null) {
            return $guardRedirect;
        }

        $this->managedCredentials->issueTemporaryPassword($user, $currentUser);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Temporary password issued. Reveal it once before it expires.');
    }

    public function revealManagedUserPassword(User $user)
    {
        $currentUser = auth()->user();

        $guardRedirect = $this->guardManagedCredentialAccess($currentUser, $user, 'revealManagedPassword');
        if ($guardRedirect !== null) {
            return $guardRedirect;
        }

        $temporaryPassword = $this->managedCredentials->revealTemporaryPassword($user);
        if ($temporaryPassword === null) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'No active temporary password is available for this account.');
        }

        return redirect()->route('admin.users.show', $user)
            ->with('managed_password_reveal', $temporaryPassword)
            ->with('success', 'Temporary password revealed once. Copy it now; it cannot be viewed again.');
    }

    private function guardManagedCredentialAccess(User $currentUser, User $targetUser, string $ability)
    {
        $baseAccessError = $this->managedUserAccess->credentialAccessBoundaryError($currentUser, $targetUser);
        if ($baseAccessError !== null) {
            return redirect()->route('admin.users.index')
                ->with('error', $baseAccessError);
        }

        if ($targetUser->isShadow()) {
            return redirect()->route('admin.users.show', $targetUser)
                ->with('error', 'Shadow account passwords cannot be revealed from user management.');
        }

        if ($targetUser->id === $currentUser->id) {
            return redirect()->route('admin.users.show', $targetUser)
                ->with('error', 'Use Account Settings to update your own password.');
        }

        $this->authorize($ability, $targetUser);

        return null;
    }
}
