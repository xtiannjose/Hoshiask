<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\ManagedUserAccessService;
use App\Services\Admin\ManagedUserDeletionService;
use App\Services\SystemLogService;

class ManagedUserAccountController extends Controller
{
    public function __construct(
        private ManagedUserAccessService $managedUserAccess,
        private ManagedUserDeletionService $managedUserDeletion,
        private SystemLogService $systemLogs,
    ) {}

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        $error = $this->managedUserAccess->destroyError($currentUser, $user);
        if ($error !== null) {
            return redirect()->route('admin.users.index')
                ->with('error', $error);
        }

        $deletedUser = $this->managedUserDeletion->delete($user);

        $this->systemLogs->record(
            'user.deleted',
            'Deleted a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $deletedUser['deleted_user_id'],
                'metadata' => [
                    'role' => $deletedUser['deleted_user_role'],
                    'department' => $deletedUser['deleted_user_department'],
                ],
            ]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully. Ticket and chat history were preserved.');
    }

    public function toggleStatus(User $user)
    {
        $currentUser = auth()->user();

        $error = $this->managedUserAccess->toggleStatusError($currentUser, $user);
        if ($error !== null) {
            return response()->json(['error' => $error], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);
        $this->systemLogs->record(
            'user.status.toggled',
            'Toggled user account status.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'is_active' => (bool) $user->is_active,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.',
        ]);
    }
}
