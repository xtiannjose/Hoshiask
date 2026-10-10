<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Concerns\RecordsExports;
use App\Http\Controllers\Concerns\SanitizesCsvCells;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketSavedView;
use App\Models\TicketUserState;
use App\Models\User;
use App\Services\Admin\TicketAssignmentAutomationService;
use App\Services\Admin\TicketAssignmentService;
use App\Services\Admin\TicketIndexService;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Services\TicketEmailAlertService;
use App\Support\DuplicateSubmissionGuard;
use App\Support\LeadingUppercaseNormalizer;
use App\Support\TicketListSort;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;
    use RecordsExports;
    use SanitizesCsvCells;

    private const FILTER_OPTIONS_CACHE_SECONDS = 60;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private SystemLogService $systemLogs,
        private TicketIndexService $ticketIndex,
        private TicketAssignmentService $ticketAssignments,
        private TicketEmailAlertService $emailAlerts,
        private TicketAssignmentAutomationService $ticketAssignmentAutomation,
    ) {}

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString(), $currentUser);
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus($request->string('status')->toString(), $activeTab);
        $createdDateRange = $this->ticketIndex->resolveCreatedDateRange($request);
        $currentPage = max(1, $request->integer('page', 1));
        $perPage = TicketListSort::perPage($request);
        ['sort' => $sort, 'direction' => $sortDirection] = TicketListSort::resolve($request);

        $filteredQuery = $this->ticketIndex->scopedTicketQueryFor($currentUser, $activeTab);
        $this->ticketIndex->applyTabScope($filteredQuery, $activeTab);
        $this->ticketIndex->applyFilters($filteredQuery, $request, $selectedStatus, $createdDateRange);

        $liveSnapshotToken = $this->ticketIndex->buildTicketListSnapshotToken(clone $filteredQuery);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
                'page_token' => $this->ticketIndex->buildTicketListPageSnapshotToken(
                    TicketListSort::apply(clone $filteredQuery, $sort, $sortDirection),
                    $currentPage,
                    $perPage
                ),
            ]);
        }

        $tickets = TicketListSort::apply(
            (clone $filteredQuery)->with(['user', 'createdByUser', 'category', 'assignedUser', 'assignedUsers', 'closedBy']),
            $sort,
            $sortDirection
        )->paginate($perPage, ['*'], 'page', $currentPage);
        $pageSnapshotToken = $this->ticketIndex->buildTicketListPageSnapshotTokenForTickets($tickets);
        $ticketSeenTimestamps = TicketUserState::where('user_id', auth()->id())
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get()
            ->mapWithKeys(function (TicketUserState $state) {
                return [
                    (int) $state->ticket_id => optional($state->last_seen_at)->timestamp,
                ];
            })
            ->all();

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.tickets.partials.results', compact(
                    'tickets',
                    'ticketSeenTimestamps',
                    'createdDateRange',
                    'activeTab',
                    'sort',
                    'sortDirection',
                    'perPage'
                ))->render(),
                'token' => $liveSnapshotToken,
                'page_token' => $pageSnapshotToken,
            ]);
        }

        $filterOptions = Cache::remember(
            $this->filterOptionsCacheKey($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange),
            now()->addSeconds(self::FILTER_OPTIONS_CACHE_SECONDS),
            function () use ($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange): array {
                return [
                    'provinceOptions' => $this->ticketIndex->distinctTicketColumnOptions(
                        'province',
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['province'])
                    ),
                    'municipalityOptions' => $this->ticketIndex->distinctTicketColumnOptions(
                        'municipality',
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['municipality'])
                    ),
                    'accountOptions' => $this->ticketIndex->accountOptionsFor(
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['account'])
                    ),
                    'monthOptions' => $this->ticketIndex->monthOptionsFor(
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['month'])
                    ),
                    'categories' => $this->ticketIndex->categoryOptionsFor(
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['category'])
                    ),
                    'filterAssignees' => $this->ticketIndex->assignedAgentOptionsFor(
                        $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['assigned_to'])
                    ),
                    'assignmentAssignees' => $this->ticketIndex->activeAssignableAgents(),
                ];
            }
        );
        $provinceOptions = $filterOptions['provinceOptions'];
        $municipalityOptions = $filterOptions['municipalityOptions'];
        $accountOptions = $filterOptions['accountOptions'];
        $monthOptions = $filterOptions['monthOptions'];
        $categories = $filterOptions['categories'];
        $filterAssignees = $filterOptions['filterAssignees'];
        $assignmentAssignees = $filterOptions['assignmentAssignees'];
        $departmentOptions = User::allowedDepartments();
        $savedViews = TicketSavedView::query()
            ->forUser($currentUser->id)
            ->orderBy('name')
            ->get();

        return view('admin.tickets.index', compact(
            'tickets',
            'categories',
            'filterAssignees',
            'assignmentAssignees',
            'departmentOptions',
            'provinceOptions',
            'municipalityOptions',
            'accountOptions',
            'monthOptions',
            'activeTab',
            'liveSnapshotToken',
            'pageSnapshotToken',
            'ticketSeenTimestamps',
            'createdDateRange',
            'savedViews',
            'sort',
            'sortDirection',
            'perPage'
        ));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canCreateClientTickets(), 403);

        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name']);
        $clientAccounts = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'department']);
        $supportAccounts = User::query()
            ->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'department', 'role']);
        $assignmentAssignees = $this->ticketIndex->activeAssignableAgents();
        $canSetManualDates = auth()->user()->canSetManualTicketDates();

        return view('admin.tickets.create', compact('categories', 'clientAccounts', 'supportAccounts', 'assignmentAssignees', 'canSetManualDates'));
    }

    public function export(Request $request): StreamedResponse
    {
        $currentUser = auth()->user();
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString(), $currentUser);
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus($request->string('status')->toString(), $activeTab);
        $createdDateRange = $this->ticketIndex->resolveCreatedDateRange($request);

        $filteredQuery = $this->ticketIndex->scopedTicketQueryFor($currentUser, $activeTab);
        $this->ticketIndex->applyTabScope($filteredQuery, $activeTab);
        $this->ticketIndex->applyFilters($filteredQuery, $request, $selectedStatus, $createdDateRange);

        $this->recordExport($request, 'export.tickets_csv', 'Exported the ticket list to CSV.', (clone $filteredQuery)->count(), ['tab' => $activeTab]);

        $filename = 'tickets-'.now()->format('Y-m-d-His').'.csv';

        return $this->streamCsvDownload($filename, function ($handle) use ($filteredQuery): void {
            fputcsv($handle, [
                'Ticket Number',
                'Subject',
                'Requester',
                'Status',
                'Severity',
                'Ticket Type',
                'Category',
                'Assigned Staff',
                'Province',
                'Municipality',
                'Created At',
                'Resolved At',
                'Closed At',
                'SLA State',
                'Cancellation Reason',
                'Field Office',
            ]);

            foreach ((clone $filteredQuery)
                ->with(['user', 'createdByUser', 'category', 'assignedUser', 'assignedUsers'])
                ->latest()
                ->orderByDesc('tickets.id') // tickets with the same created_at (imports) must not repeat or vanish between pages
                ->lazy(200) as $ticket) {
                if (! $ticket instanceof Ticket) {
                    continue;
                }

                fputcsv($handle, [
                    $this->csvCell($ticket->ticket_number),
                    $this->csvCell($ticket->subject),
                    $this->csvCell($ticket->requester_display_label),
                    $this->csvCell($ticket->status_label),
                    $this->csvCell($ticket->priority_label),
                    $this->csvCell($ticket->ticket_type_label),
                    $this->csvCell(optional($ticket->category)->name ?: 'Uncategorized'),
                    $this->csvCell($ticket->assigned_users_label),
                    $this->csvCell($ticket->province),
                    $this->csvCell($ticket->municipality),
                    $this->csvCell(optional($ticket->created_at)->toDateTimeString()),
                    $this->csvCell(optional($ticket->resolved_at)->toDateTimeString()),
                    $this->csvCell(optional($ticket->closed_at)->toDateTimeString()),
                    $this->csvCell($ticket->sla_badge_label ?? ''),
                    $this->csvCell($ticket->cancellation_reason),
                    $this->csvCell($ticket->department),
                ]);
            }
        });
    }

    public function store(StoreTicketRequest $request)
    {
        $assignedUserIds = $this->ticketAssignments->normalizedAssigneeIdsFromRequest($request);
        $assignedTo = $this->ticketAssignments->primaryAssigneeId($assignedUserIds);

        $loggedAt = $request->filled('created_at') ? Carbon::parse($request->input('created_at')) : now();
        $resolvedAt = $request->filled('resolved_at') ? Carbon::parse($request->input('resolved_at')) : null;
        $closedAt = $request->filled('closed_at') ? Carbon::parse($request->input('closed_at')) : null;

        if ($closedAt) {
            $status = 'closed';
            $resolvedAt = $resolvedAt ?? $closedAt;
        } elseif ($resolvedAt) {
            $status = 'resolved';
        } else {
            $status = 'open';
        }

        $createTicket = fn () => $this->withAttachmentWriteGuard(function () use ($request, $assignedTo, $assignedUserIds, $loggedAt, $resolvedAt, $closedAt, $status) {
            return DB::transaction(function () use ($request, $assignedTo, $assignedUserIds, $loggedAt, $resolvedAt, $closedAt, $status) {
                $ticket = Ticket::create([
                    'name' => $request->string('name')->toString(),
                    'contact_number' => $request->filled('contact_number')
                        ? $request->string('contact_number')->toString()
                        : null,
                    'email' => $request->filled('email')
                        ? $request->string('email')->toString()
                        : null,
                    ...LeadingUppercaseNormalizer::normalizeTicketFields($request),
                    'description' => $request->string('description')->toString(),
                    'category_id' => $request->integer('category_id'),
                    'ticket_type' => $request->string('ticket_type')->toString(),
                    'priority' => null,
                    'status' => $status,
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $closedAt,
                    'closed_by' => $closedAt ? auth()->id() : null,
                    'user_id' => $request->integer('user_id'),
                    'created_by_user_id' => (int) auth()->id(),
                    'assigned_to' => $assignedTo,
                    'assigned_at' => $assignedTo !== null ? $loggedAt->copy() : null,
                    'creation_source' => $request->string('ticket_type')->toString() === Ticket::TYPE_INTERNAL
                        ? Ticket::CREATION_SOURCE_STAFF_FOR_STAFF
                        : Ticket::CREATION_SOURCE_STAFF_FOR_CLIENT,
                ]);

                if ($request->filled('created_at')) {
                    Ticket::query()->whereKey($ticket->id)->update(['created_at' => $loggedAt]);
                    $ticket->setAttribute('created_at', $loggedAt);
                }

                $this->persistAttachmentsFromRequest($request, $ticket);
                if ($assignedUserIds !== []) {
                    $this->ticketAssignments->syncAssignmentState($ticket, [], $assignedUserIds, notifyNewAssignees: true);
                } else {
                    $this->ticketAssignmentAutomation->autoAssignIfUnassigned($ticket);
                }
                $this->ticketAcknowledgments->acknowledge($ticket, auth()->user());

                return $ticket;
            });
        });

        // The same form sent twice (a double click, the back button, a retry on a weak connection) must not make two tickets.
        try {
            ['ticket' => $ticket, 'repeat' => $repeat] = DuplicateSubmissionGuard::once(
                DuplicateSubmissionGuard::fingerprint('staff', (int) auth()->id(), $request),
                $createTicket
            );
        } catch (LockTimeoutException) {
            return redirect()->back()->withInput()
                ->with('error', 'This ticket is still being created. Please wait a moment and check the ticket list before creating it again.');
        }

        if ($repeat) {
            return redirect()->route('admin.tickets.show', $ticket)
                ->with('success', 'This ticket was already created a moment ago, so it was not created again.');
        }

        $this->systemLogs->record(
            'ticket.created_by_support_user',
            'Created a ticket on behalf of a requester account.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'actor_role' => auth()->user()?->normalizedRole(),
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_type' => $ticket->ticket_type,
                    'requester_user_id' => (int) $ticket->user_id,
                    'category_id' => (int) $ticket->category_id,
                    'assigned_to' => $assignedTo,
                    'assigned_user_ids' => $assignedUserIds,
                ],
                'request' => $request,
            ]
        );

        if ($assignedUserIds !== []) {
            $this->systemLogs->record(
                'ticket.assignment.updated',
                'Assigned ticket during creation.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => $this->ticketAssignments->assignmentLogMetadata($ticket, [], $assignedUserIds),
                    'request' => $request,
                ]
            );
        }

        $this->emailAlerts->notifySuperUsersAboutNewTicket($ticket);
        $this->emailAlerts->notifyClientOfTicketReceived($ticket);

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);
        $this->recordReadOnlyView($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket);
        $ticket->loadMissing(['assignedUser', 'assignedUsers']);
        $replyFeedCursor = $this->replyFeedCursorForReplies($ticket->replies);
        $assignees = $this->ticketIndex->activeAssignableAgents();
        $currentUserState = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', (int) auth()->id())
            ->first();

        return view('admin.tickets.show', compact('ticket', 'assignees', 'currentUserState', 'replyFeedCursor'));
    }

    /**
     * Opening a ticket you cannot change (a technician looking at a colleague's ticket) is allowed,
     * but leaves an entry in the system logs so access to other people's tickets can be reviewed.
     */
    private function recordReadOnlyView(Ticket $ticket): void
    {
        $viewer = auth()->user();
        if (! $viewer || $viewer->can('manage', $ticket)) {
            return;
        }

        $this->systemLogs->record(
            'ticket.viewed_read_only',
            'Viewed a ticket assigned to someone else (read-only).',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => ['ticket_number' => $ticket->ticket_number],
            ]
        );
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }

    private function filterOptionsCacheKey(
        Request $request,
        ?User $currentUser,
        string $activeTab,
        string $selectedStatus,
        ?array $createdDateRange,
    ): string {
        $scope = $currentUser && $currentUser->isTechnician() ? 'technician:'.$currentUser->id : 'shared';
        $queryParams = $request->except(['page', 'partial', 'heartbeat']);
        ksort($queryParams);

        return implode(':', [
            'admin.tickets.filter-options',
            $scope,
            $activeTab,
            $selectedStatus,
            json_encode($createdDateRange),
            sha1(http_build_query($queryParams)), // the raw query string can exceed the cache table's 255-character key
        ]);
    }

    private function buildFilterOptionQuery(
        Request $request,
        ?User $currentUser,
        string $activeTab,
        string $selectedStatus,
        ?array $createdDateRange,
        array $excludedFilters = [],
    ): Builder {
        $query = $this->ticketIndex->scopedTicketQueryFor($currentUser, $activeTab);
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFiltersExcept($query, $request, $selectedStatus, $createdDateRange, $excludedFilters);

        return $query;
    }
}
