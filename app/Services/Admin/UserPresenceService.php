<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\DeviceLabel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is signed in right now, read from the database session store. A session counts as
 * online while its last request falls inside the configured presence window; open pages
 * poll the server while visible, so an open tab keeps its session fresh.
 *
 * @phpstan-type OnlineSession array{ip_address: ?string, device: string, last_activity_at: Carbon, is_active: bool}
 * @phpstan-type PresenceFlag array{key: string, label: string, description: string}
 * @phpstan-type OnlineUserRow array{user: User, is_active: bool, last_activity_at: Carbon, sessions: array<int, OnlineSession>, flags: list<PresenceFlag>}
 */
class UserPresenceService
{
    public const SEGMENTS = ['all', 'staff', 'clients'];

    /**
     * Online users with their live sessions: flagged accounts first, then most recently active.
     *
     * @return array<int, OnlineUserRow>
     */
    public function onlineUsers(): array
    {
        // Session ids and payloads are never selected: nothing here should be able to leak them.
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $this->onlineSince()->getTimestamp())
            ->orderByDesc('last_activity')
            ->get(['user_id', 'ip_address', 'user_agent', 'last_activity']);

        if ($sessions->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $sessions->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $activeSince = $this->activeSince();
        $timezone = (string) config('app.timezone');

        $rows = [];
        foreach ($sessions->groupBy('user_id') as $userId => $userSessions) {
            $user = $users->get((int) $userId);
            if (! $user instanceof User) {
                continue;
            }

            $rows[] = $this->buildOnlineUserRow($user, $userSessions, $activeSince, $timezone);
        }

        usort($rows, fn (array $left, array $right) => [$right['flags'] !== [], $right['last_activity_at']->getTimestamp()]
            <=> [$left['flags'] !== [], $left['last_activity_at']->getTimestamp()]);

        return $rows;
    }

    /**
     * @param  Collection<int, \stdClass>  $userSessions
     * @return OnlineUserRow
     */
    private function buildOnlineUserRow(User $user, Collection $userSessions, Carbon $activeSince, string $timezone): array
    {
        $sessionRows = $userSessions
            ->map(function (object $session) use ($activeSince, $timezone) {
                $lastActivityAt = Carbon::createFromTimestamp((int) $session->last_activity, $timezone);

                return [
                    'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
                    'device' => DeviceLabel::fromUserAgent($session->user_agent),
                    'last_activity_at' => $lastActivityAt,
                    'is_active' => $lastActivityAt->gte($activeSince),
                ];
            })
            ->values()
            ->all();

        // Sessions arrive newest first, so the first one is the user's latest activity.
        return [
            'user' => $user,
            'is_active' => $sessionRows[0]['is_active'],
            'last_activity_at' => $sessionRows[0]['last_activity_at'],
            'sessions' => $sessionRows,
            'flags' => $this->flagsFor($user, $userSessions),
        ];
    }

    /**
     * @param  array<int, OnlineUserRow>  $onlineUsers
     * @return array{total: int, staff: int, clients: int, active: int, idle: int, flagged: int}
     */
    public function summarize(array $onlineUsers): array
    {
        $total = count($onlineUsers);
        $clients = count(array_filter($onlineUsers, fn (array $row) => $row['user']->isClient()));
        $active = count(array_filter($onlineUsers, fn (array $row) => $row['is_active']));

        return [
            'total' => $total,
            'staff' => $total - $clients,
            'clients' => $clients,
            'active' => $active,
            'idle' => $total - $active,
            'flagged' => count(array_filter($onlineUsers, fn (array $row) => $row['flags'] !== [])),
        ];
    }

    /**
     * @param  array<int, OnlineUserRow>  $onlineUsers
     * @return array<int, OnlineUserRow>
     */
    public function filterBySegment(array $onlineUsers, string $segment): array
    {
        return match ($segment) {
            'staff' => array_values(array_filter($onlineUsers, fn (array $row) => ! $row['user']->isClient())),
            'clients' => array_values(array_filter($onlineUsers, fn (array $row) => $row['user']->isClient())),
            default => $onlineUsers,
        };
    }

    /**
     * Which of the given users currently have an online session.
     *
     * @param  array<int, int>  $userIds
     * @return list<int>
     */
    public function onlineUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('sessions')
            ->whereIn('user_id', $userIds)
            ->where('last_activity', '>=', $this->onlineSince()->getTimestamp())
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->values()
            ->all();
    }

    public function isOnline(User $user): bool
    {
        return $this->onlineUserIds([(int) $user->id]) !== [];
    }

    /**
     * Staff (ticket console) accounts with an online session, for the shadow header badge.
     */
    public function onlineStaffCount(): int
    {
        return DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->whereIn('users.role', User::TICKET_CONSOLE_ROLES)
            ->where('sessions.last_activity', '>=', $this->onlineSince()->getTimestamp())
            ->distinct()
            ->count('sessions.user_id');
    }

    public function onlineWindowMinutes(): int
    {
        return max(1, (int) config('hoshiask.presence.online_window_minutes', 5));
    }

    /**
     * @param  Collection<int, \stdClass>  $sessions
     * @return list<PresenceFlag>
     */
    private function flagsFor(User $user, Collection $sessions): array
    {
        $flags = [];

        $ipAddressCount = $sessions->pluck('ip_address')->filter()->unique()->count();
        if ($ipAddressCount > 1) {
            $flags[] = [
                'key' => 'multiple_ips',
                'label' => 'Multiple IPs',
                'description' => "Signed in from {$ipAddressCount} different IP addresses at the same time.",
            ];
        }

        if (! $user->is_active) {
            $flags[] = [
                'key' => 'inactive_account',
                'label' => 'Deactivated',
                'description' => 'This account is deactivated but still has an open session.',
            ];
        }

        return $flags;
    }

    private function onlineSince(): Carbon
    {
        return now()->subMinutes($this->onlineWindowMinutes());
    }

    private function activeSince(): Carbon
    {
        $activeWindowMinutes = max(1, (int) config('hoshiask.presence.active_window_minutes', 2));

        return now()->subMinutes(min($activeWindowMinutes, $this->onlineWindowMinutes()));
    }
}
