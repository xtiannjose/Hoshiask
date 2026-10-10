<?php

namespace App\Services\Admin;

use App\Models\CannedResponse;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ManagedUserDeletionService
{
    /**
     * @return array{deleted_user_id:int, deleted_user_role:string, deleted_user_department:string}
     */
    public function delete(User $user): array
    {
        $deletedUserId = (int) $user->id;
        $deletedUserRole = User::normalizeRole($user->role);
        $deletedUserDepartment = (string) $user->department;

        DB::transaction(function () use ($user): void {
            $replacementUser = $this->replacementUserForDeletedAccount($user);

            Ticket::where('user_id', $user->id)->update([
                'user_id' => $replacementUser->id,
            ]);

            Ticket::where('closed_by', $user->id)->update([
                'closed_by' => $replacementUser->id,
            ]);

            TicketReply::where('user_id', $user->id)->update([
                'user_id' => $replacementUser->id,
            ]);

            // Both cascade on delete: shared team content would vanish with its author.
            KnowledgeArticle::where('created_by_user_id', $user->id)->update([
                'created_by_user_id' => $replacementUser->id,
            ]);

            CannedResponse::where('created_by_user_id', $user->id)->update([
                'created_by_user_id' => $replacementUser->id,
            ]);

            $this->removeDeletedUserTicketAssignments($user);

            $user->delete();
        });

        return [
            'deleted_user_id' => $deletedUserId,
            'deleted_user_role' => $deletedUserRole,
            'deleted_user_department' => $deletedUserDepartment,
        ];
    }

    private function replacementUserForDeletedAccount(User $deletedUser): User
    {
        $normalizedRole = $deletedUser->normalizedRole();
        $isSupportAccount = in_array($normalizedRole, User::TICKET_CONSOLE_ROLES, true);

        if ($isSupportAccount) {
            return User::firstOrCreate(
                ['email' => 'deleted.support@system.local'],
                [
                    'name' => 'Deleted Support Account',
                    'phone' => null,
                    'department' => User::supportDepartment(),
                    'role' => User::ROLE_TECHNICAL,
                    'password' => Hash::make(Str::random(64)),
                    'is_active' => false,
                ]
            );
        }

        return User::firstOrCreate(
            ['email' => 'deleted.client@system.local'],
            [
                'name' => 'Deleted Client Account',
                'phone' => null,
                'department' => User::supportDepartment(),
                'role' => User::ROLE_CLIENT,
                'password' => Hash::make(Str::random(64)),
                'is_active' => false,
            ]
        );
    }

    private function removeDeletedUserTicketAssignments(User $user): void
    {
        $primaryAssignedTicketIds = $this->primaryAssignedTicketIdsForUser($user);

        DB::table('ticket_assignments')
            ->where('user_id', $user->id)
            ->delete();

        $this->syncPrimaryTicketAssignmentsAfterUserDeletion((int) $user->id, $primaryAssignedTicketIds);
    }

    /**
     * @return array<int>
     */
    private function primaryAssignedTicketIdsForUser(User $user): array
    {
        return Ticket::query()
            ->where('assigned_to', $user->id)
            ->pluck('id')
            ->map(fn ($ticketId): int => (int) $ticketId)
            ->all();
    }

    /**
     * @param  array<int>  $ticketIds
     */
    private function syncPrimaryTicketAssignmentsAfterUserDeletion(int $deletedUserId, array $ticketIds): void
    {
        if ($ticketIds === []) {
            return;
        }

        $timestamp = now();
        Ticket::query()
            ->whereIn('id', $ticketIds)
            ->where('assigned_to', $deletedUserId)
            ->update([
                'assigned_to' => null,
                'updated_at' => $timestamp,
            ]);

        $replacementAssigneeMap = $this->nextPrimaryAssigneeIdsForTickets($ticketIds);
        if ($replacementAssigneeMap === []) {
            return;
        }

        $caseStatement = collect($replacementAssigneeMap)
            ->map(fn (int $assigneeId, int $ticketId): string => "WHEN {$ticketId} THEN {$assigneeId}")
            ->implode(' ');

        Ticket::query()
            ->whereIn('id', array_keys($replacementAssigneeMap))
            ->update([
                'assigned_to' => DB::raw("CASE id {$caseStatement} END"),
                'updated_at' => $timestamp,
            ]);
    }

    /**
     * @param  array<int>  $ticketIds
     * @return array<int, int>
     */
    private function nextPrimaryAssigneeIdsForTickets(array $ticketIds): array
    {
        return DB::table('ticket_assignments')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MIN(user_id) as next_assigned_to')
            ->groupBy('ticket_id')
            ->pluck('next_assigned_to', 'ticket_id')
            ->mapWithKeys(fn ($assigneeId, $ticketId): array => [
                (int) $ticketId => (int) $assigneeId,
            ])
            ->all();
    }
}
