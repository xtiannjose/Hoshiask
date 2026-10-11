<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketAssignmentRules\StoreTicketAssignmentRuleRequest;
use App\Http\Requests\Admin\TicketAssignmentRules\UpdateTechnicianAvailabilityRequest;
use App\Http\Requests\Admin\TicketAssignmentRules\UpdateTicketAssignmentSettingRequest;
use App\Models\Category;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Support\Carbon;

class TicketAssignmentRuleController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function index()
    {
        $categories = Category::active()->orderBy('name')->get();

        $rulesByCategory = TicketAssignmentRule::query()
            ->with('technician')
            ->orderByDesc('weight')
            ->get()
            ->groupBy('category_id');

        $technicians = User::query()
            ->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'assignment_paused_until']);

        $distributionMethod = TicketAssignmentSetting::current()->distribution_method;

        return view('admin.assignment-rules.index', compact(
            'categories',
            'rulesByCategory',
            'technicians',
            'distributionMethod'
        ));
    }

    public function store(StoreTicketAssignmentRuleRequest $request)
    {
        $rule = TicketAssignmentRule::create([
            'category_id' => $request->integer('category_id'),
            'user_id' => $request->integer('user_id'),
            'weight' => $request->integer('weight'),
        ]);

        $this->systemLogs->record(
            'ticket_assignment_rule.created',
            'Created a ticket assignment automation rule.',
            [
                'category' => 'configuration',
                'target_type' => TicketAssignmentRule::class,
                'target_id' => $rule->id,
                'metadata' => [
                    'category_id' => $rule->category_id,
                    'user_id' => $rule->user_id,
                    'weight' => $rule->weight,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.assignment-rules.index')
            ->with('success', 'Assignment rule created successfully.');
    }

    public function destroy(TicketAssignmentRule $ticketAssignmentRule)
    {
        $ticketAssignmentRule->delete();

        $this->systemLogs->record(
            'ticket_assignment_rule.deleted',
            'Deleted a ticket assignment automation rule.',
            [
                'category' => 'configuration',
                'target_type' => TicketAssignmentRule::class,
                'target_id' => $ticketAssignmentRule->id,
                'metadata' => [
                    'category_id' => $ticketAssignmentRule->category_id,
                    'user_id' => $ticketAssignmentRule->user_id,
                ],
                'request' => request(),
            ]
        );

        return redirect()->route('admin.assignment-rules.index')
            ->with('success', 'Assignment rule removed.');
    }

    /**
     * Skip one person in the assignment rules until a day, or make them available again. Staff can still assign
     * them by hand; only the automatic rules look at it.
     */
    public function updateAvailability(UpdateTechnicianAvailabilityRequest $request, User $user)
    {
        abort_unless($user->is_active && in_array($user->role, User::TICKET_ASSIGNABLE_ROLES, true), 404);

        $backOn = $request->input('back_on');
        $user->forceFill([
            'assignment_paused_until' => $backOn ? Carbon::createFromFormat('Y-m-d', $backOn)->startOfDay() : null,
        ])->save();

        $this->systemLogs->record(
            'ticket_assignment_rule.availability_updated',
            $backOn ? 'Paused automatic assignment for a technician.' : 'Made a technician available for automatic assignment.',
            [
                'category' => 'configuration',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => ['back_on' => $backOn ?: null],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.assignment-rules.index')
            ->with('success', $backOn
                ? $user->name.' is skipped by the assignment rules until '.Carbon::createFromFormat('Y-m-d', $backOn)->format('M j, Y').'.'
                : $user->name.' is available for automatic assignment.');
    }

    public function updateDistributionMethod(UpdateTicketAssignmentSettingRequest $request)
    {
        $setting = TicketAssignmentSetting::current();
        $setting->update([
            'distribution_method' => $request->string('distribution_method')->toString(),
        ]);

        $this->systemLogs->record(
            'ticket_assignment_rule.distribution_method_updated',
            'Updated the ticket assignment distribution method.',
            [
                'category' => 'configuration',
                'metadata' => [
                    'distribution_method' => $setting->distribution_method,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.assignment-rules.index')
            ->with('success', 'Assignment distribution method updated.');
    }
}
