<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Admin\TicketAssignmentAutomationService;
use App\Services\Client\ClientTicketIndexService;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use App\Support\DuplicateSubmissionGuard;
use App\Support\LeadingUppercaseNormalizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    public function __construct(
        private ClientTicketIndexService $ticketIndex,
        private SystemLogService $systemLogs,
        private TicketEmailAlertService $emailAlerts,
        private TicketAssignmentAutomationService $ticketAssignmentAutomation,
    ) {}

    public function index(Request $request)
    {
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString());
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus(
            $request->string('status')->toString(),
            $activeTab
        );

        $query = $this->ticketIndex->scopedTicketQueryFor(auth()->user());
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFilters($query, $request, $selectedStatus);

        $liveSnapshotToken = $this->ticketIndex->buildSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $tickets = $query->latest()->paginate(10)->withQueryString();

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('client.tickets.partials.results', compact('tickets', 'activeTab'))->render(),
                'token' => $liveSnapshotToken,
            ]);
        }

        return view('client.tickets.index', compact('tickets', 'liveSnapshotToken', 'activeTab', 'selectedStatus'));
    }

    public function create()
    {
        $categories = Category::active()->get();

        return view('client.tickets.create', compact('categories'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticketData = [
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            ...LeadingUppercaseNormalizer::normalizeTicketFields($request),
            'description' => $request->description,
            'category_id' => $request->category_id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'priority' => null,
            'user_id' => auth()->id(),
            'created_by_user_id' => auth()->id(),
            'creation_source' => Ticket::CREATION_SOURCE_CLIENT_SELF_SERVICE,
            'consent_accepted_at' => now(),
            'consent_version' => (string) config('legal.ticket_consent_version'),
            'consent_ip_address' => $request->ip(),
            'consent_user_agent' => $request->userAgent(),
        ];

        // The same form sent twice (a double click, the back button, a retry on a weak connection) must not make two tickets.
        try {
            ['ticket' => $ticket, 'repeat' => $repeat] = DuplicateSubmissionGuard::once(
                DuplicateSubmissionGuard::fingerprint('client', (int) auth()->id(), $request),
                fn () => $this->withAttachmentWriteGuard(function () use ($request, $ticketData) {
                    return DB::transaction(function () use ($request, $ticketData) {
                        $ticket = Ticket::create($ticketData);
                        $this->persistAttachmentsFromRequest($request, $ticket);
                        $this->ticketAssignmentAutomation->autoAssignIfUnassigned($ticket);

                        return $ticket;
                    });
                })
            );
        } catch (LockTimeoutException) {
            return redirect()->back()->withInput()
                ->with('error', 'Your ticket is still being sent. Please wait a moment and check My Tickets before sending it again.');
        }

        if ($repeat) {
            return redirect()->route('client.tickets.show', $ticket)
                ->with('success', 'This ticket was already sent a moment ago, so it was not created again.');
        }

        $this->systemLogs->record(
            'ticket.created',
            'Created a support ticket.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'category_id' => (int) $ticket->category_id,
                ],
                'request' => $request,
            ]
        );

        $this->emailAlerts->notifySuperUsersAboutNewTicket($ticket);
        $this->emailAlerts->notifyClientOfTicketReceived($ticket);

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');
    }
}
