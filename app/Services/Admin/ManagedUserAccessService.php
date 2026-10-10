<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ManagedUserAccessService
{
    public function __construct(
        private UserDirectoryService $userDirectory,
    ) {}

    public function showError(User $currentUser, User $targetUser): ?string
    {
        return $this->systemAccessError($targetUser)
            ?? $this->managementPermissionError($currentUser, $targetUser, 'You do not have permission to view this user.');
    }

    public function editError(User $currentUser, User $targetUser): ?string
    {
        return $this->systemAccessError($targetUser)
            ?? $this->selfTargetError($currentUser, $targetUser, 'Use Account Settings to edit your own account.')
            ?? $this->managementPermissionError($currentUser, $targetUser, 'You do not have permission to edit this user.');
    }

    public function updateError(User $currentUser, User $targetUser): ?string
    {
        return $this->systemModifyError($targetUser)
            ?? $this->selfTargetError($currentUser, $targetUser, 'Use Account Settings to edit your own account.')
            ?? $this->managementPermissionError($currentUser, $targetUser, 'You do not have permission to edit this user.');
    }

    public function destroyError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be deleted.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'You cannot delete your own account.';
        }

        if ($targetUser->is_profile_locked) {
            return 'Locked users cannot be deleted. Unlock the profile first.';
        }

        if (Gate::forUser($currentUser)->denies('delete', $targetUser)) {
            return $this->permissionDeniedMessage($currentUser, $targetUser, 'delete this user', 'deleted');
        }

        return null;
    }

    public function toggleStatusError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be modified.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'You cannot deactivate your own account.';
        }

        if (Gate::forUser($currentUser)->denies('toggleStatus', $targetUser)) {
            return $this->permissionDeniedMessage($currentUser, $targetUser, 'change this user status', 'deactivated');
        }

        return null;
    }

    /**
     * Both `delete` and `toggleStatus` policy checks already encode the single
     * source of truth for *whether* an action is allowed (via
     * `User::canManageUserTarget()`); this only picks the user-facing wording
     * for a denial that's already been decided.
     */
    private function permissionDeniedMessage(User $currentUser, User $targetUser, string $action, string $blockedVerb): string
    {
        if ($targetUser->isShadow()) {
            return $currentUser->isShadow()
                ? "This account cannot be {$blockedVerb}."
                : "You do not have permission to {$action}.";
        }

        if ($targetUser->normalizedRole() === User::ROLE_ADMIN) {
            return "Admin users cannot be {$blockedVerb}.";
        }

        return "You do not have permission to {$action}.";
    }

    public function credentialAccessBoundaryError(User $currentUser, User $targetUser): ?string
    {
        return $this->systemModifyError($targetUser)
            ?? $this->managementPermissionError($currentUser, $targetUser, 'You do not have permission to modify this user.');
    }

    public function resolveReturnUrl(mixed $candidate, User $targetUser): string
    {
        $default = $targetUser->isClient()
            ? route('admin.users.clients', absolute: false)
            : route('admin.users.index', absolute: false);

        if (! is_string($candidate)) {
            return $default;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return $default;
        }

        $parsed = parse_url($candidate);
        if ($parsed === false) {
            return $default;
        }

        if (
            isset($parsed['scheme'])
            || isset($parsed['host'])
            || isset($parsed['port'])
            || isset($parsed['user'])
            || isset($parsed['pass'])
        ) {
            return $default;
        }

        $path = '/'.ltrim((string) ($parsed['path'] ?? ''), '/');
        if (! in_array($path, ['/admin/users', '/admin/users/clients'], true)) {
            return $default;
        }

        $query = isset($parsed['query']) && $parsed['query'] !== ''
            ? '?'.$parsed['query']
            : '';

        return $path.$query;
    }

    private function isSystemReplacementUser(User $user): bool
    {
        return $user->isSystemReplacementAccount();
    }

    private function systemAccessError(User $targetUser): ?string
    {
        return $this->isSystemReplacementUser($targetUser)
            ? 'System archive users cannot be accessed from user management.'
            : null;
    }

    private function systemModifyError(User $targetUser): ?string
    {
        return $this->isSystemReplacementUser($targetUser)
            ? 'System archive users cannot be modified.'
            : null;
    }

    private function selfTargetError(User $currentUser, User $targetUser, string $message): ?string
    {
        return $targetUser->id === $currentUser->id
            ? $message
            : null;
    }

    private function managementPermissionError(User $currentUser, User $targetUser, string $message): ?string
    {
        return $this->userDirectory->cannotManageTarget($currentUser, $targetUser)
            ? $message
            : null;
    }
}
