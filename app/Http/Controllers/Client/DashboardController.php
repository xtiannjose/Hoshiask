<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\ClientDashboardSummaryService;
use App\Services\DashboardSnapshotService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ClientDashboardSummaryService $dashboardSummary,
        private DashboardSnapshotService $dashboardSnapshot,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $summary = $this->dashboardSummary->summaryFor($user);
        $liveSnapshotToken = $summary['live_snapshot_token'];

        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $stats = $summary['stats'];

        [$recentStart, $recentEnd] = $this->dashboardSnapshot->recentWindow();

        /** @var Builder $recentTicketQuery */
        $recentTicketQuery = $this->dashboardSummary->recentTicketsQueryFor($user);
        $recentTickets = $recentTicketQuery
            ->whereBetween('created_at', [$recentStart, $recentEnd])
            ->latest()
            ->take(5)
            ->get();

        return view('client.dashboard', compact('stats', 'recentTickets', 'liveSnapshotToken'));
    }
}
