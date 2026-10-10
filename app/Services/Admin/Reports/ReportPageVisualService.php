<?php

namespace App\Services\Admin\Reports;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Support\TicketCategoryBuckets;

class ReportPageVisualService
{
    /**
     * @param  array<int, array<string, mixed>>  $monthlyPerformanceSeries
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScope
     */
    public function build(
        array $monthlyPerformanceSeries,
        string $monthlyPerformanceFocusMonthKey,
        array $ticketsBreakdownOverview,
        array $categoryBreakdownBuckets,
        array $priorityBreakdownBuckets,
        array $ticketHistoryScope,
    ): array {
        $mixScopeLabel = (string) ($ticketsBreakdownOverview['label'] ?? 'All Time');
        $ticketHistoryScopeParams = collect($ticketHistoryScope)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $pieRadius = 58;
        $pieCircumference = 2 * pi() * $pieRadius;

        $ticketPie = $this->buildPieVisualization(
            $this->buildTicketPieSlices($ticketsBreakdownOverview, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $categoryPie = $this->buildPieVisualization(
            $this->buildCategoryPieSlices($categoryBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $priorityPie = $this->buildPieVisualization(
            $this->buildPriorityPieSlices($priorityBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );

        $monthlyCountMax = max(1, collect($monthlyPerformanceSeries)->map(
            fn (array $point): int => max((int) ($point['received'] ?? 0), (int) ($point['resolved'] ?? 0))
        )->max() ?? 0);
        $chartHeight = 320;
        $chartWidth = max(760, count($monthlyPerformanceSeries) * 70);
        $paddingLeft = 44;
        $paddingRight = 24;
        $paddingTop = 18;
        $paddingBottom = 52;
        $plotWidth = max(1, $chartWidth - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $chartHeight - $paddingTop - $paddingBottom);
        $step = count($monthlyPerformanceSeries) > 0 ? ($plotWidth / count($monthlyPerformanceSeries)) : $plotWidth;
        $gridLines = collect([0, 25, 50, 75, 100])
            ->map(function (int $lineRate) use ($paddingTop, $plotHeight, $monthlyCountMax): array {
                $lineY = $paddingTop + ($plotHeight - (($lineRate / 100) * $plotHeight));

                return [
                    'rate' => $lineRate,
                    'y' => $lineY,
                    'count_label' => (int) round(($lineRate / 100) * $monthlyCountMax),
                ];
            })
            ->all();
        $barWidth = max(8, (int) floor(($step * 0.28)));
        $bars = collect($monthlyPerformanceSeries)
            ->values()
            ->map(function (array $point, int $pointIndex) use ($paddingLeft, $step, $barWidth, $monthlyCountMax, $paddingTop, $plotHeight): array {
                $centerX = $paddingLeft + ($pointIndex * $step) + ($step / 2);
                $received = (int) ($point['received'] ?? 0);
                $resolved = (int) ($point['resolved'] ?? 0);
                $receivedHeight = $received > 0 ? (($received / $monthlyCountMax) * $plotHeight) : 0;
                $resolvedHeight = $resolved > 0 ? (($resolved / $monthlyCountMax) * $plotHeight) : 0;

                return [
                    'label' => (string) ($point['label'] ?? ''),
                    'center_x' => $centerX,
                    'bar_width' => $barWidth,
                    'received_height' => max(1, $receivedHeight),
                    'resolved_height' => max(1, $resolvedHeight),
                    'received_y' => $paddingTop + ($plotHeight - $receivedHeight),
                    'resolved_y' => $paddingTop + ($plotHeight - $resolvedHeight),
                    'label_y' => $paddingTop + $plotHeight + 14,
                ];
            })
            ->all();

        return [
            'mix_scope_label' => $mixScopeLabel,
            'pie_radius' => $pieRadius,
            'pie_circumference' => $pieCircumference,
            'ticket_pie' => $ticketPie,
            'category_pie' => $categoryPie,
            'priority_pie' => $priorityPie,
            'monthly_performance' => [
                'series' => $monthlyPerformanceSeries,
                'count_max' => $monthlyCountMax,
                'chart_height' => $chartHeight,
                'chart_width' => $chartWidth,
                'padding_left' => $paddingLeft,
                'padding_right' => $paddingRight,
                'padding_top' => $paddingTop,
                'padding_bottom' => $paddingBottom,
                'plot_width' => $plotWidth,
                'plot_height' => $plotHeight,
                'step' => $step,
                'grid_lines' => $gridLines,
                'bars' => $bars,
                'selected_point' => collect($monthlyPerformanceSeries)->firstWhere('key', $monthlyPerformanceFocusMonthKey)
                    ?? collect($monthlyPerformanceSeries)->last(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     */
    private function buildPieVisualization(array $slices, float $circumference): array
    {
        $total = max(0, (int) collect($slices)->sum('count'));

        return [
            'total' => $total,
            'slices' => $slices,
            'segments' => $this->buildPieSegments($slices, $total, $circumference),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     * @return array<int, array<string, mixed>>
     */
    private function buildPieSegments(array $slices, int $total, float $circumference): array
    {
        if ($total <= 0) {
            return [];
        }

        $segments = [];
        $accumulatedLength = 0.0;
        foreach ($slices as $slice) {
            $count = (int) ($slice['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $segmentLength = ($count / $total) * $circumference;
            $segments[] = [
                'label' => (string) ($slice['label'] ?? ''),
                'count' => $count,
                'color' => (string) ($slice['color'] ?? '#94a3b8'),
                'length' => $segmentLength,
                'offset' => -$accumulatedLength,
            ];
            $accumulatedLength += $segmentLength;
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $ticketsBreakdownOverview
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildTicketPieSlices(array $ticketsBreakdownOverview, array $ticketHistoryScopeParams): array
    {
        $pieResolved = (int) ($ticketsBreakdownOverview['resolved'] ?? 0);
        $pieClosed = (int) ($ticketsBreakdownOverview['closed'] ?? 0);
        $pieResolvedOnly = max($pieResolved - $pieClosed, 0);
        $cancelledCount = (int) ($ticketsBreakdownOverview['cancelled'] ?? 0);

        $openSlice = fn (TicketStatus $status): array => [
            'label' => $status->label(),
            'count' => (int) ($ticketsBreakdownOverview[$status->value] ?? 0),
            'display_count' => (int) ($ticketsBreakdownOverview[$status->value] ?? 0),
            'color' => $status->chartColor(),
            'tab' => 'tickets',
            'status' => $status->value,
        ];

        return collect([
            $openSlice(TicketStatus::Open),
            $openSlice(TicketStatus::InProgress),
            $openSlice(TicketStatus::Pending),
            ['label' => TicketStatus::Resolved->label(), 'count' => $pieResolvedOnly, 'display_count' => $pieResolved, 'color' => TicketStatus::Resolved->chartColor(), 'tab' => 'history', 'status' => null],
            ['label' => TicketStatus::Closed->label(), 'count' => $pieClosed, 'display_count' => $pieClosed, 'color' => TicketStatus::Closed->chartColor(), 'tab' => 'history', 'status' => TicketStatus::Closed->value],
            // The card's total counts cancelled tickets too, so they need a slice for the slices to add up to it.
            ['label' => TicketStatus::Cancelled->label(), 'count' => $cancelledCount, 'display_count' => $cancelledCount, 'color' => TicketStatus::Cancelled->chartColor(), 'tab' => 'history', 'status' => TicketStatus::Cancelled->value],
        ])->map(function (array $slice) use ($ticketHistoryScopeParams): array {
            $statusLinkParams = array_merge($ticketHistoryScopeParams, ['tab' => $slice['tab']]);
            if ($slice['status'] !== null) {
                $statusLinkParams['status'] = $slice['status'];
            }

            $slice['link'] = route('admin.tickets.index', $statusLinkParams);

            return $slice;
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryPieSlices(array $categoryBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        return collect($categoryBreakdownBuckets)
            ->map(function (array $bucket) use ($ticketHistoryScopeParams): array {
                $label = (string) ($bucket['name'] ?? TicketCategoryBuckets::OTHER);

                return [
                    'label' => $label,
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => TicketCategoryBuckets::color($label),
                    'link' => route('admin.tickets.index', array_merge($ticketHistoryScopeParams, [
                        'tab' => 'all',
                        'category_bucket' => TicketCategoryBuckets::filterKey($label),
                    ])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildPriorityPieSlices(array $priorityBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        return collect($priorityBreakdownBuckets)
            ->map(function (array $bucket) use ($ticketHistoryScopeParams): array {
                $priority = TicketPriority::tryFromValue($bucket['value'] ?? null);

                return [
                    'label' => (string) ($bucket['name'] ?? TicketPriority::SEVERITY_NOT_SET_LABEL),
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => $priority?->chartColor() ?? TicketPriority::SEVERITY_NOT_SET_CHART_COLOR,
                    // The ticket list's "unassigned" priority filter means "no severity yet".
                    'link' => route('admin.tickets.index', array_merge($ticketHistoryScopeParams, [
                        'tab' => 'all',
                        'priority' => $priority->value ?? 'unassigned',
                    ])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }
}
