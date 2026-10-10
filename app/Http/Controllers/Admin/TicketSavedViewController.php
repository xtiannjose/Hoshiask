<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\StoreTicketSavedViewRequest;
use App\Models\TicketSavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketSavedViewController extends Controller
{
    private const ALLOWED_FILTERS = [
        'tab',
        'status',
        'search',
        'priority',
        'category',
        'category_bucket',
        'assigned_to',
        'account_id',
        'related_user_id',
        'month',
        'ticket_type',
        'province',
        'municipality',
        'created_from',
        'created_to',
        'report_scope',
    ];

    private const ALLOWED_TABS = [
        'all',
        'tickets',
        'attention',
        'history',
    ];

    public function store(StoreTicketSavedViewRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $name = Str::squish($validated['name']);
        $normalizedName = TicketSavedView::normalizeName($name);
        $filters = $this->normalizedFilters($validated['filters'] ?? []);

        TicketSavedView::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'name_normalized' => $normalizedName,
            ],
            [
                'name' => $name,
                'filters' => $filters,
            ]
        );

        return redirect()
            ->route('admin.tickets.index', $filters)
            ->with('success', "Saved view \"{$name}\".");
    }

    public function destroy(Request $request, TicketSavedView $ticketSavedView): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $ticketSavedView), 404);

        $ticketSavedView->delete();

        return back()->with('success', 'Saved view deleted.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizedFilters(array $filters): array
    {
        $normalized = [];

        foreach (self::ALLOWED_FILTERS as $key) {
            if (! array_key_exists($key, $filters) || ! is_scalar($filters[$key])) {
                continue;
            }

            $value = Str::limit(Str::squish((string) $filters[$key]), 120, '');
            if ($value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        if (! in_array($normalized['tab'] ?? null, self::ALLOWED_TABS, true)) {
            $normalized['tab'] = 'tickets';
        }

        return $normalized;
    }
}
