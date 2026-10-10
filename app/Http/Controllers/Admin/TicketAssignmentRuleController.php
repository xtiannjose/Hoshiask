<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketAssignmentRules\StoreTicketAssignmentRuleRequest;
use App\Http\Requests\Admin\TicketAssignmentRules\UpdateTicketAssignmentSettingRequest;
use App\Models\Category;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Models\User;
use App\Services\SystemLogService;

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
            ->get(['id', 'name', 'role']);

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
