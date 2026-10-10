<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class DashboardSnapshotService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function recentWindow(int $days = 10): array
    {
        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }

    /**
     * @param  array<string, mixed>  $extraSignals
     */
    public function buildToken(?string $latestUpdatedAt, array $extraSignals = []): string
    {
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime($latestUpdatedAt) : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            ...$extraSignals,
        ]));
    }
}
